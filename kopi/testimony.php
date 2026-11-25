<?php
require_once 'auth_erp.php';

// =========================================================
// 1. DETEKSI & SINKRONISASI TABEL TESTIMONI OTOMATIS
// =========================================================
$tableName  = 'testimonials';
$cekTesti   = $pdo->query("SHOW TABLES LIKE 'testimonials'")->rowCount() > 0;
$cekProdRev = $pdo->query("SHOW TABLES LIKE 'product_reviews'")->rowCount() > 0;
$cekRev     = $pdo->query("SHOW TABLES LIKE 'reviews'")->rowCount() > 0;

if ($cekTesti) {
    $tableName = 'testimonials';
} elseif ($cekProdRev) {
    $tableName = 'product_reviews';
} elseif ($cekRev) {
    $tableName = 'reviews';
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `testimonials` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) DEFAULT NULL,
          `customer_name` varchar(100) NOT NULL,
          `customer_role` varchar(100) DEFAULT 'Pelanggan',
          `rating` tinyint(1) NOT NULL DEFAULT 5,
          `message` text NOT NULL,
          `media_url` varchar(255) DEFAULT NULL,
          `status` enum('Tampil','Sembunyi') DEFAULT 'Tampil',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $tableName = 'testimonials';
}

// Ambil daftar kolom yang tersedia pada tabel aktif
$columns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);

$colName    = in_array('customer_name', $columns, true) ? 'customer_name' : (in_array('name', $columns, true) ? 'name' : null);
$colRole    = in_array('customer_role', $columns, true) ? 'customer_role' : (in_array('role', $columns, true) ? 'role' : null);
$colMessage = in_array('message', $columns, true) ? 'message' : (in_array('comment', $columns, true) ? 'comment' : (in_array('content', $columns, true) ? 'content' : 'review'));
$colRating  = in_array('rating', $columns, true) ? 'rating' : null;
$colStatus  = in_array('status', $columns, true) ? 'status' : null;
$colDate    = in_array('created_at', $columns, true) ? 'created_at' : (in_array('published_at', $columns, true) ? 'published_at' : null);
$colMedia   = in_array('media_url', $columns, true) ? 'media_url'
            : (in_array('photo_url', $columns, true) ? 'photo_url'
            : (in_array('image_url', $columns, true) ? 'image_url'
            : (in_array('photo', $columns, true) ? 'photo' : null)));

// Tambahkan kolom media_url secara otomatis jika tabel belum memiliki kolom gambar
if (!$colMedia) {
    try {
        $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `media_url` VARCHAR(255) DEFAULT NULL");
        $colMedia = 'media_url';
    } catch (PDOException $e) {
        // Abaikan jika gagal menambah kolom
    }
}

$hasUserId = in_array('user_id', $columns, true);
$hasProdId = in_array('product_id', $columns, true);

