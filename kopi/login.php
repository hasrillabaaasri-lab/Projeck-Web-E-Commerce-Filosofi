<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Filosofi Kopi</title>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS utama -->
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="main-container">
        <div class="image-section"></div>

        <div class="form-section">
            <div class="form-wrapper">
                <div class="logo-container">
                    <img src="image 24.png" alt="Logo" class="logo">
                </div>

                <!-- BAGIAN INI YANG DIUBAH: action diarahkan ke beranda.php -->
                <form action="beranda.php" method="POST">
                    <div class="input-group">
                        <input type="email" name="email" class="input-field" placeholder="Email" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" class="input-field" placeholder="Kata Sandi" required>
                    </div>

                    <div class="form-links">
                        <div class="left-links">
                            <span>Belum Punya Akun?</span>
                            <a href="#">Daftar Sekarang</a>
                        </div>
                        <div class="right-link">
                            <a href="#">Lupa Kata Sandi?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Masuk</button>
                </form>

                <p class="terms-text">
                    Dengan Login, Anda menyetujui <span class="red-text">Syarat, Kententuan dan<br>Kebijakan dari Samson D. Coffe & Kebijakan Privasi</span><br>Samson D. Coffe
                </p>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo Footer" class="footer-logo">
            <h2 class="footer-brand">Filosofi Kopi</h2>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>

            <div class="footer-col">
                <h4>Ikutin Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div class="footer-col app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskons 5% hanya terdapat di aplikasi</p>
                <img src="WhatsApp Image 2026-09-13 at 13.38.34.jpeg" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>