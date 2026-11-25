<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// halaman_berita.php
$pageTitle  = "Berita & Cerita Kopi - Filosofi Kopi";
$isLoggedIn = isset($_SESSION['user_id']);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

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

$searchQuery   = trim($_GET['q'] ?? '');
$activeTag     = trim($_GET['kategori'] ?? 'Semua');
$beritaList    = [];
$dbCats        = [];
$bgSlides      = [];
$slideInterval = 5;
$isRollingBg   = 1;

if ($pdo instanceof PDO) {
    try {
        // Pastikan tabel `news_categories` tersedia
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `news_categories` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(80) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // Ambil daftar kategori dari tabel `news_categories`
        $catStmt = $pdo->query("SELECT `name` FROM `news_categories` ORDER BY `id` ASC");
        $dbCats  = $catStmt->fetchAll(PDO::FETCH_COLUMN);

        // Ambil pengaturan background & durasi ganti jika tabel tersedia
        $chkBg = $pdo->query("SHOW TABLES LIKE 'news_hero_settings'");
        if ($chkBg && $chkBg->rowCount() > 0) {
            $bgRows = $pdo->query("SELECT `image_url`, `slide_interval`, `is_rolling` FROM `news_hero_settings` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
            if (count($bgRows) > 0) {
                $bgSlides      = $bgRows;
                $slideInterval = max(1, (int)$bgRows[0]['slide_interval']);
                $isRollingBg   = (int)$bgRows[0]['is_rolling'];
            }
        }

        // Siapkan query ke tabel `news` (join `users` untuk nama penulis jika tersedia)
        $where  = [];
        $params = [];

        if ($searchQuery !== '') {
            $where[]  = "(n.`title` LIKE ? OR n.`content` LIKE ? OR n.`category` LIKE ?)";
            $params[] = '%' . $searchQuery . '%';
            $params[] = '%' . $searchQuery . '%';
            $params[] = '%' . $searchQuery . '%';
        }

        if ($activeTag !== '' && $activeTag !== 'Semua') {
            $where[]  = "COALESCE(NULLIF(n.`category`, ''), 'Berita Kedai') = ?";
            $params[] = $activeTag;
        }

        $whereSql = count($where) > 0 ? (" WHERE " . implode(" AND ", $where)) : "";
        $sql = "
            SELECT n.`id`, n.`author_id`, n.`title`,
                   COALESCE(NULLIF(n.`category`, ''), 'Berita Kedai') AS `category`,
                   n.`content`, n.`image_url`, n.`published_at`,
                   COALESCE(NULLIF(u.`name`, ''), 'Filosofi Kopi') AS `author_name`
            FROM `news` n
            LEFT JOIN `users` u ON u.`id` = n.`author_id`
            {$whereSql}
            ORDER BY n.`published_at` DESC, n.`id` DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $rawContent = (string)($r['content'] ?? '');
            $plainText  = trim(strip_tags($rawContent));
            $summary    = mb_strlen($plainText) > 135 ? (mb_substr($plainText, 0, 135) . '...') : $plainText;
            $rawImg     = trim((string)($r['image_url'] ?? ''));
            $rowCat     = (string)($r['category'] ?? 'Berita Kedai');

            if (!in_array($rowCat, $dbCats, true)) {
                $dbCats[] = $rowCat;
            }

            $beritaList[] = [
                'id'         => (int)($r['id'] ?? 0),
                'title'      => (string)($r['title'] ?? 'Berita Filosofi Kopi'),
                'category'   => $rowCat,
                'author'     => (string)($r['author_name'] ?? 'Filosofi Kopi'),
                'created_at' => (string)($r['published_at'] ?? date('Y-m-d H:i:s')),
                'image_url'  => $rawImg !== '' ? $rawImg : 'uploads/1792743004_image 20.png',
                'summary'    => $summary !== '' ? $summary : 'Baca informasi terbaru dari Filosofi Kopi.',
                'content'    => $rawContent !== '' ? $rawContent : $summary,
            ];
        }
    } catch (PDOException $e) {
        $beritaList = [];
    }
}

