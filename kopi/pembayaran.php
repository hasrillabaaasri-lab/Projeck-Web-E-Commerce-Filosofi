<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Bersihkan penanda redirect setelah kembali ke halaman pembayaran
unset($_SESSION['checkout_redirect']);

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 10;
$qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;

// ==========================================
// 1. LOGIKA PEMILIHAN ALAMAT
// ==========================================
$address = null;
if (isset($_GET['address_id'])) {
    $stmt_addr = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt_addr->execute([(int)$_GET['address_id'], $user_id]);
    $address = $stmt_addr->fetch();
}

if (!$address) {
    $stmt_addr = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? AND is_primary = 1 LIMIT 1");
    $stmt_addr->execute([$user_id]);
    $address = $stmt_addr->fetch();
}

if (!$address) {
    $stmt_addr = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? LIMIT 1");
    $stmt_addr->execute([$user_id]);
    $address = $stmt_addr->fetch();
}

// ==========================================
// 2. AMBIL DATA PRODUK
// ==========================================
$stmt_prod = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt_prod->execute([$product_id]);
$product = $stmt_prod->fetch();

if (!$product) {
    echo "<script>alert('Produk tidak ditemukan!'); window.location.href='beranda.php';</script>";
    exit();
}

// ==========================================
// 3. LOGIKA PERHITUNGAN
// ==========================================
$harga_barang  = $product['price'] * $qty;
$biaya_layanan = 1000; 
$pajak         = 0;    
$ongkir        = 0;    
$total_bayar   = (int) round($harga_barang + $biaya_layanan + $pajak + $ongkir);

// ==========================================
// 4. PROSES CHECKOUT
// ==========================================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment'] ?? 'COD';
    $order_code = 'ORD-' . strtoupper(uniqid());

    try {
        $pdo->beginTransaction();

        // Simpan pesanan ke database dengan status 'Pending'
        $sql_order = "INSERT INTO orders (user_id, order_code, channel, total_amount, payment_method, status) 
                      VALUES (?, ?, 'E-Commerce', ?, ?, 'Pending')";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$user_id, $order_code, $total_bayar, $payment_method]);
        
        $order_id = $pdo->lastInsertId();

        // Simpan item pesanan
        $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) 
                     VALUES (?, ?, ?, ?)";
        $stmt_item = $pdo->prepare($sql_item);
        $stmt_item->execute([$order_id, $product_id, $qty, $product['price']]);

        // ==========================================
        // LOGIKA QRIS DINAMIS OTOMATIS
        // ==========================================
        if ($payment_method == 'QRIS') {
            if (!file_exists('qris_dinamis.php')) {
                throw new Exception("File qris_dinamis.php tidak ditemukan.");
            }
            require_once 'qris_dinamis.php';

            // Sisipkan nominal total_bayar ke dalam QRIS statis
            $string_qris_dinamis = generateDynamicQris($qris_statis, $total_bayar);

            // Generate gambar QR Code dari string QRIS dinamis
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($string_qris_dinamis);

            $pdo->commit();

            $_SESSION['qris_payment'] = [
                'order_code'   => $order_code,
                'qr_url'       => $qr_url,
                'total_amount' => $total_bayar
            ];

            header("Location: qris.php");
            exit();
        } else {
            $pdo->commit();
            echo "<script>alert('Pesanan berhasil dibuat! Kode: $order_code'); window.location.href='profile.php?status=success';</script>";
            exit();
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal memproses pesanan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Filosofi Kopi</title>
    <link rel="stylesheet" href="pembayaran.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <h1 class="nav-brand">Filosofi Kopi</h1>
            <span class="nav-menu">Kategori</span>
        </div>
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>
        <div class="nav-right">
            <a href="halaman_keranjang.php"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-envelope"></i></a>
            <a href="profile.php"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        
        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; grid-column: 1 / -1; font-weight: 600;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" style="display: contents;">
            <!-- Kolom Kiri -->
            <div class="left-column">
                
                <!-- Alamat Pengiriman -->
                <section class="section-block">
                    <h2 class="section-title">Alamat Pengiriman</h2>
                    <div class="card address-card">
                        <?php if($address): ?>
                            <div class="address-header">
                                <div class="address-name">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($address['recipient_name']) ?>
                                </div>
                                <a href="alamat.php?redirect=pembayaran&id=<?= $product_id ?>&qty=<?= $qty ?>" class="edit-link">Ubah Alamat</a>
                            </div>
                            <p class="address-detail">
                                <i class="fas fa-phone-alt" style="font-size: 12px; margin-right: 5px;"></i> <?= htmlspecialchars($address['phone']) ?><br>
                                <?= nl2br(htmlspecialchars($address['full_address'])) ?>
                            </p>
                        <?php else: ?>
                            <div class="address-header">
                                <div class="address-name">
                                    <i class="fas fa-exclamation-circle" style="color: red;"></i> Alamat Belum Diatur
                                </div>
                                <a href="tambah_alamat.php?redirect=pembayaran&id=<?= $product_id ?>&qty=<?= $qty ?>" class="edit-link">Tambah Alamat</a>
                            </div>
                            <p class="address-detail">Anda belum menambahkan alamat pengiriman. Silakan tambahkan alamat terlebih dahulu.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Produk -->
                <section class="section-block">
                    <div class="card product-card">
                        <div class="product-image">
                            <img src="<?= !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://via.placeholder.com/100x120/8B5A2B/FFFFFF?text=Kopi' ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>
                            <p class="product-variant">Ice Normal, Gula Normal 100%, Reguler, x<?= $qty ?></p>
                            <div class="product-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                </section>

                <!-- Metode Pembayaran -->
                <section class="section-block">
                    <h2 class="section-title">Metode Pembayaran</h2>
                    <div class="card payment-card">
                        <div class="payment-option">
                            <div class="payment-logo">
                                <img src="https://via.placeholder.com/60x25/000000/FFFFFF?text=QRIS" alt="QRIS">
                                <span>Qris</span>
                            </div>
                            <input type="radio" name="payment" id="qris" value="QRIS" class="radio-custom">
                        </div>
                        <div class="divider"></div>
                        <div class="payment-option">
                            <div class="payment-logo">
                                <i class="fas fa-money-bill-wave cod-icon"></i>
                                <span>COD</span>
                            </div>
                            <input type="radio" name="payment" id="cod" value="COD" class="radio-custom" checked>
                        </div>
                    </div>
                </section>

            </div>

            <!-- Kolom Kanan -->
            <div class="right-column">
                <div class="card summary-card">
                    <h3 class="summary-title">Ringkasan pesanan</h3>
                    <div class="summary-row">
                        <span><?= $qty ?>x <?= htmlspecialchars($product['name']) ?></span>
                        <span>Rp <?= number_format($harga_barang, 0, ',', '.') ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Layanan Biaya</span>
                        <span>Rp <?= number_format($biaya_layanan, 0, ',', '.') ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Pajak Penjualan</span>
                        <span>Rp-</span>
                    </div>
                    <div class="summary-row">
                        <span>Ongkir</span>
                        <span>Rp-</span>
                    </div>
                    <div class="summary-total">
                        <span>Total</span>
                        <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                </div>

                <button type="submit" class="btn-order" <?= !$address ? 'disabled style="background:#ccc; cursor:not-allowed;"' : '' ?>>
                    Pesan Sekarang
                </button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo" class="footer-logo">
            <h2 class="footer-brand">Filosofi Kopi</h2>
        </div>
        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col">
                <h4>Ikutin Kami</h4>
                <div class="social-icons">
                    <a href="#" class="fb"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="ig"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FilosofiKopi" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>