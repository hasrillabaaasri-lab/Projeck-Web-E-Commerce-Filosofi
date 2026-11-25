<?php
require_once 'config.php';
$pesan = "";

// Ambil data lama berdasarkan ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $produk = $stmt->fetch();
    
    if (!$produk) {
        die("Produk tidak ditemukan.");
    }
} else {
    header("Location: produk.php");
    exit;
}

// Ambil data kategori untuk dropdown
$kategori_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$kategori_list = $kategori_stmt->fetchAll();

// Proses Update Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_produk']);
    $sku = trim($_POST['kode_barang']);
    $kategori = $_POST['kategori'] ?: null;
    $harga = (int)$_POST['harga'];
    $deskripsi = trim($_POST['deskripsi']);
    $gambar = trim($_POST['url_gambar']);
    
    try {
        $sql = "UPDATE products SET name = ?, sku = ?, category_id = ?, price = ?, description = ?, image_url = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $sku, $kategori, $harga, $deskripsi, $gambar, $id]);
        
        header("Location: produk.php?status=updated");
        exit;
    } catch (PDOException $e) {
        $pesan = "<div style='background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>Gagal update: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_stok.css"> <!-- Menggunakan CSS form yang sudah ada -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="navbar">
        <div class="logo-container">
            <div class="logo-circle"><i class="fa-solid fa-mug-hot"></i></div>
            <span class="brand-name">Filosofi Kopi</span>
        </div>
        <nav class="breadcrumb">
            <span>&gt; Produk Menu</span>
            <span>&gt; Edit Produk</span>
        </nav>
    </header>

    <main class="container">
        <div class="form-wrapper">
            <h1 class="form-title">Edit Produk Menu</h1>
            <p class="form-subtitle">Perbarui informasi profil produk</p>

            <?= $pesan ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Nama Produk*</label>
                    <input type="text" name="nama_produk" value="<?= htmlspecialchars($produk['name']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Kode Barang / SKU*</label>
                        <input type="text" name="kode_barang" value="<?= htmlspecialchars($produk['sku']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>" <?= ($produk['category_id'] == $kat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Harga (Rp)*</label>
                        <input type="number" name="harga" value="<?= $produk['price'] ?>" required min="0">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <input type="text" name="deskripsi" value="<?= htmlspecialchars($produk['description']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Gambar Produk*</label>
                    <input type="url" name="url_gambar" value="<?= htmlspecialchars($produk['image_url']) ?>" required>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='produk.php'">Batal</button>
                    <button type="submit" class="btn btn-submit">Update Produk</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>