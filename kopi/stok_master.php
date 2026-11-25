<?php
require_once 'auth_erp.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

$all_menus   = getDaftarMenuErp();
$notif_aksi  = '';
$notif_error = '';

// Pastikan kolom pendukung pada stock_master & product_hpp_items tersedia
try {
    $hppCols = $pdo->query("SHOW COLUMNS FROM `product_hpp_items`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('stock_id', $hppCols, true)) {
        $pdo->exec("ALTER TABLE `product_hpp_items` ADD COLUMN `stock_id` INT(11) DEFAULT NULL AFTER `product_id`");
    }
    if (!in_array('qty_pemakaian', $hppCols, true)) {
        $pdo->exec("ALTER TABLE `product_hpp_items` ADD COLUMN `qty_pemakaian` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `takaran`");
    }
} catch (PDOException $e) {
    // Abaikan jika tabel belum ada
}

try {
    $stockCols = $pdo->query("SHOW COLUMNS FROM `stock_master`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('product_id', $stockCols, true)) {
        $pdo->exec("ALTER TABLE `stock_master` ADD COLUMN `product_id` INT(11) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    // Abaikan jika tabel belum ada
}

// =========================================================================
// AKSI POST: RESTOK / UPDATE STOK BAHAN BAKU LANGSUNG
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_restok_bahan'])) {
    $stockId     = (int) ($_POST['bahan_stock_id'] ?? 0);
    $modeUpdate  = $_POST['mode_update'] ?? 'tambah'; // 'tambah' atau 'set'
    $jumlahInput = (float) ($_POST['jumlah_input'] ?? 0);

    if ($stockId <= 0) {
        $notif_error = "Pilih bahan baku terlebih dahulu.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtBahan = $pdo->prepare("
                SELECT id, name, sku, current_stock, unit
                FROM stock_master
                WHERE id = ?
                FOR UPDATE
            ");
            $stmtBahan->execute([$stockId]);
            $bahan = $stmtBahan->fetch(PDO::FETCH_ASSOC);

            if (!$bahan) {
                throw new Exception("Data bahan baku tidak ditemukan.");
            }

            $stokLama = (float) $bahan['current_stock'];
            $satuan   = !empty($bahan['unit']) ? $bahan['unit'] : 'GRAM';

            if ($modeUpdate === 'set') {
                if ($jumlahInput < 0) {
                    throw new Exception("Jumlah stok bahan baku tidak boleh kurang dari 0.");
                }
                $stokBaru = $jumlahInput;
                $ketAksi  = "diatur menjadi";
            } else {
                if ($jumlahInput <= 0) {
                    throw new Exception("Jumlah penambahan stok harus lebih dari 0.");
                }
                $stokBaru = $stokLama + $jumlahInput;
                $fmtTambah = rtrim(rtrim(number_format($jumlahInput, 2, ',', '.'), '0'), ',');
                $ketAksi  = "ditambah <strong>+{$fmtTambah} {$satuan}</strong> menjadi";
            }

            $stmtUpd = $pdo->prepare("UPDATE stock_master SET current_stock = ? WHERE id = ?");
            $stmtUpd->execute([$stokBaru, $stockId]);

            $pdo->commit();

            $fmtBaru = rtrim(rtrim(number_format($stokBaru, 2, ',', '.'), '0'), ',');
            $notif_aksi = "Stok bahan baku <strong>" . htmlspecialchars($bahan['name']) . "</strong> berhasil {$ketAksi} <strong>{$fmtBaru} {$satuan}</strong>.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $notif_error = $e->getMessage();
        }
    }
}

// =========================================================================
// AKSI POST: MODAL TAMBAH / HAPUS ROLE DARI SIDEBAR
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

