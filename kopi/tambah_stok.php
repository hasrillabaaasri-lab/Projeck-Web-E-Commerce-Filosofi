<?php
require_once 'config.php';
$pesan = "";

// Pastikan kolom name, sku, unit, image_url, dan description tersedia pada stock_master
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `stock_master`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('name', $cols, true)) {
        $pdo->exec("ALTER TABLE `stock_master` ADD COLUMN `name` VARCHAR(150) DEFAULT NULL AFTER `id`");
    }
    if (!in_array('sku', $cols, true)) {
        $pdo->exec("ALTER TABLE `stock_master` ADD COLUMN `sku` VARCHAR(50) DEFAULT NULL AFTER `name`");
    }
    if (!in_array('image_url', $cols, true)) {
        $pdo->exec("ALTER TABLE `stock_master` ADD COLUMN `image_url` VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('description', $cols, true)) {
        $pdo->exec("ALTER TABLE `stock_master` ADD COLUMN `description` TEXT DEFAULT NULL");
    }
} catch (PDOException $e) {
    // Abaikan jika sudah tersedia
}

// Proses Simpan Data Bahan Baku
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama_produk'] ?? '');
    $sku        = trim($_POST['kode_barang'] ?? '');
    $deskripsi  = trim($_POST['supplier'] ?? '');
    $stok       = (float)($_POST['stok'] ?? 0);
    $min_stok   = (float)($_POST['min_stok'] ?? 10);
    $unit       = strtoupper(trim($_POST['unit'] ?? 'GRAM'));
    if ($unit === '' || $unit === 'CUP') {
        $unit = 'GRAM';
    }
    $gambar     = '';

    if (empty($nama) || empty($sku)) {
        $pesan = "<div class='alert alert-error'>
                    <i class='fa-solid fa-circle-exclamation'></i> Nama Bahan Baku dan Kode Barang wajib diisi!
                  </div>";
    } elseif (!isset($_FILES['gambar_produk']) || $_FILES['gambar_produk']['error'] !== UPLOAD_ERR_OK) {
        $pesan = "<div class='alert alert-error'>
                    <i class='fa-solid fa-circle-exclamation'></i> Gambar bahan baku wajib diunggah!
                  </div>";
    } else {
        $fileTmp   = $_FILES['gambar_produk']['tmp_name'];
        $fileName  = $_FILES['gambar_produk']['name'];
        $fileSize  = $_FILES['gambar_produk']['size'];
        $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSize   = 2 * 1024 * 1024; // Maksimal 2MB

        if (!in_array($ext, $allowed, true) || @getimagesize($fileTmp) === false) {
            $pesan = "<div class='alert alert-error'>
                        <i class='fa-solid fa-circle-exclamation'></i> Format file harus berupa gambar (JPG, JPEG, PNG, WEBP, atau GIF)!
                      </div>";
        } elseif ($fileSize > $maxSize) {
            $pesan = "<div class='alert alert-error'>
                        <i class='fa-solid fa-circle-exclamation'></i> Ukuran gambar maksimal 2MB!
                      </div>";
        } else {
            try {
                // Cek apakah SKU sudah terdaftar di stock_master atau products
                $cekStok = $pdo->prepare("SELECT id FROM stock_master WHERE sku = ?");
                $cekStok->execute([$sku]);

                $cekProd = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
                $cekProd->execute([$sku]);

                if ($cekStok->rowCount() > 0 || $cekProd->rowCount() > 0) {
                    $pesan = "<div class='alert alert-error'>
                                <i class='fa-solid fa-circle-exclamation'></i> Kode Barang / SKU sudah terdaftar!
                              </div>";
                } else {
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $newFileName = uniqid('bahan_', true) . '.' . $ext;
                    $targetPath  = $uploadDir . $newFileName;

                    if (!move_uploaded_file($fileTmp, $targetPath)) {
                        $pesan = "<div class='alert alert-error'>
                                    <i class='fa-solid fa-circle-exclamation'></i> Gagal mengunggah file gambar ke server!
                                  </div>";
                    } else {
                        $gambar = $targetPath;

                        // Simpan langsung ke tabel stock_master sebagai Bahan Baku (product_id = 0)
                        $sql_stok = "INSERT INTO stock_master (product_id, name, sku, description, current_stock, min_stock, unit, image_url) 
                                     VALUES (0, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_stok = $pdo->prepare($sql_stok);
                        $stmt_stok->execute([$nama, $sku, $deskripsi, $stok, $min_stok, $unit, $gambar]);

                        // Redirect ke halaman stok_master.php setelah berhasil simpan
                        header("Location: stok_master.php?status=success");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                if (!empty($gambar) && file_exists($gambar)) {
                    @unlink($gambar);
                }
                $pesan = "<div class='alert alert-error'>
                            <i class='fa-solid fa-circle-exclamation'></i> Gagal menyimpan data: " . htmlspecialchars($e->getMessage()) . "
                          </div>";
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
    <title>Tambah Bahan Baku - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_stok.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- TOP NAVBAR -->
    <header class="top-navbar">
        <div class="nav-left">
            <a href="dashboard_admin.php" style="display: flex; align-items: center; gap: 15px;">
                <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
                <span class="nav-brand">Filosofi Kopi</span>
            </a>
            <span class="nav-separator">&gt;</span>
            <a href="stok_master.php" class="nav-page">Inventaris &amp; Stock Master</a>
            <span class="nav-separator">&gt;</span>
            <span class="nav-page active">Tambah Bahan Baku</span>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="container">
        <div class="form-wrapper">
            <h1 class="form-title">Tambah Stok Bahan Baku</h1>
            <p class="form-subtitle">Lengkapi informasi persediaan bahan baku sistem ERP</p>

            <?= $pesan ?>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label for="nama_produk">Nama Bahan Baku*</label>
                    <input type="text" id="nama_produk" name="nama_produk" placeholder="Contoh: Biji Kopi Arabika, Susu UHT, Gula Aren" value="<?= htmlspecialchars($_POST['nama_produk'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="kode_barang">Kode Bahan / SKU*</label>
                        <input type="text" id="kode_barang" name="kode_barang" placeholder="Contoh: BHN-001" value="<?= htmlspecialchars($_POST['kode_barang'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="supplier">Supplier / Keterangan*</label>
                        <input type="text" id="supplier" name="supplier" placeholder="Contoh: PT Roastery Nusantara" value="<?= htmlspecialchars($_POST['supplier'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="stok">Stok Saat Ini*</label>
                        <input type="number" step="0.01" id="stok" name="stok" value="<?= htmlspecialchars($_POST['stok'] ?? '') ?>" required min="0">
                    </div>
                    <div class="form-group">
                        <label for="min_stok">Minimal Stok (Peringatan)*</label>
                        <input type="number" step="0.01" id="min_stok" name="min_stok" required min="0" value="<?= htmlspecialchars($_POST['min_stok'] ?? '10') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="unit">Satuan Bahan Baku*</label>
                    <select id="unit" name="unit" required style="padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-family: inherit; font-size: 14px;">
                        <option value="GRAM" <?= (($_POST['unit'] ?? 'GRAM') === 'GRAM') ? 'selected' : '' ?>>GRAM (gr)</option>
                        <option value="ML" <?= (($_POST['unit'] ?? '') === 'ML') ? 'selected' : '' ?>>MILILITER (ml)</option>
                        <option value="KG" <?= (($_POST['unit'] ?? '') === 'KG') ? 'selected' : '' ?>>KILOGRAM (kg)</option>
                        <option value="LITER" <?= (($_POST['unit'] ?? '') === 'LITER') ? 'selected' : '' ?>>LITER (L)</option>
                        <option value="PCS" <?= (($_POST['unit'] ?? '') === 'PCS') ? 'selected' : '' ?>>PCS / BUAH</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="gambar_produk">Unggah Gambar Bahan Baku*</label>
                    <input type="file" id="gambar_produk" name="gambar_produk" accept="image/*" required onchange="previewImage(event)">
                    <small class="file-hint">Format yang didukung: JPG, JPEG, PNG, WEBP (Maksimal 2MB)</small>
                    
                    <div id="previewContainer" class="image-preview-box" style="display: none;">
                        <img id="imagePreview" src="#" alt="Preview Gambar Bahan Baku">
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='stok_master.php'">Batal</button>
                    <button type="submit" class="btn btn-submit">Simpan</button>
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

    <script>
        function previewImage(event) {
            const input = event.target;
            const previewContainer = document.getElementById('previewContainer');
            const preview = document.getElementById('imagePreview');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'inline-block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '#';
                previewContainer.style.display = 'none';
            }
        }
    </script>

</body>
</html>