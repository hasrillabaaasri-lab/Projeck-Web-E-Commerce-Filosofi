<?php
session_start();
require_once 'config.php';

$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax'])
);

function sendJsonResponse(array $payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function redirectWithAlert(string $message, string $fallbackUrl = 'beranda.php'): void {
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($fallbackUrl, ENT_QUOTES, 'UTF-8');
    echo "<script>alert('{$safeMsg}'); window.location.href = '{$safeUrl}';</script>";
    exit();
}

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => 'Silakan login terlebih dahulu.']);
    }
    header("Location: login.php");
    exit();
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cart` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `ice_level` varchar(50) DEFAULT 'ICE',
          `sugar_level` varchar(50) DEFAULT 'Normal Sugar',
          `cup_size` varchar(50) DEFAULT 'Reguler',
          `quantity` int(11) NOT NULL DEFAULT 1,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_cart_user` (`user_id`),
          KEY `idx_cart_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
} catch (PDOException $e) {
    // Abaikan jika tabel sudah tersedia
}

$user_id     = (int) $_SESSION['user_id'];
$product_id  = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
$quantity    = isset($_REQUEST['quantity']) ? (int) $_REQUEST['quantity'] : 1;
$ice_level   = isset($_REQUEST['ice_level']) && trim($_REQUEST['ice_level']) !== '' ? trim($_REQUEST['ice_level']) : 'ICE';
$sugar_level = isset($_REQUEST['sugar_level']) && trim($_REQUEST['sugar_level']) !== '' ? trim($_REQUEST['sugar_level']) : 'Normal Sugar';
$cup_size    = isset($_REQUEST['cup_size']) && trim($_REQUEST['cup_size']) !== '' ? trim($_REQUEST['cup_size']) : 'Reguler';

if ($product_id <= 0 || $quantity <= 0) {
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => 'Produk atau jumlah tidak valid.']);
    }
    header("Location: beranda.php");
    exit();
}

// Ambil informasi produk dan stok terkini dari stock_master
$stmtProd = $pdo->prepare("
    SELECT p.id, p.name, COALESCE(s.current_stock, 0) AS current_stock
    FROM products p
    LEFT JOIN stock_master s ON p.sku = s.sku AND p.sku != ''
    WHERE p.id = :id
    LIMIT 1
");
$stmtProd->execute(['id' => $product_id]);
$produk = $stmtProd->fetch(PDO::FETCH_ASSOC);

if (!$produk) {
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => 'Produk tidak ditemukan!']);
    }
    redirectWithAlert('Produk tidak ditemukan!', 'beranda.php');
}

$stokTersedia = (int) $produk['current_stock'];

// Blokir jika stok 0 atau kurang
if ($stokTersedia <= 0) {
    $msgHabis = 'Maaf, stok ' . $produk['name'] . ' sedang habis (0) dan tidak dapat ditambahkan ke keranjang.';
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => $msgHabis]);
    }
    redirectWithAlert($msgHabis, 'detail_produk.php?id=' . $product_id);
}

// Hitung total kuantitas produk ini (lintas semua varian) yang sudah ada di keranjang user
$stmtTotalProdInCart = $pdo->prepare("
    SELECT COALESCE(SUM(quantity), 0) 
    FROM cart 
    WHERE user_id = :user_id AND product_id = :product_id
");
$stmtTotalProdInCart->execute([
    'user_id'    => $user_id,
    'product_id' => $product_id
]);
$sudahDiKeranjang = (int) $stmtTotalProdInCart->fetchColumn();
$sisaKuotaStok    = max(0, $stokTersedia - $sudahDiKeranjang);

if ($sisaKuotaStok <= 0) {
    $msgLimit = "Jumlah {$produk['name']} di keranjang Anda sudah mencapai batas maksimal stok tersedia ({$stokTersedia} cup).";
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => $msgLimit]);
    }
    redirectWithAlert($msgLimit, 'halaman_keranjang.php');
}

if ($quantity > $sisaKuotaStok) {
    $msgKurang = "Stok tidak mencukupi. Sisa stok yang bisa ditambahkan hanya {$sisaKuotaStok} cup (total stok: {$stokTersedia} cup, di keranjang: {$sudahDiKeranjang} cup).";
    if ($isAjax) {
        sendJsonResponse(['status' => 'error', 'message' => $msgKurang]);
    }
    redirectWithAlert($msgKurang, 'detail_produk.php?id=' . $product_id);
}

// Cek apakah kombinasi varian yang sama sudah ada di keranjang
$stmtCheck = $pdo->prepare("
    SELECT id, quantity 
    FROM cart 
    WHERE user_id = :user_id 
      AND product_id = :product_id 
      AND ice_level = :ice_level 
      AND sugar_level = :sugar_level 
      AND cup_size = :cup_size
    LIMIT 1
");
$stmtCheck->execute([
    'user_id'     => $user_id,
    'product_id'  => $product_id,
    'ice_level'   => $ice_level,
    'sugar_level' => $sugar_level,
    'cup_size'    => $cup_size
]);
$existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $newQty = (int) $existing['quantity'] + $quantity;
    $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = :qty WHERE id = :id AND user_id = :user_id");
    $stmtUpdate->execute([
        'qty'     => $newQty,
        'id'      => $existing['id'],
        'user_id' => $user_id
    ]);
} else {
    $stmtInsert = $pdo->prepare("
        INSERT INTO cart (user_id, product_id, ice_level, sugar_level, cup_size, quantity)
        VALUES (:user_id, :product_id, :ice_level, :sugar_level, :cup_size, :quantity)
    ");
    $stmtInsert->execute([
        'user_id'     => $user_id,
        'product_id'  => $product_id,
        'ice_level'   => $ice_level,
        'sugar_level' => $sugar_level,
        'cup_size'    => $cup_size,
        'quantity'    => $quantity
    ]);
}

// Hitung ulang total item keranjang untuk badge navbar
$stmtCount = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = :uid");
$stmtCount->execute(['uid' => $user_id]);
$totalItems = (int) $stmtCount->fetchColumn();

if ($isAjax) {
    sendJsonResponse([
        'status'       => 'success',
        'product_name' => $produk['name'],
        'variant'      => "$ice_level • $sugar_level",
        'total_items'  => $totalItems
    ]);
}

header("Location: halaman_keranjang.php");
exit();
?>