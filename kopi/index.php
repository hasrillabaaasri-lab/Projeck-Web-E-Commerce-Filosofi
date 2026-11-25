<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// index.php
$pageTitle  = "Filosofi Kopi - Kedai Kopi Terbaik";
$isLoggedIn = isset($_SESSION['user_id']);

// Muat konfigurasi database jika tersedia
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Pastikan objek $pdo tersedia dan mengarah ke db_filkop
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && ($conn instanceof PDO)) {
        $pdo = $conn;
    } else {
        try {
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
        } catch (PDOException $e) {
            $pdo = null;
        }
    }
}

$sectionTitle = "Produk Terlaris Minggu Ini";
$sectionDesc  = "Pilihan favorit para penikmat kopi yang paling dicari minggu ini.\nRacikan terbaik yang paling diminati dan siap menemani harimu.\nDeretan menu juara yang paling laris dan wajib kamu coba sekarang.\nCita rasa pilihan yang paling digemari pelanggan setia kami.";
$produkTerlarisList = [];

if ($pdo instanceof PDO) {
    try {
        // 1. Ambil judul & deskripsi kustom dari tabel `beranda_terlaris_settings`
        $cekSet = $pdo->query("SHOW TABLES LIKE 'beranda_terlaris_settings'");
        if ($cekSet && $cekSet->rowCount() > 0) {
            $setRow = $pdo->query("SELECT section_title, section_description FROM beranda_terlaris_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($setRow) {
                if (isset($setRow['section_title']) && trim($setRow['section_title']) !== '') {
                    $sectionTitle = $setRow['section_title'];
                }
                if (isset($setRow['section_description']) && trim($setRow['section_description']) !== '') {
                    $sectionDesc = $setRow['section_description'];
                }
            }
        }

        // 2. Ambil daftar produk yang ditambahkan Admin dari `produk_terlaris_beranda`
        $cekTbl = $pdo->query("SHOW TABLES LIKE 'produk_terlaris_beranda'");
        if ($cekTbl && $cekTbl->rowCount() > 0) {
            $stmtPtb = $pdo->query("
                SELECT p.id, p.name, p.price, p.image_url,
                       COALESCE(ROUND(AVG(t.rating)), 5) AS avg_rating
                FROM produk_terlaris_beranda ptb
                INNER JOIN products p ON p.id = ptb.product_id
                LEFT JOIN testimonials t ON t.product_id = p.id
                GROUP BY p.id, p.name, p.price, p.image_url, ptb.urutan
                ORDER BY ptb.urutan ASC, ptb.id ASC
            ");
            $produkTerlarisList = $stmtPtb->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Fallback otomatis: jika belum dipilih manual, tampilkan Top 4 penjualan tertinggi
        if (count($produkTerlarisList) === 0) {
            $stmtTop = $pdo->query("
                SELECT p.id, p.name, p.price, p.image_url,
                       COALESCE(ROUND(AVG(t.rating)), 5) AS avg_rating,
                       COALESCE(SUM(CASE 
                           WHEN o.id IS NOT NULL 
                                AND o.status NOT IN ('Batal', 'Dikembalikan', 'Dihentikan')
                           THEN oi.quantity ELSE 0 
                       END), 0) AS total_terjual
                FROM products p
                LEFT JOIN order_items oi ON oi.product_id = p.id
                LEFT JOIN orders o ON o.id = oi.order_id
                LEFT JOIN testimonials t ON t.product_id = p.id
                GROUP BY p.id, p.name, p.price, p.image_url
                ORDER BY total_terjual DESC, p.id ASC
                LIMIT 4
            ");
            $produkTerlarisList = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $produkTerlarisList = [];
    }
}

$defaultCoffeeImg = "https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?q=80&w=500&auto=format&fit=crop";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts (Dynalight & Poppins) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dynalight&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Memanggil file CSS terpisah -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- HEADER / HERO SECTION -->
    <header>
        <nav class="navbar">
            <div class="nav-brand">Filosofi Kopi</div>
            <ul class="nav-links">
                <li><a href="halaman_berita.php">Berita</a></li>
                <li><a href="halaman_tentangkami.php">Tentang Kami</a></li>
                <li><a href="hubungin.php">Hubungi</a></li>
                <li>
                    <a href="<?php echo $isLoggedIn ? 'profile.php' : 'login.php'; ?>" title="<?php echo $isLoggedIn ? 'Profil Saya' : 'Masuk'; ?>">
                        <i class="fa-regular fa-user"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="hero">
            <img src="image 24.png" alt="Logo Filosofi Kopi" class="hero-logo" onerror="this.style.display='none'">
            
            <h1>Kedai kopi terbaik, Juara Kopi Nya</h1>
            <p>Kualitas Terbaik, Rasa Paling Asik, Nikmat Sejati di Setiap Cangkir–Satu Tegukan, Sejuta Cerita, Racikan Istimewa untuk Penikmat Jiwa, Kopi Pilihan, Teman Setia Hari-hari, Kelezatan Autentik dari Biji Pilihan</p>
            
            <a href="<?php echo $isLoggedIn ? 'beranda.php' : 'login.php'; ?>" class="btn-masuk">
                <?php echo $isLoggedIn ? 'Lihat Menu' : 'Masuk'; ?>
            </a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main id="produk-terlaris-minggu-ini">
        <h2 class="section-title"><?php echo htmlspecialchars($sectionTitle); ?></h2>
        
        <p class="section-subtitle">
            <?php echo nl2br(htmlspecialchars($sectionDesc)); ?>
        </p>

        <div class="product-grid">
            <?php if (count($produkTerlarisList) > 0): ?>
                <?php foreach ($produkTerlarisList as $item): ?>
                    <?php
                        $rating     = max(1, min(5, (int)($item['avg_rating'] ?? 5)));
                        $imgSrc     = !empty($item['image_url']) ? $item['image_url'] : $defaultCoffeeImg;
                        $targetLink = $isLoggedIn ? ('detail_produk.php?id=' . (int)$item['id']) : 'login.php';
                    ?>
                    <article class="product-card" style="cursor: pointer;" onclick="window.location.href='<?php echo htmlspecialchars($targetLink, ENT_QUOTES); ?>'">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($defaultCoffeeImg, ENT_QUOTES); ?>';">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <div class="stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <?php if ($s <= $rating): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php else: ?>
                                    <i class="fa-regular fa-star empty"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <p class="price">Rp<?php echo number_format((float)$item['price'], 0, ',', '.'); ?></p>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="product-card">
                    <img src="<?php echo htmlspecialchars($defaultCoffeeImg); ?>" alt="Espresso">
                    <h3>Espresso</h3>
                    <div class="stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-regular fa-star empty"></i>
                    </div>
                    <p class="price">Rp15.000</p>
                </article>
            <?php endif; ?>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo Filosofi Kopi" class="footer-logo" onerror="this.style.display='none'">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>

            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f fa-facebook"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram fa-instagram"></i></a>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://filosofikopi.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>