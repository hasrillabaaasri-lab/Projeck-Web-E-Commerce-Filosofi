<?php
// Mulai session dan hapus sisa-sisa session pendaftaran (seperti OTP)
session_start();
session_unset();
session_destroy();

// Arahkan URL ke halaman login
$login_url = "login.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filosofi Kopi - Akun Berhasil Dibuat</title>
    <!-- Tambahan FontAwesome untuk Ikon Footer -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="berhasil_buat_akun.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="header">
        <div class="brand">
            <div class="logo">
                <img src="image 24.png" alt="Logo">
            </div>
            <span class="brand-title">Filosofi Kopi</span>
            <span class="separator">&gt;</span>
            <span class="page-title">Daftar</span>
        </div>
    </header>

    <!-- Main Section -->
    <main class="main-container">
        <div class="card-success">
            
            <!-- Checkmark Icon -->
            <div class="check-icon-wrapper">
                <svg viewBox="0 0 24 24" class="check-icon">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>

            <!-- Pesan Sukses -->
            <p class="success-text">
                Akun Anda berhasil dibuat. Terima kasih telah bergabung dengan Samson's Coffee. Silakan masuk untuk menikmati berbagai kemudahan memesan kopi favorit Anda
            </p>

            <!-- Action Button -->
            <div class="action-wrapper">
                <a href="<?php echo htmlspecialchars($login_url); ?>" class="btn-login">Kembali Ke Halaman Login</a>
            </div>

        </div>
    </main>

    <!-- FOOTER (Desain 100% Sama dengan login.css) -->
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
                        <a href="#" class="fb"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="ig"><i class="fa-brands fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>