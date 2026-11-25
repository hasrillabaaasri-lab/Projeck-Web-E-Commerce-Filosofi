<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 3;
}
$user_id = (int) $_SESSION['user_id'];

$order_code  = '';
$qr_url      = '';
$total_bayar = 0;

// 1. Jika dibuka dari halaman pesanan.php (?order_code=...)
if (!empty($_GET['order_code'])) {
    $req_code = trim($_GET['order_code']);
    $stmt = $pdo->prepare("SELECT order_code, total_amount, payment_method, status FROM orders WHERE order_code = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$req_code, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "<script>alert('Pesanan tidak ditemukan.'); window.location.href='pesanan.php';</script>";
        exit();
    }

    if ($order['status'] !== 'Pending') {
        header("Location: pesanan.php?status=akan-dikirim");
        exit();
    }

    if (!file_exists('qris_dinamis.php')) {
        echo "<script>alert('File qris_dinamis.php tidak ditemukan.'); window.location.href='pesanan.php';</script>";
        exit();
    }
    require_once 'qris_dinamis.php';

    $order_code  = $order['order_code'];
    $total_bayar = (int) round($order['total_amount']);
    $string_qris_dinamis = generateDynamicQris($qris_statis, $total_bayar);
    $qr_url      = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($string_qris_dinamis);

    $_SESSION['qris_payment'] = [
        'order_code'   => $order_code,
        'qr_url'       => $qr_url,
        'total_amount' => $total_bayar
    ];
} else {
    // 2. Jika dibuka langsung setelah proses checkout dari pembayaran.php
    $qris_data   = $_SESSION['qris_payment'] ?? null;
    $order_code  = $qris_data['order_code'] ?? '';
    $qr_url      = $qris_data['qr_url'] ?? '';
    $total_bayar = $qris_data['total_amount'] ?? 0;
}

if (empty($qr_url) || empty($order_code)) {
    echo "<script>alert('Data pembayaran tidak valid atau sudah kedaluwarsa.'); window.location.href='pesanan.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS - Filosofi Kopi</title>
    <link rel="stylesheet" href="qris.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .payment-toast {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #1e7e34;
            color: #ffffff;
            padding: 16px 28px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            z-index: 9999;
            font-size: 15px;
            font-weight: 500;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; top: -50px; }
            to { opacity: 1; top: 20px; }
        }
        .auto-status-box {
            margin: 30px auto 40px auto;
            max-width: 460px;
            background-color: #f5ebe4;
            border: 1px solid #d7c3b5;
            color: #3b2316;
            padding: 14px 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <!-- NOTIFIKASI PEMBAYARAN BERHASIL DI ATAS -->
    <div id="payment-toast" class="payment-toast">
        <i class="fas fa-check-circle" style="font-size: 22px;"></i>
        <div>
            <strong>Pembayaran telah berhasil!</strong><br>
            <span style="font-size: 13px;">Pesanan Anda sedang diproses, mengalihkan ke halaman pesanan...</span>
        </div>
    </div>

    <!-- TOP NAVBAR -->
    <header class="top-navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
            <span class="nav-brand">Filosofi Kopi</span>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="main-container">
        
        <h1 class="page-title">Pembayaran Qris</h1>
        
        <!-- Menampilkan Kode Order & Nominal Tagihan -->
        <div style="text-align: center; margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            Order ID: <?= htmlspecialchars($order_code) ?><br>
            <span style="font-size: 22px; color: #3b2316; display: inline-block; margin-top: 6px;">
                Total Tagihan: Rp <?= number_format($total_bayar, 0, ',', '.') ?>
            </span>
        </div>

        <!-- QRIS BOX -->
        <div class="qr-wrapper">
            <img src="<?= htmlspecialchars($qr_url, ENT_QUOTES, 'UTF-8') ?>" alt="QR Code QRIS" class="qr-image">
        </div>

        <!-- TIMER (Countdown Berjalan) -->
        <div class="timer-text" id="countdown">
            15:00 Menit
        </div>

        <!-- STATUS PENGECEKAN OTOMATIS -->
        <div id="auto-status-box" class="auto-status-box">
            <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>
            Menunggu pembayaran... Halaman akan otomatis berpindah setelah QRIS dibayar.
        </div>

        <!-- INSTRUCTIONS -->
        <div class="instructions">
            <ol>
                <li>
                    <strong>Buka Aplikasi Pembayaran:</strong><br>
                    Buka aplikasi GoPay, OVO, DANA, ShopeePay, atau aplikasi mobile banking (BCA, Mandiri, BRI, dll).
                </li>
                <li>
                    <strong>Pilih Menu Scan QRIS:</strong><br>
                    Cari dan tekan ikon "Scan", "Pay", "Bayar", atau logo QRIS di aplikasi Anda.
                </li>
                <li>
                    <strong>Arahkan Kamera ke Kode QR:</strong><br>
                    Arahkan kamera HP Anda ke kode QRIS di layar ini. Pastikan seluruh bagian kotak masuk ke dalam bingkai scan.
                </li>
                <li>
                    <strong>Cek Detail Pembayaran:</strong><br>
                    Nama toko <strong>Filosofi Kopi</strong> dan nominal tagihan akan muncul otomatis. Periksa kembali kesesuaiannya.
                </li>
                <li>
                    <strong>Konfirmasi & Masukkan PIN:</strong><br>
                    Tekan tombol Bayar, lalu masukkan PIN aplikasi Anda untuk menyelesaikan transaksi.
                </li>
            </ol>
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

            <div class="footer-col app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <!-- SCRIPT COUNTDOWN & AUTO-POLLING STATUS PEMBAYARAN -->
    <script>
        let timeInSeconds = 15 * 60; 
        const timerElement = document.getElementById('countdown');
        const orderCode = '<?= htmlspecialchars($order_code, ENT_QUOTES, 'UTF-8') ?>';

        function updateTimer() {
            if (timeInSeconds <= 0) {
                timerElement.innerHTML = "Waktu Habis";
                timerElement.style.color = "red";
                return;
            }

            let minutes = Math.floor(timeInSeconds / 60);
            let seconds = timeInSeconds % 60;

            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            timerElement.innerHTML = minutes + ":" + seconds + " Menit";
            timeInSeconds--;
        }

        setInterval(updateTimer, 1000);

        // Cek status pembayaran otomatis setiap 3 detik tanpa tombol manual
        const pollingInterval = setInterval(() => {
            fetch('cek_status_qris.php?order_code=' + encodeURIComponent(orderCode))
                .then(response => response.json())
                .then(data => {
                    if (data.paid) {
                        clearInterval(pollingInterval);

                        // Tampilkan notifikasi hijau di atas layar
                        const toast = document.getElementById('payment-toast');
                        toast.style.display = 'flex';

                        // Ubah kotak status di layar
                        const statusBox = document.getElementById('auto-status-box');
                        statusBox.style.backgroundColor = '#d4edda';
                        statusBox.style.borderColor = '#c3e6cb';
                        statusBox.style.color = '#155724';
                        statusBox.innerHTML = '<i class="fas fa-check-circle" style="margin-right: 8px;"></i> Pembayaran telah berhasil! Mengalihkan ke pesanan...';

                        // Pindah otomatis ke tab Akan Di Kirim di pesanan.php
                        setTimeout(() => {
                            window.location.href = 'pesanan.php?status=akan-dikirim';
                        }, 2000);
                    }
                })
                .catch(err => console.error('Gagal mengecek status:', err));
        }, 3000);
    </script>
</body>
</html>