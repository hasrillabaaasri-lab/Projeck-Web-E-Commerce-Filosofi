<?php
// beranda.php
$pageTitle = "Beranda - Filosofi Kopi";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Memanggil file CSS -->
    <link rel="stylesheet" href="beranda.css">
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <a href="beranda.php" class="nav-brand">Filosofi Kopi</a>
            <a href="Kategori.php" class="nav-menu">Kategori</a>
        </div>

        <div class="nav-center">
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>

        <div class="nav-right">
            <!-- IKON KERANJANG MENGARAH KE HALAMAN_KERANJANG.PHP -->
            <a href="halaman_keranjang.php"><i class="fa-solid fa-cart-shopping"></i></a>
            <a href="#"><i class="fa-solid fa-envelope"></i></a>
            <a href="#"><i class="fa-solid fa-user"></i></a>
        </div>
    </nav>

    <!-- HERO BANNER -->
    <section class="hero-banner">
        <div class="hero-content">
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=600&auto=format&fit=crop" alt="Kenikmatan Kopi">
            </div>
            <div class="hero-text">
                <h1>Kenikmatan Autentik di Setiap Cangkir Samson's</h1>
                <p>Secangkir kopi hangat dari Samson's Coffee bukan sekadar pelepasan penat atau teman di kala senggang, melainkan sebuah mahakarya rasa yang diracik khusus dari biji kopi pilihan berkualitas tinggi untuk membangkitkan semangat, mempertajam fokus, dan menemani setiap langkah perjalanan produktifmu sepanjang hari.</p>
            </div>
        </div>
    </section>

    <!-- KONTEN UTAMA -->
    <main>
        <div class="breadcrumb">
            <span>Beranda</span>
        </div>

        <!-- KARTU PRODUK -->
        <article class="product-card">
            <div class="product-image">
                <img src="imagaaae 20.png" alt="Sunset Brew">
            </div>

            <div class="product-details">
                <h2 class="product-title">Sunset Brew</h2>
                <hr class="divider">
                <p class="product-desc">Perpaduan cold drip yang smooth dengan pink grapefruit syrup yang manis-asam. Segarnya langsung nyapa dari tegukan pertama</p>
                
                <div class="product-actions">
                    <button class="btn-cart">
                        <i class="fa-solid fa-cart-shopping"></i> Tambah Keranjang
                    </button>
                    <div class="price">Rp.25.000</div>
                </div>
            </div>

            <a href="detail_produk.php" class="detail-link">Klik Detail</a>
        </article>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo Footer" class="footer-logo">
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

</body>
</html>