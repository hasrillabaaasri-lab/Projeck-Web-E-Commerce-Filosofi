<?php
// index.php
$pageTitle = "Filosofi Kopi - Kedai Kopi Terbaik";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
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
            <!-- Font brand diubah menjadi Dutch801 -->
            <div class="nav-brand">Filosofi Kopi</div>
            <ul class="nav-links">
                <li><a href="#">Berita</a></li>
                <li><a href="tentang.php">Tentang Kami</a></li>
                <!-- BAGIAN INI YANG DIPERBAIKI: Diarahkan ke hubungin.php -->
                <li><a href="hubungin.php">Hubungi</a></li>
                <li><i class="fa-regular fa-user"></i></li>
            </ul>
        </nav>

        <div class="hero">
            <img src="image 24.png" alt="Logo Filosofi Kopi" class="hero-logo">
            
            <h1>Kedai kopi terbaik, Juara Kopi Nya</h1>
            <p>Kualitas Terbaik, Rasa Paling Asik, Nikmat Sejati di Setiap Cangkir–Satu Tegukan, Sejuta Cerita, Racikan Istimewa untuk Penikmat Jiwa, Kopi Pilihan, Teman Setia Hari-hari, Kelezatan Autentik dari Biji Pilihan</p>
            
            <a href="login.php" class="btn-masuk">Masuk</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <h2 class="section-title">Produk Teralis Minggu Ini</h2>
        
        <p class="section-subtitle">
            Pilihan favorit para penikmat kopi yang paling dicari minggu ini.<br>
            Racikan terbaik yang paling diminati dan siap menemani harimu.<br>
            Deretan menu juara yang paling laris dan wajib kamu coba sekarang.<br>
            Cita rasa pilihan yang paling digemari pelanggan setia kami.
        </p>

        <div class="product-grid">
            <article class="product-card">
                <img src="https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?q=80&w=500&auto=format&fit=crop" alt="Espresso">
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
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo Filosofi Kopi" class="footer-logo">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>

            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fa-brands fa-facebook-f fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram fa-instagram"></i></a>
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