// Ambil daftar produk aktif untuk dropdown modal & pencocokan nama produk
$daftar_produk = [];
try {
    $daftar_produk = $pdo->query("SELECT id, name FROM `products` ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $daftar_produk = [];
}

// =========================================================
// 2. AKSI POST: TAMBAH, UBAH STATUS, & HAPUS TESTIMONI
// =========================================================
$pesan_sukses = '';
$pesan_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tambah Testimoni Baru
    if (isset($_POST['tambah_testimoni'])) {
        $nama      = trim($_POST['customer_name'] ?? '');
        $ket       = trim($_POST['customer_role'] ?? 'Pelanggan');
        $prodId    = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int)$_POST['product_id'] : null;
        $rating    = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $isi       = trim($_POST['message'] ?? '');
        $status    = ($_POST['status'] ?? 'Tampil') === 'Sembunyi' ? 'Sembunyi' : 'Tampil';
        $mediaPath = null;

        // Proses upload foto jika admin memilih gambar
        if (isset($_FILES['review_media']) && $_FILES['review_media']['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($_FILES['review_media']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $pesan_error = "Format foto harus JPG, PNG, WEBP, atau GIF.";
            } elseif ($_FILES['review_media']['size'] > 5 * 1024 * 1024) {
                $pesan_error = "Ukuran foto maksimal 5 MB.";
            } else {
                $uploadDir = __DIR__ . '/uploads/reviews/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'admin_rev_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['review_media']['tmp_name'], $uploadDir . $newFileName)) {
                    $mediaPath = 'uploads/reviews/' . $newFileName;
                } else {
                    $pesan_error = "Gagal mengunggah foto testimoni.";
                }
            }
        }

        if ($pesan_error === '') {
            if ($nama !== '' && $isi !== '') {
                $insertCols = [];
                $insertVals = [];
                $params     = [];

                if ($colName) {
                    $insertCols[] = "`$colName`";
                    $insertVals[] = ":name";
                    $params[':name'] = $nama;
                } elseif ($hasUserId) {
                    $insertCols[] = "`user_id`";
                    $insertVals[] = ":uid";
                    $params[':uid'] = (int)($_SESSION['user_id'] ?? 1);
                }

                if ($hasProdId && $prodId !== null) {
                    $insertCols[] = "`product_id`";
                    $insertVals[] = ":pid";
                    $params[':pid'] = $prodId;
                }

                if ($colRole) {
                    $insertCols[] = "`$colRole`";
                    $insertVals[] = ":role";
                    $params[':role'] = $ket !== '' ? $ket : 'Pelanggan';
                }

                if ($colRating) {
                    $insertCols[] = "`$colRating`";
                    $insertVals[] = ":rating";
                    $params[':rating'] = $rating;
                }

                $insertCols[] = "`$colMessage`";
                $insertVals[] = ":msg";
                $params[':msg'] = $isi;

                if ($colMedia && $mediaPath !== null) {
                    $insertCols[] = "`$colMedia`";
                    $insertVals[] = ":media";
                    $params[':media'] = $mediaPath;
                }

                if ($colStatus) {
                    $insertCols[] = "`$colStatus`";
                    $insertVals[] = ":status";
                    $params[':status'] = $status;
                }

                $sqlIns = "INSERT INTO `$tableName` (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
                $stmtIns = $pdo->prepare($sqlIns);
                $stmtIns->execute($params);

                $pesan_sukses = "Testimoni dari <strong>" . htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') . "</strong> berhasil ditambahkan.";
            } else {
                $pesan_error = "Nama pelanggan dan isi testimoni wajib diisi.";
            }
        }
    }

    // Ubah Status Tampil / Sembunyi
    if (isset($_POST['toggle_status']) && $colStatus) {
        $id          = (int)($_POST['id'] ?? 0);
        $status_baru = ($_POST['status_baru'] ?? 'Tampil') === 'Sembunyi' ? 'Sembunyi' : 'Tampil';
        if ($id > 0) {
            $stmtUpd = $pdo->prepare("UPDATE `$tableName` SET `$colStatus` = :st WHERE `id` = :id");
            $stmtUpd->execute([':st' => $status_baru, ':id' => $id]);
            $pesan_sukses = "Status testimoni berhasil diubah menjadi <strong>" . htmlspecialchars($status_baru, ENT_QUOTES, 'UTF-8') . "</strong>.";
        }
    }

    // Hapus Testimoni (sekaligus hapus file foto jika tersimpan lokal)
    if (isset($_POST['hapus_testimoni'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($colMedia) {
                $stmtGetImg = $pdo->prepare("SELECT `$colMedia` FROM `$tableName` WHERE `id` = :id");
                $stmtGetImg->execute([':id' => $id]);
                $oldMedia = $stmtGetImg->fetchColumn();
                if ($oldMedia && strpos($oldMedia, 'uploads/reviews/') === 0) {
                    $fullImgPath = __DIR__ . '/' . $oldMedia;
                    if (is_file($fullImgPath)) {
                        @unlink($fullImgPath);
                    }
                }
            }

            $stmtDel = $pdo->prepare("DELETE FROM `$tableName` WHERE `id` = :id");
            $stmtDel->execute([':id' => $id]);
            $pesan_sukses = "Data testimoni berhasil dihapus.";
        }
    }
}

// =========================================================
// 3. AMBIL DATA TESTIMONI UNTUK DITAMPILKAN
// =========================================================
$selectParts = ["t.*"];
$joinParts   = "";

