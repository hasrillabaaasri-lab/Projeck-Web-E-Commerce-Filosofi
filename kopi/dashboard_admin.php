<?php
require_once 'auth_erp.php';

// Helper formatRupiah jika belum didefinisikan di config.php
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

$all_menus = getDaftarMenuErp();

// =========================================================
// SINKRONISASI TABEL CART & ORDERS OTOMATIS
// =========================================================
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

try {
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('Pending','Diproses','Dikirim','Selesai','Batal','Dikembalikan','Dihentikan') DEFAULT 'Pending'");
    $cekCol = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'catatan_admin'")->fetch();
    if (!$cekCol) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `catatan_admin` VARCHAR(255) DEFAULT NULL AFTER `status`");
    }
} catch (PDOException $e) {}

// =========================================================
// AKSI POST: TAMBAH/HAPUS ROLE & UPDATE STATUS PESANAN
// =========================================================
$notif_aksi  = '';
$notif_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Tambah Role Baru + Hak Akses Menu Sidebar dari Modal
    if (isset($_POST['tambah_role'])) {
        $role_baru      = trim($_POST['role_name'] ?? '');
        $can_erp        = isset($_POST['can_access_erp']) ? 1 : 0;
        $selected_menus = $_POST['menus'] ?? [];

        $valid_menus = ($can_erp === 1)
            ? array_values(array_intersect(array_keys($all_menus), $selected_menus))
            : [];

        if ($role_baru !== '') {
            try {
                $stmtRole = $pdo->prepare("
                    INSERT INTO `user_roles` (`role_name`, `can_access_erp`, `allowed_menus`)
                    VALUES (:role, :erp, :menus)
                ");
                $stmtRole->execute([
                    ':role'  => $role_baru,
                    ':erp'   => $can_erp,
                    ':menus' => json_encode($valid_menus)
                ]);
                $notif_aksi = "Role baru <strong>" . htmlspecialchars($role_baru) . "</strong> berhasil ditambahkan beserta hak akses menunya.";
            } catch (PDOException $e) {
                $notif_error = "Role <strong>" . htmlspecialchars($role_baru) . "</strong> sudah ada.";
            }
        } else {
            $notif_error = "Nama role tidak boleh kosong.";
        }
    }

    // 2. Hapus Role Custom
    if (isset($_POST['hapus_role'])) {
        $role_hapus  = trim($_POST['role_name_del'] ?? '');
        $role_bawaan = ['Admin', 'Kasir', 'Pelanggan'];
        if ($role_hapus !== '' && !in_array($role_hapus, $role_bawaan, true)) {
            $stmtReset = $pdo->prepare("UPDATE `users` SET `role` = 'Pelanggan' WHERE `role` = :role");
            $stmtReset->execute([':role' => $role_hapus]);

            $stmtDelRole = $pdo->prepare("DELETE FROM `user_roles` WHERE `role_name` = :role");
            $stmtDelRole->execute([':role' => $role_hapus]);
            $notif_aksi = "Role <strong>" . htmlspecialchars($role_hapus) . "</strong> berhasil dihapus.";
        }
    }

    // 3. Update Cepat Status Pesanan
    if (isset($_POST['quick_update_order'])) {
        $order_id    = (int) $_POST['order_id'];
        $status_baru = $_POST['status'] ?? 'Pending';
        $allowed     = ['Pending', 'Diproses', 'Dikirim', 'Selesai', 'Batal', 'Dikembalikan'];

        if (in_array($status_baru, $allowed, true)) {
            $stmtUpd = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmtUpd->execute([':status' => $status_baru, ':id' => $order_id]);
            $notif_aksi = "Status pesanan berhasil diubah menjadi <strong>" . htmlspecialchars($status_baru) . "</strong>.";
        }
    }
}