// 1. Data Role untuk Sidebar Kiri
$roles_list = $pdo->query("
    SELECT r.id, r.role_name, r.can_access_erp, r.allowed_menus, COUNT(u.id) AS total_user
    FROM `user_roles` r
    LEFT JOIN `users` u ON u.role = r.role_name
    GROUP BY r.id, r.role_name, r.can_access_erp, r.allowed_menus
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
$total_semua_user = (int) $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

// 2. Data Notifikasi Header (Pesanan & Keranjang)
$pesanan_pending = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
$recent_orders = $pdo->query("
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
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$cart_notifications = $pdo->query("
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
")->fetchAll(PDO::FETCH_ASSOC);

$total_cart_qty    = (int) $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM cart")->fetchColumn();
$total_notif_badge = $total_cart_qty + $pesanan_pending;

// 3. Query Khusus Data BAHAN BAKU dari stock_master (mengecualikan item minuman jadi / CUP)
$query = "
    SELECT s.*,
           COALESCE(NULLIF(s.name, ''), 'Bahan Baku Tanpa Nama') AS name,
           COALESCE(NULLIF(s.sku, ''), '-') AS sku
    FROM stock_master s
    LEFT JOIN products p_sku ON s.sku = p_sku.sku AND s.sku != ''
    WHERE LOWER(COALESCE(s.unit, '')) != 'cup'
      AND p_sku.id IS NULL
    ORDER BY s.id DESC
";
$stmt = $pdo->query($query);
$bahan_baku_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_item_bahan = count($bahan_baku_data);
$total_stok_menipis = 0;
foreach ($bahan_baku_data as $bb) {
    if ((float)($bb['current_stock'] ?? 0) <= (float)($bb['min_stock'] ?? 0)) {
        $total_stok_menipis++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Master Bahan Baku - Filosofi Kopi</title>
    <link rel="stylesheet" href="stok_master.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-btn-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-restok-top {
            background: #2e7d32;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: 0.2s;
        }
        .btn-restok-top:hover { background: #1b5e20; }

        .stok-tabs {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            flex-wrap: wrap;
            align-items: center;
        }
        .stok-tab-btn {
            background: #f3ece7;
            color: #2b170e;
            border: 1px solid #dfd3cb;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .stok-tab-btn.active, .stok-tab-btn:hover {
            background: #2b170e;
            color: #fff;
            border-color: #2b170e;
        }

        .jenis-pill {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .btn-restok-row {
            background: #2e7d32;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .btn-restok-row:hover { background: #1b5e20; }

        .restok-modal-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .restok-modal-field label {
            font-size: 12px;
            font-weight: 700;
            color: #2b170e;
        }
        .restok-modal-field select, .restok-modal-field input {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        .restok-preview-box {
            background: #faf6f3;
            border: 1px solid #e2d4ca;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #2b170e;
        }
    </style>
</head>
<body>
    <!-- KONTAINER TOAST NOTIFIKASI REAL-TIME -->
    <div id="erp-toast-container" class="erp-toast-container"></div>

    <!-- HEADER -->
    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
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
                        <?php foreach ($recent_orders as $ro): ?>
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

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>

            <?php renderSidebarMenuErp('stok_master.php', $currentUserRole, $allowedMenus); ?>

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

        <!-- CONTENT AREA -->
        <main class="content-area">
            <?php if ($notif_aksi): ?>
                <div class="alert-dashboard"><i class="fa-solid fa-circle-check"></i> <div><?= $notif_aksi ?></div></div>
            <?php endif; ?>
            <?php if ($notif_error): ?>
                <div class="alert-dashboard error"><i class="fa-solid fa-circle-exclamation"></i> <div><?= $notif_error ?></div></div>
            <?php endif; ?>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Bahan baku baru berhasil ditambahkan!</div>
                <?php elseif ($_GET['status'] === 'deleted'): ?>
                    <div class="alert-success alert-danger-custom"><i class="fa-solid fa-trash"></i> Data bahan baku berhasil dihapus!</div>
                <?php elseif ($_GET['status'] === 'updated'): ?>
                    <div class="alert-success"><i class="fa-solid fa-pen"></i> Data bahan baku berhasil diperbarui!</div>
                <?php elseif ($_GET['status'] === 'error'): ?>
                    <div class="alert-success alert-danger-custom"><i class="fa-solid fa-circle-exclamation"></i> Terjadi kesalahan saat memproses data bahan baku.</div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="control-card">
                <div class="control-header">
                    <div class="title-section">
                        <h2>Stock Bahan Baku</h2>
                        <p>Kelola persediaan bahan baku kedai (biji kopi, susu, gula aren, sirup, kemasan) dan batas stok minimum</p>
                    </div>
                    <div class="header-btn-group">
                        <button type="button" class="btn-restok-top" onclick="openRestokModal(0)">
                            <i class="fa-solid fa-boxes-packing"></i> Restok Bahan Baku
                        </button>
                        <a href="tambah_stok.php" class="btn-add">
                            <i class="fa-solid fa-plus"></i> Tambah Bahan Baku
                        </a>
                    </div>
                </div>
                
                <div class="search-section">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Cari nama bahan baku atau kode SKU..." onkeyup="filterTable()">
                    </div>
                    <div class="stok-tabs">
                        <button type="button" class="stok-tab-btn active" data-kondisi="all" onclick="setKondisiFilter('all', this)">
                            Semua Bahan Baku (<?= $total_item_bahan ?>)
                        </button>
                        <button type="button" class="stok-tab-btn" data-kondisi="aman" onclick="setKondisiFilter('aman', this)">
                            Stok Aman (<?= max(0, $total_item_bahan - $total_stok_menipis) ?>)
                        </button>
                        <button type="button" class="stok-tab-btn" data-kondisi="menipis" onclick="setKondisiFilter('menipis', this)">
                            Stok Menipis / Habis (<?= $total_stok_menipis ?>)
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <table class="inventory-table" id="stokTable">
                    <thead>
                        <tr>
                            <th>NAMA BAHAN BAKU</th>
                            <th>JENIS</th>
                            <th>KD BAHAN (SKU)</th>
                            <th>STOK SAAT INI</th>
                            <th>MIN. STOK</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bahan_baku_data) > 0): ?>
                            <?php foreach ($bahan_baku_data as $row): ?>
                                <?php 
                                    $stok_sekarang = (float) ($row['current_stock'] ?? 0);
                                    $batas_minimal = (float) ($row['min_stock'] ?? 0);
                                    $is_low_stock  = ($stok_sekarang <= $batas_minimal);
                                    $satuan        = !empty($row['unit']) ? $row['unit'] : 'GRAM';
                                    $kondisiAttr   = $is_low_stock ? 'menipis' : 'aman';

                                    $fmtStok = rtrim(rtrim(number_format($stok_sekarang, 2, ',', '.'), '0'), ',');
                                    $fmtMin  = rtrim(rtrim(number_format($batas_minimal, 2, ',', '.'), '0'), ',');
                                ?>
                                <tr class="stok-row" data-kondisi="<?= $kondisiAttr ?>">
                                    <td>
                                        <div class="product-info">
                                            <img src="<?= !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'https://via.placeholder.com/35' ?>" 
                                                 alt="img" 
                                                 onerror="this.src='https://via.placeholder.com/35'">
                                            <span><?= htmlspecialchars($row['name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="jenis-pill"><i class="fa-solid fa-box"></i> Bahan Baku</span>
                                    </td>
                                    <td><?= htmlspecialchars($row['sku']) ?></td>
                                    <td>
                                        <span class="badge <?= $is_low_stock ? 'badge-danger' : 'badge-success' ?>">
                                            <?= $fmtStok ?> <?= htmlspecialchars($satuan) ?>
                                        </span>
                                    </td>
                                    <td><?= $fmtMin ?> <?= htmlspecialchars($satuan) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-restok-row" onclick="openRestokModal(<?= (int)$row['id'] ?>)" title="Tambah / Update Stok Bahan Baku">
                                                <i class="fa-solid fa-plus-circle"></i> Restok
                                            </button>
                                            <a href="edit_stok.php?id=<?= (int)$row['id'] ?>" class="btn-icon edit-btn" title="Edit Bahan Baku">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="hapus_stok.php?id=<?= (int)$row['id'] ?>" 
                                               class="btn-icon delete-btn" 
                                               onclick="return confirm('Hapus data bahan baku <?= htmlspecialchars($row['name'], ENT_QUOTES) ?>?')" 
                                               title="Hapus Bahan Baku">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noSearchResultRow" style="display: none;">
                                <td colspan="6" style="text-align: center; padding: 30px; color: #777;">
                                    Bahan baku atau kode SKU tidak ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #777;">
                                    Belum ada data bahan baku. Klik <strong>"Tambah Bahan Baku"</strong> untuk menambahkan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- MODAL RESTOK / UPDATE STOK BAHAN BAKU -->
    <div id="modalRestokBahan" class="modal-overlay">
        <div class="modal-box" style="max-width: 480px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-boxes-packing"></i> Update Stok Bahan Baku</h3>
                <button type="button" class="modal-close" onclick="closeRestokModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi_restok_bahan" value="1">
                <div class="modal-body">
                    <div class="restok-modal-field">
                        <label>Pilih Bahan Baku</label>
                        <select name="bahan_stock_id" id="bahanStockSelect" onchange="updateRestokPreview()" required>
                            <option value="">-- Pilih Bahan Baku --</option>
                            <?php foreach ($bahan_baku_data as $bb): ?>
                                <?php
                                    $fmtS = rtrim(rtrim(number_format((float)$bb['current_stock'], 2, ',', '.'), '0'), ',');
                                    $uS   = !empty($bb['unit']) ? $bb['unit'] : 'GRAM';
                                ?>
                                <option value="<?= (int)$bb['id'] ?>"
                                        data-name="<?= htmlspecialchars($bb['name'], ENT_QUOTES) ?>"
                                        data-stock="<?= (float)$bb['current_stock'] ?>"
                                        data-unit="<?= htmlspecialchars($uS, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($bb['name']) ?> (Stok saat ini: <?= $fmtS ?> <?= htmlspecialchars($uS) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="restok-modal-field">
                            <label>Metode Update</label>
                            <select name="mode_update" id="modeRestokSelect" onchange="updateRestokPreview()">
                                <option value="tambah">Tambah Stok Masuk (+)</option>
                                <option value="set">Set Stok Opname (=)</option>
                            </select>
                        </div>

                        <div class="restok-modal-field">
                            <label id="labelJumlahRestok">Jumlah Masuk</label>
                            <input type="number" step="0.01" name="jumlah_input" id="jumlahRestokInput" min="0.01" value="100" oninput="updateRestokPreview()" required>
                        </div>
                    </div>

                    <div class="restok-preview-box" id="restokPreviewBox">
                        Pilih bahan baku untuk melihat simulasi perubahan stok.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeRestokModal()">Batal</button>
                    <button type="submit" class="btn-modal-save">
                        <i class="fa-solid fa-check"></i> Simpan Perubahan Stok
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH & KELOLA ROLE AKUN -->
    <div id="modalRoleSidebar" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-shield"></i> Tambah &amp; Kelola Role Akun</h3>
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

    <script>
        let activeKondisiFilter = 'all';

        function setKondisiFilter(kondisi, btnEl) {
            activeKondisiFilter = kondisi;
            document.querySelectorAll('.stok-tab-btn').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');
            filterTable();
        }

        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#stokTable tbody tr.stok-row');
            const noResultRow = document.getElementById('noSearchResultRow');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowKondisi = row.getAttribute('data-kondisi');
                const matchText = text.includes(input);
                const matchKondisi = (activeKondisiFilter === 'all' || rowKondisi === activeKondisiFilter);

                if (matchText && matchKondisi) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResultRow) {
                noResultRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
            }
        }

        function openRestokModal(stockId = 0) {
            const modal = document.getElementById('modalRestokBahan');
            const sel   = document.getElementById('bahanStockSelect');
            const mode  = document.getElementById('modeRestokSelect');
            const qty   = document.getElementById('jumlahRestokInput');

            mode.value = 'tambah';
            qty.value  = 100;
            if (stockId > 0) {
                sel.value = String(stockId);
            }
            updateRestokPreview();
            modal.classList.add('active');
        }

        function closeRestokModal() {
            document.getElementById('modalRestokBahan').classList.remove('active');
        }

        function updateRestokPreview() {
            const sel        = document.getElementById('bahanStockSelect');
            const mode       = document.getElementById('modeRestokSelect').value;
            const qtyInp     = document.getElementById('jumlahRestokInput');
            const labelQty   = document.getElementById('labelJumlahRestok');
            const previewBox = document.getElementById('restokPreviewBox');

            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) {
                previewBox.innerHTML = 'Pilih bahan baku untuk melihat simulasi perubahan stok.';
                return;
            }

            const nama     = opt.getAttribute('data-name') || '';
            const stokLama = parseFloat(opt.getAttribute('data-stock')) || 0;
            const unit     = opt.getAttribute('data-unit') || 'GRAM';
            const valInput = parseFloat(qtyInp.value) || 0;

            labelQty.textContent = (mode === 'set') ? `Stok Akhir (${unit})` : `Jumlah Masuk (${unit})`;
            qtyInp.min = (mode === 'set') ? '0' : '0.01';

            const stokBaru = (mode === 'set') ? valInput : (stokLama + valInput);
            previewBox.innerHTML = `
                Stok <strong>${nama}</strong>:<br>
                ${stokLama.toLocaleString('id-ID')} ${unit} &rarr;
                <strong style="color:#2e7d32;">${stokBaru.toLocaleString('id-ID')} ${unit}</strong>
            `;
        }

        function openRoleModal() {
            document.getElementById('modalRoleSidebar').classList.add('active');
        }
        function closeRoleModal() {
            document.getElementById('modalRoleSidebar').classList.remove('active');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const notifBtn       = document.getElementById('erp-notif-btn');
            const notifDropdown  = document.getElementById('erp-notif-dropdown');
            const notifBadge     = document.getElementById('erp-notif-badge');
            const toastContainer = document.getElementById('erp-toast-container');
            const modalRole      = document.getElementById('modalRoleSidebar');
            const modalRestok    = document.getElementById('modalRestokBahan');

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
                if (e.target === modalRestok) {
                    closeRestokModal();
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