$defaultCats = ['Menu Baru', 'Edukasi Kopi', 'Promo', 'Berita Kedai'];
$mergedCats  = array_values(array_unique(array_merge($defaultCats, $dbCats)));
if ($activeTag !== '' && $activeTag !== 'Semua' && !in_array($activeTag, $mergedCats, true)) {
    $mergedCats[] = $activeTag;
}
$categories     = array_merge(['Semua'], $mergedCats);
$defaultNewsImg = "https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?q=80&w=600&auto=format&fit=crop";
$hasCustomBg    = count($bgSlides) > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dynalight&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="halaman_berita.css">
</head>
<body class="berita-page">

    <!-- HEADER / NAVBAR -->
    <header class="berita-header <?php echo $hasCustomBg ? 'has-rolling-bg' : ''; ?>">
        <?php if ($hasCustomBg): ?>
            <div class="berita-bg-track" id="beritaBgTrack" aria-hidden="true">
                <?php foreach ($bgSlides as $bg): ?>
                    <div class="berita-bg-slide" style="background-image: url('<?php echo htmlspecialchars($bg['image_url'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                <?php endforeach; ?>
            </div>
            <div class="berita-bg-overlay" aria-hidden="true"></div>
        <?php endif; ?>

        <div class="berita-header-inner">
            <nav class="berita-navbar">
                <a href="index.php" class="berita-brand">Filosofi Kopi</a>
                <ul class="berita-nav-links">
                    <li><a href="halaman_berita.php" class="active-link">Berita</a></li>
                    <li><a href="halaman_tentangkami.php">Tentang Kami</a></li>
                    <li><a href="hubungin.php">Hubungi</a></li>
                    <li>
                        <a href="<?php echo $isLoggedIn ? 'profile.php' : 'login.php'; ?>" class="berita-user-icon" title="<?php echo $isLoggedIn ? 'Profil Saya' : 'Masuk'; ?>">
                            <i class="fa-regular fa-user"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="berita-hero">
                <h1>Berita Filosofi Kopi</h1>
                <p>Informasi menu terbaru, cerita di balik seduhan, promo menarik, dan kabar terkini dari kedai kami.</p>
            </div>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="berita-main">
        <!-- TOOLBAR PENCARIAN & KATEGORI -->
        <section class="berita-toolbar">
            <div class="berita-tags">
                <?php foreach ($categories as $cat): ?>
                    <?php
                        $qs = ['kategori' => $cat];
                        if ($searchQuery !== '') {
                            $qs['q'] = $searchQuery;
                        }
                    ?>
                    <a href="halaman_berita.php?<?php echo htmlspecialchars(http_build_query($qs)); ?>"
                       class="berita-tag <?php echo ($activeTag === $cat) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form action="halaman_berita.php" method="GET" class="berita-search-form">
                <?php if ($activeTag !== '' && $activeTag !== 'Semua'): ?>
                    <input type="hidden" name="kategori" value="<?php echo htmlspecialchars($activeTag); ?>">
                <?php endif; ?>
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>"
                       placeholder="Cari judul atau topik berita..." aria-label="Cari berita">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
            </form>
        </section>

        <!-- DAFTAR KARTU BERITA -->
        <?php if (count($beritaList) > 0): ?>
            <section class="berita-grid">
                <?php foreach ($beritaList as $idx => $item): ?>
                    <?php
                        $imgSrc = !empty($item['image_url']) ? $item['image_url'] : $defaultNewsImg;
                        $tglFmt = !empty($item['created_at']) ? date('d M Y', strtotime($item['created_at'])) : date('d M Y');
                    ?>
                    <article class="berita-card">
                        <div class="berita-img-wrap">
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                 onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($defaultNewsImg, ENT_QUOTES); ?>';">
                            <span class="berita-cat-pill"><?php echo htmlspecialchars($item['category']); ?></span>
                        </div>
                        <div class="berita-body">
                            <div class="berita-meta">
                                <span><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($tglFmt); ?></span>
                                <span><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($item['author']); ?></span>
                            </div>
                            <h2><?php echo htmlspecialchars($item['title']); ?></h2>
                            <p class="berita-excerpt"><?php echo htmlspecialchars($item['summary']); ?></p>
                            <button type="button"
                                    class="btn-baca-berita"
                                    data-title="<?php echo htmlspecialchars($item['title'], ENT_QUOTES); ?>"
                                    data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                                    data-date="<?php echo htmlspecialchars($tglFmt, ENT_QUOTES); ?>"
                                    data-author="<?php echo htmlspecialchars($item['author'], ENT_QUOTES); ?>"
                                    data-img="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES); ?>"
                                    data-content="<?php echo htmlspecialchars($item['content'], ENT_QUOTES); ?>"
                                    onclick="openBeritaModal(this)">
                                Baca Selengkapnya <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <div class="berita-empty">
                <i class="fa-regular fa-folder-open"></i>
                <h3>Berita tidak ditemukan</h3>
                <p>Belum ada berita yang cocok dengan kata kunci atau kategori yang dipilih.</p>
                <a href="halaman_berita.php" class="btn-reset-berita">Tampilkan Semua Berita</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL BACA SELENGKAPNYA -->
    <div id="beritaModal" class="berita-modal" role="dialog" aria-modal="true" aria-labelledby="modalBeritaTitle">
        <div class="berita-modal-backdrop" onclick="closeBeritaModal()"></div>
        <div class="berita-modal-box">
            <button type="button" class="berita-modal-close" onclick="closeBeritaModal()" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <img id="modalBeritaImg" src="" alt="" class="berita-modal-img">
            <div class="berita-modal-content">
                <div class="berita-modal-meta">
                    <span id="modalBeritaCat" class="berita-cat-inline"></span>
                    <span id="modalBeritaDate"></span>
                    <span id="modalBeritaAuthor"></span>
                </div>
                <h2 id="modalBeritaTitle"></h2>
                <div id="modalBeritaBody" class="berita-modal-text"></div>
            </div>
        </div>
    </div>

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
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f fa-facebook"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram fa-instagram"></i></a>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://filosofikopi.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <script>
        (function initRollingBg() {
            const track = document.getElementById('beritaBgTrack');
            const isRolling = <?php echo (int)$isRollingBg; ?> === 1;
            const intervalMs = <?php echo (int)$slideInterval; ?> * 1000;
            if (!track || !isRolling) return;

            const totalSlides = track.children.length;
            if (totalSlides <= 1) return;

            let currentIdx = 0;
            setInterval(() => {
                currentIdx = (currentIdx + 1) % totalSlides;
                track.style.transform = `translateX(-${currentIdx * 100}%)`;
            }, intervalMs);
        })();

        function openBeritaModal(btn) {
            const modal = document.getElementById('beritaModal');
            document.getElementById('modalBeritaTitle').textContent  = btn.getAttribute('data-title') || '';
            document.getElementById('modalBeritaCat').textContent    = btn.getAttribute('data-category') || '';
            document.getElementById('modalBeritaDate').innerHTML   = '<i class="fa-regular fa-calendar"></i> ' + (btn.getAttribute('data-date') || '');
            document.getElementById('modalBeritaAuthor').innerHTML = '<i class="fa-regular fa-user"></i> ' + (btn.getAttribute('data-author') || '');

            const imgEl = document.getElementById('modalBeritaImg');
            imgEl.src = btn.getAttribute('data-img') || '';
            imgEl.alt = btn.getAttribute('data-title') || '';

            const rawText = btn.getAttribute('data-content') || '';
            const bodyEl  = document.getElementById('modalBeritaBody');
            bodyEl.innerHTML = '';

            rawText.split(/\r?\n\r?\n/).forEach(para => {
                const trimmed = para.trim();
                if (trimmed !== '') {
                    const p = document.createElement('p');
                    p.innerHTML = trimmed.replace(/\r?\n/g, '<br>');
                    bodyEl.appendChild(p);
                }
            });

            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeBeritaModal() {
            const modal = document.getElementById('beritaModal');
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeBeritaModal();
            }
        });
    </script>
</body>
</html>