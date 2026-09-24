<?php
$pageTitle = "Kategori - Filosofi Kopi";
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

    <!-- Memanggil file CSS (PASTIKAN NAMA FILE KATEGORI.CSS) -->
    <link rel="stylesheet" href="Kategori.css">
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <!-- BRAND DIUBAH MENJADI LINK KE BERANDA -->
            <a href="beranda.php" class="nav-brand">Filosofi Kopi</a>
            <!-- MENU KATEGORI TETAP DI HALAMAN INI -->
            <a href="Kategori.php" class="nav-menu">Kategori</a>
        </div>

        <div class="nav-center">
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>

        <div class="nav-right">
            <!-- IKON KERANJANG MENGARAH KE KERANJANG.PHP -->
            <a href="keranjang.php"><i class="fa-solid fa-cart-shopping"></i></a>
            <a href="#"><i class="fa-solid fa-envelope"></i></a>
            <a href="#"><i class="fa-solid fa-user"></i></a>
        </div>
    </nav>

    <!-- KONTEN UTAMA DENGAN SIDEBAR -->
    <div class="container">
        
        <!-- SIDEBAR KIRI -->
        <aside class="sidebar">
            <h2>Kategori</h2>
            <ul class="sidebar-menu">
                <li><a href="#">Arabica</a></li>
                <li><a href="#">Robusta</a></li>
                <li><a href="#">Liberika</a></li>
                <li><a href="#">Excelsa</a></li>
            </ul>
        </aside>

        <!-- KONTEN KANAN -->
        <main class="main-content">
            
            <!-- BANNER PROMOSI -->
            <div class="banner">
                <div class="banner-image">
                    <img src="https://images.unsplash.com/photo-1559525839-b184a4d698c7?q=80&w=300&auto=format&fit=crop" alt="Banner Kopi">
                </div>
                <div class="banner-text">
                    <p>Nikmati perjalanan rasa yang unik melalui berbagai pilihan kategori biji kopi terbaik kami. Mulai dari keharuman khas Arabica, ketegasan rasa Robusta, keunikan aroma Liberika, hingga eksotiknya sensasi rasa Excelsa, temukan seduhan yang paling pas untuk menemani hari Anda.</p>
                </div>
            </div>

            <!-- JUDUL SECTION -->
            <h2 class="section-title">Pilihan Produk</h2>

            <!-- KARTU PRODUK -->
            <article class="product-card">
                <!-- Gambar Produk -->
                <div class="product-image">
                    <img src="https://images.unsplash.com/photo-1552346989-e069318e20a5?q=80&w=400&auto=format&fit=crop" alt="Kopi Aing Macan">
                </div>

                <!-- Detail Produk -->
                <div class="product-details">
                    <div class="product-header">
                        <h3 class="product-title">Kopi Aing Macan</h3>
                        <p class="product-tags">Ringan • Lembut • Rendah Kafein</p>
                        <hr class="divider">
                        <p class="product-desc">Kuat dan kaya rasa dengan sentuhan cokelat hitam serta karamel, sempurna untuk membangkitkan semangat di pagi hari.</p>
                    </div>
                    
                    <!-- OPSI PRODUK -->
                    <div class="product-options">
                        <select class="option-select">
                            <option>250g</option>
                            <option>500g</option>
                            <option>1kg</option>
                        </select>
                        <select class="option-select">
                            <option>Biji Utuh</option>
                            <option>Giling Halus</option>
                            <option>Giling Kasar</option>
                        </select>
                        
                        <div class="qty-control">
                            <button type="button" class="qty-btn">-</button>
                            <span class="qty-val">1</span>
                            <button type="button" class="qty-btn">+</button>
                        </div>
                    </div>

                    <!-- HARGA -->
                    <div class="product-bottom">
                        <div class="price">Rp15.000</div>
                    </div>
                </div>

                <!-- LINK KLIK DETAIL -->
                <a href="#" class="detail-link">Klik Detail</a>
            </article>

        </main>
    </div>

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