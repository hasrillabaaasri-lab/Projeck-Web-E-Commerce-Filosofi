<?php
session_start();
require_once 'config.php';

$pageTitle = "Tentang Kami - Filosofi Kopi";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Halaman Tentang Kami -->
    <link rel="stylesheet" href="halaman_tentangkami.css">
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-breadcrumb">
            <a href="index.php" class="nav-brand-link">
                <div class="nav-logo-circle">
                    <img src="image 24.png" alt="Logo Filosofi Kopi" onerror="this.style.display='none'">
                </div>
                <span class="nav-brand">Filosofi Kopi</span>
            </a>
            <span class="nav-separator">&gt;</span>
            <span class="nav-current">Tentang Kami</span>
        </div>
    </nav>

    <!-- KONTEN UTAMA -->
    <main class="tentang-main">
        <div class="tentang-container">
            <h1 class="tentang-title">Tentang Kami</h1>

            <div class="tentang-text-content">
                <div class="tentang-block">
                    <h2>Visi Filosofi Kopi</h2>
                    <p>
                        Mempromosikan dan mengangkat isu serta kekuatan kopi lokal Indonesia ke tingkat yang lebih tinggi, sekaligus menjadikannya sebagai media literasi budaya nusantara. Mereka ingin agar kopi tidak sekadar dipandang sebagai minuman biasa, melainkan sebagai sebuah perjalanan rasa dan makna yang mempertemukan komunitas pencinta kopi dengan dedikasi para petani lokal.
                    </p>
                </div>

                <div class="tentang-block">
                    <h2>Misi Filosofi Kopi</h2>
                    <p>
                        <strong>Eksplorasi Kopi Nusantara:</strong> Menyajikan cerita, asal-usul (origins), serta keanekaragaman budaya kopi nusantara melalui program seperti Filosofi Literasi.
                    </p>
                    <p>
                        <strong>Menjaga Proses yang Tulus:</strong> Memastikan setiap cangkir kopi yang disajikan melalui proses yang dikerjakan dengan hati–mulai dari pemilihan biji terbaik hingga ke tangan konsumen.
                    </p>
                    <p>
                        <strong>Pemberdayaan Komunitas:</strong> Membangun ekosistem yang menghubungkan penikmat kopi urban dengan nilai-nilai lokal petani kopi tradisional.
                    </p>
                </div>
            </div>

            <div class="tentang-image-wrapper">
                <img src="tentang_kami.jpg"
                     alt="Suasana Kedai Filosofi Kopi"
                     onerror="this.src='https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=1200&auto=format&fit=crop'">
            </div>
        </div>
    </main>

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
                        <a href="#" class="fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <div class="qr-wrapper">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
                </div>
            </div>
        </div>
    </footer>

</body>
</html>