<?php
// tentang.php
$pageTitle = "Tentang Kami - Filosofi Kopi";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="tentang.css">
    <!-- FontAwesome untuk ikon footer -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts untuk font Sans-serif -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- HEADER BARU SESUAI GAMBAR -->
    <header class="page-header">
        <div class="header-content">
            
            <!-- BAGIAN INI DIUBAH: DIBUNGKUS DENGAN LINK KE INDEX.PHP -->
            <!-- Inline style ditambahkan agar rapi tanpa perlu ubah CSS -->
            <a href="index.php" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                <div class="logo-wrapper">
                    <!-- Path logo disesuaikan dengan file proyek Anda -->
                    <img src="samson_s-removebg-preview 1 1.png" alt="Filosofi Kopi Logo" class="logo-img">
                </div>
                <span class="brand-name">Filosofi Kopi</span>
            </a>
            
            <span class="breadcrumb-separator">&gt;</span>
            <span class="breadcrumb-current">Tentang Kami</span>
        </div>
    </header>

    <!-- Main Content Section (Tetap sama) -->
    <main class="main-content-tentang">
        <h1 class="page-title">Tentang Kami</h1>
        
        <div class="text-container">
            <p><strong>Visi Filosofi Kopi</strong><br>
            Mempromosikan dan mengangkat isu serta kekuatan kopi lokal Indonesia ke tingkat yang lebih tinggi, sekaligus menjadikannya sebagai media literasi budaya nusantara. Mereka ingin agar kopi tidak sekadar dipandang sebagai minuman biasa, melainkan sebagai sebuah perjalanan rasa dan makna yang mempertemukan komunitas pencinta kopi dengan dedikasi para petani lokal.</p>

            <p><strong>Misi Filosofi Kopi</strong><br>
            Eksplorasi Kopi Nusantara: Menyajikan cerita, asal-usul (origins), serta keanekaragaman budaya kopi nusantara melalui program seperti Filosofi Literasi. Menjaga Proses yang Tulus: Memastikan setiap cangkir kopi yang disajikan melalui proses yang dikerjakan dengan hati—mulai dari pemilihan biji terbaik hingga ke tangan konsumen. Pemberdayaan Komunitas: Membangun ekosistem yang menghubungkan penikmat kopi urban dengan nilai-nilai lokal petani kopi tradisional.</p>
        </div>

        <div class="image-container">
            <!-- Ganti dengan gambar interior kafe Anda -->
            <img src="Informasi lokasi kedaai.png" alt="Interior Filosofi Kopi" class="featured-image">
        </div>
    </main>

    <!-- Footer Section (Mengikuti beranda.css) -->
    <footer>
        <div class="footer-left">
            <img src="samson_s-removebg-preview 1 1.png" alt="Filosofi Kopi Logo" class="footer-logo">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code Aplikasi" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>