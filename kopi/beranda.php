<?php
// ==========================================
// 1. CEK SESSION (OPSIONAL - BOLEH AKSES TANPA LOGIN)
// ==========================================
session_start();
require_once 'config.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

function ekstrakTanggalIso($rawDate): string {
    $str = trim((string)$rawDate);
    if ($str === '') {
        return '';
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $str, $m)) {
        return $m[1];
    }
    $ts = strtotime($str);
    return ($ts !== false) ? date('Y-m-d', $ts) : '';
}

function ambilDataBeritaBeranda(PDO $pdo): array {
    $beritaList = [];
    try {
        $checkBerita = $pdo->query("SHOW TABLES LIKE 'berita'");
        $checkNews   = $pdo->query("SHOW TABLES LIKE 'news'");

        if ($checkBerita && $checkBerita->rowCount() > 0) {
            $stmtBerita = $pdo->query("SELECT * FROM `berita` ORDER BY `id` DESC");
            $beritaList = $stmtBerita->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($checkNews && $checkNews->rowCount() > 0) {
            $colsStmt = $pdo->query("SHOW COLUMNS FROM `news`");
            $newsCols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $hasCat   = in_array('category', $newsCols, true);

            if ($hasCat) {
                $stmtBerita = $pdo->query("
                    SELECT `id`, `title`, COALESCE(NULLIF(`category`, ''), 'Berita Kedai') AS `category`, `content`, `image_url`, `published_at`
                    FROM `news`
                    ORDER BY `published_at` DESC, `id` DESC
                ");
            } else {
                $stmtBerita = $pdo->query("
                    SELECT `id`, `title`, 'Berita Kedai' AS `category`, `content`, `image_url`, `published_at`
                    FROM `news`
                    ORDER BY `published_at` DESC, `id` DESC
                ");
            }
            $beritaList = $stmtBerita->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $beritaList = [];
    }

    $normalized = [];
    foreach ($beritaList as $b) {
        $judul   = $b['judul'] ?? $b['title'] ?? 'Berita Filosofi Kopi';
        $isi     = $b['isi'] ?? $b['konten'] ?? $b['content'] ?? $b['description'] ?? '';
        $gambar  = $b['gambar'] ?? $b['image_url'] ?? $b['image'] ?? '';
        $tanggal = $b['tanggal'] ?? $b['published_at'] ?? $b['created_at'] ?? '';
        $cat     = trim((string)($b['category'] ?? 'Berita Kedai'));
        if ($cat === '') {
            $cat = 'Berita Kedai';
        }
        $catNorm = strtolower(trim(preg_replace('/[\s\-]+/', ' ', $cat)));

        $normalized[] = [
            'id'           => (int)($b['id'] ?? 0),
            'title'        => $judul,
            'category'     => $cat,
            'cat_norm'     => $catNorm,
            'content'      => $isi,
            'content_html' => nl2br(htmlspecialchars($isi, ENT_QUOTES, 'UTF-8')),
            'image_url'    => $gambar,
            'published_at' => $tanggal,
            'date_iso'     => ekstrakTanggalIso($tanggal),
        ];
    }

    return $normalized;
}

// Endpoint AJAX real-time agar berita baru langsung muncul tanpa reload
if (isset($_GET['ajax_berita_realtime'])) {
    header('Content-Type: application/json');
    $list = ambilDataBeritaBeranda($pdo);
    echo json_encode([
        'count' => count($list),
        'items' => $list,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id            = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$showDashboardAdmin = false;
$dashboardLink      = 'dashboard_admin.php';
$currentRole        = 'Guest';

// Jika sedang login, sinkronkan role terbaru dari database
if ($user_id > 0) {
    try {
        $stmtRoleUser = $pdo->prepare("SELECT name, role FROM users WHERE id = :uid LIMIT 1");
        $stmtRoleUser->execute([':uid' => $user_id]);
        $dataUser = $stmtRoleUser->fetch(PDO::FETCH_ASSOC);

        if ($dataUser) {
            $_SESSION['name'] = $dataUser['name'];
            $_SESSION['role'] = trim($dataUser['role']);
            $currentRole      = $_SESSION['role'];
        } else {
            $_SESSION = [];
            $user_id  = 0;
        }
    } catch (PDOException $e) {
        $currentRole = trim($_SESSION['role'] ?? 'Pelanggan');
    }

    if ($user_id > 0) {
        if ($currentRole === 'Admin') {
            $showDashboardAdmin = true;
            $dashboardLink      = 'dashboard_admin.php';
        } elseif ($currentRole !== '' && strcasecmp($currentRole, 'Pelanggan') !== 0) {
            try {
                $cekTabelRole = $pdo->query("SHOW TABLES LIKE 'user_roles'");
                if ($cekTabelRole && $cekTabelRole->rowCount() > 0) {
                    $stmtAkses = $pdo->prepare("SELECT can_access_erp, allowed_menus FROM user_roles WHERE role_name = :role LIMIT 1");
                    $stmtAkses->execute([':role' => $currentRole]);
                    $cfgRole = $stmtAkses->fetch(PDO::FETCH_ASSOC);

                    if ($cfgRole) {
                        if ((int)$cfgRole['can_access_erp'] === 1) {
                            $showDashboardAdmin = true;
                            $menuList           = json_decode($cfgRole['allowed_menus'] ?? '[]', true);
                            $dashboardLink      = (!empty($menuList) && is_array($menuList)) ? $menuList[0] : 'dashboard_admin.php';
                        }
                    } else {
                        $showDashboardAdmin = true;
                        $dashboardLink      = 'dashboard_admin.php';
                    }
                } else {
                    $showDashboardAdmin = true;
                    $dashboardLink      = 'dashboard_admin.php';
                }
            } catch (PDOException $e) {
                $showDashboardAdmin = true;
                $dashboardLink      = 'dashboard_admin.php';
            }
        }
    }
}

// ==========================================
// 2. DATA BERANDA
// ==========================================
$pageTitle = "Beranda - Filosofi Kopi";

// Hitung jumlah item di keranjang (0 jika belum login)
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

// Sinkronkan pesan otomatis pengguna baru & hitung pesan yang belum dibaca
$unreadMessages = 0;
if ($user_id > 0) {
    if (file_exists(__DIR__ . '/helper_pesan.php')) {
        require_once __DIR__ . '/helper_pesan.php';
        kirimPesanOtomatisGabung($pdo, $user_id, $_SESSION['name'] ?? '');
    }
    try {
        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM `messages` WHERE `user_id` = :uid AND `is_read` = 0");
        $stmtUnread->execute([':uid' => $user_id]);
        $unreadMessages = (int)$stmtUnread->fetchColumn();
    } catch (PDOException $e) {
        $unreadMessages = 0;
    }
}

// Ambil gambar banner promosi aktif & pengaturan durasi gulingkan (default 5 detik)
$promosiAktif   = [];
$promoSlideSec  = 5;
$promoAutoSlide = 1;

try {
    $cekTabelPromo = $pdo->query("SHOW TABLES LIKE 'promotions'");
    if ($cekTabelPromo && $cekTabelPromo->rowCount() > 0) {
        $stmtPromo = $pdo->query("
            SELECT id, image_url
            FROM promotions
            WHERE is_active = 1 AND image_url IS NOT NULL AND image_url != ''
            ORDER BY urutan ASC, id DESC
        ");
        $promosiAktif = $stmtPromo->fetchAll(PDO::FETCH_ASSOC);
    }

    $cekTabelSetting = $pdo->query("SHOW TABLES LIKE 'promo_settings'");
    if ($cekTabelSetting && $cekTabelSetting->rowCount() > 0) {
        $st = $pdo->query("SELECT slide_seconds, auto_slide FROM promo_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($st) {
            $promoSlideSec  = max(1, (int)$st['slide_seconds']);
            $promoAutoSlide = (int)$st['auto_slide'];
        }
    }

    if (count($promosiAktif) === 0) {
        $cekNewsBg = $pdo->query("SHOW TABLES LIKE 'news_hero_settings'");
        if ($cekNewsBg && $cekNewsBg->rowCount() > 0) {
            $bgRows = $pdo->query("SELECT id, image_url, slide_interval, is_rolling FROM news_hero_settings WHERE image_url IS NOT NULL AND image_url != '' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            if (count($bgRows) > 0) {
                foreach ($bgRows as $bg) {
                    $promosiAktif[] = ['id' => $bg['id'], 'image_url' => $bg['image_url']];
                }
                $promoSlideSec  = max(1, (int)$bgRows[0]['slide_interval']);
                $promoAutoSlide = (int)$bgRows[0]['is_rolling'];
            }
        }
    }
} catch (PDOException $e) {
    $promosiAktif = [];
}

// Ambil daftar kategori produk langsung dari database
$kategoriTabs = [];
try {
    $stmtKat = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC");
    $kategoriTabs = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kategoriTabs = [];
}

// Ambil daftar kategori berita jika tersedia
$beritaCategories = [];
try {
    $cekNewsCat = $pdo->query("SHOW TABLES LIKE 'news_categories'");
    if ($cekNewsCat && $cekNewsCat->rowCount() > 0) {
        $beritaCategories = $pdo->query("SELECT name FROM news_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $beritaCategories = [];
}

// Ambil data produk dari database
$products = [];
try {
    $query = "SELECT 
                p.id, 
                p.name, 
                p.price, 
                p.description, 
                p.image_url, 
                c.name AS category_name,
                s.current_stock
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN stock_master s ON p.sku = s.sku 
              ORDER BY p.id DESC";
    $stmt = $pdo->query($query);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

// Ambil data berita terbaru dari database
$beritaList    = ambilDataBeritaBeranda($pdo);
$openBeritaTab = (isset($_GET['tab']) && $_GET['tab'] === 'berita');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Memanggil file CSS -->
    <link rel="stylesheet" href="beranda.css">

    <style>
        .dashboard-admin-bar {
            width: 100%;
            margin: 16px auto;
            display: flex;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
        }
        .has-admin-bar .hero-banner {
            margin-top: 0 !important;
        }
        .btn-dashboard-admin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 24px;
            background-color: #ffffff;
            color: #3b2316;
            border: 1.5px solid #3b2316;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(59, 35, 22, 0.08);
            transition: all 0.2s ease;
        }
        .btn-dashboard-admin:hover {
            background-color: #3b2316;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(59, 35, 22, 0.18);
            transform: translateY(-1px);
        }

        /* ================= HERO BANNER FULL GAMBAR & SLIDER GULINGKAN ================= */
        .hero-slider-viewport {
            position: relative;
            width: 100%;
            height: 330px;
            border-radius: 20px;
            overflow: hidden;
            background-color: #4a2c1d;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        .hero-slider-track {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.65s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .hero-slide-item {
            min-width: 100%;
            width: 100%;
            height: 100%;
            flex-shrink: 0;
        }
        .hero-slide-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .hero-slider-dots {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 35, 22, 0.45);
            padding: 6px 14px;
            border-radius: 20px;
            z-index: 5;
        }
        .hero-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: none;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.25s ease;
            padding: 0;
        }
        .hero-dot.active {
            width: 24px;
            border-radius: 10px;
            background-color: #ffffff;
        }
        @media (max-width: 900px) {
            .hero-slider-viewport {
                height: 200px;
            }
        }

        /* ================= KATEGORI & FILTER TANGGAL BERITA ================= */
        .berita-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }
        .berita-cat-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .berita-cat-btn {
            background: #f5efe9;
            color: #3b2316;
            border: 1px solid #dccbc0;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .berita-cat-btn.active,
        .berita-cat-btn:hover {
            background: #3b2316;
            color: #ffffff;
            border-color: #3b2316;
        }
        .berita-date-filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #faf6f2;
            border: 1px solid #e6d8ce;
            border-radius: 14px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .berita-date-inputs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #3b2316;
            font-weight: 600;
        }
        .berita-date-inputs input[type="date"] {
            padding: 6px 10px;
            border: 1px solid #cbb7a8;
            border-radius: 10px;
            background: #ffffff;
            color: #2b170e;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            outline: none;
        }
        .berita-date-presets {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .berita-preset-btn {
            background: #ffffff;
            color: #3b2316;
            border: 1px solid #d5c2b4;
            padding: 5px 11px;
            border-radius: 16px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .berita-preset-btn.active,
        .berita-preset-btn:hover {
            background: #3b2316;
            color: #ffffff;
            border-color: #3b2316;
        }
        .berita-reset-btn {
            background: transparent;
            color: #8b3a2b;
            border: 1px solid #d9a79c;
            padding: 5px 11px;
            border-radius: 16px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
        }
        .berita-reset-btn:hover {
            background: #8b3a2b;
            color: #ffffff;
        }
        .berita-cat-tag {
            display: inline-block;
            background: #f5efe9;
            color: #3b2316;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* ================= BADGE & TOAST KERANJANG ================= */
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
        .notif-toast.show { transform: translateX(0); opacity: 1; }
        .notif-toast.error { border-left-color: #e74c3c; }
        .notif-icon { font-size: 1.6rem; color: #d4a373; margin-top: 2px; }
        .notif-toast.error .notif-icon { color: #e74c3c; }
        .notif-content { flex: 1; }
        .notif-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
        .notif-message { font-size: 0.83rem; color: #f2e6e6; line-height: 1.4; margin-bottom: 10px; }
        .notif-actions { display: flex; align-items: center; gap: 10px; }
        .notif-btn-cart {
            background-color: #d4a373;
            color: #2b180e;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
        }
        .notif-close {
            background: none;
            border: none;
            color: #ccc;
            font-size: 1.1rem;
            cursor: pointer;
            line-height: 1;
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
    </style>
</head>
<body class="<?= $showDashboardAdmin ? 'has-admin-bar' : '' ?>">

    <!-- KONTAINER NOTIFIKASI POPUP -->
    <div id="notif-container" class="notif-container"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
            <a href="beranda.php" class="nav-brand">Filosofi Kopi</a>
        </div>

        <div class="nav-center">
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="navSearchInput" placeholder="Cari kopi, produk, berita, atau layanan...">
            </div>
        </div>

        <div class="nav-right">
            <a href="<?= $user_id > 0 ? 'halaman_keranjang.php' : 'login.php' ?>" class="cart-link-wrapper" title="Keranjang">
                <i class="fa-solid fa-cart-shopping"></i>
                <span id="cart-badge" class="cart-badge" style="display: <?= $totalCartItems > 0 ? 'flex' : 'none' ?>;">
                    <?= $totalCartItems ?>
                </span>
            </a>
            <a href="<?= $user_id > 0 ? 'pesan.php' : 'login.php' ?>" class="cart-link-wrapper" title="Pesan">
                <i class="fa-solid fa-envelope"></i>
                <?php if ($unreadMessages > 0): ?>
                    <span class="cart-badge"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $user_id > 0 ? 'profile.php' : 'login.php' ?>" title="<?= $user_id > 0 ? 'Profil Saya' : 'Login / Masuk' ?>"><i class="fa-solid fa-user"></i></a>
        </div>
    </nav>      

    <!-- TOMBOL DASHBOARD SESUAI ROLE -->
    <?php if ($showDashboardAdmin): ?>
        <div class="dashboard-admin-bar" id="dashboardAdminBar">
            <a href="<?= htmlspecialchars($dashboardLink) ?>" class="btn-dashboard-admin">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard <?= htmlspecialchars($currentRole) ?></span>
            </a>
        </div>
    <?php endif; ?>

    <!-- HERO BANNER: GAMBAR PROMOSI / BACKGROUND BERITA & SLIDER GULINGKAN OTOMATIS -->
    <section class="hero-banner" id="heroBanner">
        <?php if (count($promosiAktif) > 0): ?>
            <div class="hero-slider-viewport">
                <div class="hero-slider-track" id="heroSliderTrack">
                    <?php foreach ($promosiAktif as $promo): ?>
                        <div class="hero-slide-item">
                            <img src="<?= htmlspecialchars($promo['image_url']) ?>"
                                 alt="Banner Promosi Filosofi Kopi"
                                 onerror="this.src='https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=1280&auto=format&fit=crop'">
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($promosiAktif) > 1): ?>
                    <div class="hero-slider-dots" id="heroSliderDots">
                        <?php foreach ($promosiAktif as $idx => $promo): ?>
                            <button type="button" class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx ?>" aria-label="Banner <?= $idx + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hero-content">
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=600&auto=format&fit=crop" alt="Kenikmatan Kopi">
                </div>
                <div class="hero-text">
                    <h1>Kenikmatan Autentik di Setiap Cangkir Filosofi Kopi</h1>
                    <p>Secangkir kopi hangat dari Filosofi Kopi Coffee bukan sekadar pelepasan penat atau teman di kala senggang, melainkan sebuah mahakarya rasa yang diracik khusus dari biji kopi pilihan berkualitas tinggi untuk membangkitkan semangat, mempertajam fokus, dan menemani setiap langkah perjalanan produktifmu sepanjang hari.</p>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        
        <!-- BREADCRUMB & NAVIGASI KATEGORI DINAMIS -->
        <div class="breadcrumb-bar">
            <a href="beranda.php" class="breadcrumb-item <?= !$openBeritaTab ? 'active' : '' ?>" data-filter="all">Beranda</a>
            <?php foreach ($kategoriTabs as $kat): ?>
                <?php
                    $filterKey = strtolower(trim(preg_replace('/[\s\-]+/', ' ', $kat['name'])));
                    $slugKey   = strtolower(trim(preg_replace('/[\s\-]+/', '-', $kat['name'])));
                ?>
                <a href="?kategori=<?= urlencode($slugKey) ?>" class="breadcrumb-item" data-filter="<?= htmlspecialchars($filterKey) ?>">
                    <?= htmlspecialchars($kat['name']) ?>
                </a>
            <?php endforeach; ?>
            <a href="?tab=berita" class="breadcrumb-item <?= $openBeritaTab ? 'active' : '' ?>" data-tab="berita">
                Berita <span id="beritaCountBadge">(<?= count($beritaList) ?>)</span>
            </a>
        </div>

        <!-- LOOPING KARTU PRODUK -->
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $item): ?>
                <?php 
                    $catNormalized = strtolower(trim(preg_replace('/[\s\-]+/', ' ', $item['category_name'] ?? '')));
                    $stok = isset($item['current_stock']) ? (int)$item['current_stock'] : 0;
                ?>
                <article class="product-card" data-category="<?= htmlspecialchars($catNormalized) ?>" style="<?= $openBeritaTab ? 'display:none;' : '' ?>">
                    <div class="product-image">
                        <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/250?text=No+Image' ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>

                    <div class="product-details">
                        <h2 class="product-title"><?= htmlspecialchars($item['name']) ?></h2>
                        <hr class="divider">
                        <p class="product-desc">
                            <?= htmlspecialchars(!empty($item['description']) ? $item['description'] : 'Deskripsi belum tersedia untuk produk ini.') ?>
                        </p>
                        
                        <form action="tambah_keranjang.php" method="POST" class="form-add-cart">
                            <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="quantity" value="1">

                            <div class="option-group" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <select name="ice_level" style="padding: 8px 15px; border-radius: 20px; border: 1px solid #ccc; font-family: 'Poppins', sans-serif; outline: none;">
                                    <option value="ICE">ICE</option>
                                    <option value="Hot">Hot</option>
                                </select>
                                <select name="sugar_level" style="padding: 8px 15px; border-radius: 20px; border: 1px solid #ccc; font-family: 'Poppins', sans-serif; outline: none;">
                                    <option value="Normal Sugar">Normal Sugar</option>
                                    <option value="Less Sugar">Less Sugar</option>
                                    <option value="No Sugar">No Sugar</option>
                                </select>
                            </div>

                            <div class="product-actions">
                                <button type="submit" class="btn-cart" <?= $stok <= 0 ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-cart-shopping"></i> Tambah Keranjang
                                </button>
                                <div class="price"><?= formatRupiah($item['price']) ?></div>
                            </div>
                        </form>
                    </div>

                    <a href="detail_produk.php?id=<?= (int)$item['id'] ?>" class="detail-link">Klik Detail </a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- PESAN OTOMATIS JIKA PRODUK TIDAK ADA -->
        <div id="empty-product-box" class="empty-state-box" style="display: <?= (!$openBeritaTab && count($products) === 0) ? 'block' : 'none' ?>;">
            <i class="fa-solid fa-mug-hot" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
            <h3 style="color: #555;">Produk tidak ada</h3>
        </div>

        <!-- BAGIAN BERITA TERBARU (SINKRON DENGAN MANAJEMEN BERITA + FILTER KATEGORI & TANGGAL) -->
        <section id="berita-section" style="display: <?= $openBeritaTab ? 'block' : 'none' ?>;">
            <div class="berita-top-bar">
                <h2 class="berita-heading" style="margin: 0;">Berita Terbaru</h2>
                <div class="berita-cat-pills" id="beritaCatPills">
                    <button type="button" class="berita-cat-btn active" data-news-cat="all">Semua</button>
                    <?php foreach ($beritaCategories as $bCat): ?>
                        <?php $bCatNorm = strtolower(trim(preg_replace('/[\s\-]+/', ' ', $bCat))); ?>
                        <button type="button" class="berita-cat-btn" data-news-cat="<?= htmlspecialchars($bCatNorm) ?>">
                            <?= htmlspecialchars($bCat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- FILTER TANGGAL BERITA YANG INGIN DIBACA -->
            <div class="berita-date-filter-bar" id="beritaDateFilterBar">
                <div class="berita-date-inputs">
                    <span><i class="fa-regular fa-calendar"></i> Pilih Tanggal:</span>
                    <label>
                        Dari
                        <input type="date" id="newsDateFrom" title="Dari tanggal">
                    </label>
                    <label>
                        Sampai
                        <input type="date" id="newsDateTo" title="Sampai tanggal">
                    </label>
                </div>

                <div class="berita-date-presets">
                    <button type="button" class="berita-preset-btn active" data-range="all">Semua Tanggal</button>
                    <button type="button" class="berita-preset-btn" data-range="today">Hari Ini</button>
                    <button type="button" class="berita-preset-btn" data-range="7d">7 Hari Terakhir</button>
                    <button type="button" class="berita-preset-btn" data-range="month">Bulan Ini</button>
                    <button type="button" class="berita-reset-btn" id="btnResetNewsDate">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </button>
                </div>
            </div>

            <div id="beritaListContainer">
                <?php if (count($beritaList) > 0): ?>
                    <?php foreach ($beritaList as $b): ?>
                        <article class="berita-card"
                                 data-news-category="<?= htmlspecialchars($b['cat_norm']) ?>"
                                 data-news-date="<?= htmlspecialchars($b['date_iso']) ?>">
                            <?php if (!empty($b['image_url'])): ?>
                                <div class="berita-image">
                                    <img src="<?= htmlspecialchars($b['image_url']) ?>" alt="<?= htmlspecialchars($b['title']) ?>" onerror="this.style.display='none'">
                                </div>
                            <?php endif; ?>
                            <div class="berita-details">
                                <h3 class="berita-title"><?= htmlspecialchars($b['title']) ?></h3>
                                <div class="berita-meta">
                                    <?php if (!empty($b['published_at'])): ?>
                                        <span class="berita-date"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($b['published_at']) ?></span>
                                    <?php endif; ?>
                                    <span class="berita-cat-tag"><?= htmlspecialchars($b['category']) ?></span>
                                </div>
                                <hr class="divider">
                                <p class="berita-desc"><?= $b['content_html'] ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state-box">
                        <i class="fa-regular fa-newspaper" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
                        <h3 style="color: #555;">Tidak ada berita terbaru</h3>
                    </div>
                <?php endif; ?>
            </div>

            <div id="beritaFilteredEmptyBox" class="empty-state-box" style="display: none;">
                <i class="fa-regular fa-calendar-xmark" style="font-size: 46px; color: #ccc; margin-bottom: 12px;"></i>
                <h3 style="color: #555;">Tidak ada berita pada tanggal atau kategori yang dipilih</h3>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo Footer" class="footer-logo" onerror="this.style.display='none'">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" class="fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" class="ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isLoggedIn = <?= $user_id > 0 ? 'true' : 'false' ?>;
            let isBeritaTabOpen = <?= $openBeritaTab ? 'true' : 'false' ?>;

            // ==========================================
            // GULINGKAN / SLIDER OTOMATIS BANNER PROMOSI (DEFAULT 5 DETIK)
            // ==========================================
            const sliderTrack  = document.getElementById('heroSliderTrack');
            const sliderDots   = document.querySelectorAll('#heroSliderDots .hero-dot');
            const totalSlides  = <?= count($promosiAktif) ?>;
            const autoSlide    = <?= $promoAutoSlide === 1 ? 'true' : 'false' ?>;
            const slideDelayMs = <?= $promoSlideSec * 1000 ?>;

            if (sliderTrack && totalSlides > 1) {
                let currentSlide = 0;

                function goToSlide(index) {
                    currentSlide = (index + totalSlides) % totalSlides;
                    sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
                    sliderDots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
                }

                let autoTimer = autoSlide ? setInterval(() => goToSlide(currentSlide + 1), slideDelayMs) : null;

                sliderDots.forEach(dot => {
                    dot.addEventListener('click', function () {
                        if (autoTimer) clearInterval(autoTimer);
                        goToSlide(parseInt(this.getAttribute('data-slide'), 10));
                        if (autoSlide) {
                            autoTimer = setInterval(() => goToSlide(currentSlide + 1), slideDelayMs);
                        }
                    });
                });
            }

            // ==========================================
            // FILTER KATEGORI, TANGGAL, PENCARIAN & TAB BERITA REAL-TIME
            // ==========================================
            const allTabs = document.querySelectorAll('.breadcrumb-item');
            const filterTabs = document.querySelectorAll('.breadcrumb-item[data-filter]');
            const beritaTab = document.querySelector('.breadcrumb-item[data-tab="berita"]');
            const productCards = document.querySelectorAll('.product-card');
            const emptyBox = document.getElementById('empty-product-box');
            const beritaSection = document.getElementById('berita-section');
            const beritaListContainer = document.getElementById('beritaListContainer');
            const beritaFilteredEmptyBox = document.getElementById('beritaFilteredEmptyBox');
            const beritaCountBadge = document.getElementById('beritaCountBadge');
            const navSearchInput = document.getElementById('navSearchInput');
            const newsDateFrom = document.getElementById('newsDateFrom');
            const newsDateTo = document.getElementById('newsDateTo');
            const presetButtons = document.querySelectorAll('.berita-preset-btn');
            const resetNewsDateBtn = document.getElementById('btnResetNewsDate');

            let activeCategory = 'all';
            let activeNewsCategory = 'all';
            let lastBeritaSignature = <?= json_encode(md5(json_encode($beritaList))) ?>;

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatLocalIsoDate(dateObj) {
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function setActivePreset(rangeKey) {
                presetButtons.forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-range') === rangeKey);
                });
            }

            function applyNewsFilters() {
                const keyword = navSearchInput ? navSearchInput.value.toLowerCase().trim() : '';
                let fromVal = newsDateFrom ? newsDateFrom.value : '';
                let toVal = newsDateTo ? newsDateTo.value : '';

                if (fromVal && toVal && fromVal > toVal) {
                    const tmp = fromVal;
                    fromVal = toVal;
                    toVal = tmp;
                }

                const newsCards = beritaListContainer ? beritaListContainer.querySelectorAll('.berita-card') : [];
                let shown = 0;

                newsCards.forEach(card => {
                    const cardCat = card.getAttribute('data-news-category') || '';
                    const cardDate = card.getAttribute('data-news-date') || '';
                    const cardText = card.textContent.toLowerCase();

                    const matchCat = (activeNewsCategory === 'all' || cardCat === activeNewsCategory);
                    const matchKw = (keyword === '' || cardText.includes(keyword));

                    let matchDate = true;
                    if (fromVal || toVal) {
                        if (!cardDate) {
                            matchDate = false;
                        } else {
                            if (fromVal && cardDate < fromVal) matchDate = false;
                            if (toVal && cardDate > toVal) matchDate = false;
                        }
                    }

                    if (matchCat && matchKw && matchDate) {
                        card.style.display = '';
                        shown++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (beritaFilteredEmptyBox) {
                    beritaFilteredEmptyBox.style.display = (newsCards.length > 0 && shown === 0) ? 'block' : 'none';
                }
            }

            function applyProductFilters() {
                isBeritaTabOpen = false;
                beritaSection.style.display = 'none';
                const keyword = navSearchInput ? navSearchInput.value.toLowerCase().trim() : '';
                let visibleCount = 0;

                productCards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    const cardText = card.textContent.toLowerCase();
                    const matchCategory = (activeCategory === 'all' || cardCategory === activeCategory);
                    const matchSearch = (keyword === '' || cardText.includes(keyword));

                    if (matchCategory && matchSearch) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                emptyBox.style.display = (visibleCount === 0) ? 'block' : 'none';
            }

            function showBerita() {
                isBeritaTabOpen = true;
                productCards.forEach(card => card.style.display = 'none');
                emptyBox.style.display = 'none';
                beritaSection.style.display = 'block';
                applyNewsFilters();
            }

            function renderBeritaCards(items) {
                if (!beritaListContainer) return;
                if (beritaCountBadge) {
                    beritaCountBadge.textContent = `(${items.length})`;
                }

                if (!Array.isArray(items) || items.length === 0) {
                    beritaListContainer.innerHTML = `
                        <div class="empty-state-box">
                            <i class="fa-regular fa-newspaper" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
                            <h3 style="color: #555;">Tidak ada berita terbaru</h3>
                        </div>
                    `;
                    if (beritaFilteredEmptyBox) beritaFilteredEmptyBox.style.display = 'none';
                    return;
                }

                beritaListContainer.innerHTML = items.map(item => `
                    <article class="berita-card"
                             data-news-category="${escapeHtml(item.cat_norm)}"
                             data-news-date="${escapeHtml(item.date_iso)}">
                        ${item.image_url ? `
                            <div class="berita-image">
                                <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" onerror="this.style.display='none'">
                            </div>
                        ` : ''}
                        <div class="berita-details">
                            <h3 class="berita-title">${escapeHtml(item.title)}</h3>
                            <div class="berita-meta">
                                ${item.published_at ? `<span class="berita-date"><i class="fa-regular fa-calendar"></i> ${escapeHtml(item.published_at)}</span>` : ''}
                                <span class="berita-cat-tag">${escapeHtml(item.category)}</span>
                            </div>
                            <hr class="divider">
                            <p class="berita-desc">${item.content_html || escapeHtml(item.content)}</p>
                        </div>
                    </article>
                `).join('');

                applyNewsFilters();
            }

            filterTabs.forEach(tab => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    allTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    activeCategory = this.getAttribute('data-filter');
                    applyProductFilters();
                });
            });

            document.querySelectorAll('#beritaCatPills .berita-cat-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('#beritaCatPills .berita-cat-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    activeNewsCategory = this.getAttribute('data-news-cat') || 'all';
                    applyNewsFilters();
                });
            });

            if (newsDateFrom) {
                newsDateFrom.addEventListener('change', function () {
                    setActivePreset('custom');
                    applyNewsFilters();
                });
            }

            if (newsDateTo) {
                newsDateTo.addEventListener('change', function () {
                    setActivePreset('custom');
                    applyNewsFilters();
                });
            }

            presetButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const range = this.getAttribute('data-range');
                    const now = new Date();
                    const todayIso = formatLocalIsoDate(now);

                    if (range === 'all') {
                        if (newsDateFrom) newsDateFrom.value = '';
                        if (newsDateTo) newsDateTo.value = '';
                        setActivePreset('all');
                    } else if (range === 'today') {
                        if (newsDateFrom) newsDateFrom.value = todayIso;
                        if (newsDateTo) newsDateTo.value = todayIso;
                        setActivePreset('today');
                    } else if (range === '7d') {
                        const past7 = new Date(now);
                        past7.setDate(now.getDate() - 6);
                        if (newsDateFrom) newsDateFrom.value = formatLocalIsoDate(past7);
                        if (newsDateTo) newsDateTo.value = todayIso;
                        setActivePreset('7d');
                    } else if (range === 'month') {
                        const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                        if (newsDateFrom) newsDateFrom.value = formatLocalIsoDate(firstOfMonth);
                        if (newsDateTo) newsDateTo.value = todayIso;
                        setActivePreset('month');
                    }

                    applyNewsFilters();
                });
            });

            if (resetNewsDateBtn) {
                resetNewsDateBtn.addEventListener('click', function () {
                    if (newsDateFrom) newsDateFrom.value = '';
                    if (newsDateTo) newsDateTo.value = '';
                    setActivePreset('all');
                    applyNewsFilters();
                });
            }

            if (navSearchInput) {
                navSearchInput.addEventListener('input', function () {
                    if (isBeritaTabOpen) {
                        applyNewsFilters();
                    } else {
                        applyProductFilters();
                    }
                });
            }

            if (beritaTab) {
                beritaTab.addEventListener('click', function (e) {
                    e.preventDefault();
                    allTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    showBerita();
                });
            }

            // Sinkronisasi berita baru dari database setiap 5 detik
            setInterval(function () {
                fetch('beranda.php?ajax_berita_realtime=1')
                    .then(res => res.json())
                    .then(data => {
                        if (!data || !Array.isArray(data.items)) return;
                        const sig = JSON.stringify(data.items);
                        const sigHash = String(sig.length) + ':' + (data.items[0]?.id ?? 0) + ':' + (data.items[0]?.title ?? '');
                        if (sigHash !== lastBeritaSignature) {
                            lastBeritaSignature = sigHash;
                            renderBeritaCards(data.items);
                        }
                    })
                    .catch(() => {});
            }, 5000);

            // ==========================================
            // NOTIFIKASI TAMBAH KE KERANJANG
            // ==========================================
            const notifContainer = document.getElementById('notif-container');
            const cartBadge = document.getElementById('cart-badge');

            function tampilkanNotifikasi(judul, pesan, isError = false) {
                const toast = document.createElement('div');
                toast.className = 'notif-toast' + (isError ? ' error' : '');
                toast.innerHTML = `
                    <div class="notif-icon">
                        <i class="fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>
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

            document.querySelectorAll('.form-add-cart').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (!isLoggedIn) {
                        window.location.href = 'login.php';
                        return;
                    }

                    const btn = form.querySelector('.btn-cart');
                    const isiAwalBtn = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menambahkan...';

                    const formData = new FormData(form);
                    formData.append('ajax', '1');

                    fetch('tambah_keranjang.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (cartBadge) {
                                cartBadge.textContent = data.total_items;
                                cartBadge.style.display = 'flex';
                                cartBadge.classList.add('bump');
                                setTimeout(() => cartBadge.classList.remove('bump'), 250);
                            }
                            tampilkanNotifikasi(
                                'Ditambahkan ke Keranjang!',
                                `<strong>${data.product_name}</strong> (${data.variant}) berhasil masuk ke keranjang.`
                            );
                        } else {
                            tampilkanNotifikasi(
                                'Gagal Menambahkan',
                                data.message || 'Produk gagal ditambahkan.',
                                true
                            );
                        }
                    })
                    .catch(() => {
                        form.submit();
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = isiAwalBtn;
                    });
                });
            });
        });
    </script>

</body>
</html>