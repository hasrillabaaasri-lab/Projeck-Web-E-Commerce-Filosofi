<?php
require_once 'auth_erp.php';
$pesan = "";

$roleLabel = $currentUserRole ?? 'Admin';
$userLabel = $currentUserName ?? 'Admin';

if (isset($_GET['id'])) {
    $stock_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM stock_master WHERE id = ?");
    $stmt->execute([$stock_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        die("Data tidak ditemukan.");
    }
} else {
    header("Location: stok_master.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stok = (int)$_POST['stok'];
    $min_stok = (int)$_POST['min_stok'];
    try {
        // Hanya update tabel stock_master
        $stmt = $pdo->prepare("UPDATE stock_master SET current_stock = ?, min_stock = ? WHERE id = ?");
        $stmt->execute([$stok, $min_stok, $stock_id]);
        header("Location: stok_master.php?status=updated");
        exit;
    } catch (PDOException $e) {
        $pesan = "<div class='alert alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stok - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_stok.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Sinkronisasi Header dengan stok_master.php */
        .top-header {
            background-color: #3b2316;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            flex-shrink: 0;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
        }
        .logo-img {
            width: 45px;
            height: 45px;
            background-color: #fff;
            border-radius: 50%;
            padding: 5px;
            object-fit: contain;
        }
        .brand-title {
            font-family: 'Dutch801 Rm BT', 'Playfair Display', serif;
            font-size: 26px;
            font-weight: normal;
            letter-spacing: 0.5px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 15px;
            font-weight: bold;
        }
        .user-badge-header {
            background-color: #ffffff;
            color: #000000;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        @media (max-width: 900px) {
            .top-header {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="top-header">
        <a href="dashboard_admin.php" class="header-logo">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($roleLabel) ?>)</span>
            <div class="user-badge-header">
                <i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($userLabel) ?></span>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="container">
        <div class="form-wrapper">
            <h1 class="form-title">Edit Stok Gudang</h1>
            <p class="form-subtitle">Produk: <strong><?= htmlspecialchars($data['name'] ?? '') ?></strong></p>
            <?= $pesan ?>
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Stok Saat Ini*</label>
                        <input type="number" name="stok" value="<?= (int)$data['current_stock'] ?>" required min="0">
                    </div>
                    <div class="form-group">
                        <label>Minimal Stok*</label>
                        <input type="number" name="min_stok" value="<?= (int)$data['min_stock'] ?>" required min="0">
                    </div>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='stok_master.php'">Batal</button>
                    <button type="submit" class="btn btn-submit">Update Stok</button>
                </div>
            </form>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <a href="dashboard_admin.php" style="display: flex; align-items: center; gap: 20px;">
                <div class="footer-logo-circle">
                    <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
                </div>
                <span class="footer-brand">Filosofi Kopi</span>
            </a>
        </div>

        <div class="footer-right">
            <div class="footer-col payment-col">
                <h4>Metode Pembayaran</h4>
            </div>
            
            <div class="footer-col social-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" class="fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" class="ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>

            <div class="footer-col app-col">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>