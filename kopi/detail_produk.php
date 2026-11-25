<?php
session_start();
require_once 'config.php';

// Pastikan tabel log aktivitas produk tersedia di database
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_activity_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `event_type` ENUM('detail_click', 'add_to_cart', 'buy_now') NOT NULL,
            `session_id` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_event_date` (`event_type`, `created_at`),
            KEY `idx_product_event` (`product_id`, `event_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
} catch (PDOException $e) {
    // Abaikan jika tabel sudah ada
}

// Pastikan kolom order_id, order_item_id & media_url tersedia di tabel testimonials
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `testimonials` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `product_id` INT(11) NOT NULL,
            `order_id` INT(11) DEFAULT NULL,
            `order_item_id` INT(11) DEFAULT NULL,
            `rating` INT(1) NOT NULL DEFAULT 5,
            `comment` TEXT NOT NULL,
            `media_url` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
} catch (PDOException $e) {
    // Abaikan jika tabel sudah ada
}

foreach ([
    "ALTER TABLE `testimonials` ADD COLUMN `order_id` INT(11) DEFAULT NULL AFTER `product_id`",
    "ALTER TABLE `testimonials` ADD COLUMN `order_item_id` INT(11) DEFAULT NULL AFTER `order_id`",
    "ALTER TABLE `testimonials` ADD COLUMN `media_url` VARCHAR(255) DEFAULT NULL AFTER `comment`"
] as $alterSql) {
    try {
        $pdo->exec($alterSql);
    } catch (PDOException $e) {
        // Abaikan jika kolom sudah ada
    }
}

// Endpoint pencatat aksi Tambah Keranjang & Langsung Beli dari halaman pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_track'])) {
    header('Content-Type: application/json');
    $trackPid  = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $eventType = $_POST['event_type'] ?? '';

    if ($trackPid > 0 && in_array($eventType, ['detail_click', 'add_to_cart', 'buy_now'], true)) {
        try {
            $sessId = session_id() ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest');
            $stmtTrack = $pdo->prepare("INSERT INTO product_activity_logs (product_id, event_type, session_id) VALUES (:pid, :ev, :sid)");
            $stmtTrack->execute([
                ':pid' => $trackPid,
                ':ev'  => $eventType,
                ':sid' => substr($sessId, 0, 100),
            ]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'invalid']);
    }
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: beranda.php");
    exit;
}

$id_produk     = (int)$_GET['id'];
$order_id_get  = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id       = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$role_user     = $_SESSION['role'] ?? 'Pelanggan';
$home_link     = ($role_user === 'Admin') ? 'admin_dashboard.php' : 'beranda.php';

// =========================================================
// CEK HAK AKSES MEMBERI ULASAN (ADMIN ATAU PESANAN SELESAI)
// =========================================================
$canReview       = false;
$eligibleOrderId = null;
$eligibleItemId  = null;

if ($user_id > 0) {
    if ($role_user === 'Admin') {
        $canReview       = true;
        $eligibleOrderId = $order_id_get > 0 ? $order_id_get : null;
    } else {
        $sqlCheckOrder = "
            SELECT o.id AS order_id, oi.id AS item_id
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = :uid 
              AND oi.product_id = :pid 
              AND o.status = 'Selesai'
        ";
        $paramsCheck = [':uid' => $user_id, ':pid' => $id_produk];

        if ($order_id_get > 0) {
            $sqlCheckOrder .= " AND o.id = :oid";
            $paramsCheck[':oid'] = $order_id_get;
        }
        $sqlCheckOrder .= " ORDER BY o.id DESC, oi.id DESC LIMIT 1";

        $stmtCheck = $pdo->prepare($sqlCheckOrder);
        $stmtCheck->execute($paramsCheck);
        $foundOrder = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($foundOrder) {
            $canReview       = true;
            $eligibleOrderId = (int)$foundOrder['order_id'];
            $eligibleItemId  = (int)$foundOrder['item_id'];
        }
    }
}

