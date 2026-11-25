<?php
// isipesan.php
$pageTitle = "Pesan - Filosofi Kopi";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Google Fonts (Poppins & Playfair Display) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Memanggil file CSS -->
    <link rel="stylesheet" href="isipesan.css">
</head>
<body>

    <!-- NAVBAR (Struktur persis seperti settingan CSS) -->
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
            <a href="halaman_keranjang.php"><i class="fa-solid fa-cart-shopping"></i></a>
            <a href="pesan.php"><i class="fa-solid fa-envelope"></i></a>
            <a href="#"><i class="fa-solid fa-user"></i></a>
        </div>
    </nav>

    <!-- LAYOUT UTAMA (Menggunakan .container dan .main-content) -->
    <div class="container">
        <div class="main-content">
            
            <div class="breadcrumb">
                <span>Pesan</span>
            </div>

            <div class="message-card">
                <div class="message-content">
                    <p>Kami ucapkan selamat datang yang hangat dan mendalam telah melangkahkan kaki ke ruang perenungan kami. Situs ini bukan sekadar toko daring biasa, melainkan sebuah perjalanan—perjumpaan antara kopi terbaik dan kedalaman refleksi kehidupan. Sebagaimana kopi yang diseduh dengan kesabaran untuk mengeluarkan aroma dan rasa terbaiknya, demikian pula kami mengundang Anda untuk menikmati setiap halaman di sini perlahan. Biarkan rasa ingin tahu Anda menyusuri lorong-lorong makna, dari filosofi pahit yang menguatkan hingga aroma manis harapan.</p>
                    <p>Mari duduk sejenak, reguk kebijaksanaannya, dan temukan diri Anda kembali dalam setiap seduhan cerita.</p>
                    <p>Selamat menjelajah!</p>
                </div>
                
                <div class="message-footer">
                    <div class="sender">Admin, Udin</div>
                    <div class="date">17-06-2016 17:20</div>
                </div>
            </div>

        </div>
    </div>

    <!-- FOOTER (Struktur persis seperti settingan CSS) -->
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