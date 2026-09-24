<?php
// halaman_keranjang.php
// Halaman keranjang untuk Filosofi Kopi
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Filosofi Kopi</title>
    
    <!-- Import Google Fonts (Sesuai Beranda) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- Memanggil file CSS -->
    <link rel="stylesheet" href="halaman_keranjang.css">
    <!-- Menggunakan Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Navbar (Sesuai Beranda) -->
    <nav class="navbar">
        <div class="nav-left">
            <!-- Ganti src di bawah ini dengan logo asli Anda -->
            <img src="https://via.placeholder.com/45/3b2316/ffffff?text=Logo" alt="Logo" class="nav-logo">
            <span class="nav-brand">Filosofi Kopi</span>
            <span class="nav-menu">Kategori</span>
        </div>
        
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>
        
        <div class="nav-right">
            <a href="#"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-envelope"></i></a>
            <a href="#"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="cart-header">
            <div class="cart-title">Keranjang</div>
            <a href="#" class="delete-link">Hapus</a>
        </div>

        <!-- Kartu Item Keranjang -->
        <div class="cart-item-card">
            <div class="item-left">
                <input type="checkbox" class="item-checkbox">
                <div class="item-image">
                    <img src="https://via.placeholder.com/150/8B4513/FFFFFF?text=Kopi" alt="Kopi Aing Macan">
                </div>
            </div>

            <div class="item-right">
                <div class="item-details">
                    <h2>Kopi Aing Macan</h2>
                    <!-- Bagian subjudul sudah dihapus di sini -->
                    <hr class="divider">
                    <p class="item-desc">Kuat dan kaya rasa dengan sentuhan cokelat hitam serta karamel, sempurna untuk membangkitkan semangat di pagi hari.</p>
                </div>

                <div class="item-bottom">
                    <a href="#" class="detail-link">Klik Detail</a>
                    <div class="item-price">Rp15.000</div>
                </div>
            </div>
        </div>

        <!-- Tombol Beli -->
        <div class="buy-now-container">
            <button class="btn-buy">Beli Sekarang</button>
        </div>
    </main>

    <!-- Footer (Sesuai Beranda) -->
    <footer>
        <div class="footer-left">
            <!-- Ganti src di bawah ini dengan logo asli Anda -->
            <img src="https://via.placeholder.com/60/3b2316/ffffff?text=S" alt="SamsonS" class="footer-logo">
            <div class="footer-brand">Filosofi Kopi</div>
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
            </div>
            
            <img src="https://via.placeholder.com/60/ffffff/000000?text=QR" alt="QR Code" class="qr-code">
        </div>
    </footer>

</body>
</html>