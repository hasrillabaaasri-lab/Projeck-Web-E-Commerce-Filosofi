<?php
// ====== AKTIFKAN TAMPILAN ERROR (untuk debugging) ======
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php'; // Koneksi database PDO ($pdo)

$errors          = [];
$info            = '';
$debug_otp       = '';
$success_message = '';
$redirectPage    = 'login.php';

// ====== Hitung cooldown kirim OTP (20 detik) ======
$cooldown  = 20;
$remaining = 0;
if (isset($_SESSION['reset_otp_last_sent'])) {
    $elapsed = time() - $_SESSION['reset_otp_last_sent'];
    if ($elapsed < $cooldown) {
        $remaining = $cooldown - $elapsed;
    }
}

// ====== Proses Form ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action           = $_POST['action'] ?? 'reset_password';
    $email            = trim($_POST['email'] ?? '');
    $otp_code         = trim($_POST['otp_code'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ---------- AKSI 1: KIRIM OTP KE GMAIL ----------
    if ($action === 'send_otp') {
        if (empty($email)) {
            $errors[] = "Email tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format email tidak valid.";
        } elseif ($remaining > 0) {
            $errors[] = "Mohon tunggu {$remaining} detik lagi sebelum mengirim ulang OTP.";
        } else {
            try {
                $stmt_user = $pdo->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $stmt_user->execute([$email]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $errors[] = "Email tersebut tidak terdaftar di database Filosofi Kopi.";
                } else {
                    $otp_generated = sprintf("%06d", mt_rand(1, 999999));
                    $_SESSION['reset_otp']           = $otp_generated;
                    $_SESSION['reset_otp_email']     = $user['email'];
                    $_SESSION['reset_otp_user_id']   = (int)$user['id'];
                    $_SESSION['reset_otp_expiry']    = time() + (5 * 60);
                    $_SESSION['reset_otp_last_sent'] = time();
                    $remaining = $cooldown;

                    if (!file_exists('PHPMailer/PHPMailer.php')) {
                        $info      = "❌ Folder PHPMailer tidak ditemukan.";
                        $debug_otp = $otp_generated;
                    } else {
                        require_once 'PHPMailer/Exception.php';
                        require_once 'PHPMailer/PHPMailer.php';
                        require_once 'PHPMailer/SMTP.php';

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'hasrillabaaasri@gmail.com';
                            $mail->Password   = 'rsngzxqvjtiezfdo';
                            $mail->setFrom('hasrillabaaasri@gmail.com', 'Filosofi Kopi 3');
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                            $mail->Port       = 465;
                            $mail->CharSet    = 'UTF-8';
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer'       => false,
                                    'verify_peer_name'  => false,
                                    'allow_self_signed' => true,
                                ],
                            ];

                            $mail->addAddress($user['email'], $user['name']);
                            $mail->isHTML(true);
                            $mail->Subject = 'Kode OTP Reset Kata Sandi Filosofi Kopi';
                            $mail->Body    = "Halo <b>" . htmlspecialchars($user['name']) . "</b>,<br><br>Kode OTP untuk mengatur ulang kata sandi Anda adalah: <b>{$otp_generated}</b><br><br>Kode ini berlaku selama 5 menit.<br><br>Terima kasih,<br>Filosofi Kopi";
                            $mail->AltBody = "Kode OTP reset kata sandi Anda adalah: {$otp_generated}. Berlaku 5 menit.";

                            $mail->send();
                            $info = "✅ OTP telah dikirim ke email <b>" . htmlspecialchars($user['email']) . "</b>. Silakan cek Inbox (atau folder Spam).";
                        } catch (Exception $e) {
                            $info      = "❌ Gagal mengirim email.<br><b>Detail Error:</b> " . htmlspecialchars($mail->ErrorInfo);
                            $debug_otp = $otp_generated;
                        }
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Error Database: " . $e->getMessage();
            }
        }
    }
    // ---------- AKSI 2: VERIFIKASI OTP & UPDATE PASSWORD KE DATABASE ----------
    else {
        if (empty($email)) {
            $errors[] = "Masukkan email terlebih dahulu.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format email tidak valid.";
        }

        if (empty($otp_code) || strlen($otp_code) !== 6) {
            $errors[] = "Kode OTP harus 6 digit.";
        } else {
            if (!isset($_SESSION['reset_otp']) || $_SESSION['reset_otp'] !== $otp_code) {
                $errors[] = "Kode OTP salah.";
            } elseif (isset($_SESSION['reset_otp_email']) && strcasecmp($_SESSION['reset_otp_email'], $email) !== 0) {
                $errors[] = "Email tidak sesuai dengan email tujuan pengiriman OTP.";
            } elseif (isset($_SESSION['reset_otp_expiry']) && time() > $_SESSION['reset_otp_expiry']) {
                $errors[] = "Kode OTP sudah kedaluwarsa. Silakan kirim ulang.";
            }
        }

        if (empty($new_password)) {
            $errors[] = "Buat kata sandi baru tidak boleh kosong.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Kata sandi minimal 6 karakter.";
        }

        if ($new_password !== $confirm_password) {
            $errors[] = "Konfirmasi kata sandi baru tidak cocok.";
        }

        if (empty($errors)) {
            try {
                $stmtCek = $pdo->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $stmtCek->execute([$email]);
                $targetUser = $stmtCek->fetch(PDO::FETCH_ASSOC);

                if (!$targetUser) {
                    $errors[] = "Akun dengan email tersebut tidak ditemukan di database.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update kolom password pada tabel users di db_filkop
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmtUpdate->execute([$hashed_password, (int)$targetUser['id']])) {

                        // Catat pemberitahuan ke tabel messages milik user
                        try {
                            $stmtNotif = $pdo->prepare("
                                INSERT INTO messages (user_id, sender_name, subject, message, message_type, is_read)
                                VALUES (?, 'Sistem Filosofi Kopi', 'Kata Sandi Akun Diperbarui', ?, 'manual', 0)
                            ");
                            $pesanNotif = "Halo " . $targetUser['name'] . ",\n\nKata sandi akun Filosofi Kopi Anda baru saja diperbarui. Jika Anda tidak melakukan perubahan ini, segera hubungi admin.";
                            $stmtNotif->execute([(int)$targetUser['id'], $pesanNotif]);
                        } catch (PDOException $e) {
                            // Abaikan jika gagal insert notifikasi pesan
                        }

                        // Bersihkan session OTP & sesi login lama agar benar-benar kembali ke login.php
                        unset(
                            $_SESSION['reset_otp'],
                            $_SESSION['reset_otp_expiry'],
                            $_SESSION['reset_otp_email'],
                            $_SESSION['reset_otp_user_id'],
                            $_SESSION['reset_otp_last_sent'],
                            $_SESSION['reset_origin'],
                            $_SESSION['user_id'],
                            $_SESSION['name'],
                            $_SESSION['role']
                        );

                        $_SESSION['flash_success'] = "Kata sandi berhasil diubah! Silakan masuk menggunakan kata sandi baru.";
                        $_SESSION['flash_email']   = $targetUser['email'];
                        $success_message           = "✅ Kata sandi sudah berhasil diubah! Mengalihkan ke halaman login...";
                    } else {
                        $errors[] = "Gagal memperbarui kata sandi di database.";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Error Database: " . $e->getMessage();
            }
        }
    }
}

$emailValue = $_POST['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($success_message)): ?>
        <meta http-equiv="refresh" content="2;url=login.php">
    <?php endif; ?>
    <title>Filosofi Kopi - Lupa Kata Sandi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="lupa_kata_sandi.css">
</head>
<body>

    <header class="header">
        <div class="brand">
            <a href="index.php" style="display: flex; align-items: center; gap: 14px;">
                <div class="logo">
                    <img src="image 24.png" class="logo-img" alt="Logo" onerror="this.style.display='none'">
                </div>
                <span class="brand-title">Filosofi Kopi</span>
            </a>
            <span class="separator">&gt;</span>
            <span class="page-title">Lupa Kata Sandi</span>
        </div>
    </header>

    <main class="main-container">
        <div class="card-forgot">
            <h1 class="form-title">Lupa Kata Sandi</h1>

            <!-- Notifikasi Berhasil Ubah Kata Sandi -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="font-weight: 600; text-align: center;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Tampilkan Error Validasi -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Tampilkan Info Sukses / Gagal Kirim OTP -->
            <?php if (!empty($info)): ?>
                <div class="alert <?php echo (strpos($info, '✅') !== false) ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo $info; ?>
                </div>
            <?php endif; ?>

            <!-- KODE OTP CADANGAN JIKA PENGIRIMAN EMAIL GAGAL -->
            <?php if (!empty($debug_otp)): ?>
                <div class="alert" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                    🔑 <b>KODE OTP ANDA (cadangan):</b>
                    <span style="font-weight: 800; letter-spacing: 2px; font-size: 15px;"><?php echo htmlspecialchars($debug_otp); ?></span>
                </div>
            <?php endif; ?>

            <form id="forgotForm" method="POST" action="lupa_kata_sandi.php">
                <div class="form-group">
                    <label for="email">Masukan email</label>
                    <div class="input-with-button">
                        <input type="email" id="email" name="email" placeholder="Masukan email" value="<?php echo htmlspecialchars($emailValue); ?>" required autocomplete="off">
                        <button type="submit" name="action" value="send_otp" class="btn-otp" formnovalidate>KIRIM OTP</button>
                    </div>
                    <p style="font-size: 12px; color: #e6d5cd; margin-top: 6px;">
                        Tidak menerima kode? Tunggu
                        <span id="timer"><?php echo $remaining > 0 ? $remaining : 20; ?></span>
                        detik lalu klik lagi KIRIM OTP.
                    </p>
                </div>

                <div class="form-group">
                    <label for="otp_code">Masukkan Kode</label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="text" id="otp_code" name="otp_code" class="input-short" maxlength="6" placeholder="Masukkan 6 digit OTP" value="<?php echo htmlspecialchars($_POST['otp_code'] ?? ''); ?>" required>
                        <span style="font-size: 14px; color: #ffffff;">6 Digit</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">Buat kata sandi baru</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Masukan kata sandi baru" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi kata sandi baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Konfirmasi kata sandi baru" required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="action" value="reset_password" class="btn-submit-center">Ubah Kata sandi</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="footer-left">
            <a href="index.php" style="display: flex; align-items: center; gap: 15px;">
                <img src="image 24.png" class="footer-logo" alt="Logo" onerror="this.style.display='none'">
                <span class="footer-brand">Filosofi Kopi</span>
            </a>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col">
                <h4>Ikutin Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi</h4>
                <p class="discount-text">&amp; Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=FilosofiKopiApp" alt="QR Code App" class="qr-code">
            </div>
        </div>
    </footer>

    <?php if (!empty($success_message)): ?>
    <script>
        setTimeout(function () {
            window.location.href = 'login.php';
        }, 1500);
    </script>
    <?php endif; ?>

    <?php if ($remaining > 0): ?>
    <script>
        (function () {
            let sisa = <?php echo (int)$remaining; ?>;
            const timerEl = document.getElementById('timer');
            const interval = setInterval(function () {
                sisa--;
                if (sisa <= 0) {
                    clearInterval(interval);
                    if (timerEl) timerEl.textContent = '0';
                } else if (timerEl) {
                    timerEl.textContent = sisa;
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>

</body>
</html>