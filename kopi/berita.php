<?php
require_once 'auth_erp.php';

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && ($conn instanceof PDO)) {
        $pdo = $conn;
    } else {
        $pdo = new PDO(
            "mysql:host=127.0.0.1;dbname=db_filkop;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
}

$notifMsg  = '';
$notifType = '';

// Pastikan tabel kategori `news_categories`, kolom `category` di `news`, dan `news_hero_settings` siap dipakai
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `news_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(80) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $defaultCats = ['Menu Baru', 'Edukasi Kopi', 'Promo', 'Berita Kedai'];
    $insCatStmt  = $pdo->prepare("INSERT IGNORE INTO `news_categories` (`name`) VALUES (?)");
    foreach ($defaultCats as $dc) {
        $insCatStmt->execute([$dc]);
    }

    $colsNewsStmt = $pdo->query("SHOW COLUMNS FROM `news`");
    $newsCols     = array_column($colsNewsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('category', $newsCols, true)) {
        $pdo->exec("ALTER TABLE `news` ADD COLUMN `category` varchar(80) NOT NULL DEFAULT 'Berita Kedai' AFTER `title`");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `news_hero_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `image_url` varchar(255) NOT NULL,
            `slide_interval` int(11) NOT NULL DEFAULT 5,
            `is_rolling` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (PDOException $e) {
    // Abaikan jika sudah ada atau keterbatasan izin alter
}

// Proses tambah kategori baru via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    $newCat = trim($_POST['category_name'] ?? '');
    if ($newCat === '') {
        $notifMsg  = 'Nama kategori tidak boleh kosong.';
        $notifType = 'danger';
    } elseif (mb_strlen($newCat) > 80) {
        $notifMsg  = 'Nama kategori maksimal 80 karakter.';
        $notifType = 'danger';
    } else {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM `news_categories` WHERE LOWER(`name`) = LOWER(?)");
            $chk->execute([$newCat]);
            if ((int)$chk->fetchColumn() > 0) {
                $notifMsg  = 'Kategori "' . htmlspecialchars($newCat, ENT_QUOTES, 'UTF-8') . '" sudah ada.';
                $notifType = 'info';
            } else {
                $ins = $pdo->prepare("INSERT INTO `news_categories` (`name`) VALUES (?)");
                $ins->execute([$newCat]);
                $notifMsg  = 'Kategori "' . htmlspecialchars($newCat, ENT_QUOTES, 'UTF-8') . '" berhasil ditambahkan!';
                $notifType = 'success';
            }
        } catch (PDOException $e) {
            $notifMsg  = 'Gagal menambahkan kategori: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $notifType = 'danger';
        }
    }
}

// Proses hapus kategori via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_category') {
    $delCat = trim($_POST['category_name'] ?? '');
    if ($delCat === '') {
        $notifMsg  = 'Kategori tidak valid.';
        $notifType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            if (strcasecmp($delCat, 'Berita Kedai') !== 0) {
                $resetNews = $pdo->prepare("UPDATE `news` SET `category` = 'Berita Kedai' WHERE `category` = ?");
                $resetNews->execute([$delCat]);
            }

            $delStmt = $pdo->prepare("DELETE FROM `news_categories` WHERE `name` = ?");
            $delStmt->execute([$delCat]);

            $pdo->commit();
            $notifMsg  = 'Kategori "' . htmlspecialchars($delCat, ENT_QUOTES, 'UTF-8') . '" berhasil dihapus.';
            $notifType = 'danger';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $notifMsg  = 'Gagal menghapus kategori: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $notifType = 'danger';
        }
    }
}