// =========================================================
// PROSES SIMPAN RATING, KOMENTAR & UPLOAD FOTO ULASAN
// =========================================================
$reviewFeedback = '';
$reviewError    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$canReview || $user_id <= 0) {
        $reviewError = 'Ulasan hanya dapat dikirim setelah pesanan produk ini berstatus Selesai.';
    } else {
        $rating      = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment     = trim($_POST['comment'] ?? '');
        $postOrderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : $eligibleOrderId;
        $postItemId  = !empty($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : $eligibleItemId;

        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Silakan pilih jumlah bintang (1 sampai 5).';
        } elseif ($comment === '') {
            $reviewError = 'Komentar ulasan tidak boleh kosong.';
        } else {
            $mediaUrl = null;

            if (isset($_FILES['review_media']) && $_FILES['review_media']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileTmp      = $_FILES['review_media']['tmp_name'];
                $fileType     = mime_content_type($fileTmp);
                $fileSize     = $_FILES['review_media']['size'];

                if (!in_array($fileType, $allowedTypes, true)) {
                    $reviewError = 'Format foto harus JPG, PNG, WEBP, atau GIF.';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $reviewError = 'Ukuran foto maksimal 5 MB.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/reviews/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $ext      = pathinfo($_FILES['review_media']['name'], PATHINFO_EXTENSION);
                    $fileName = 'rev_' . $id_produk . '_' . $user_id . '_' . time() . '.' . strtolower($ext);
                    $destPath = $uploadDir . $fileName;

                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $mediaUrl = 'uploads/reviews/' . $fileName;
                    } else {
                        $reviewError = 'Gagal mengunggah foto ulasan.';
                    }
                }
            }

            if ($reviewError === '') {
                try {
                    $existingRev = null;
                    if ($role_user !== 'Admin' && $postOrderId) {
                        $stmtDup = $pdo->prepare("
                            SELECT id, media_url FROM testimonials 
                            WHERE user_id = :uid AND product_id = :pid 
                              AND (
                                  (:oiid IS NOT NULL AND order_item_id = :oiid2) 
                                  OR (order_item_id IS NULL AND order_id = :oid)
                              )
                            LIMIT 1
                        ");
                        $stmtDup->execute([
                            ':uid'   => $user_id,
                            ':pid'   => $id_produk,
                            ':oiid'  => $postItemId,
                            ':oiid2' => $postItemId,
                            ':oid'   => $postOrderId
                        ]);
                        $existingRev = $stmtDup->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($existingRev) {
                        $stmtUpdRev = $pdo->prepare("
                            UPDATE testimonials 
                            SET rating = :rating, comment = :comment, media_url = :media, order_id = :oid, order_item_id = :oiid
                            WHERE id = :id
                        ");
                        $stmtUpdRev->execute([
                            ':rating'  => $rating,
                            ':comment' => $comment,
                            ':media'   => $mediaUrl ?? $existingRev['media_url'],
                            ':oid'     => $postOrderId,
                            ':oiid'    => $postItemId,
                            ':id'      => $existingRev['id'],
                        ]);
                    } else {
                        $stmtInsRev = $pdo->prepare("
                            INSERT INTO testimonials (user_id, product_id, order_id, order_item_id, rating, comment, media_url)
                            VALUES (:uid, :pid, :oid, :oiid, :rating, :comment, :media)
                        ");
                        $stmtInsRev->execute([
                            ':uid'     => $user_id,
                            ':pid'     => $id_produk,
                            ':oid'     => $postOrderId,
                            ':oiid'    => $postItemId,
                            ':rating'  => $rating,
                            ':comment' => $comment,
                            ':media'   => $mediaUrl,
                        ]);
                    }

                    $redirectUrl = 'detail_produk.php?id=' . $id_produk . ($postOrderId ? '&order_id=' . $postOrderId : '') . '&review_saved=1#section-ulasan';
                    header("Location: " . $redirectUrl);
                    exit;
                } catch (PDOException $e) {
                    $reviewError = 'Terjadi kesalahan saat menyimpan ulasan: ' . $e->getMessage();
                }
            }
        }
    }
}

if (isset($_GET['review_saved']) && $_GET['review_saved'] == '1') {
    $reviewFeedback = 'Terima kasih! Rating, komentar, dan foto ulasan Anda berhasil disimpan dan ditampilkan di bawah.';
}

// Hitung jumlah item di keranjang user untuk badge navbar
$totalCartItems = 0;
if ($user_id > 0) {
    try {
        $stmtCart = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = :uid");
        $stmtCart->execute(['uid' => $user_id]);
        $totalCartItems = (int)$stmtCart->fetchColumn();
    } catch (PDOException $e) {
        $totalCartItems = 0;
    }
}

$query_produk = "SELECT 
                    p.id, 
                    p.name, 
                    p.price, 
                    p.description, 
                    p.image_url, 
                    c.name AS category_name,
                    COALESCE(s.current_stock, 0) AS current_stock,
                    COALESCE(s.unit, 'CUP') AS unit
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 LEFT JOIN stock_master s ON p.sku = s.sku AND p.sku != ''
                 WHERE p.id = :id";
$stmt = $pdo->prepare($query_produk);
$stmt->execute(['id' => $id_produk]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<script>alert('Produk tidak ditemukan!'); window.location.href='beranda.php';</script>";
    exit;
}

// Cek ketersediaan stok
$stok_tersedia = max(0, (int)($product['current_stock'] ?? 0));
$is_out_of_stock = ($stok_tersedia <= 0);

// Catat otomatis aktivitas "Lihat Detail" ke database saat halaman detail produk dibuka
try {
    $sessId = session_id() ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    $stmtLogDetail = $pdo->prepare("INSERT INTO product_activity_logs (product_id, event_type, session_id) VALUES (:pid, 'detail_click', :sid)");
    $stmtLogDetail->execute([
        ':pid' => (int)$product['id'],
        ':sid' => substr($sessId, 0, 100),
    ]);
} catch (PDOException $e) {
    // Abaikan jika gagal mencatat log
}

$query_reviews = "SELECT t.*, u.name AS user_name, u.role AS user_role 
                  FROM testimonials t 
                  LEFT JOIN users u ON t.user_id = u.id 
                  WHERE t.product_id = :product_id 
                  ORDER BY t.created_at DESC";
$stmt_reviews = $pdo->prepare($query_reviews);
$stmt_reviews->execute(['product_id' => $id_produk]);
$reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

$total_rating = 0;
$count_rating = count($reviews);
foreach ($reviews as $rev) {
    $total_rating += $rev['rating'];
}
$avg_rating = $count_rating > 0 ? number_format($total_rating / $count_rating, 1) : "5.0";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Filosofi Kopi</title>
    <link rel="stylesheet" href="detail_produk.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <style>
        .cart-link-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -12px;
            background-color: #d9381e;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            transition: transform 0.2s ease;
        }
        .cart-badge.bump {
            transform: scale(1.3);
        }

        /* Styling saat Stok Habis (0) */
        .stock-empty-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 6px;
        }
        .btn-disabled-stock {
            background-color: #bdbdbd !important;
            color: #ffffff !important;
            border-color: #bdbdbd !important;
            cursor: not-allowed !important;
            opacity: 0.85;
        }
        .product-image-wrap {
            position: relative;
            width: 100%;
        }
        .sold-out-overlay {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(198, 40, 40, 0.92);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 6px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        /* Kontainer Notifikasi Popup */
        .notif-container {
            position: fixed;
            top: 95px;
            right: 25px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }
        .notif-toast {
            pointer-events: auto;
            background-color: #3b2316;
            color: #ffffff;
            border-left: 6px solid #d4a373;
            border-radius: 12px;
            padding: 16px 20px;
            min-width: 310px;
            max-width: 380px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.28);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            position: relative;
            overflow: hidden;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .notif-toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .notif-toast.error {
            border-left-color: #e74c3c;
        }
        .notif-icon {
            font-size: 1.6rem;
            color: #d4a373;
            margin-top: 2px;
        }
        .notif-toast.error .notif-icon {
            color: #e74c3c;
        }
        .notif-content {
            flex: 1;
        }
        .notif-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .notif-message {
            font-size: 0.83rem;
            color: #f2e6e6;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        .notif-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notif-btn-cart {
            background-color: #d4a373;
            color: #2b180e;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .notif-btn-cart:hover {
            background-color: #e6b88a;
        }
        .notif-close {
            background: none;
            border: none;
            color: #ccc;
            font-size: 1.1rem;
            cursor: pointer;
            line-height: 1;
        }
        .notif-close:hover {
            color: #fff;
        }
        .notif-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background-color: #d4a373;
            width: 100%;
            animation: notifShrink 4s linear forwards;
        }
        @keyframes notifShrink {
            from { width: 100%; }
            to { width: 0%; }
        }

        /* Star Rating Interaktif */
        .star-rating {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 5px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 1.5rem;
            color: #ccc;
            cursor: pointer;
            transition: color 0.15s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f5b301;
            font-weight: 900;
        }

        /* Preview Foto Sebelum Kirim & Foto di Daftar Ulasan */
        .review-preview-box {
            margin-top: 10px;
            display: none;
            align-items: center;
            gap: 12px;
        }
        .review-preview-box img {
            width: 85px;
            height: 85px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #d4a373;
        }
        .review-photo {
            margin-top: 10px;
            max-width: 180px;
            max-height: 180px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e0d6d3;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .review-photo:hover {
            transform: scale(1.03);
        }
        .badge-role-admin {
            background-color: #3b2316;
            color: #d4a373;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 6px;
            font-weight: 600;
        }
        .review-alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .review-alert.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .review-alert.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .review-locked-box {
            background: #f8f4f0;
            border: 1px dashed #c8b6a6;
            border-radius: 12px;
            padding: 18px 20px;
            color: #5c4542;
            font-size: 0.9rem;
            width: 100%;
        }
    </style>
</head>
<body>

    <!-- KONTAINER NOTIFIKASI POPUP -->
    <div id="notif-container" class="notif-container"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <a href="<?= $home_link ?>" class="nav-brand">Filosofi Kopi</a>
        </div>
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>
        <div class="nav-right">
            <a href="halaman_keranjang.php" class="cart-link-wrapper" title="Keranjang">
                <i class="fas fa-shopping-cart"></i>
                <span id="cart-badge" class="cart-badge" style="display: <?= $totalCartItems > 0 ? 'flex' : 'none' ?>;">
                    <?= $totalCartItems ?>
                </span>
            </a>
            <a href="pesanan.php"><i class="fas fa-envelope"></i></a>
            <a href="profile.php"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- KONTEN UTAMA -->
    <main class="detail-container">
        
        <!-- FORM DETAIL PRODUK UNTUK TAMBAH KERANJANG -->
        <form action="tambah_keranjang.php" method="POST" id="form-detail-cart">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

            <section class="product-detail">
                <div class="product-left">
                    <div class="product-image-wrap">
                        <img src="<?= !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://via.placeholder.com/400x400?text=No+Image' ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="product-image"
                             style="<?= $is_out_of_stock ? 'filter: grayscale(40%);' : '' ?>">
                        <?php if ($is_out_of_stock): ?>
                            <span class="sold-out-overlay">STOK HABIS</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_out_of_stock): ?>
                        <button type="button" class="btn-primary btn-disabled-stock" id="btn-buy-now" disabled>
                            Stok Habis
                        </button>
                        <button type="button" class="btn-secondary btn-disabled-stock" id="btn-add-cart" disabled>
                            <i class="fas fa-ban"></i> Tidak Tersedia
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-primary" id="btn-buy-now">Beli Sekarang</button>
                        <button type="submit" class="btn-secondary" id="btn-add-cart">
                            <i class="fas fa-shopping-cart"></i> Tambah Keranjang
                        </button>
                    <?php endif; ?>
                </div>

                <div class="product-right">
                    <div class="product-header">
                        <div class="title-group">
                            <h2><?= htmlspecialchars($product['name']) ?></h2>
                            <div class="rating">
                                <span class="stars">
                                    <?php 
                                        $full_stars = floor($avg_rating);
                                        for($i=0; $i<5; $i++){
                                            if($i < $full_stars) echo "★"; 
                                            else echo "☆"; 
                                        }
                                    ?>
                                </span>
                                <span class="score"><?= $avg_rating ?>/5 (<?= $count_rating ?> ulasan)</span>
                            </div>
                        </div>
                        
                        <div class="price-display">
                            Rp <?= number_format($product['price'], 0, ',', '.') ?>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="description">
                        <h3>Deskripsi :</h3>
                        <p><?= nl2br(htmlspecialchars($product['description'] ?? 'Belum ada deskripsi untuk produk ini.')) ?></p>
                        
                        <h3>Informasi Produk :</h3>
                        <p>
                            Kategori: <strong><?= htmlspecialchars($product['category_name'] ?? 'Umum') ?></strong><br>
                            Sisa Stok: 
                            <?php if ($is_out_of_stock): ?>
                                <strong style="color:#c62828;">0 <?= htmlspecialchars($product['unit'] ?? 'CUP') ?> (Habis)</strong>
                            <?php else: ?>
                                <strong style="color:#2e7d32;"><?= $stok_tersedia ?> <?= htmlspecialchars($product['unit'] ?? 'CUP') ?></strong>
                            <?php endif; ?>
                        </p>

                        <?php if ($is_out_of_stock): ?>
                            <div class="stock-empty-badge">
                                <i class="fas fa-circle-exclamation"></i> Maaf, stok produk ini sedang kosong dan belum dapat dipesan.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="divider"></div>

                    <!-- Opsi Suhu -->
                    <div class="options-group">
                        <h4>Pilihan Suhu Es ( Ice Level ) :</h4>
                        <div class="options-row">
                            <label class="option-btn"><input type="radio" name="ice_level" value="Ice (Normal)" checked <?= $is_out_of_stock ? 'disabled' : '' ?>> Ice (Normal)</label>
                            <label class="option-btn"><input type="radio" name="ice_level" value="Less Ice (Sedikit Es)" <?= $is_out_of_stock ? 'disabled' : '' ?>> Less Ice (Sedikit Es)</label>
                            <label class="option-btn"><input type="radio" name="ice_level" value="No Ice (Tanpa Es)" <?= $is_out_of_stock ? 'disabled' : '' ?>> No Ice (Tanpa Es)</label>
                        </div>
                    </div>

                    <!-- Opsi Gula -->
                    <div class="options-group">
                        <h4>Pilihan Gula (Sugar Level) :</h4>
                        <div class="options-row">
                            <label class="option-btn"><input type="radio" name="sugar_level" value="Normal Gula 100%" checked <?= $is_out_of_stock ? 'disabled' : '' ?>> Normal Gula 100%</label>
                            <label class="option-btn"><input type="radio" name="sugar_level" value="Sedikit Gula 70%" <?= $is_out_of_stock ? 'disabled' : '' ?>> Sedikit Gula 70%</label>
                            <label class="option-btn"><input type="radio" name="sugar_level" value="Setengah Gula 50%" <?= $is_out_of_stock ? 'disabled' : '' ?>> Setengah Gula 50%</label>
                            <label class="option-btn"><input type="radio" name="sugar_level" value="Tanpa Gula 0%" <?= $is_out_of_stock ? 'disabled' : '' ?>> Tanpa Gula 0%</label>
                        </div>
                    </div>

                    <!-- Kuantitas -->
                    <div class="quantity-wrapper">
                        <div class="quantity-box" style="<?= $is_out_of_stock ? 'opacity:0.5; pointer-events:none;' : '' ?>">
                            <button type="button" id="btn-minus" <?= $is_out_of_stock ? 'disabled' : '' ?>>-</button>
                            <input type="text" name="quantity" id="qty-input" value="<?= $is_out_of_stock ? '0' : '1' ?>" readonly>
                            <button type="button" id="btn-plus" <?= $is_out_of_stock ? 'disabled' : '' ?>>+</button>
                        </div>
                    </div>
                </div>
            </section>
        </form>

        <div class="divider-full"></div>

        <!-- KOMENTAR & RATING -->
        <section class="comment-section" id="section-ulasan">
            <div class="comment-title">
                <h3>Komentar<br>&<br>Rating</h3>
            </div>
            <div class="comment-form">
                <?php if ($reviewFeedback !== ''): ?>
                    <div class="review-alert success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($reviewFeedback) ?>
                    </div>
                <?php endif; ?>

                <?php if ($reviewError !== ''): ?>
                    <div class="review-alert error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($reviewError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($canReview): ?>
                    <form action="detail_produk.php?id=<?= $id_produk ?><?= $eligibleOrderId ? '&order_id=' . $eligibleOrderId : '' ?>#section-ulasan" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="submit_review" value="1">
                        <input type="hidden" name="order_id" value="<?= (int)$eligibleOrderId ?>">
                        <input type="hidden" name="order_item_id" value="<?= (int)$eligibleItemId ?>">

                        <textarea name="comment" placeholder="Bagikan pengalamanmu menikmati kopi ini..." required></textarea>
                        
                        <div class="review-preview-box" id="review-preview-box">
                            <img id="review-preview-img" src="" alt="Preview Foto Ulasan">
                            <button type="button" id="btn-remove-photo" style="background:#8b1e1e; color:#fff; border:none; padding:5px 12px; border-radius:15px; font-size:12px; cursor:pointer;">
                                Hapus Foto
                            </button>
                        </div>

                        <div class="form-actions">
                            <div class="star-rating" title="Pilih Rating Bintang">
                                <input type="radio" id="star5" name="rating" value="5" checked />
                                <label for="star5" class="fas fa-star"></label>
                                <input type="radio" id="star4" name="rating" value="4" />
                                <label for="star4" class="fas fa-star"></label>
                                <input type="radio" id="star3" name="rating" value="3" />
                                <label for="star3" class="fas fa-star"></label>
                                <input type="radio" id="star2" name="rating" value="2" />
                                <label for="star2" class="fas fa-star"></label>
                                <input type="radio" id="star1" name="rating" value="1" />
                                <label for="star1" class="fas fa-star"></label>
                            </div>

                            <div class="upload-btns">
                                <input type="file" name="review_media" id="review_media" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                                <button type="button" class="btn-upload" onclick="document.getElementById('review_media').click();">
                                    <i class="fas fa-camera"></i><br>Upload Foto
                                </button>
                            </div>
                            <button type="submit" class="btn-submit">Kirim Ulasan</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="review-locked-box">
                        <i class="fas fa-lock" style="margin-right:6px;"></i>
                        Form rating dan ulasan hanya tersedia bagi pelanggan yang telah menyelesaikan pesanan produk ini (Status: <strong>Selesai</strong>). 
                        Cek status pesanan Anda di halaman <a href="pesanan.php?status=selesai" style="color:#3b2316; font-weight:700; text-decoration:underline;">Pesanan Saya</a>.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="divider-full"></div>

        <!-- DAFTAR KOMENTAR (REVIEW) DINAMIS -->
        <section class="review-list">
            <?php if(count($reviews) > 0): ?>
                <?php foreach($reviews as $rev): ?>
                    <div class="review-card">
                        <div class="review-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="review-content">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <h4>
                                        <?= htmlspecialchars($rev['user_name'] ?? 'Pelanggan') ?>
                                        <?php if (($rev['user_role'] ?? '') === 'Admin'): ?>
                                            <span class="badge-role-admin">Admin</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="stars">
                                        <?php 
                                            for($i=1; $i<=5; $i++){
                                                if($i <= $rev['rating']) echo "★"; 
                                                else echo "☆"; 
                                            }
                                        ?>
                                    </span>
                                </div>
                                <span class="review-date"><?= date('d-m-Y H:i', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>

                            <?php if (!empty($rev['media_url'])): ?>
                                <div>
                                    <img src="<?= htmlspecialchars($rev['media_url']) ?>" 
                                         alt="Foto Ulasan <?= htmlspecialchars($rev['user_name'] ?? '') ?>" 
                                         class="review-photo"
                                         onclick="window.open(this.src, '_blank')">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #888; background: #f9f9f9; border-radius: 10px;">
                    <i class="fas fa-comment-slash" style="font-size: 30px; margin-bottom: 10px;"></i>
                    <p>Belum ada ulasan untuk produk ini. Selesaikan pesananmu untuk menjadi yang pertama memberi rating!</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo" class="footer-logo">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        
        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FilosofiKopi" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <!-- SCRIPT KUANTITAS, NOTIFIKASI KERANJANG, PREVIEW FOTO & PROTEKSI STOK 0 -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productId     = <?= (int)$product['id'] ?>;
            const maxStok       = <?= (int)$stok_tersedia ?>;
            const isOutOfStock  = <?= $is_out_of_stock ? 'true' : 'false' ?>;

            const btnMinus  = document.getElementById('btn-minus');
            const btnPlus   = document.getElementById('btn-plus');
            const qtyInput  = document.getElementById('qty-input');
            const btnBuyNow = document.getElementById('btn-buy-now');

            // Preview Foto Ulasan Sebelum Dikirim
            const reviewMediaInput = document.getElementById('review_media');
            const previewBox       = document.getElementById('review-preview-box');
            const previewImg       = document.getElementById('review-preview-img');
            const btnRemovePhoto   = document.getElementById('btn-remove-photo');

            if (reviewMediaInput) {
                reviewMediaInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            previewBox.style.display = 'flex';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (btnRemovePhoto) {
                btnRemovePhoto.addEventListener('click', function() {
                    reviewMediaInput.value = '';
                    previewImg.src = '';
                    previewBox.style.display = 'none';
                });
            }

            function catatAktivitasMenu(eventType) {
                const payload = new URLSearchParams();
                payload.append('action_track', '1');
                payload.append('product_id', productId);
                payload.append('event_type', eventType);

                if (navigator.sendBeacon) {
                    const blob = new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
                    navigator.sendBeacon('detail_produk.php?id=' + productId, blob);
                } else {
                    fetch('detail_produk.php?id=' + productId, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload.toString(),
                        keepalive: true
                    });
                }
            }

            const formDetailCart = document.getElementById('form-detail-cart');
            const btnAddCart = document.getElementById('btn-add-cart');
            const notifContainer = document.getElementById('notif-container');
            const cartBadge = document.getElementById('cart-badge');
            const namaProdukDefault = <?= json_encode($product['name']) ?>;

            function tampilkanNotifikasi(judul, pesan, isError = false) {
                const toast = document.createElement('div');
                toast.className = 'notif-toast' + (isError ? ' error' : '');
                toast.innerHTML = `
                    <div class="notif-icon">
                        <i class="fas ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">${judul}</div>
                        <div class="notif-message">${pesan}</div>
                        ${!isError ? `
                        <div class="notif-actions">
                            <a href="halaman_keranjang.php" class="notif-btn-cart">Lihat Keranjang</a>
                        </div>` : ''}
                    </div>
                    <button type="button" class="notif-close" title="Tutup">&times;</button>
                    <div class="notif-progress"></div>
                `;

                notifContainer.appendChild(toast);
                requestAnimationFrame(() => toast.classList.add('show'));

                const tutupToast = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 350);
                };

                toast.querySelector('.notif-close').addEventListener('click', tutupToast);
                setTimeout(tutupToast, 4000);
            }

            if (btnBuyNow) {
                btnBuyNow.addEventListener('click', function() {
                    if (isOutOfStock || maxStok <= 0) {
                        tampilkanNotifikasi('Stok Habis', 'Produk ini sedang kosong dan tidak dapat dibeli.', true);
                        return;
                    }
                    catatAktivitasMenu('buy_now');
                    window.location.href = 'pembayaran.php?id=' + productId;
                });
            }

            if (btnPlus) {
                btnPlus.addEventListener('click', function() {
                    if (isOutOfStock || maxStok <= 0) return;
                    let currentValue = parseInt(qtyInput.value) || 1;
                    if (currentValue < maxStok) {
                        qtyInput.value = currentValue + 1;
                    } else {
                        tampilkanNotifikasi('Batas Stok Tercapai', `Maksimal pembelian adalah ${maxStok} cup sesuai sisa stok.`, true);
                    }
                });
            }

            if (btnMinus) {
                btnMinus.addEventListener('click', function() {
                    if (isOutOfStock || maxStok <= 0) return;
                    let currentValue = parseInt(qtyInput.value) || 1;
                    if (currentValue > 1) {
                        qtyInput.value = currentValue - 1;
                    }
                });
            }

            if (formDetailCart) {
                formDetailCart.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (isOutOfStock || maxStok <= 0) {
                        tampilkanNotifikasi('Stok Habis', 'Produk ini sedang kosong dan tidak dapat dimasukkan ke keranjang.', true);
                        return;
                    }

                    const qty = parseInt(qtyInput.value) || 1;
                    if (qty > maxStok) {
                        tampilkanNotifikasi('Stok Tidak Cukup', `Sisa stok hanya ${maxStok} cup.`, true);
                        return;
                    }

                    const isiAwalBtn = btnAddCart.innerHTML;
                    btnAddCart.disabled = true;
                    btnAddCart.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';

                    const formData = new FormData(formDetailCart);
                    formData.append('ajax', '1');

                    const ice = formData.get('ice_level') || '';
                    const sugar = formData.get('sugar_level') || '';
                    const detailVarian = [ice, sugar].filter(Boolean).join(', ');

                    fetch('tambah_keranjang.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            catatAktivitasMenu('add_to_cart');

                            if (cartBadge) {
                                cartBadge.textContent = data.total_items;
                                cartBadge.style.display = 'flex';
                                cartBadge.classList.add('bump');
                                setTimeout(() => cartBadge.classList.remove('bump'), 250);
                            }
                            const namaProduk = data.product_name || namaProdukDefault;
                            tampilkanNotifikasi(
                                'Ditambahkan ke Keranjang!',
                                `<strong>${qty}x ${namaProduk}</strong> (${detailVarian}) berhasil masuk ke keranjang.`
                            );
                        } else {
                            tampilkanNotifikasi(
                                'Gagal Menambahkan',
                                data.message || 'Produk gagal ditambahkan ke keranjang.',
                                true
                            );
                        }
                    })
                    .catch(() => {
                        tampilkanNotifikasi(
                            'Gagal Menambahkan',
                            'Terjadi gangguan koneksi atau stok produk tidak mencukupi.',
                            true
                        );
                    })
                    .finally(() => {
                        btnAddCart.disabled = false;
                        btnAddCart.innerHTML = isiAwalBtn;
                    });
                });
            }
        });
    </script>

</body>
</html>