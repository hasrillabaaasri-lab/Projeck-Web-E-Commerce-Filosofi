<?php
require_once 'auth_erp.php';

// =========================================================
// 1. BUAT TABEL PROMOSI & PENGATURAN SLIDE OTOMATIS
// =========================================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `promotions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(180) DEFAULT 'Banner Promosi',
      `description` text DEFAULT NULL,
      `image_url` varchar(255) NOT NULL,
      `badge_text` varchar(80) DEFAULT NULL,
      `link_url` varchar(255) DEFAULT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `urutan` int(11) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `promo_settings` (
      `id` int(11) NOT NULL PRIMARY KEY,
      `slide_seconds` int(11) NOT NULL DEFAULT 5,
      `auto_slide` tinyint(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$pdo->exec("INSERT IGNORE INTO `promo_settings` (`id`, `slide_seconds`, `auto_slide`) VALUES (1, 5, 1)");

// =========================================================
// 2. HELPER UPLOAD GAMBAR BANNER
// =========================================================
function uploadBannerPromosi($fileInputName): string {
    if (!empty($_FILES[$fileInputName]['name']) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt, true)) {
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            $safeName = 'banner_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $target   = 'uploads/' . $safeName;
            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target)) {
                return $target;
            }
        }
    }
    return '';
}