// =========================================================
// ENDPOINT AJAX NOTIFIKASI REAL-TIME (KERANJANG & PESANAN)
// =========================================================
if (isset($_GET['ajax_erp_notif']) || isset($_GET['ajax_cart_notif'])) {
    header('Content-Type: application/json');

    $stmtNotif = $pdo->query("
        SELECT 
            c.id,
            c.quantity,
            c.ice_level,
            c.sugar_level,
            c.updated_at,
            u.name AS user_name,
            u.role AS user_role,
            p.name AS product_name
        FROM cart c
        INNER JOIN users u ON c.user_id = u.id
        INNER JOIN products p ON c.product_id = p.id
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 5
    ");
    $cartItems = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    $totalQty  = (int) $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart")->fetchColumn();

    $stmtOrder = $pdo->query("
        SELECT 
            o.id,
            o.order_code,
            o.total_amount,
            o.payment_method,
            o.status,
            o.order_date,
            COALESCE(u.name, 'Pelanggan') AS customer_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC
        LIMIT 5
    ");
    $orderItems   = $stmtOrder->fetchAll(PDO::FETCH_ASSOC);
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();

    echo json_encode([
        'total_qty'     => $totalQty,
        'latest_id'     => count($cartItems) > 0 ? (int)$cartItems[0]['id'] : 0,
        'latest_ts'     => count($cartItems) > 0 ? $cartItems[0]['updated_at'] : '',
        'items'         => $cartItems,
        'pending_count' => $pendingCount,
        'latest_order'  => count($orderItems) > 0 ? $orderItems[0] : null,
        'orders'        => $orderItems
    ]);
    exit();
}

// =========================================================
// 1. DATA ROLE UNTUK SIDEBAR KIRI
// =========================================================
$roles_list = $pdo->query("
    SELECT r.id, r.role_name, r.can_access_erp, r.allowed_menus, COUNT(u.id) AS total_user
    FROM `user_roles` r
    LEFT JOIN `users` u ON u.role = r.role_name
    GROUP BY r.id, r.role_name, r.can_access_erp, r.allowed_menus
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
$total_semua_user = (int) $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

// =========================================================
// 2. STATISTIK UTAMA
// =========================================================
$total_pesanan    = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pesanan_pending  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
$total_pendapatan = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'Batal'")->fetchColumn();
$total_produk     = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// =========================================================
// 3. PRODUK PERLU RESTOK & DETAIL STOK MENIPIS
// =========================================================
$produk_restok = (int) $pdo->query("SELECT COUNT(*) FROM stock_master WHERE current_stock <= min_stock")->fetchColumn();
$low_stock = $pdo->query("
    SELECT name, sku, image_url, current_stock, min_stock, unit 
    FROM stock_master 
    WHERE current_stock <= min_stock 
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// 4. BERITA TERBARU
// =========================================================
$latest_news = $pdo->query("
    SELECT id, title, image_url, published_at 
    FROM news 
    ORDER BY published_at DESC 
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// 5. NOTIFIKASI KERANJANG USER & ADMIN TERBARU
// =========================================================
$cart_notifications = $pdo->query("
    SELECT 
        c.id,
        c.quantity,
        c.ice_level,
        c.sugar_level,
        c.updated_at,
        u.name AS user_name,
        u.role AS user_role,
        p.name AS product_name,
        p.image_url
    FROM cart c
    INNER JOIN users u ON c.user_id = u.id
    INNER JOIN products p ON c.product_id = p.id
    ORDER BY c.updated_at DESC, c.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$total_cart_qty = (int) $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart")->fetchColumn();

// =========================================================
// 6. DAFTAR PESANAN PENGGUNA TERBARU (UNTUK ADMIN)
// =========================================================
$recent_orders = $pdo->query("
    SELECT 
        o.id,
        o.order_code,
        o.channel,
        o.total_amount,
        o.payment_method,
        o.status,
        o.order_date,
        COALESCE(u.name, 'Pelanggan') AS customer_name,
        GROUP_CONCAT(CONCAT(COALESCE(p.name, 'Produk'), ' x', oi.quantity) SEPARATOR ', ') AS items_summary
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id, o.order_code, o.channel, o.total_amount, o.payment_method, o.status, o.order_date, u.name
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$total_notif_badge = $total_cart_qty + $pesanan_pending;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
        }
        .erp-notif-btn {
            position: relative;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .erp-notif-btn:hover {
            background: rgba(255,255,255,0.22);
        }
        .erp-notif-badge {
            position: absolute;
            top: -5px;
            right: -6px;
            background-color: #e74c3c;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .erp-notif-dropdown {
            display: none;
            position: absolute;
            top: 48px;
            right: 0;
            width: 360px;
            background: #fff;
            color: #222;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.22);
            border: 1px solid #e0d6d1;
            z-index: 9999;
            overflow: hidden;
        }
        .erp-notif-dropdown.open {
            display: block;
        }
        .erp-notif-header {
            background-color: #3b2316;
            color: #fff;
            padding: 12px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .erp-notif-list {
            max-height: 330px;
            overflow-y: auto;
        }
        .erp-notif-section-title {
            background: #f6f1ee;
            padding: 6px 15px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #3b2316;
            text-transform: uppercase;
            border-bottom: 1px solid #e8dfd9;
        }
        .erp-notif-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0ece9;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        .erp-notif-item:last-child {
            border-bottom: none;
        }
        .erp-role-tag {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            background: #f2e6e6;
            color: #3b2316;
            margin-left: 4px;
        }

        /* ================= SIDEBAR ROLE AKUN (BAGIAN KIRI) ================= */
        .sidebar-role-box {
            margin-top: 10px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #e6d7cf;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 0 4px;
        }
        .btn-sidebar-add-role {
            background-color: #d4a373;
            color: #2b170e;
            border: none;
            border-radius: 14px;
            padding: 3px 9px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: 0.2s;
        }
        .btn-sidebar-add-role:hover {
            background-color: #e6b88a;
        }
        .role-nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .role-nav-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.08);
            color: #f3ece8;
            padding: 7px 12px;
            border-radius: 18px;
            font-size: 11px;
            font-weight: 600;
            transition: 0.2s;
        }
        .role-nav-item:hover {
            background-color: #ffffff;
            color: #2b170e;
        }
        .role-count-pill {
            background-color: rgba(0, 0, 0, 0.28);
            color: #fff;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 10px;
        }
        .role-nav-item:hover .role-count-pill {
            background-color: #3b2316;
            color: #fff;
        }

        /* Badge Status Pesanan */
        .st-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
        }
        .st-pending      { background: #fff3cd; color: #856404; }
        .st-diproses     { background: #cce5ff; color: #004085; }
        .st-dikirim      { background: #d1ecf1; color: #0c5460; }
        .st-selesai      { background: #d4edda; color: #155724; }
        .st-batal        { background: #f8d7da; color: #721c24; }
        .st-dikembalikan { background: #e2e3e5; color: #383d41; }

        .quick-status-form {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .quick-status-form select {
            padding: 4px 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 11px;
        }
        .quick-status-form button {
            background: #3b2316;
            color: #fff;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        .quick-status-form button:hover {
            background: #5c3824;
        }

        .alert-dashboard {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            color: #1b5e20;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .alert-dashboard.error {
            background: #ffebee;
            border-left-color: #c62828;
            color: #b71c1c;
        }

        /* Modal Tambah Role */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 470px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        .modal-header {
            background-color: #3b2316;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 15px; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 75vh;
            overflow-y: auto;
        }
        .modal-body input[type="text"] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 13px;
        }
        .erp-access-toggle {
            background: #f2e6e6;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #3b2316;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .menu-checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px 10px;
            background: #f9f6f4;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #e2d6d0;
        }
        .menu-chk-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
        }
        .menu-chk-item input[type="checkbox"] {
            accent-color: #3b2316;
            width: 14px;
            height: 14px;
        }
        .existing-role-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #eee;
            font-size: 12.5px;
        }
        .modal-footer {
            padding: 10px 20px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .btn-modal-save {
            background-color: #3b2316;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-modal-cancel {
            background-color: #eee;
            color: #333;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Popup Toast Notifikasi Masuk */
        .erp-toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .erp-toast {
            background-color: #3b2316;
            color: #fff;
            border-left: 5px solid #4caf50;
            border-radius: 10px;
            padding: 14px 18px;
            min-width: 300px;
            max-width: 370px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transform: translateY(30px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        .erp-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .erp-toast-icon {
            color: #4caf50;
            font-size: 1.3rem;
            margin-top: 2px;
        }
        .erp-toast-body {
            flex: 1;
            font-size: 0.82rem;
            line-height: 1.4;
        }
        .erp-toast-body strong {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        .erp-toast-close {
            background: none;
            border: none;
            color: #ccc;
            font-size: 1rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- KONTAINER TOAST NOTIFIKASI REAL-TIME -->
    <div id="erp-toast-container" class="erp-toast-container"></div>

    <!-- HEADER (KLIK LOGO / FILOSOFI KOPI MENUJU KE BERANDA.PHP) -->
    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <!-- LONCENG NOTIFIKASI PESANAN & KERANJANG -->
            <button type="button" id="erp-notif-btn" class="erp-notif-btn" title="Notifikasi Pesanan & Keranjang">
                <i class="fa-solid fa-bell"></i>
                <span id="erp-notif-badge" class="erp-notif-badge" style="display: <?= $total_notif_badge > 0 ? 'flex' : 'none' ?>;">
                    <?= $total_notif_badge ?>
                </span>
            </button>

            <div id="erp-notif-dropdown" class="erp-notif-dropdown">
                <div class="erp-notif-header">
                    <span><i class="fa-solid fa-bell"></i> Notifikasi ERP</span>
                    <a href="pemesanan_pelanggan.php" style="color: #d4a373; font-size: 0.75rem; text-decoration: underline;">Kelola Pesanan</a>
                </div>
                <div id="erp-notif-list" class="erp-notif-list">
                    <div class="erp-notif-section-title">Pesanan Masuk Terbaru (<?= $pesanan_pending ?> Pending)</div>
                    <?php if (count($recent_orders) > 0): ?>
                        <?php foreach (array_slice($recent_orders, 0, 3) as $ro): ?>
                            <div class="erp-notif-item">
                                <strong><?= htmlspecialchars($ro['order_code']) ?></strong> - <?= htmlspecialchars($ro['customer_name']) ?>
                                <span class="erp-role-tag"><?= htmlspecialchars($ro['status']) ?></span><br>
                                Total: <b><?= formatRupiah($ro['total_amount']) ?></b> (<?= htmlspecialchars($ro['payment_method'] ?? '-') ?>)<br>
                                <small style="color: #888;"><i class="fa-regular fa-clock"></i> <?= date('d M Y H:i', strtotime($ro['order_date'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="erp-notif-item" style="color: #888;">Belum ada pesanan masuk.</div>
                    <?php endif; ?>

                    <div class="erp-notif-section-title">Aktivitas Keranjang (<?= $total_cart_qty ?> Item)</div>
                    <?php if (count($cart_notifications) > 0): ?>
                        <?php foreach ($cart_notifications as $cn): ?>
                            <div class="erp-notif-item">
                                <strong><?= htmlspecialchars($cn['user_name']) ?></strong>
                                <span class="erp-role-tag"><?= htmlspecialchars($cn['user_role']) ?></span><br>
                                Menambahkan <b><?= (int)$cn['quantity'] ?>x <?= htmlspecialchars($cn['product_name']) ?></b>
                                (<?= htmlspecialchars($cn['ice_level']) ?>, <?= htmlspecialchars($cn['sugar_level']) ?>)<br>
                                <small style="color: #888;"><i class="fa-regular fa-clock"></i> <?= date('d M Y H:i', strtotime($cn['updated_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="erp-notif-item" style="text-align: center; color: #888; padding: 15px;">
                            Belum ada produk di keranjang.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>

            <!-- MENU SIDEBAR DINAMIS SESUAI HAK AKSES ROLE -->
            <?php renderSidebarMenuErp('dashboard_admin.php', $currentUserRole, $allowedMenus); ?>

            <!-- FITUR ROLE AKUN & TAMBAH ROLE DI BAGIAN KIRI -->
            <?php if ($currentUserRole === 'Admin' || in_array('pelanggan.php', $allowedMenus, true)): ?>
                <div class="sidebar-role-box">
                    <div class="sidebar-role-header">
                        <span><i class="fa-solid fa-user-shield"></i> ROLE AKUN</span>
                        <button type="button" class="btn-sidebar-add-role" onclick="openRoleModal()">
                            <i class="fa-solid fa-plus"></i> Role
                        </button>
                    </div>

                    <div class="role-nav-list">
                        <a href="pelanggan.php?role=Semua" class="role-nav-item">
                            <span>Semua Akun</span>
                            <span class="role-count-pill"><?= $total_semua_user ?></span>
                        </a>

                        <?php foreach ($roles_list as $rl): ?>
                            <a href="pelanggan.php?role=<?= urlencode($rl['role_name']) ?>" class="role-nav-item">
                                <span><?= htmlspecialchars($rl['role_name']) ?></span>
                                <span class="role-count-pill"><?= (int)$rl['total_user'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content-area">
            <div class="welcome-banner">
                <h2>Selamat Datang di Filosofi Kopi ERP Suite</h2>
                <p>Solusi bisnis terintegrasi untuk E-Commerce Toko Online, Inventaris, & Laporan Penjualan Real-time.</p>
            </div>

            <?php if ($notif_aksi): ?>
                <div class="alert-dashboard"><i class="fa-solid fa-circle-check"></i> <?= $notif_aksi ?></div>
            <?php endif; ?>
            <?php if ($notif_error): ?>
                <div class="alert-dashboard error"><i class="fa-solid fa-circle-exclamation"></i> <?= $notif_error ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <small>TOTAL PESANAN</small>
                        <h3><?= $total_pesanan ?> Transaksi</h3>
                        <p class="sub-text <?= $pesanan_pending > 0 ? 'warning' : '' ?>">
                            <?= $pesanan_pending > 0 ? $pesanan_pending . ' Pesanan Menunggu Diproses' : 'Semua Pesanan Terproses' ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <small>STATUS INTEGRASI</small>
                        <h3>Terhubung</h3>
                        <p class="sub-text">Filosofi Kopi Automatic Sync</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-rotate"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <small>TOTAL PENDAPATAN</small>
                        <h3><?= formatRupiah($total_pendapatan) ?></h3>
                        <p class="sub-text success"><i class="fa-solid fa-arrow-trend-up"></i> Synchronized ERP</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <small>STOK PERLU RESTOK</small>
                        <h3><?= $produk_restok ?> ITEM</h3>
                        <p class="sub-text warning"><i class="fa-solid fa-triangle-exclamation"></i> Segera Restock</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                </div>
            </div>

            <div class="details-grid">
                <div class="left-column">
                    <!-- TABEL PESANAN PENGGUNA TERBARU -->
                    <div class="card">
                        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>
                                <i class="fa-solid fa-receipt"></i> Pesanan Pelanggan Terbaru
                                <?php if ($pesanan_pending > 0): ?>
                                    <span class="badge warning-badge" style="margin-left: 6px;"><?= $pesanan_pending ?> Baru</span>
                                <?php endif; ?>
                            </span>
                            <a href="pemesanan_pelanggan.php" class="btn-action" style="text-decoration: none;">Kelola Semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>KODE ORDER</th>
                                        <th>PELANGGAN & MENU</th>
                                        <th>TOTAL</th>
                                        <th>STATUS</th>
                                        <th>UBAH STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_orders)): ?>
                                        <?php foreach ($recent_orders as $ord): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($ord['order_code']) ?></strong><br>
                                                    <small style="color:#777; font-size:10px;"><?= date('d M H:i', strtotime($ord['order_date'])) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($ord['customer_name']) ?></strong><br>
                                                    <small style="color:#555; font-size:11px;"><?= htmlspecialchars($ord['items_summary'] ?? '-') ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= formatRupiah($ord['total_amount']) ?></strong><br>
                                                    <small style="color:#777; font-size:10px;"><?= htmlspecialchars($ord['payment_method'] ?? '-') ?></small>
                                                </td>
                                                <td>
                                                    <span class="st-badge st-<?= strtolower($ord['status']) ?>">
                                                        <?= htmlspecialchars($ord['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="quick-status-form">
                                                        <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                        <select name="status">
                                                            <?php 
                                                            $opsi = ['Pending', 'Diproses', 'Dikirim', 'Selesai', 'Batal', 'Dikembalikan'];
                                                            foreach ($opsi as $op): ?>
                                                                <option value="<?= $op ?>" <?= $ord['status'] === $op ? 'selected' : '' ?>><?= $op ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="quick_update_order">OK</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" style="text-align:center;">Belum ada pesanan pengguna.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TABEL STOK MENIPIS -->
                    <div class="card warning-card">
                        <div class="card-title"><i class="fa-solid fa-triangle-exclamation text-danger"></i> Peringatan Bahan Baku / Stok Menipis</div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Nama Barang</th><th>SKU</th><th>Sisa Stock</th><th>MIN.STOK</th><th>AKSI</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell" style="display:flex; align-items:center; gap:8px;">
                                                <img src="<?= !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'https://via.placeholder.com/35' ?>" 
                                                     alt="img" style="width:35px; height:35px; border-radius:6px; object-fit:cover;"
                                                     onerror="this.src='https://via.placeholder.com/35'"> 
                                                <span><?= htmlspecialchars($row['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['sku']) ?></td>
                                        <td><span class="badge warning-badge"><?= (int)$row['current_stock'] ?> <?= htmlspecialchars($row['unit']) ?></span></td>
                                        <td><b><?= (int)$row['min_stock'] ?> <?= htmlspecialchars($row['unit']) ?></b></td>
                                        <td><a href="stok_master.php" class="btn-action">Restock</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (!$low_stock): ?>
                                        <tr><td colspan="5" class="text-center">Semua stok aman.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- KARTU OMZET -->
                    <div class="card revenue-card">
                        <div class="card-header-icon" style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 10px;">
                            <span><i class="fa-solid fa-layer-group"></i> <strong>Pendapatan Kotor / Omzet</strong></span>
                            <a href="laporan_penjualan.php" class="btn-action" style="font-size: 10px; padding: 5px 10px; text-decoration: none; background-color: #4caf50;">
                                <i class="fa-solid fa-chart-line"></i> Detail Laporan
                            </a>
                        </div>
                        <div class="revenue-status" style="margin-bottom: 8px; font-weight: bold;">
                            <span class="dot green"></span> E-COMMERCE &bull; <span class="amount"><?= formatRupiah($total_pendapatan) ?></span>
                        </div>
                        <p class="card-desc" style="font-size: 12px; color: #555;">Auto Sync: Setiap transaksi baik di POS Kasir maupun Toko E-Commerce secara instan terhubung dengan database pusat secara akurat.</p>
                    </div>
                </div>

                <div class="right-column">
                    <!-- KARTU NOTIFIKASI KERANJANG TERBARU -->
                    <div class="card news-card" style="margin-bottom: 20px;">
                        <div class="news-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fa-solid fa-cart-shopping"></i> Keranjang Aktif</span>
                            <span style="font-size: 11px; background: #fff; color: #3b2316; padding: 2px 8px; border-radius: 10px; font-weight: bold;">
                                <?= $total_cart_qty ?> Item
                            </span>
                        </div>
                        <div class="news-body" id="card-cart-notif-body">
                            <?php if (count($cart_notifications) > 0): ?>
                                <div class="news-list">
                                    <?php foreach ($cart_notifications as $cn): ?>
                                        <div class="news-item" style="cursor: default;">
                                            <?php if (!empty($cn['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($cn['image_url']) ?>" alt="Produk" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="news-img-placeholder"><i class="fa-solid fa-mug-hot"></i></div>
                                            <?php endif; ?>
                                            <div class="news-info">
                                                <h4><?= htmlspecialchars($cn['product_name']) ?> (<?= (int)$cn['quantity'] ?>x)</h4>
                                                <small>
                                                    Oleh: <b><?= htmlspecialchars($cn['user_name']) ?></b> (<?= htmlspecialchars($cn['user_role']) ?>) &bull; <?= htmlspecialchars($cn['ice_level']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-news">
                                    <i class="fa-solid fa-cart-arrow-down"></i>
                                    <p>Belum ada item di keranjang.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- KARTU BERITA -->
                    <div class="card news-card">
                        <div class="news-header">Berita/Blog</div>
                        <div class="news-body">
                            <?php if (count($latest_news) > 0): ?>
                                <div class="news-list">
                                    <?php foreach ($latest_news as $n): ?>
                                        <a href="berita.php" class="news-item">
                                            <?php if (!empty($n['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($n['image_url']) ?>" alt="News Image" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="news-img-placeholder"><i class="fa-solid fa-newspaper"></i></div>
                                            <?php endif; ?>
                                            <div class="news-info">
                                                <h4><?= htmlspecialchars($n['title']) ?></h4>
                                                <small><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($n['published_at'])) ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-news">
                                    <i class="fa-solid fa-newspaper"></i>
                                    <p>Belum ada berita.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- MODAL TAMBAH & KELOLA ROLE AKUN + HAK AKSES MENU SIDEBAR -->
    <div id="modalRoleSidebar" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-shield"></i> Tambah & Kelola Role Akun</h3>
                <button type="button" class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div>
                        <label style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">Nama Role Baru</label>
                        <input type="text" name="role_name" placeholder="Contoh: Barista, Gudang, Supervisor" required>
                    </div>

                    <label class="erp-access-toggle">
                        <input type="checkbox" name="can_access_erp" value="1" checked>
                        <span>Izinkan Login ke Dashboard ERP</span>
                    </label>

                    <div>
                        <label style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">Pilih Menu Sidebar yang Dikelola Role Ini:</label>
                        <div class="menu-checkbox-grid">
                            <?php foreach ($all_menus as $file => $label): ?>
                                <label class="menu-chk-item">
                                    <input type="checkbox" name="menus[]" value="<?= htmlspecialchars($file) ?>">
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="border-top:1px solid #eee; padding-top:10px;">
                        <label style="font-size:12px; font-weight:700; color:#555; display:block; margin-bottom:5px;">Role Terdaftar (Klik role untuk atur menu di Data Pengguna):</label>
                        <?php foreach ($roles_list as $rl): ?>
                            <div class="existing-role-row">
                                <a href="pelanggan.php?role=<?= urlencode($rl['role_name']) ?>" style="color:#3b2316;">
                                    <strong><?= htmlspecialchars($rl['role_name']) ?></strong>
                                    <small style="color:#777;">(<?= (int)$rl['total_user'] ?> akun)</small>
                                </a>
                                <?php if (!in_array($rl['role_name'], ['Admin', 'Kasir', 'Pelanggan'], true)): ?>
                                    <button type="submit" name="hapus_role" value="1" formnovalidate
                                            onclick="document.getElementById('roleNameDelInput').value='<?= htmlspecialchars($rl['role_name'], ENT_QUOTES, 'UTF-8') ?>'; return confirm('Hapus role <?= htmlspecialchars($rl['role_name'], ENT_QUOTES, 'UTF-8') ?>?');"
                                            style="background:#c62828; color:#fff; border:none; padding:3px 8px; border-radius:10px; font-size:10px; cursor:pointer;">
                                        Hapus
                                    </button>
                                <?php else: ?>
                                    <small style="color:#888; font-size:10px;">Bawaan</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <input type="hidden" name="role_name_del" id="roleNameDelInput">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeRoleModal()">Tutup</button>
                    <button type="submit" name="tambah_role" class="btn-modal-save">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <div class="footer-logo-circle">
                <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
            </div>
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-top-row">
                <div class="footer-col">
                    <h4>Metode Pembayaran</h4>
                </div>
                
                <div class="footer-col">
                    <h4>Ikuti Kami</h4>
                    <div class="social-icons">
                        <a href="#" class="fb"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="ig"><i class="fa-brands fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <!-- SCRIPT MODAL ROLE & NOTIFIKASI REAL-TIME -->
    <script>
        function openRoleModal() {
            document.getElementById('modalRoleSidebar').classList.add('active');
        }
        function closeRoleModal() {
            document.getElementById('modalRoleSidebar').classList.remove('active');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const notifBtn = document.getElementById('erp-notif-btn');
            const notifDropdown = document.getElementById('erp-notif-dropdown');
            const notifBadge = document.getElementById('erp-notif-badge');
            const toastContainer = document.getElementById('erp-toast-container');
            const modalRole = document.getElementById('modalRoleSidebar');

            let lastCartTimestamp = <?= json_encode(count($cart_notifications) > 0 ? $cart_notifications[0]['updated_at'] : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            let lastCartQty = <?= (int)$total_cart_qty ?>;
            let lastOrderId = <?= count($recent_orders) > 0 ? (int)$recent_orders[0]['id'] : 0 ?>;

            notifBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                notifDropdown.classList.toggle('open');
            });

            document.addEventListener('click', function (e) {
                if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
                    notifDropdown.classList.remove('open');
                }
                if (e.target === modalRole) {
                    closeRoleModal();
                }
            });

            function showErpToast(title, message, iconClass = 'fa-cart-plus') {
                const toast = document.createElement('div');
                toast.className = 'erp-toast';
                toast.innerHTML = `
                    <div class="erp-toast-icon"><i class="fa-solid ${iconClass}"></i></div>
                    <div class="erp-toast-body">
                        <strong>${title}</strong>
                        ${message}
                    </div>
                    <button type="button" class="erp-toast-close">&times;</button>
                `;
                toastContainer.appendChild(toast);
                requestAnimationFrame(() => toast.classList.add('show'));

                const closeFn = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                };
                toast.querySelector('.erp-toast-close').addEventListener('click', closeFn);
                setTimeout(closeFn, 5000);
            }

            // Polling otomatis setiap 5 detik untuk keranjang & pesanan baru
            setInterval(function () {
                fetch('dashboard_admin.php?ajax_erp_notif=1')
                    .then(res => res.json())
                    .then(data => {
                        const totalNotif = (data.total_qty || 0) + (data.pending_count || 0);
                        if (totalNotif > 0) {
                            notifBadge.textContent = totalNotif;
                            notifBadge.style.display = 'flex';
                        } else {
                            notifBadge.style.display = 'none';
                        }

                        if (data.latest_order && parseInt(data.latest_order.id) > lastOrderId) {
                            lastOrderId = parseInt(data.latest_order.id);
                            showErpToast(
                                'Pesanan Baru Masuk!',
                                `<b>${data.latest_order.order_code}</b> oleh ${data.latest_order.customer_name} (${data.latest_order.payment_method}).`,
                                'fa-receipt'
                            );
                        }

                        if (data.items && data.items.length > 0) {
                            const newest = data.items[0];
                            if (newest.updated_at !== lastCartTimestamp && data.total_qty >= lastCartQty) {
                                lastCartTimestamp = newest.updated_at;
                                lastCartQty = data.total_qty;
                                showErpToast(
                                    'Produk Masuk ke Keranjang!',
                                    `${newest.user_name} (${newest.user_role}) menambahkan <b>${newest.quantity}x ${newest.product_name}</b> (${newest.ice_level}, ${newest.sugar_level}).`,
                                    'fa-cart-plus'
                                );
                            }
                            lastCartQty = data.total_qty;
                        }
                    })
                    .catch(() => {});
            }, 5000);
        });
    </script>

</body>
</html>