// Proses ubah kategori pada berita tertentu via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_news_category') {
    $newsId = (int)($_POST['news_id'] ?? 0);
    $catVal = trim($_POST['category'] ?? 'Berita Kedai');
    if ($newsId > 0 && $catVal !== '') {
        try {
            $upd = $pdo->prepare("UPDATE `news` SET `category` = ? WHERE `id` = ?");
            $upd->execute([$catVal, $newsId]);
            $notifMsg  = 'Kategori berita berhasil diperbarui menjadi "' . htmlspecialchars($catVal, ENT_QUOTES, 'UTF-8') . '".';
            $notifType = 'info';
        } catch (PDOException $e) {
            $notifMsg  = 'Gagal memperbarui kategori berita.';
            $notifType = 'danger';
        }
    }
}

// Proses simpan pengaturan interval / mode guling background berita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bg_settings') {
    $intervalSec = max(1, min(60, (int)($_POST['slide_interval'] ?? 5)));
    $isRolling   = isset($_POST['is_rolling']) ? 1 : 0;

    try {
        $updCfg = $pdo->prepare("UPDATE `news_hero_settings` SET `slide_interval` = ?, `is_rolling` = ?");
        $updCfg->execute([$intervalSec, $isRolling]);
        $notifMsg  = 'Pengaturan background disimpan (ganti otomatis setiap ' . $intervalSec . ' detik, guling: ' . ($isRolling ? 'Aktif' : 'Nonaktif') . ').';
        $notifType = 'success';
    } catch (PDOException $e) {
        $notifMsg  = 'Gagal menyimpan pengaturan background: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $notifType = 'danger';
    }
}

// Proses tambah/upload gambar background berita baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_bg_image') {
    $intervalSec = max(1, min(60, (int)($_POST['slide_interval'] ?? 5)));
    $isRolling   = isset($_POST['is_rolling']) ? 1 : 0;
    $bgUrl       = trim($_POST['bg_image_url'] ?? '');

    if (isset($_FILES['bg_image_file']) && $_FILES['bg_image_file']['error'] === UPLOAD_ERR_OK) {
        $bgDir = "uploads/news_bg/";
        if (!is_dir($bgDir)) {
            mkdir($bgDir, 0777, true);
        }
        $ext     = strtolower(pathinfo($_FILES['bg_image_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $notifMsg  = 'Format gambar background tidak didukung (gunakan JPG, PNG, GIF, atau WEBP).';
            $notifType = 'danger';
        } elseif ($_FILES['bg_image_file']['size'] > 4 * 1024 * 1024) {
            $notifMsg  = 'Ukuran gambar background maksimal 4 MB.';
            $notifType = 'danger';
        } else {
            $newFileName = 'bg_' . time() . '_' . uniqid() . '.' . $ext;
            $destPath    = $bgDir . $newFileName;
            if (move_uploaded_file($_FILES['bg_image_file']['tmp_name'], $destPath)) {
                $bgUrl = $destPath;
            } else {
                $notifMsg  = 'Gagal mengunggah gambar background ke folder uploads/news_bg/.';
                $notifType = 'danger';
            }
        }
    }

    if ($notifMsg === '') {
        if ($bgUrl === '') {
            $notifMsg  = 'Pilih file gambar atau isi URL/path gambar background terlebih dahulu.';
            $notifType = 'danger';
        } else {
            try {
                $insBg = $pdo->prepare("INSERT INTO `news_hero_settings` (`image_url`, `slide_interval`, `is_rolling`) VALUES (?, ?, ?)");
                $insBg->execute([$bgUrl, $intervalSec, $isRolling]);
                $updAll = $pdo->prepare("UPDATE `news_hero_settings` SET `slide_interval` = ?, `is_rolling` = ?");
                $updAll->execute([$intervalSec, $isRolling]);
                $notifMsg  = 'Gambar background berita berhasil ditambahkan!';
                $notifType = 'success';
            } catch (PDOException $e) {
                $notifMsg  = 'Gagal menyimpan gambar background: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $notifType = 'danger';
            }
        }
    }
}

// Proses hapus gambar background berita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bg_image') {
    $bgId  = (int)($_POST['bg_id'] ?? 0);
    $bgUrl = trim($_POST['bg_image_url'] ?? '');

    if ($bgId > 0 || $bgUrl !== '') {
        try {
            if ($bgId > 0) {
                $sel = $pdo->prepare("SELECT `id`, `image_url` FROM `news_hero_settings` WHERE `id` = ?");
                $sel->execute([$bgId]);
                $rowBg = $sel->fetch(PDO::FETCH_ASSOC);

                $delBg = $pdo->prepare("DELETE FROM `news_hero_settings` WHERE `id` = ?");
                $delBg->execute([$bgId]);
            } else {
                $sel = $pdo->prepare("SELECT `id`, `image_url` FROM `news_hero_settings` WHERE `image_url` = ?");
                $sel->execute([$bgUrl]);
                $rowBg = $sel->fetch(PDO::FETCH_ASSOC);

                $delBg = $pdo->prepare("DELETE FROM `news_hero_settings` WHERE `image_url` = ?");
                $delBg->execute([$bgUrl]);
            }

            if ($rowBg && !empty($rowBg['image_url'])) {
                $rel = ltrim(str_replace('\\', '/', $rowBg['image_url']), '/');
                if (strpos($rel, 'uploads/news_bg/') === 0 && is_file($rel)) {
                    @unlink($rel);
                }
            }

            $notifMsg  = 'Gambar background berita berhasil dihapus.';
            $notifType = 'danger';
        } catch (PDOException $e) {
            $notifMsg  = 'Gagal menghapus gambar background: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $notifType = 'danger';
        }
    }
}