if ($hasUserId) {
    $selectParts[] = "u.name AS joined_user_name";
    $joinParts    .= " LEFT JOIN `users` u ON t.user_id = u.id";
}
if ($hasProdId) {
    $selectParts[] = "p.id AS joined_product_id";
    $selectParts[] = "p.name AS joined_product_name";
    $joinParts    .= " LEFT JOIN `products` p ON t.product_id = p.id";
}

$sqlSelect = "SELECT " . implode(', ', $selectParts) . " FROM `$tableName` t $joinParts ORDER BY t.id DESC";
$testimoni_list = $pdo->query($sqlSelect)->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik ringkas
$total_testimoni = count($testimoni_list);
$total_tampil    = 0;
$sum_rating      = 0;

foreach ($testimoni_list as $row) {
    $r = $colRating ? (int)($row[$colRating] ?? 5) : 5;
    $sum_rating += $r;
    if (!$colStatus || ($row[$colStatus] ?? 'Tampil') === 'Tampil') {
        $total_tampil++;
    }
}
$rata_rating = $total_testimoni > 0 ? number_format($sum_rating / $total_testimoni, 1) : '0.0';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Testimoni - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .content-header h2 {
            font-size: 22px;
            color: #2b170e;
            margin: 0 0 5px 0;
        }
        .content-header p {
            font-size: 13px;
            color: #777;
            margin: 0;
        }
        .btn-add {
            background: linear-gradient(135deg, #2b170e, #4a2d1e);
            color: #fff;
            padding: 11px 20px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(43, 23, 14, 0.2);
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(43, 23, 14, 0.35);
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            border-left: 5px solid #3b2316;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .summary-card small {
            font-size: 11px;
            font-weight: 700;
            color: #777;
            text-transform: uppercase;
        }
        .summary-card h3 {
            font-size: 22px;
            color: #2b170e;
            margin-top: 4px;
        }

        .filter-bar {
            background: #fff;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .search-input-wrap {
            flex: 1;
            min-width: 220px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-input-wrap i { color: #999; }
        .search-input-wrap input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            background: transparent;
            color: #333;
        }
        .rating-filter-select {
            padding: 7px 12px;
            border: 1px solid #d5c7c0;
            border-radius: 8px;
            font-size: 13px;
            color: #2b170e;
            background: #faf7f5;
            outline: none;
        }

        .testi-table-wrapper {
            background: #fff;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .testi-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .testi-table thead {
            background: #4e342e;
            color: #fff;
        }
        .testi-table thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 11.5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .testi-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .testi-table tbody tr:hover {
            background-color: #faf7f5;
        }

        /* Link Produk Menuju Detail Produk */
        .product-detail-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #6d4c41;
            background: #f3ebe7;
            padding: 3px 9px;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        .product-detail-link:hover {
            background: #3b2316;
            color: #fff;
        }

        /* Thumbnail Foto Ulasan di Tabel */
        .testi-photo-thumb {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #d7ccc8;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
        }
        .testi-photo-thumb:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 10px rgba(0,0,0,0.18);
        }
        .no-photo-badge {
            display: inline-block;
            font-size: 11px;
            color: #9e9e9e;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .star-icons {
            color: #f59e0b;
            font-size: 13px;
            white-space: nowrap;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-tampil { background: #e8f5e9; color: #2e7d32; }
        .badge-sembunyi { background: #eceff1; color: #546e7a; }

        .action-btn-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .btn-toggle {
            background-color: #3b2316;
            color: #fff;
            border: none;
            padding: 6px 11px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-toggle:hover { background-color: #5c3824; }
        .btn-delete {
            background-color: #e53935;
            color: #fff;
            border: none;
            width: 32px;
            height: 30px;
            border-radius: 7px;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .btn-delete:hover { background-color: #c62828; }

        .alert-notif {
            padding: 13px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-danger  { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        .empty-row {
            text-align: center;
            padding: 40px !important;
            color: #999;
        }
        .empty-row i {
            font-size: 38px;
            color: #ddd;
            display: block;
            margin-bottom: 10px;
        }

        /* Modal Tambah Testimoni */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        .modal-header {
            background-color: #3b2316;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 15px; margin: 0; }
        .modal-close { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
        .modal-body {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 75vh;
            overflow-y: auto;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #333; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 13px;
            font-family: inherit;
        }
        .modal-footer {
            padding: 10px 20px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            background: #eee;
            color: #333;
            border: none;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Lightbox Preview Foto Besar */
        .lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lightbox-overlay.active { display: flex; }
        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 88vh;
            text-align: center;
        }
        .lightbox-content img {
            max-width: 100%;
            max-height: 82vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .lightbox-caption {
            color: #fff;
            margin-top: 10px;
            font-size: 13px;
        }
        .lightbox-close {
            position: absolute;
            top: -14px;
            right: -14px;
            background: #fff;
            color: #2b170e;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .summary-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- HEADER (KLIK LOGO / FILOSOFI KOPI MENUJU KE BERANDA.PHP) -->
    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
            <div class="user-badge-header">
                <i class="fa-solid fa-user"></i>
                <span><?= htmlspecialchars($currentUserName) ?></span>
            </div>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>

            <!-- MENU SIDEBAR DINAMIS SESUAI HAK AKSES ROLE -->
            <?php renderSidebarMenuErp('testimony.php', $currentUserRole, $allowedMenus); ?>
        </aside>

        <main class="content-area">

            <?php if ($pesan_sukses): ?>
                <div class="alert-notif alert-success">
                    <i class="fa-solid fa-circle-check"></i> <span><?= $pesan_sukses ?></span>
                </div>
            <?php endif; ?>
            <?php if ($pesan_error): ?>
                <div class="alert-notif alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i> <span><?= $pesan_error ?></span>
                </div>
            <?php endif; ?>

            <div class="content-header">
                <div>
                    <h2><i class="fa-solid fa-comments"></i> Manajemen Testimoni & Ulasan</h2>
                    <p>Kelola ulasan pelanggan, rating produk, foto ulasan, dan testimoni yang ditampilkan di Filosofi Kopi</p>
                </div>
                <button type="button" class="btn-add" onclick="openTestiModal()">
                    <i class="fa-solid fa-plus"></i> Tambah Testimoni
                </button>
            </div>

            <!-- RINGKASAN STATISTIK TESTIMONI -->
            <div class="summary-row">
                <div class="summary-card">
                    <small>Total Testimoni</small>
                    <h3><?= $total_testimoni ?> Ulasan</h3>
                </div>
                <div class="summary-card">
                    <small>Rata-rata Rating</small>
                    <h3><i class="fa-solid fa-star" style="color:#f59e0b; font-size:18px;"></i> <?= $rata_rating ?> / 5.0</h3>
                </div>
                <div class="summary-card">
                    <small>Testimoni Aktif</small>
                    <h3><?= $total_tampil ?> Ditampilkan</h3>
                </div>
            </div>

            <!-- PENCARIAN & FILTER RATING -->
            <div class="filter-bar">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchTesti" placeholder="Cari nama pelanggan, produk, atau isi testimoni..." onkeyup="filterTestimoni()">
                </div>
                <select id="filterRating" class="rating-filter-select" onchange="filterTestimoni()">
                    <option value="all">Semua Rating</option>
                    <option value="5">5 Bintang</option>
                    <option value="4">4 Bintang</option>
                    <option value="3">3 Bintang</option>
                    <option value="2">2 Bintang</option>
                    <option value="1">1 Bintang</option>
                </select>
            </div>

            <!-- TABEL TESTIMONI -->
            <div class="testi-table-wrapper">
                <table class="testi-table" id="testiTable">
                    <thead>
                        <tr>
                            <th style="width: 20%;">PELANGGAN &amp; PRODUK</th>
                            <th style="width: 10%;">FOTO</th>
                            <th style="width: 13%;">RATING</th>
                            <th style="width: 29%;">ISI TESTIMONI</th>
                            <th style="width: 12%;">TANGGAL</th>
                            <?php if ($colStatus): ?>
                                <th style="width: 8%;">STATUS</th>
                            <?php endif; ?>
                            <th style="width: 12%; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($testimoni_list) > 0): ?>
                            <?php foreach ($testimoni_list as $t): ?>
                                <?php
                                    $namaTampil = $t[$colName] ?? $t['joined_user_name'] ?? 'Pelanggan';
                                    $prodIdVal  = (int)($t['product_id'] ?? $t['joined_product_id'] ?? 0);
                                    $namaProduk = $t['joined_product_name'] ?? '';
                                    $ketTampil  = ($colRole ? ($t[$colRole] ?? '') : '') ?: ($namaProduk ?: 'Pelanggan');
                                    $isiTampil  = $t[$colMessage] ?? '';
                                    $ratingVal  = $colRating ? max(1, min(5, (int)($t[$colRating] ?? 5))) : 5;
                                    $statusVal  = $colStatus ? ($t[$colStatus] ?? 'Tampil') : 'Tampil';
                                    $tglVal     = $colDate && !empty($t[$colDate]) ? date('d M Y, H:i', strtotime($t[$colDate])) : '-';
                                    $fotoUrl    = $colMedia ? trim((string)($t[$colMedia] ?? '')) : '';
                                    $detailUrl  = $prodIdVal > 0 ? 'detail_produk.php?id=' . $prodIdVal . '#section-ulasan' : '';
                                ?>
                                <tr class="testi-row" data-rating="<?= $ratingVal ?>">
                                    <td>
                                        <strong style="color:#2b170e;"><?= htmlspecialchars($namaTampil) ?></strong><br>
                                        <?php if ($prodIdVal > 0): ?>
                                            <a href="<?= htmlspecialchars($detailUrl) ?>" class="product-detail-link" title="Lihat halaman detail produk <?= htmlspecialchars($ketTampil) ?>">
                                                <i class="fa-solid fa-mug-hot"></i>
                                                <?= htmlspecialchars($namaProduk !== '' ? $namaProduk : $ketTampil) ?>
                                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;"></i>
                                            </a>
                                        <?php else: ?>
                                            <small style="color:#777;"><?= htmlspecialchars($ketTampil) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fotoUrl !== ''): ?>
                                            <img src="<?= htmlspecialchars($fotoUrl) ?>" 
                                                 alt="Foto Ulasan <?= htmlspecialchars($namaTampil) ?>" 
                                                 class="testi-photo-thumb"
                                                 title="Klik untuk memperbesar foto"
                                                 onclick="openLightbox('<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') ?>', 'Ulasan dari <?= htmlspecialchars($namaTampil, ENT_QUOTES, 'UTF-8') ?>')">
                                        <?php else: ?>
                                            <span class="no-photo-badge"><i class="fa-regular fa-image"></i> Tanpa foto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="star-icons" title="<?= $ratingVal ?> dari 5">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa-<?= $i <= $ratingVal ? 'solid' : 'regular' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td style="line-height: 1.5; color: #333;">
                                        <?= nl2br(htmlspecialchars($isiTampil)) ?>
                                    </td>
                                    <td style="font-size: 12px; color: #777;">
                                        <i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($tglVal) ?>
                                    </td>
                                    <?php if ($colStatus): ?>
                                        <td>
                                            <span class="badge-status <?= $statusVal === 'Tampil' ? 'badge-tampil' : 'badge-sembunyi' ?>">
                                                <?= htmlspecialchars($statusVal) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="action-btn-group" style="justify-content: center;">
                                            <?php if ($colStatus): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                                    <input type="hidden" name="status_baru" value="<?= $statusVal === 'Tampil' ? 'Sembunyi' : 'Tampil' ?>">
                                                    <button type="submit" name="toggle_status" class="btn-toggle" title="Ubah Visibilitas">
                                                        <?= $statusVal === 'Tampil' ? 'Sembunyikan' : 'Tampilkan' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus testimoni dari <?= htmlspecialchars($namaTampil, ENT_QUOTES, 'UTF-8') ?>?');" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                                <button type="submit" name="hapus_testimoni" class="btn-delete" title="Hapus Testimoni">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noSearchResultRow" style="display: none;">
                                <td colspan="<?= $colStatus ? 7 : 6 ?>" class="empty-row">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    Testimoni yang dicari tidak ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $colStatus ? 7 : 6 ?>" class="empty-row">
                                    <i class="fa-solid fa-comments"></i>
                                    Belum ada data testimoni pelanggan.<br>
                                    <small>Klik tombol <strong>"Tambah Testimoni"</strong> untuk menambahkan ulasan baru.</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- MODAL TAMBAH TESTIMONI -->
    <div id="modalTambahTesti" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-comment-medical"></i> Tambah Testimoni Baru</h3>
                <button type="button" class="modal-close" onclick="closeTestiModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Pelanggan</label>
                        <input type="text" name="customer_name" placeholder="Contoh: Budi Santoso" required>
                    </div>
                    <?php if ($hasProdId && count($daftar_produk) > 0): ?>
                        <div class="form-group">
                            <label>Produk yang Diulas</label>
                            <select name="product_id" onchange="syncRoleFromProduct(this)">
                                <option value="">-- Pilih Produk (Opsional) --</option>
                                <?php foreach ($daftar_produk as $prd): ?>
                                    <option value="<?= (int)$prd['id'] ?>" data-name="<?= htmlspecialchars($prd['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($prd['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Keterangan / Menu Favorit</label>
                        <input type="text" id="inputCustomerRole" name="customer_role" placeholder="Contoh: Penikmat Sunset Brew / Pelanggan Tetap" value="Pelanggan">
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <select name="rating" required>
                            <option value="5" selected>5 Bintang - Sangat Puas</option>
                            <option value="4">4 Bintang - Puas</option>
                            <option value="3">3 Bintang - Cukup</option>
                            <option value="2">2 Bintang - Kurang</option>
                            <option value="1">1 Bintang - Sangat Kurang</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Isi Testimoni</label>
                        <textarea name="message" rows="4" placeholder="Tulis ulasan atau pengalaman pelanggan..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Foto Ulasan (Opsional, maks 5MB)</label>
                        <input type="file" name="review_media" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewAdminFoto(this)">
                        <img id="adminFotoPreview" alt="Preview Foto" style="display:none; max-height:110px; width:fit-content; border-radius:8px; margin-top:6px; border:1px solid #ccc;">
                    </div>
                    <?php if ($colStatus): ?>
                        <div class="form-group">
                            <label>Status Tampilan</label>
                            <select name="status">
                                <option value="Tampil" selected>Tampil</option>
                                <option value="Sembunyi">Sembunyi</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeTestiModal()">Batal</button>
                    <button type="submit" name="tambah_testimoni" class="btn-add">Simpan Testimoni</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LIGHTBOX PERBESAR FOTO ULASAN -->
    <div id="photoLightbox" class="lightbox-overlay" onclick="if(event.target === this) closeLightbox()">
        <div class="lightbox-content">
            <button type="button" class="lightbox-close" onclick="closeLightbox()">&times;</button>
            <img id="lightboxImg" src="" alt="Foto Ulasan">
            <div id="lightboxCaption" class="lightbox-caption"></div>
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
        function openTestiModal() {
            document.getElementById('modalTambahTesti').classList.add('active');
        }
        function closeTestiModal() {
            document.getElementById('modalTambahTesti').classList.remove('active');
        }
        window.addEventListener('click', function (e) {
            const modal = document.getElementById('modalTambahTesti');
            if (e.target === modal) {
                closeTestiModal();
            }
        });

        function syncRoleFromProduct(selectEl) {
            const roleInput = document.getElementById('inputCustomerRole');
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const prodName = selectedOption ? selectedOption.getAttribute('data-name') : '';
            if (roleInput && prodName) {
                roleInput.value = prodName;
            }
        }

        function previewAdminFoto(input) {
            const preview = document.getElementById('adminFotoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        }

        function openLightbox(src, caption) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightboxCaption').textContent = caption || '';
            document.getElementById('photoLightbox').classList.add('active');
        }

        function closeLightbox() {
            document.getElementById('photoLightbox').classList.remove('active');
        }

        function filterTestimoni() {
            const keyword = document.getElementById('searchTesti').value.toLowerCase().trim();
            const ratingFilter = document.getElementById('filterRating').value;
            const rows = document.querySelectorAll('#testiTable tbody tr.testi-row');
            const noResultRow = document.getElementById('noSearchResultRow');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowRating = row.getAttribute('data-rating');
                const matchKeyword = keyword === '' || text.includes(keyword);
                const matchRating  = ratingFilter === 'all' || rowRating === ratingFilter;

                if (matchKeyword && matchRating) {
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
    </script>
</body>
</html>