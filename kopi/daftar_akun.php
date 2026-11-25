<?php
// ====== AKTIFKAN TAMPILAN ERROR (untuk debugging) ======
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php'; // PENTING: Panggil koneksi database

$errors = [];
$info = '';
$debug_otp = '';

// ====== Hitung cooldown kirim OTP (20 detik) ======
$cooldown = 20;
$remaining = 0;
if (isset($_SESSION['otp_last_sent'])) {
    $elapsed = time() - $_SESSION['otp_last_sent'];
    if ($elapsed < $cooldown) {
        $remaining = $cooldown - $elapsed;
    }
}

// ====== Proses Form ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action           = $_POST['action'] ?? 'register';
    $nama             = trim($_POST['nama'] ?? '');
    $telepon          = trim($_POST['telepon'] ?? '');
    $otp              = trim($_POST['otp'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ---------- AKSI: KIRIM OTP ----------
    if ($action === 'send_otp') {
        if (empty($telepon)) {
            $errors[] = "Email / No HP tidak boleh kosong.";
        } elseif ($remaining > 0) {
            $errors[] = "Mohon tunggu {$remaining} detik lagi sebelum mengirim ulang OTP.";
        } else {
            $otp_code = sprintf("%06d", mt_rand(1, 999999));
            $_SESSION['otp']           = $otp_code;
            $_SESSION['otp_contact']   = $telepon;
            $_SESSION['otp_expiry']    = time() + (5 * 60);
            $_SESSION['otp_last_sent'] = time();
            $remaining = $cooldown;

            if (filter_var($telepon, FILTER_VALIDATE_EMAIL)) {
                if (!file_exists('PHPMailer/PHPMailer.php')) {
                    $info = "❌ Folder PHPMailer tidak ditemukan.";
                    $debug_otp = $otp_code;
                } else {
                    require 'PHPMailer/Exception.php';
                    require 'PHPMailer/PHPMailer.php';
                    require 'PHPMailer/SMTP.php';
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
                        $mail->addAddress($telepon);
                        $mail->isHTML(true);
                        $mail->Subject = 'Kode OTP Pendaftaran Filosofi Kopi';
                        $mail->Body    = "Halo,<br><br>Kode OTP Anda adalah: <b>{$otp_code}</b><br><br>Kode ini berlaku selama 5 menit.<br><br>Terima kasih,<br>Filosofi Kopi";
                        $mail->AltBody = "Kode OTP Anda adalah: {$otp_code}. Berlaku 5 menit.";
                        $mail->send();
                        $info = "✅ OTP telah dikirim ke email <b>" . htmlspecialchars($telepon) . "</b>. Silakan cek Inbox (atau folder Spam).";
                    } catch (Exception $e) {
                        $info = "❌ Gagal mengirim email.<br><b>Detail Error:</b> " . htmlspecialchars($mail->ErrorInfo);
                        $debug_otp = $otp_code;
                    }
                }
            } else {
                $info = "📱 Simulasi pengiriman OTP ke No HP <b>" . htmlspecialchars($telepon) . "</b>.";
                $debug_otp = $otp_code;
            }
        }
    } 
    // ---------- AKSI: DAFTAR AKUN ----------
    else {
        if (empty($nama))    $errors[] = "Nama tidak boleh kosong.";
        if (empty($telepon)) $errors[] = "Nomor telepon / Email tidak boleh kosong.";
        
        // Validasi OTP
        if (empty($otp) || strlen($otp) !== 6) {
            $errors[] = "Kode OTP harus 6 digit.";
        } else {
            if (!isset($_SESSION['otp']) || $_SESSION['otp'] !== $otp) {
                $errors[] = "Kode OTP salah.";
            } elseif (isset($_SESSION['otp_expiry']) && time() > $_SESSION['otp_expiry']) {
                $errors[] = "Kode OTP sudah kedaluwarsa. Silakan kirim ulang.";
            }
        }

        // Validasi Password
        if (empty($password))                $errors[] = "Kata sandi tidak boleh kosong.";
        if ($password !== $confirm_password) $errors[] = "Konfirmasi kata sandi tidak cocok.";

        // ====================================================
        // PROSES SIMPAN KE DATABASE (Jika tidak ada error)
        // ====================================================
        if (empty($errors)) {
            try {
                // Tentukan Email dan No Telp untuk database
                if (filter_var($telepon, FILTER_VALIDATE_EMAIL)) {
                    $email_db = $telepon;
                    $no_telp_db = NULL;
                } else {
                    // Jika input berupa nomor HP, buat email dummy agar tidak error di database
                    $email_db = $telepon . "@pelanggan.filkop"; 
                    $no_telp_db = $telepon;
                }

                // Cek apakah email sudah terdaftar
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt_check->execute([$email_db]);
                
                if ($stmt_check->rowCount() > 0) {
                    $errors[] = "Email / No HP tersebut sudah terdaftar. Silakan gunakan yang lain.";
                } else {
                    // Hash password untuk keamanan
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'Pelanggan';

                    // Insert ke database
                    $sql = "INSERT INTO users (name, email, no_telp, password, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$nama, $email_db, $no_telp_db, $hashed_password, $role])) {
                        // Hapus session OTP jika berhasil
                        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_contact'], $_SESSION['otp_last_sent']);
                        
                        // Arahkan ke halaman sukses
                        header("Location: berhasil_buat_akun.php?status=sukses");
                        exit();
                    } else {
                        $errors[] = "Gagal menyimpan data ke database.";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Error Database: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filosofi Kopi - Daftar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="daftar_akun.css">
</head>
<body>

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

    <main class="main-container">
        <div class="card-register">
            <h1 class="form-title">Daftar</h1>

            <!-- Tampilkan Error Validasi -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
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

            <!-- KODE OTP MUNCUL DI SINI JIKA EMAIL GAGAL / SMS -->
            <?php if (!empty($debug_otp)): ?>
                <div class="alert alert-warning">
                    🔑 <b>KODE OTP ANDA (untuk testing):</b> 
                    <span class="otp-code"><?php echo $debug_otp; ?></span>
                </div>
            <?php endif; ?>
            
            <form id="registerForm" method="POST" action="daftar_akun.php">
                <div class="form-group">
                    <label for="nama">Nama</label>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama Anda" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="telepon">No Telp / Email</label>
                    <div class="input-otp-wrapper">
                        <input type="text" id="telepon" name="telepon" placeholder="Masukkan Email atau No HP" value="<?php echo htmlspecialchars($_POST['telepon'] ?? ''); ?>" required>
                        <button type="submit" name="action" value="send_otp" class="btn-otp" formnovalidate>Kirim OTP</button>
                    </div>
                    <p class="helper-text">
                        Tidak menerima kode? Tunggu 
                        <span id="timer"><?php echo $remaining > 0 ? $remaining : 20; ?></span> 
                        detik lalu klik lagi Kirim OTP.
                    </p>
                </div>

                <div class="form-group row-otp">
                    <input type="text" id="otp" name="otp" maxlength="6" placeholder="Masukkan 6 digit OTP" value="<?php echo htmlspecialchars($_POST['otp'] ?? ''); ?>" required>
                    <span class="digit-label">6 Digit</span>
                </div>

                <div class="form-group">
                    <label for="password">Buat Kata Sandi</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Buat Kata Sandi Sekali Lagi</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Masukkan kata sandi lagi" required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="action" value="register" class="btn-submit">Buat</button>
                </div>
            </form>
        </div>
    </main>

    <!-- FOOTER -->
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