// =========================================================
// 3. AKSI POST: SETTING GULINGKAN, UPLOAD, EDIT, TOGGLE, HAPUS
// =========================================================
$pesan_sukses = '';
$pesan_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan Pengaturan Durasi Gulingkan (Default 5 Detik)
    if (isset($_POST['simpan_setting_slide'])) {
        $detik     = max(1, min(60, (int)($_POST['slide_seconds'] ?? 5)));
        $autoSlide = isset($_POST['auto_slide']) ? 1 : 0;

        $stmtSet = $pdo->prepare("UPDATE `promo_settings` SET `slide_seconds` = :sec, `auto_slide` = :auto WHERE `id` = 1");
        $stmtSet->execute([':sec' => $detik, ':auto' => $autoSlide]);
        $pesan_sukses = "Pengaturan gulingkan banner disimpan: berubah setiap <strong>{$detik} detik</strong>.";
    }

    // Upload Gambar Banner Baru
    if (isset($_POST['tambah_promosi'])) {
        $urutan   = max(1, (int)($_POST['urutan'] ?? 1));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $imageUrl = uploadBannerPromosi('image_file');

        if ($imageUrl !== '') {
            $stmtIns = $pdo->prepare("
                INSERT INTO `promotions` (`title`, `description`, `image_url`, `is_active`, `urutan`)
                VALUES (:title, '', :img, :active, :urutan)
            ");
            $stmtIns->execute([
                ':title'  => 'Banner Promosi #' . $urutan,
                ':img'    => $imageUrl,
                ':active' => $isActive,
                ':urutan' => $urutan
            ]);
            $pesan_sukses = "Gambar banner promosi berhasil diupload dan siap ditampilkan di Beranda.";
        } else {
            $pesan_error = "Pilih file gambar banner (JPG, PNG, atau WEBP) terlebih dahulu.";
        }
    }

    // Ganti Gambar / Urutan Banner
    if (isset($_POST['edit_promosi'])) {
        $id       = (int)($_POST['id'] ?? 0);
        $urutan   = max(1, (int)($_POST['urutan'] ?? 1));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $oldImage = trim($_POST['old_image_url'] ?? '');
        $newImage = uploadBannerPromosi('image_file');
        $finalImg = $newImage !== '' ? $newImage : $oldImage;

        if ($id > 0 && $finalImg !== '') {
            $stmtUpd = $pdo->prepare("
                UPDATE `promotions`
                SET `image_url` = :img,
                    `is_active` = :active,
                    `urutan` = :urutan
                WHERE `id` = :id
            ");
            $stmtUpd->execute([
                ':img'    => $finalImg,
                ':active' => $isActive,
                ':urutan' => $urutan,
                ':id'     => $id
            ]);
            $pesan_sukses = "Banner promosi berhasil diperbarui.";
        } else {
            $pesan_error = "Gagal memperbarui banner promosi.";
        }
    }

    // Ubah Status Aktif / Nonaktif
    if (isset($_POST['toggle_promo'])) {
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            $stmtTog = $pdo->prepare("UPDATE `promotions` SET `is_active` = :st WHERE `id` = :id");
            $stmtTog->execute([':st' => $newStatus, ':id' => $id]);
            $pesan_sukses = $newStatus === 1
                ? "Banner diaktifkan dan tampil di Beranda."
                : "Banner disembunyikan dari Beranda.";
        }
    }

    // Hapus Banner
    if (isset($_POST['hapus_promosi'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmtGet = $pdo->prepare("SELECT `image_url` FROM `promotions` WHERE `id` = :id");
            $stmtGet->execute([':id' => $id]);
            $rowImg = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if ($rowImg && !empty($rowImg['image_url']) && strpos($rowImg['image_url'], 'uploads/banner_') === 0 && file_exists($rowImg['image_url'])) {
                @unlink($rowImg['image_url']);
            }

            $stmtDel = $pdo->prepare("DELETE FROM `promotions` WHERE `id` = :id");
            $stmtDel->execute([':id' => $id]);
            $pesan_sukses = "Banner promosi berhasil dihapus.";
        }
    }
}

// =========================================================
// 4. AMBIL PENGATURAN & DATA BANNER PROMOSI
// =========================================================
$settingSlide = $pdo->query("SELECT * FROM `promo_settings` WHERE `id` = 1")->fetch(PDO::FETCH_ASSOC);
$slideSeconds = $settingSlide ? (int)$settingSlide['slide_seconds'] : 5;
$autoSlideOn  = $settingSlide ? (int)$settingSlide['auto_slide'] : 1;

$promo_list = $pdo->query("
    SELECT * FROM `promotions`
    ORDER BY `is_active` DESC, `urutan` ASC, `id` DESC
")->fetchAll(PDO::FETCH_ASSOC);

$active_banners = array_values(array_filter($promo_list, fn($item) => (int)$item['is_active'] === 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Banner Promosi - Filosofi Kopi</title>
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
            color: #666;
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

        /* Kartu Pengaturan Durasi Gulingkan */
        .slide-setting-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 22px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-left: 5px solid #3b2316;
        }
        .slide-setting-info h4 {
            font-size: 14px;
            color: #2b170e;
            margin-bottom: 3px;
        }
        .slide-setting-info p {
            font-size: 12px;
            color: #666;
        }
        .slide-setting-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .slide-setting-form input[type="number"] {
            width: 75px;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }
        .btn-save-setting {
            background-color: #3b2316;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-save-setting:hover {
            background-color: #5a3825;
        }

        /* Live Preview Slider Gulingkan Beranda */
        .preview-wrapper {
            margin-bottom: 24px;
        }
        .preview-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #3b2316;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .preview-slider-viewport {
            width: 100%;
            height: 260px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            background-color: #4a2c1d;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .preview-slider-track {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.65s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .preview-slide {
            min-width: 100%;
            height: 100%;
        }
        .preview-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .preview-dots {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            background: rgba(0, 0, 0, 0.35);
            padding: 5px 12px;
            border-radius: 20px;
        }
        .preview-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: rgba(255,255,255,0.45);
            transition: all 0.25s;
        }
        .preview-dot.active {
            width: 24px;
            border-radius: 10px;
            background: #ffffff;
        }

        /* Tabel Daftar Banner */
        .promo-table-wrapper {
            background: #fff;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .promo-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .promo-table thead {
            background: #4e342e;
            color: #fff;
        }
        .promo-table thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 11.5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .promo-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .promo-thumb-wide {
            width: 280px;
            height: 75px;
            object-fit: cover;
            border-radius: 10px;
            display: block;
            border: 1px solid #e0d6d1;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-aktif { background: #e8f5e9; color: #2e7d32; }
        .badge-nonaktif { background: #eceff1; color: #546e7a; }

        .action-btn-group {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 11px;
            border-radius: 8px;
            border: none;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-edit { background-color: #1565c0; }
        .btn-edit:hover { background-color: #0d47a1; }
        .btn-toggle { background-color: #3b2316; }
        .btn-toggle:hover { background-color: #5c3824; }
        .btn-delete { background-color: #e53935; }
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

        /* Modal Upload Banner */
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
            max-width: 500px;
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
            gap: 14px;
        }
        .size-guide-box {
            background: #f6efe9;
            border: 1px dashed #3b2316;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12px;
            color: #3b2316;
            line-height: 1.5;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12.5px; font-weight: 700; color: #333; }
        .form-group input[type="file"],
        .form-group input[type="number"] {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 13px;
        }
        .upload-preview-img {
            width: 100%;
            height: 115px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #ddd;
            display: none;
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
    </style>
</head>
<body>

    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>
            <?php renderSidebarMenuErp('promosi.php', $currentUserRole, $allowedMenus); ?>
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
                    <h2><i class="fa-solid fa-images"></i> Banner Promosi Beranda</h2>
                    <p>Upload gambar promosi sesuai ukuran banner Beranda (<strong>1280 × 330 px</strong>)</p>
                </div>
                <button type="button" class="btn-add" onclick="openModalTambah()">
                    <i class="fa-solid fa-upload"></i> Upload Gambar Banner
                </button>
            </div>

            <!-- PENGATURAN GULINGKAN OTOMATIS (DEFAULT 5 DETIK) -->
            <div class="slide-setting-card">
                <div class="slide-setting-info">
                    <h4><i class="fa-solid fa-sliders"></i> Pengaturan Gulingkan Banner Otomatis</h4>
                    <p>Atur jeda waktu pergantian gambar banner di halaman Beranda (default: <strong>5 detik</strong>)</p>
                </div>
                <form method="POST" class="slide-setting-form">
                    <label style="font-size:12.5px; font-weight:600; display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="auto_slide" value="1" <?= $autoSlideOn === 1 ? 'checked' : '' ?>>
                        Aktifkan Gulingkan
                    </label>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="number" name="slide_seconds" value="<?= $slideSeconds ?>" min="1" max="60" required>
                        <span style="font-size:12.5px; font-weight:600;">Detik</span>
                    </div>
                    <button type="submit" name="simpan_setting_slide" class="btn-save-setting">
                        <i class="fa-solid fa-check"></i> Simpan Setting
                    </button>
                </form>
            </div>

            <!-- PREVIEW LIVE GULINGKAN BANNER BERANDA -->
            <?php if (count($active_banners) > 0): ?>
                <div class="preview-wrapper">
                    <div class="preview-label">
                        <span><i class="fa-solid fa-eye"></i> Preview Tampilan Beranda (<?= count($active_banners) ?> Banner Aktif)</span>
                        <span>Berganti setiap <?= $slideSeconds ?> detik</span>
                    </div>
                    <div class="preview-slider-viewport">
                        <div class="preview-slider-track" id="previewTrack">
                            <?php foreach ($active_banners as $ab): ?>
                                <div class="preview-slide">
                                    <img src="<?= htmlspecialchars($ab['image_url']) ?>" alt="Banner Promosi">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($active_banners) > 1): ?>
                            <div class="preview-dots" id="previewDots">
                                <?php foreach ($active_banners as $i => $ab): ?>
                                    <span class="preview-dot <?= $i === 0 ? 'active' : '' ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TABEL DAFTAR GAMBAR BANNER -->
            <div class="promo-table-wrapper">
                <table class="promo-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">URUTAN</th>
                            <th style="width: 45%;">GAMBAR BANNER (1280 × 330 PX)</th>
                            <th style="width: 20%;">STATUS TAMPIL</th>
                            <th style="width: 25%; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($promo_list) > 0): ?>
                            <?php foreach ($promo_list as $pr): ?>
                                <tr>
                                    <td><strong>#<?= (int)$pr['urutan'] ?></strong></td>
                                    <td>
                                        <img src="<?= htmlspecialchars($pr['image_url']) ?>"
                                             alt="Banner"
                                             class="promo-thumb-wide"
                                             onerror="this.src='https://via.placeholder.com/640x165?text=Banner+Promosi'">
                                    </td>
                                    <td>
                                        <span class="badge-status <?= (int)$pr['is_active'] === 1 ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                            <?= (int)$pr['is_active'] === 1 ? 'Aktif di Beranda' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <button type="button"
                                                    class="btn-action btn-edit"
                                                    title="Ganti Gambar / Urutan"
                                                    onclick='openModalEdit(<?= json_encode($pr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= (int)$pr['is_active'] === 1 ? 0 : 1 ?>">
                                                <button type="submit" name="toggle_promo" class="btn-action btn-toggle">
                                                    <?= (int)$pr['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>

                                            <form method="POST" onsubmit="return confirm('Hapus gambar banner ini?');" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                                                <button type="submit" name="hapus_promosi" class="btn-action btn-delete" title="Hapus">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 35px; color:#888;">
                                    Belum ada gambar banner promosi. Klik <strong>Upload Gambar Banner</strong> untuk menambahkan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- MODAL UPLOAD GAMBAR BANNER BARU -->
    <div id="modalTambahPromo" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-image"></i> Upload Gambar Banner Promosi</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalTambahPromo')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="size-guide-box">
                        <i class="fa-solid fa-ruler-combined"></i>
                        <strong>Ukuran Banner Ideal:</strong> <b>1280 × 330 px</b> (rasio lebar ~4:1) agar gambar memenuhi kotak banner Beranda dengan pas tanpa terpotong.
                    </div>
                    <div class="form-group">
                        <label>Pilih File Gambar (JPG / PNG / WEBP)</label>
                        <input type="file" name="image_file" accept="image/*" required onchange="previewFile(this, 'addPreviewImg')">
                        <img id="addPreviewImg" class="upload-preview-img" alt="Preview Upload">
                    </div>
                    <div class="form-group">
                        <label>Urutan Slide</label>
                        <input type="number" name="urutan" value="<?= count($promo_list) + 1 ?>" min="1">
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked> Tampilkan langsung di Beranda
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalTambahPromo')">Batal</button>
                    <button type="submit" name="tambah_promosi" class="btn-add">Upload & Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT GAMBAR BANNER -->
    <div id="modalEditPromo" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Banner Promosi</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalEditPromo')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="old_image_url" id="edit_old_image_url">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Preview Gambar Saat Ini (Ukuran 1280 × 330 px)</label>
                        <img id="editPreviewImg" class="upload-preview-img" style="display:block;" alt="Banner Saat Ini">
                    </div>
                    <div class="form-group">
                        <label>Ganti File Gambar (Opsional)</label>
                        <input type="file" name="image_file" accept="image/*" onchange="previewFile(this, 'editPreviewImg')">
                    </div>
                    <div class="form-group">
                        <label>Urutan Slide</label>
                        <input type="number" name="urutan" id="edit_urutan" min="1">
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1"> Aktif tampil di Beranda
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalEditPromo')">Batal</button>
                    <button type="submit" name="edit_promosi" class="btn-add">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

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
        function openModalTambah() {
            document.getElementById('modalTambahPromo').classList.add('active');
        }
        function openModalEdit(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_old_image_url').value = data.image_url || '';
            document.getElementById('editPreviewImg').src = data.image_url || '';
            document.getElementById('edit_urutan').value = data.urutan || 1;
            document.getElementById('edit_is_active').checked = parseInt(data.is_active) === 1;
            document.getElementById('modalEditPromo').classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        function previewFile(input, targetImgId) {
            const img = document.getElementById(targetImgId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    img.src = e.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        window.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
            }
        });

        // Preview Auto-Slide Gulingkan di Admin
        document.addEventListener('DOMContentLoaded', function () {
            const track = document.getElementById('previewTrack');
            const dots = document.querySelectorAll('#previewDots .preview-dot');
            const totalSlides = <?= count($active_banners) ?>;
            const autoSlide = <?= $autoSlideOn === 1 ? 'true' : 'false' ?>;
            const intervalMs = <?= $slideSeconds * 1000 ?>;

            if (track && totalSlides > 1 && autoSlide) {
                let idx = 0;
                setInterval(() => {
                    idx = (idx + 1) % totalSlides;
                    track.style.transform = `translateX(-${idx * 100}%)`;
                    dots.forEach((d, i) => d.classList.toggle('active', i === idx));
                }, intervalMs);
            }
        });
    </script>
</body>
</html>