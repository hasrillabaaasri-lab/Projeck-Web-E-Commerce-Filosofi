<?php
require_once 'config.php';

// Cek apakah yang buka adalah admin (mode preview)
$is_admin_preview = isAdmin();

// Ambil data untuk beranda user
$products = $pdo->query("SELECT p.*, s.current_stock 
                         FROM products p 
                         LEFT JOIN stock_master s ON p.id = s.product_id 
                         WHERE s.current_stock > 0 
                         ORDER BY p.id DESC LIMIT 8")->fetchAll();
$news = $pdo->query("SELECT * FROM news ORDER BY published_at DESC LIMIT 3")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Filosofi Kopi - Beranda</title>
    <link rel="stylesheet" href="beranda_user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if($is_admin_preview): ?>
    <style>
        /* Banner Preview */
        .preview-banner {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #ffa000;
            color: #2b170e;
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 700;
            z-index: 9998;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        body { padding-top: 42px; }

        /* Floating Button Kembali */
        .back-to-admin {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            background: linear-gradient(135deg, #2b170e, #4a2d1e);
            color: #fff;
            padding: 14px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.35);
            transition: all 0.3s ease;
            border: 2px solid #c49a6c;
            animation: slideUp 0.5s ease-out;
        }
        .back-to-admin:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
            background: linear-gradient(135deg, #3b2319, #5a3d2e);
        }
        .back-to-admin .badge-admin {
            background: #ffa000;
            color: #2b170e;
            font-size: 10px;
            padding: 3px 9px;
            border-radius: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        @keyframes slideUp {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
    <?php endif; ?>
</head>
<body>

    <?php if($is_admin_preview): ?>
        <!-- BANNER: Sedang Mode Preview -->
        <div class="preview-banner">
            <i class="fa-solid fa-eye"></i>
            Mode Preview — Anda melihat beranda sebagai <strong>ADMIN</strong>
        </div>
    <?php endif; ?>

    <!-- ============================================= -->
    <!-- ISI BERANDA USER ANDA (header, produk, berita, footer) -->
    <!-- Salin semua HTML beranda_user Anda yang sudah ada di sini -->
    <!-- ============================================= -->

    <!-- Contoh minimal: -->
    <header style="padding:20px; background:#fff;">
        <h1>Filosofi Kopi</h1>
    </header>
    <main style="padding:20px;">
        <h2>Produk Terbaru</h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <?php foreach($products as $p): ?>
                <div style="border:1px solid #ddd; padding:10px; border-radius:8px; width:200px;">
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" style="width:100%; border-radius:6px;">
                    <h4><?= htmlspecialchars($p['name']) ?></h4>
                    <p>Rp <?= number_format($p['price'], 0, ',', '.') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php if($is_admin_preview): ?>
        <!-- FLOATING BUTTON: Kembali ke Dashboard Admin -->
        <a href="beranda_admin.php" class="back-to-admin" title="Kembali ke Dashboard Admin">
            <i class="fa-solid fa-arrow-left"></i>
            <span class="badge-admin">ADMIN</span>
            Kembali ke Dashboard
        </a>
    <?php endif; ?>

</body>
</html>