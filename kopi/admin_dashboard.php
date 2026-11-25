<?php
// ==========================================
// SATPAM: PROTEKSI HALAMAN ADMIN
// ==========================================
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] != 'Admin') {
    header("Location: beranda.php");
    exit();
}

require_once 'config.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

$user_id = (int) $_SESSION['user_id'];

/**
 * Mengambil seluruh snapshot data dashboard dari database secara real time.
 */
function ambilSnapshotDashboardAdmin(PDO $pdo, int $userId): array {
    // 1. Hitung jumlah item di keranjang admin
    $totalCartItems = 0;
    try {
        $stmtCart = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = :uid");
        $stmtCart->execute(['uid' => $userId]);
        $totalCartItems = (int) $stmtCart->fetchColumn();
    } catch (PDOException $e) {
        $totalCartItems = 0;
    }

    // 2. Gambar banner promosi aktif & pengaturan durasi gulingkan (default 5 detik)
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
    } catch (PDOException $e) {
        $promosiAktif = [];
    }

    // 3. Daftar kategori langsung dari database
    $kategoriTabs = [];
    try {
        $stmtKat = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC");
        $kategoriTabs = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $kategoriTabs = [];
    }

    // 4. Data produk & stok dari database
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

    // 5. Data berita terbaru dari database (tabel 'berita' atau 'news')
    $beritaList = [];
    try {
        $checkBerita = $pdo->query("SHOW TABLES LIKE 'berita'");
        $checkNews   = $pdo->query("SHOW TABLES LIKE 'news'");

        if ($checkBerita && $checkBerita->rowCount() > 0) {
            $stmtBerita = $pdo->query("SELECT * FROM berita ORDER BY id DESC");
            $beritaList = $stmtBerita->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($checkNews && $checkNews->rowCount() > 0) {
            $stmtBerita = $pdo->query("SELECT * FROM news ORDER BY id DESC");
            $beritaList = $stmtBerita->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $beritaList = [];
    }

    return [
        'total_cart_items' => $totalCartItems,
        'promosi_aktif'    => $promosiAktif,
        'promo_slide_sec'  => $promoSlideSec,
        'promo_auto_slide' => $promoAutoSlide,
        'kategori_tabs'    => $kategoriTabs,
        'products'         => $products,
        'berita_list'      => $beritaList,
    ];
}

// Endpoint AJAX Real Time Polling
if (isset($_GET['ajax_live']) && $_GET['ajax_live'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $snapshot = ambilSnapshotDashboardAdmin($pdo, $user_id);
    $formattedProducts = array_map(function ($p) {
        $catRaw = (string)($p['category_name'] ?? '');
        $catNorm = strtolower(trim(preg_replace('/[\s\-]+/', ' ', $catRaw)));
        $stokVal = isset($p['current_stock']) ? (int)$p['current_stock'] : 0;
        return [
            'id'              => (int)$p['id'],
            'name'            => (string)$p['name'],
            'price'           => (float)$p['price'],
            'price_formatted' => formatRupiah($p['price']),
            'description'     => (string)($p['description'] ?? ''),
            'image_url'       => (string)($p['image_url'] ?? ''),
            'category_name'   => $catRaw,
            'category_norm'   => $catNorm,
            'current_stock'   => $stokVal,
        ];
    }, $snapshot['products']);

    $formattedBerita = array_map(function ($b, $idx) {
        return [
            'id'         => (int)($b['id'] ?? ($idx + 1)),
            'judul'      => (string)($b['judul'] ?? $b['title'] ?? 'Berita Filosofi Kopi'),
            'isi'        => (string)($b['isi'] ?? $b['konten'] ?? $b['content'] ?? $b['description'] ?? ''),
            'gambar'     => (string)($b['gambar'] ?? $b['image_url'] ?? $b['image'] ?? ''),
            'tanggal'    => (string)($b['tanggal'] ?? $b['published_at'] ?? $b['created_at'] ?? ''),
        ];
    }, $snapshot['berita_list'], array_keys($snapshot['berita_list']));

    $formattedCats = array_map(function ($k) {
        $name = (string)$k['name'];
        return [
            'id'         => (int)$k['id'],
            'name'       => $name,
            'filter_key' => strtolower(trim(preg_replace('/[\s\-]+/', ' ', $name))),
            'slug_key'   => strtolower(trim(preg_replace('/[\s\-]+/', '-', $name))),
        ];
    }, $snapshot['kategori_tabs']);

    $signature = md5(json_encode([
        $snapshot['total_cart_items'],
        $snapshot['promosi_aktif'],
        $snapshot['promo_slide_sec'],
        $snapshot['promo_auto_slide'],
        $formattedCats,
        $formattedProducts,
        $formattedBerita,
    ]));

    echo json_encode([
        'signature'        => $signature,
        'total_cart_items' => $snapshot['total_cart_items'],
        'promosi_aktif'    => $snapshot['promosi_aktif'],
        'promo_slide_sec'  => $snapshot['promo_slide_sec'],
        'promo_auto_slide' => $snapshot['promo_auto_slide'],
        'kategori_tabs'    => $formattedCats,
        'products'         => $formattedProducts,
        'berita_list'      => $formattedBerita,
        'timestamp'        => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$pageTitle = "Dashboard Admin - Filosofi Kopi";
$snapshot  = ambilSnapshotDashboardAdmin($pdo, $user_id);

$totalCartItems = $snapshot['total_cart_items'];
$promosiAktif   = $snapshot['promosi_aktif'];
$promoSlideSec  = $snapshot['promo_slide_sec'];
$promoAutoSlide = $snapshot['promo_auto_slide'];
$kategoriTabs   = $snapshot['kategori_tabs'];
$products       = $snapshot['products'];
$beritaList     = $snapshot['berita_list'];
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
    <link rel="stylesheet" href="admin_dashboard.css">

    <!-- STYLE SLIDER PROMOSI, NOTIFIKASI, BADGE KERANJANG & INDIKATOR LIVE -->
    <style>
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

        .admin-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #3b2316;
            background: rgba(255, 255, 255, 0.85);
            padding: 6px 12px;
            border-radius: 999px;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #16a34a;
            display: inline-block;
            animation: livePulse 1.6s infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(1.25); }
        }

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
    </style>
</head>
<body>

    <!-- KONTAINER NOTIFIKASI POPUP -->
    <div id="notif-container" class="notif-container"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
            <a href="admin_dashboard.php" class="nav-brand">Filosofi Kopi</a>
        </div>

        <div class="nav-center">
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="navSearchInput" placeholder="Cari kopi, produk, atau layanan...">
            </div>
        </div>

        <div class="nav-right">
            <a href="halaman_keranjang.php" class="cart-link-wrapper" title="Keranjang">
                <i class="fa-solid fa-cart-shopping"></i>
                <span id="cart-badge" class="cart-badge" style="display: <?= $totalCartItems > 0 ? 'flex' : 'none' ?>;">
                    <?= $totalCartItems ?>
                </span>
            </a>
            <a href="pesan.php"><i class="fa-solid fa-envelope"></i></a>
            <a href="profile.php"><i class="fa-solid fa-user"></i></a>
        </div>
    </nav>

    <!-- TOMBOL DASHBOARD ADMIN & INDIKATOR REAL TIME -->
    <div class="admin-bar">
        <a href="dashboard_admin.php" class="btn-admin">Dashboard Admin</a>
        <span class="live-status-pill" id="liveStatusPill" title="Sinkronisasi otomatis dengan database">
            <span class="live-dot"></span>
            <span id="liveStatusText">Live Sync • <?= date('H:i:s') ?></span>
        </span>
    </div>

    <!-- HERO BANNER -->
    <section class="hero-banner" id="heroBanner"></section>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        
        <!-- BREADCRUMB & NAVIGASI KATEGORI DINAMIS DARI DATABASE -->
        <div class="breadcrumb-bar" id="breadcrumbBar"></div>

        <!-- DAFTAR KARTU PRODUK DARI DATABASE -->
        <div id="productListContainer"></div>

        <!-- PESAN OTOMATIS JIKA PRODUK TIDAK ADA -->
        <div id="empty-product-box" class="empty-state-box" style="display: none;">
            <i class="fa-solid fa-mug-hot" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
            <h3 style="color: #555;">Produk tidak ada</h3>
        </div>

        <!-- BAGIAN BERITA TERBARU -->
        <section id="berita-section" style="display: none;"></section>

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
                    <a href="#" class="fb"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="ig"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <!-- SCRIPT REAL TIME POLLING, SLIDER PROMOSI, FILTER & NOTIFIKASI -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const heroBanner          = document.getElementById('heroBanner');
            const breadcrumbBar       = document.getElementById('breadcrumbBar');
            const productListContainer= document.getElementById('productListContainer');
            const emptyBox            = document.getElementById('empty-product-box');
            const beritaSection       = document.getElementById('berita-section');
            const navSearchInput      = document.getElementById('navSearchInput');
            const notifContainer      = document.getElementById('notif-container');
            const cartBadge           = document.getElementById('cart-badge');
            const liveStatusText      = document.getElementById('liveStatusText');

            let activeCategory = 'all';
            let activeView     = 'products'; // 'products' | 'berita'
            let lastSignature  = '';
            let currentSlide   = 0;
            let slideTimer     = null;
            let livePollTimer  = null;

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function stopSliderTimer() {
                if (slideTimer) {
                    clearInterval(slideTimer);
                    slideTimer = null;
                }
            }

            function renderHeroBanner(data) {
                stopSliderTimer();
                const promos = Array.isArray(data.promosi_aktif) ? data.promosi_aktif : [];
                const autoSlide = Number(data.promo_auto_slide) === 1;
                const delayMs = Math.max(1, Number(data.promo_slide_sec) || 5) * 1000;

                if (promos.length > 0) {
                    currentSlide = currentSlide % promos.length;
                    const slidesHtml = promos.map(p => `
                        <div class="hero-slide-item">
                            <img src="${escapeHtml(p.image_url)}"
                                 alt="Banner Promosi Filosofi Kopi"
                                 onerror="this.src='https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=1280&auto=format&fit=crop'">
                        </div>
                    `).join('');

                    const dotsHtml = promos.length > 1 ? `
                        <div class="hero-slider-dots" id="heroSliderDots">
                            ${promos.map((_, idx) => `
                                <button type="button" class="hero-dot ${idx === currentSlide ? 'active' : ''}" data-slide="${idx}" aria-label="Banner ${idx + 1}"></button>
                            `).join('')}
                        </div>
                    ` : '';

                    heroBanner.innerHTML = `
                        <div class="hero-slider-viewport">
                            <div class="hero-slider-track" id="heroSliderTrack" style="transform: translateX(-${currentSlide * 100}%);">
                                ${slidesHtml}
                            </div>
                            ${dotsHtml}
                        </div>
                    `;

                    const track = document.getElementById('heroSliderTrack');
                    const dots = document.querySelectorAll('#heroSliderDots .hero-dot');

                    function goToSlide(idx) {
                        currentSlide = (idx + promos.length) % promos.length;
                        if (track) track.style.transform = `translateX(-${currentSlide * 100}%)`;
                        dots.forEach((d, i) => d.classList.toggle('active', i === currentSlide));
                    }

                    if (promos.length > 1 && autoSlide) {
                        slideTimer = setInterval(() => goToSlide(currentSlide + 1), delayMs);
                    }

                    dots.forEach(dot => {
                        dot.addEventListener('click', function () {
                            stopSliderTimer();
                            goToSlide(parseInt(this.getAttribute('data-slide'), 10));
                            if (promos.length > 1 && autoSlide) {
                                slideTimer = setInterval(() => goToSlide(currentSlide + 1), delayMs);
                            }
                        });
                    });
                } else {
                    heroBanner.innerHTML = `
                        <div class="hero-content">
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=600&auto=format&fit=crop" alt="Kenikmatan Kopi">
                            </div>
                            <div class="hero-text">
                                <h1>Kenikmatan Autentik di Setiap Cangkir Filosofi Kopi</h1>
                                <p>Secangkir kopi hangat dari Filosofi Kopi Coffee bukan sekadar pelepasan penat atau teman di kala senggang, melainkan sebuah mahakarya rasa yang diracik khusus dari biji kopi pilihan berkualitas tinggi untuk membangkitkan semangat, mempertajam fokus, dan menemani setiap langkah perjalanan produktifmu sepanjang hari.</p>
                            </div>
                        </div>
                    `;
                }
            }

            function renderBreadcrumbs(categories) {
                const cats = Array.isArray(categories) ? categories : [];
                const catLinksHtml = cats.map(k => {
                    const isAct = (activeView === 'products' && activeCategory === k.filter_key);
                    return `
                        <a href="?kategori=${encodeURIComponent(k.slug_key)}"
                           class="breadcrumb-item ${isAct ? 'active' : ''}"
                           data-filter="${escapeHtml(k.filter_key)}">
                            ${escapeHtml(k.name)}
                        </a>
                    `;
                }).join('');

                const berandaActive = (activeView === 'products' && activeCategory === 'all') ? 'active' : '';
                const beritaActive  = (activeView === 'berita') ? 'active' : '';

                breadcrumbBar.innerHTML = `
                    <a href="admin_dashboard.php" class="breadcrumb-item ${berandaActive}" data-filter="all">Beranda</a>
                    ${catLinksHtml}
                    <a href="?tab=berita" class="breadcrumb-item ${beritaActive}" data-tab="berita">Berita</a>
                `;

                breadcrumbBar.querySelectorAll('.breadcrumb-item[data-filter]').forEach(tab => {
                    tab.addEventListener('click', function (e) {
                        e.preventDefault();
                        activeView = 'products';
                        activeCategory = this.getAttribute('data-filter');
                        breadcrumbBar.querySelectorAll('.breadcrumb-item').forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        applyFilters();
                    });
                });

                const beritaTab = breadcrumbBar.querySelector('.breadcrumb-item[data-tab="berita"]');
                if (beritaTab) {
                    beritaTab.addEventListener('click', function (e) {
                        e.preventDefault();
                        activeView = 'berita';
                        breadcrumbBar.querySelectorAll('.breadcrumb-item').forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        applyFilters();
                    });
                }
            }

            function renderProducts(products) {
                const list = Array.isArray(products) ? products : [];
                if (list.length === 0) {
                    productListContainer.innerHTML = '';
                    return;
                }

                productListContainer.innerHTML = list.map(item => {
                    const imgSrc = item.image_url ? escapeHtml(item.image_url) : 'https://via.placeholder.com/250?text=No+Image';
                    const desc   = item.description ? escapeHtml(item.description) : 'Deskripsi belum tersedia untuk produk ini.';
                    const disabledAttr = item.current_stock <= 0 ? 'disabled' : '';

                    return `
                        <article class="product-card" data-category="${escapeHtml(item.category_norm)}">
                            <div class="product-image">
                                <img src="${imgSrc}" alt="${escapeHtml(item.name)}" onerror="this.src='https://via.placeholder.com/250?text=No+Image'">
                            </div>
                            <div class="product-details">
                                <h2 class="product-title">${escapeHtml(item.name)}</h2>
                                <hr class="divider">
                                <p class="product-desc">${desc}</p>
                                <form action="tambah_keranjang.php" method="POST" class="form-add-cart">
                                    <input type="hidden" name="product_id" value="${item.id}">
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
                                        <button type="submit" class="btn-cart" ${disabledAttr}>
                                            <i class="fa-solid fa-cart-shopping"></i> Tambah Keranjang
                                        </button>
                                        <div class="price">${escapeHtml(item.price_formatted)}</div>
                                    </div>
                                </form>
                            </div>
                            <a href="detail_produk.php?id=${item.id}" class="detail-link">Klik Detail </a>
                        </article>
                    `;
                }).join('');

                bindCartForms();
            }

            function renderBerita(beritaList) {
                const list = Array.isArray(beritaList) ? beritaList : [];
                if (list.length === 0) {
                    beritaSection.innerHTML = `
                        <div class="empty-state-box">
                            <i class="fa-regular fa-newspaper" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
                            <h3 style="color: #555;">Tidak ada berita terbaru</h3>
                        </div>
                    `;
                    return;
                }

                const itemsHtml = list.map(b => {
                    const imgHtml = b.gambar
                        ? `<div class="berita-image"><img src="${escapeHtml(b.gambar)}" alt="${escapeHtml(b.judul)}"></div>`
                        : '';
                    const dateHtml = b.tanggal
                        ? `<span class="berita-date"><i class="fa-regular fa-calendar"></i> ${escapeHtml(b.tanggal)}</span>`
                        : '';
                    const bodyHtml = escapeHtml(b.isi).replace(/\r?\n/g, '<br>');

                    return `
                        <article class="berita-card">
                            ${imgHtml}
                            <div class="berita-details">
                                <h3 class="berita-title">${escapeHtml(b.judul)}</h3>
                                ${dateHtml}
                                <hr class="divider">
                                <p class="berita-desc">${bodyHtml}</p>
                            </div>
                        </article>
                    `;
                }).join('');

                beritaSection.innerHTML = `<h2 class="berita-heading">Berita Terbaru</h2>${itemsHtml}`;
            }

            function applyFilters() {
                const cards = productListContainer.querySelectorAll('.product-card');
                if (activeView === 'berita') {
                    productListContainer.style.display = 'none';
                    emptyBox.style.display = 'none';
                    beritaSection.style.display = 'block';
                    return;
                }

                beritaSection.style.display = 'none';
                productListContainer.style.display = '';

                const keyword = navSearchInput ? navSearchInput.value.toLowerCase().trim() : '';
                let visibleCount = 0;

                cards.forEach(card => {
                    const cardCat  = card.getAttribute('data-category');
                    const cardText = card.textContent.toLowerCase();
                    const matchCat = (activeCategory === 'all' || cardCat === activeCategory);
                    const matchQ   = (keyword === '' || cardText.includes(keyword));

                    if (matchCat && matchQ) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                emptyBox.style.display = (visibleCount === 0) ? 'block' : 'none';
            }

            function updateCartBadge(count, animate = false) {
                const n = parseInt(count, 10) || 0;
                if (!cartBadge) return;
                cartBadge.textContent = n;
                cartBadge.style.display = n > 0 ? 'flex' : 'none';
                if (animate && n > 0) {
                    cartBadge.classList.add('bump');
                    setTimeout(() => cartBadge.classList.remove('bump'), 250);
                }
            }

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

            function bindCartForms() {
                productListContainer.querySelectorAll('.form-add-cart').forEach(form => {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();

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
                                lastSignature = '';
                                updateCartBadge(data.total_items, true);
                                tampilkanNotifikasi(
                                    'Ditambahkan ke Keranjang!',
                                    `<strong>${escapeHtml(data.product_name)}</strong> (${escapeHtml(data.variant)}) berhasil masuk ke keranjang.`
                                );
                            } else {
                                tampilkanNotifikasi(
                                    'Gagal Menambahkan',
                                    escapeHtml(data.message || 'Produk gagal ditambahkan.'),
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
            }

            function applyLiveSnapshot(data, forceRender = false) {
                if (!forceRender && data.signature && data.signature === lastSignature) {
                    if (liveStatusText && data.timestamp) {
                        liveStatusText.textContent = `Live Sync • ${data.timestamp}`;
                    }
                    return;
                }

                lastSignature = data.signature || '';
                renderHeroBanner(data);
                renderBreadcrumbs(data.kategori_tabs);
                renderProducts(data.products);
                renderBerita(data.berita_list);
                updateCartBadge(data.total_cart_items, false);
                applyFilters();

                if (liveStatusText && data.timestamp) {
                    liveStatusText.textContent = `Live Sync • ${data.timestamp}`;
                }
            }

            function pollLiveDatabase() {
                // Hindari me-render ulang DOM saat user sedang berinteraksi dengan input/select
                const activeEl = document.activeElement;
                if (activeEl && (activeEl.tagName === 'SELECT' || activeEl === navSearchInput)) {
                    return;
                }

                fetch('admin_dashboard.php?ajax_live=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                })
                .then(res => res.ok ? res.json() : null)
                .then(data => {
                    if (data) {
                        applyLiveSnapshot(data, false);
                    }
                })
                .catch(() => {});
            }

            // Render awal langsung dari data PHP
            applyLiveSnapshot({
                signature: '',
                total_cart_items: <?= (int)$totalCartItems ?>,
                promosi_aktif: <?= json_encode($promosiAktif, JSON_UNESCAPED_UNICODE) ?>,
                promo_slide_sec: <?= (int)$promoSlideSec ?>,
                promo_auto_slide: <?= (int)$promoAutoSlide ?>,
                kategori_tabs: <?= json_encode(array_map(function ($k) {
                    $name = (string)$k['name'];
                    return [
                        'id'         => (int)$k['id'],
                        'name'       => $name,
                        'filter_key' => strtolower(trim(preg_replace('/[\s\-]+/', ' ', $name))),
                        'slug_key'   => strtolower(trim(preg_replace('/[\s\-]+/', '-', $name))),
                    ];
                }, $kategoriTabs), JSON_UNESCAPED_UNICODE) ?>,
                products: <?= json_encode(array_map(function ($p) {
                    $catRaw = (string)($p['category_name'] ?? '');
                    return [
                        'id'              => (int)$p['id'],
                        'name'            => (string)$p['name'],
                        'price'           => (float)$p['price'],
                        'price_formatted' => formatRupiah($p['price']),
                        'description'     => (string)($p['description'] ?? ''),
                        'image_url'       => (string)($p['image_url'] ?? ''),
                        'category_name'   => $catRaw,
                        'category_norm'   => strtolower(trim(preg_replace('/[\s\-]+/', ' ', $catRaw))),
                        'current_stock'   => isset($p['current_stock']) ? (int)$p['current_stock'] : 0,
                    ];
                }, $products), JSON_UNESCAPED_UNICODE) ?>,
                berita_list: <?= json_encode(array_map(function ($b, $idx) {
                    return [
                        'id'      => (int)($b['id'] ?? ($idx + 1)),
                        'judul'   => (string)($b['judul'] ?? $b['title'] ?? 'Berita Filosofi Kopi'),
                        'isi'     => (string)($b['isi'] ?? $b['konten'] ?? $b['content'] ?? $b['description'] ?? ''),
                        'gambar'  => (string)($b['gambar'] ?? $b['image_url'] ?? $b['image'] ?? ''),
                        'tanggal' => (string)($b['tanggal'] ?? $b['published_at'] ?? $b['created_at'] ?? ''),
                    ];
                }, $beritaList, array_keys($beritaList)), JSON_UNESCAPED_UNICODE) ?>,
                timestamp: '<?= date('H:i:s') ?>'
            }, true);

            if (navSearchInput) {
                navSearchInput.addEventListener('input', function () {
                    if (activeView === 'berita') {
                        activeView = 'products';
                        activeCategory = 'all';
                        breadcrumbBar.querySelectorAll('.breadcrumb-item').forEach(t => t.classList.remove('active'));
                        const berandaTab = breadcrumbBar.querySelector('.breadcrumb-item[data-filter="all"]');
                        if (berandaTab) berandaTab.classList.add('active');
                    }
                    applyFilters();
                });
            }

            // Mulai polling real time tiap 3 detik
            livePollTimer = setInterval(pollLiveDatabase, 3000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    pollLiveDatabase();
                }
            });
        });
    </script>

</body>
</html>