// Ambil daftar kategori
$categories_list = ['Menu Baru', 'Edukasi Kopi', 'Promo', 'Berita Kedai'];
try {
    $catRows = $pdo->query("SELECT `name` FROM `news_categories` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (count($catRows) > 0) {
        $categories_list = $catRows;
    }
} catch (PDOException $e) {
    // Gunakan default jika gagal
}

// Ambil daftar background & durasi ganti (default 5 detik)
$bg_slides     = [];
$slideInterval = 5;
$isRollingBg   = 1;
try {
    $bgRows = $pdo->query("SELECT `id`, `image_url`, `slide_interval`, `is_rolling` FROM `news_hero_settings` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (count($bgRows) > 0) {
        $bg_slides     = $bgRows;
        $slideInterval = max(1, (int)$bgRows[0]['slide_interval']);
        $isRollingBg   = (int)$bgRows[0]['is_rolling'];
    }
} catch (PDOException $e) {
    // Biarkan kosong jika belum ada
}

// Ambil daftar berita
$stmt = $pdo->query("SELECT id, title, COALESCE(NULLIF(category, ''), 'Berita Kedai') AS category, content, image_url, published_at FROM news ORDER BY published_at DESC, id DESC");
$news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Berita - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="berita.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .content-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .content-header h2 { font-size: 22px; color: #2b170e; margin: 0 0 5px 0; }
        .content-header p { font-size: 13px; color: #777; margin: 0; }
        .header-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }

        .btn-category { background: #ffffff; color: #2b170e; border: 1px solid #2b170e; padding: 11px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.25s; }
        .btn-category:hover { background: #f6efe9; }

        .btn-add { background: linear-gradient(135deg, #2b170e, #4a2d1e); color: #fff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 3px 10px rgba(43, 23, 14, 0.2); }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(43, 23, 14, 0.35); }

        .category-panel { background: #fff; padding: 18px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #efe7e1; }
        .category-panel-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .category-panel-title { font-size: 13px; font-weight: 700; color: #2b170e; display: flex; align-items: center; gap: 8px; }
        .add-category-inline-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .add-category-inline-form input[type="text"],
        .add-category-inline-form input[type="number"] { padding: 8px 13px; border: 1px solid #d4c4b8; border-radius: 8px; font-size: 13px; color: #2b170e; min-width: 220px; outline: none; }
        .add-category-inline-form input:focus { border-color: #2b170e; }
        .add-category-inline-form button { background: #2b170e; color: #fff; border: none; padding: 8px 15px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .add-category-inline-form button:hover { background: #4a2d1e; }

        .category-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .category-chip-form { display: inline-flex; margin: 0; }
        .category-chip { background: #f6efe9; color: #2b170e; border: 1px solid #e4d5ca; padding: 5px 10px 5px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-del-cat { background: transparent; border: none; color: #8d4a32; cursor: pointer; padding: 0; width: 16px; height: 16px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; transition: background-color 0.2s, color 0.2s; }
        .btn-del-cat:hover { background: #e53935; color: #ffffff; }

        .bg-preview-strip { display: flex; flex-wrap: wrap; gap: 10px; }
        .bg-thumb-card { position: relative; width: 140px; height: 78px; border-radius: 8px; overflow: hidden; border: 1px solid #d8c7bc; background: #231109; }
        .bg-thumb-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .bg-delete-form { position: absolute; top: 6px; right: 6px; z-index: 5; margin: 0; }
        .btn-del-bg { width: 22px; height: 22px; border-radius: 50%; border: none; background: #e53935; color: #ffffff; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35); transition: background-color 0.2s ease, transform 0.2s ease; }
        .btn-del-bg:hover { background: #b71c1c; transform: scale(1.08); }

        .search-container { background: #fff; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .search-container i { color: #999; }
        .search-container input { border: none; outline: none; width: 100%; font-size: 14px; background: transparent; color: #333; }

        .news-table-wrapper { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .news-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .news-table thead { background: #4e342e; color: #fff; }
        .news-table thead th { padding: 14px 18px; text-align: left; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase; }
        .news-table tbody td { padding: 14px 18px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .news-table tbody tr:hover { background-color: #faf7f5; }
        .news-thumb { width: 130px; height: 85px; object-fit: cover; border-radius: 8px; display: block; }
        .news-title { font-weight: 600; color: #2b170e; margin: 0 0 6px 0; font-size: 15px; line-height: 1.4; }
        .news-meta-row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
        .news-date { font-size: 12px; color: #888; }
        .cat-select-form { display: inline-flex; align-items: center; gap: 6px; }
        .cat-select { padding: 4px 10px; border-radius: 999px; border: 1px solid #d8c7bc; background: #fbf5f0; color: #2b170e; font-size: 12px; font-weight: 600; cursor: pointer; outline: none; }
        .cat-select:focus { border-color: #2b170e; }

        .action-btn-group { display: flex; gap: 8px; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; text-decoration: none; color: #fff; font-size: 14px; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-edit { background-color: #1565c0; }
        .btn-edit:hover { background-color: #0d47a1; transform: scale(1.05); }
        .btn-delete { background-color: #e53935; }
        .btn-delete:hover { background-color: #c62828; transform: scale(1.05); }

        .alert-notif { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 13px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-info { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .empty-row { text-align: center; padding: 40px !important; color: #999; }
        .empty-row i { font-size: 40px; color: #ddd; display: block; margin-bottom: 10px; }
        .header-logo { display: flex; align-items: center; gap: 15px; }
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
            <?php renderSidebarMenuErp('berita.php', $currentUserRole, $allowedMenus); ?>
        </aside>

        <main class="content-area">

            <?php if ($notifMsg !== ''): ?>
                <div class="alert-notif alert-<?= htmlspecialchars($notifType) ?>">
                    <i class="fa-solid fa-circle-info"></i> <?= $notifMsg ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div class="alert-notif alert-success">
                        <i class="fa-solid fa-circle-check"></i> Berita baru berhasil dipublikasikan!
                    </div>
                <?php elseif ($_GET['status'] === 'updated'): ?>
                    <div class="alert-notif alert-info">
                        <i class="fa-solid fa-pen"></i> Berita berhasil diperbarui!
                    </div>
                <?php elseif ($_GET['status'] === 'deleted'): ?>
                    <div class="alert-notif alert-danger">
                        <i class="fa-solid fa-trash"></i> Berita berhasil dihapus!
                    </div>
                <?php elseif ($_GET['status'] === 'error'): ?>
                    <div class="alert-notif alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i> Terjadi kesalahan saat memproses data berita.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="content-header">
                <div>
                    <h2><i class="fa-solid fa-newspaper"></i> Manajemen Berita & Blog</h2>
                    <p>Kelola kategori, background, berita, artikel, dan informasi terbaru seputar Filosofi Kopi</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn-category" onclick="focusCategoryInput()">
                        <i class="fa-solid fa-tag"></i> Tambah Kategori
                    </button>
                    <a href="tambah_berita.php" class="btn-add">
                        <i class="fa-solid fa-plus"></i> Tambah Berita
                    </a>
                </div>
            </div>

            <!-- PANEL TAMBAH, HAPUS & DAFTAR KATEGORI -->
            <section class="category-panel">
                <div class="category-panel-top">
                    <div class="category-panel-title">
                        <i class="fa-solid fa-tags"></i> Kategori Berita Aktif
                    </div>
                    <form method="POST" action="berita.php" class="add-category-inline-form">
                        <input type="hidden" name="action" value="add_category">
                        <input type="text" id="newCategoryInput" name="category_name" placeholder="Nama kategori baru (misal: Event)" required maxlength="80">
                        <button type="submit"><i class="fa-solid fa-plus"></i> Simpan Kategori</button>
                    </form>
                </div>
                <div class="category-chips">
                    <?php foreach ($categories_list as $catName): ?>
                        <form method="POST" action="berita.php" class="category-chip-form"
                              onsubmit="return confirm('Hapus kategori <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>? Berita dengan kategori ini akan diarahkan ke Berita Kedai.');">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="category_name" value="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="category-chip">
                                <?= htmlspecialchars($catName) ?>
                                <button type="submit" class="btn-del-cat" title="Hapus kategori <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </span>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- PANEL PENGATURAN BACKGROUND & GULING OTOMATIS -->
            <section class="category-panel" id="bgSettingsPanel">
                <div class="category-panel-top">
                    <div class="category-panel-title">
                        <i class="fa-solid fa-image"></i> Background & Durasi Ganti Berita
                    </div>
                    <form method="POST" action="berita.php" class="add-category-inline-form">
                        <input type="hidden" name="action" value="save_bg_settings">
                        <label style="font-size: 12px; color: #555; font-weight: 600;">
                            Ganti tiap (detik):
                            <input type="number" name="slide_interval" min="1" max="60" value="<?= (int)$slideInterval ?>"
                                   style="width: 75px; min-width: 75px; margin-left: 4px;" required>
                        </label>
                        <label style="font-size: 12px; color: #2b170e; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="checkbox" name="is_rolling" value="1" <?= $isRollingBg ? 'checked' : '' ?>>
                            Guling Otomatis
                        </label>
                        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Waktu</button>
                    </form>
                </div>

                <form method="POST" action="berita.php" enctype="multipart/form-data" class="add-category-inline-form" style="margin-bottom: 14px;">
                    <input type="hidden" name="action" value="add_bg_image">
                    <input type="hidden" name="slide_interval" value="<?= (int)$slideInterval ?>">
                    <?php if ($isRollingBg): ?>
                        <input type="hidden" name="is_rolling" value="1">
                    <?php endif; ?>
                    <input type="file" name="bg_image_file" accept="image/*" style="font-size: 12px;">
                    <input type="text" name="bg_image_url" placeholder="Atau tempel URL/path gambar (misal: image 24.png)">
                    <button type="submit"><i class="fa-solid fa-upload"></i> Tambah Background</button>
                </form>

                <?php if (count($bg_slides) > 0): ?>
                    <div class="bg-preview-strip">
                        <?php foreach ($bg_slides as $bgItem): ?>
                            <div class="bg-thumb-card">
                                <img src="<?= htmlspecialchars($bgItem['image_url']) ?>" alt="Background Berita" onerror="this.src='https://via.placeholder.com/140x78?text=BG'">
                                <form method="POST" action="berita.php" class="bg-delete-form" onsubmit="return confirm('Hapus gambar background ini?');">
                                    <input type="hidden" name="action" value="delete_bg_image">
                                    <input type="hidden" name="bg_id" value="<?= (int)$bgItem['id'] ?>">
                                    <input type="hidden" name="bg_image_url" value="<?= htmlspecialchars($bgItem['image_url'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-del-bg" title="Hapus Background">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <small style="color: #777; font-size: 12px;">Belum ada gambar background khusus; halaman berita publik menggunakan background default.</small>
                <?php endif; ?>
            </section>

            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchNews" placeholder="Cari berdasarkan judul atau kategori berita..." onkeyup="filterNews()">
            </div>

            <div class="news-table-wrapper">
                <table class="news-table" id="newsTable">
                    <thead>
                        <tr>
                            <th style="width: 20%;">GAMBAR</th>
                            <th style="width: 60%;">JUDUL, KATEGORI & TANGGAL</th>
                            <th style="width: 20%; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($news_list) > 0): ?>
                            <?php foreach ($news_list as $news): ?>
                                <tr class="news-row">
                                    <td>
                                        <?php if (!empty($news['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($news['image_url']) ?>" alt="Thumbnail" class="news-thumb" onerror="this.src='https://via.placeholder.com/130x85?text=No+Image'">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/130x85?text=No+Image" alt="No Image" class="news-thumb">
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <h3 class="news-title"><?= htmlspecialchars($news['title']) ?></h3>
                                        <div class="news-meta-row">
                                            <form method="POST" action="berita.php" class="cat-select-form">
                                                <input type="hidden" name="action" value="update_news_category">
                                                <input type="hidden" name="news_id" value="<?= (int)$news['id'] ?>">
                                                <select name="category" class="cat-select" onchange="this.form.submit()" title="Ubah Kategori Berita">
                                                    <?php
                                                    $rowCats = $categories_list;
                                                    if (!in_array($news['category'], $rowCats, true)) {
                                                        array_unshift($rowCats, $news['category']);
                                                    }
                                                    foreach ($rowCats as $optCat):
                                                    ?>
                                                        <option value="<?= htmlspecialchars($optCat) ?>" <?= ($news['category'] === $optCat) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($optCat) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <span class="news-date">
                                                <i class="fa-regular fa-calendar"></i>
                                                <?= date('d M Y, H:i', strtotime($news['published_at'])) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="action-btn-group" style="justify-content: center;">
                                            <a href="edit_berita.php?id=<?= (int)$news['id'] ?>" class="btn-action btn-edit" title="Edit Berita">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="hapus_berita.php?id=<?= (int)$news['id'] ?>" class="btn-action btn-delete" title="Hapus Berita"
                                               onclick="return confirm('Yakin ingin menghapus berita <?= htmlspecialchars($news['title'], ENT_QUOTES, 'UTF-8') ?>?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noSearchResultRow" style="display: none;">
                                <td colspan="3" class="empty-row">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    Berita yang dicari tidak ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-row">
                                    <i class="fa-solid fa-newspaper"></i>
                                    Belum ada berita yang dipublikasikan.<br>
                                    <small>Klik tombol <strong>"Tambah Berita"</strong> untuk memulai.</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
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
        function focusCategoryInput() {
            const input = document.getElementById('newCategoryInput');
            if (input) {
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                input.focus();
            }
        }

        function filterNews() {
            const input = document.getElementById('searchNews').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#newsTable tbody tr.news-row');
            const noResultRow = document.getElementById('noSearchResultRow');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(input)) {
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