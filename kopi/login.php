<?php
session_start();
require_once 'config.php';

// ==========================================
// 1. CEK SESSION (Redirect ke beranda hanya jika user_id valid)
// ==========================================
if (!empty($_SESSION['user_id'])) {
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => (int)$_SESSION['user_id']]);
    if ($stmtCheck->fetch()) {
        header("Location: beranda.php");
        exit();
    } else {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}

$error_message   = '';
$success_message = $_SESSION['flash_success'] ?? '';
$prefill_email   = $_SESSION['flash_email'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_email']);

// ==========================================
// 2. PROSES LOGIN
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($password === $user['password'] || password_verify($password, $user['password'])) {
            $_SESSION = [];
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = trim($user['role']);

            header("Location: beranda.php");
            exit();
        } else {
            $error_message = "Password salah!";
        }
    } else {
        $error_message = "Email tidak terdaftar di sistem kami!";
    }
}

$emailInputValue = $_POST['email'] ?? $prefill_email;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Filosofi Kopi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="main-container">
        <div class="image-section"></div>
        
        <div class="form-section">
            <div class="form-wrapper">
                <div class="logo-container">
                    <a href="index.php" title="Kembali ke Halaman Utama" style="display: inline-block; line-height: 0;">
                        <img src="image 24.png" alt="Logo" class="logo">
                    </a>
                </div>

                <form action="login.php" method="POST">
                    <?php if (!empty($success_message)): ?>
                        <div id="flashLoginSuccess" class="success-msg" style="color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 11px 14px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-size: 13.5px; font-weight: 600;">
                            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="error-msg" style="color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; font-size: 14px;">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="input-group">
                        <input type="email" name="email" class="input-field" placeholder="Email" value="<?= htmlspecialchars($emailInputValue) ?>" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" class="input-field" placeholder="Kata Sandi" required>
                    </div>

                    <div class="form-links">
                        <div class="left-links">
                            <span>Belum Punya Akun?</span>
                            <a href="daftar_akun.php">Daftar Sekarang</a>
                        </div>
                        <div class="right-link">
                            <a href="lupa_kata_sandi.php?from=login">Lupa Kata Sandi?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Masuk</button>
                </form>

                <p class="terms-text">
                    Dengan Login, Anda menyetujui 
                    <a href="syarat.php" class="red-text">Syarat, Ketentuan dan Kebijakan</a> 
                    dari Filosofi Coffe &amp; 
                    <a href="syarat.php" class="red-text">Kebijakan Privasi Filosofi</a>
                </p>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-left">
            <a href="index.php" title="Kembali ke Halaman Utama" style="display: flex; align-items: center; gap: inherit; text-decoration: none; color: inherit;">
                <div class="footer-logo-circle">
                    <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
                </div>
                <span class="footer-brand">Filosofi Kopi</span>
            </a>
        </div>

        <div class="footer-right">
            <div class="footer-top-row">
                <div class="footer-col">
                    <h4>Metode Pembayaran</h4>
                </div>
                
                <div class="footer-col">
                    <h4>Ikuti Kami</h4>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" class="fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" class="ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const flashMsg = document.getElementById('flashLoginSuccess');
            if (flashMsg) {
                setTimeout(function () {
                    flashMsg.style.transition = 'opacity 0.5s ease';
                    flashMsg.style.opacity = '0';
                    setTimeout(() => flashMsg.remove(), 500);
                }, 5000);
            }
        });
    </script>

</body>
</html>