<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan - Filosofi Kopi</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="pesan.css">
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <nav class="navbar">
        <div class="nav-left">
            <!-- GANTI BAGIAN src INI DENGAN FILE LOGO ASLI ANDA -->
            <img src="gambar/logo-anda.png" alt="Logo" class="nav-logo">
            <span class="nav-brand">Filosofi Kopi</span>
            <span class="nav-menu">Kategori</span>
        </div>

        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari...">
            </div>
        </div>

        <div class="nav-right">
            <a href="#"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-envelope"></i></a>
            <a href="#"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- ================= MAIN CONTENT (PESAN) ================= -->
    <main class="content-area">
        <h2>Pesan</h2>

        <div class="message-card">
            <div class="message-icon">
                <i class="far fa-envelope"></i>
            </div>
            <div class="message-text">
                <h3>Terima Kasih Sudah Bergabung Web Kami</h3>
                <p>Selamat Bergabung di Web Kami, Dan Nikmati Berbagai Diskon Dan Nikmatin Belanja Di Web Kami Tersebut</p>
            </div>
        </div>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div class="footer-left">
            <!-- GANTI BAGIAN src INI DENGAN FILE LOGO FOOTER ASLI ANDA -->
            <img src="gambar/logo-samsara.png" alt="Logo Samsara" class="footer-logo">
            <span class="footer-brand">Samsara</span>
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
                <!-- GANTI BAGIAN src INI DENGAN FILE QR CODE ASLI ANDA -->
                <img src="gambar/qr-code.png" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>