<?php
session_start();
require_once 'config.php';

// Proteksi login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = (int) $_SESSION['user_id'];
$role_user = $_SESSION['role'] ?? 'Pelanggan';
$home_link = ($role_user === 'Admin') ? 'admin_dashboard.php' : 'beranda.php';

// Aksi: Ubah kuantitas (+/-)
if (isset($_GET['action']) && isset($_GET['cart_id'])) {
    $cart_id = (int) $_GET['cart_id'];
    if ($_GET['action'] === 'plus') {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $cart_id, 'uid' => $user_id]);
    } elseif ($_GET['action'] === 'minus') {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = GREATEST(1, quantity - 1) WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $cart_id, 'uid' => $user_id]);
    } elseif ($_GET['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $cart_id, 'uid' => $user_id]);
    }
    header("Location: halaman_keranjang.php");
    exit();
}

// Aksi: Hapus item yang dicentang (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_terpilih'])) {
    if (!empty($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $ids = array_map('intval', $_POST['selected_items']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$user_id], $ids);
        $stmtDel = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)");
        $stmtDel->execute($params);
    }
    header("Location: halaman_keranjang.php");
    exit();
}

// Ambil seluruh isi keranjang milik user/admin yang sedang login
$query = "SELECT 
            c.id AS cart_id,
            c.product_id,
            c.ice_level,
            c.sugar_level,
            c.cup_size,
            c.quantity,
            p.name,
            p.price,
            p.description,
            p.image_url,
            COALESCE(s.current_stock, 0) AS current_stock
          FROM cart c
          INNER JOIN products p ON c.product_id = p.id
          LEFT JOIN stock_master s ON p.sku = s.sku
          WHERE c.user_id = :user_id
          ORDER BY c.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Filosofi Kopi</title>
    
    <!-- Import Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- Memanggil file CSS -->
    <link rel="stylesheet" href="halaman_keranjang.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
            <a href="<?= $home_link ?>" class="nav-brand">Filosofi Kopi</a>
        </div>
        
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>
        
        <div class="nav-right">
            <a href="halaman_keranjang.php" style="color: #d4a373;"><i class="fas fa-shopping-cart"></i></a>
            <a href="pesan.php"><i class="fas fa-envelope"></i></a>
            <a href="profile.php"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <form method="POST" id="cart-form">
            <div class="cart-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php if (count($cartItems) > 0): ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #3b2316;">
                            <input type="checkbox" id="check-all" checked style="width: 18px; height: 18px;"> Pilih Semua
                        </label>
                    <?php endif; ?>
                </div>
                <?php if (count($cartItems) > 0): ?>
                    <button type="submit" name="hapus_terpilih" class="delete-link" style="background: none; border: none; cursor: pointer;" onclick="return confirm('Hapus produk yang dipilih dari keranjang?')">Hapus</button>
                <?php endif; ?>
            </div>

            <?php if (count($cartItems) > 0): ?>
                <?php foreach ($cartItems as $item): ?>
                    <?php $subtotal = $item['price'] * $item['quantity']; ?>
                    <div class="cart-item-card" style="margin-bottom: 25px;">
                        <div class="item-left">
                            <input type="checkbox" name="selected_items[]" value="<?= $item['cart_id'] ?>" class="item-checkbox" data-subtotal="<?= $subtotal ?>" checked>
                            <div class="item-image">
                                <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/150/8B4513/FFFFFF?text=Kopi' ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                        </div>

                        <div class="item-right">
                            <div class="item-details">
                                <h2><?= htmlspecialchars($item['name']) ?></h2>
                                <p style="font-size: 0.85rem; color: #5c3a21; font-weight: 600; margin-bottom: 10px;">
                                    Varian: <?= htmlspecialchars($item['ice_level']) ?> &bull; <?= htmlspecialchars($item['sugar_level']) ?> &bull; <?= htmlspecialchars($item['cup_size']) ?>
                                </p>
                                <hr class="divider">
                                <p class="item-desc">
                                    <?= htmlspecialchars($item['description'] ?? 'Deskripsi belum tersedia.') ?>
                                </p>
                            </div>

                            <div class="item-bottom">
                                <!-- Kontrol Kuantitas -->
                                <div style="display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #3b2316; border-radius: 20px; padding: 4px 12px;">
                                    <a href="halaman_keranjang.php?action=minus&cart_id=<?= $item['cart_id'] ?>" style="font-weight: bold; padding: 0 6px;">-</a>
                                    <span style="font-weight: 600;"><?= (int)$item['quantity'] ?></span>
                                    <a href="halaman_keranjang.php?action=plus&cart_id=<?= $item['cart_id'] ?>" style="font-weight: bold; padding: 0 6px;">+</a>
                                </div>

                                <a href="detail_produk.php?id=<?= $item['product_id'] ?>" class="detail-link">Klik Detail</a>
                                
                                <div class="item-price">
                                    Rp<?= number_format($subtotal, 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Total & Tombol Beli -->
                <div class="buy-now-container" style="align-items: center; gap: 25px;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: #3b2316;">
                        Total: <span id="total-harga">Rp0</span>
                    </div>
                    <button type="button" class="btn-buy" id="btn-beli-sekarang">Beli Sekarang</button>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 20px; max-width: 1000px; margin: 0 auto; border: 1px solid #3b2316;">
                    <i class="fas fa-shopping-cart" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
                    <h3 style="color: #555; margin-bottom: 10px;">Keranjang masih kosong</h3>
                    <p style="color: #888; margin-bottom: 25px;">Silakan pilih kopi favoritmu terlebih dahulu.</p>
                    <a href="<?= $home_link ?>" class="btn-buy" style="display: inline-block;">Belanja Sekarang</a>
                </div>
            <?php endif; ?>
        </form>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo" class="footer-logo" onerror="this.style.display='none'">
            <div class="footer-brand">Filosofi Kopi</div>
        </div>
        
        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
            </div>
            
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const checkAll = document.getElementById('check-all');
            const totalEl = document.getElementById('total-harga');
            const btnBeli = document.getElementById('btn-beli-sekarang');

            function updateTotal() {
                let total = 0;
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        total += parseFloat(cb.getAttribute('data-subtotal')) || 0;
                    }
                });
                if (totalEl) {
                    totalEl.textContent = 'Rp' + total.toLocaleString('id-ID');
                }
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateTotal));

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateTotal();
                });
            }

            if (btnBeli) {
                btnBeli.addEventListener('click', function () {
                    const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (selected.length === 0) {
                        alert('Pilih minimal 1 produk untuk dibeli!');
                        return;
                    }
                    window.location.href = 'pembayaran.php?cart_ids=' + selected.join(',');
                });
            }

            updateTotal();
        });
    </script>

</body>
</html>