<?php
require_once 'config.php';
$pesan = "";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $kategori = $stmt->fetch();
    
    if (!$kategori) {
        die("Kategori tidak ditemukan.");
    }
} else {
    header("Location: kategori.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);

    try {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$nama, $deskripsi, $id]);
        header("Location: kategori.php?status=updated");
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
    <title>Edit Kategori - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_stok.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="navbar">
        <div class="logo-container">
            <div class="logo-circle"><i class="fa-solid fa-mug-hot"></i></div>
            <span class="brand-name">Filosofi Kopi</span>
        </div>
        <nav class="breadcrumb">
            <a href="kategori.php" style="text-decoration:none; color:inherit;">&gt; Kategori Menu</a>
            <span>&gt; Edit Kategori</span>
        </nav>
    </header>

    <main class="container">
        <div class="form-wrapper">
            <h1 class="form-title">Edit Kategori Produk</h1>
            <p class="form-subtitle">Perbarui informasi nama dan deskripsi kategori</p>

            <?= $pesan ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Nama Kategori*</label>
                    <input type="text" name="nama_kategori" value="<?= htmlspecialchars($kategori['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Deskripsi Kategori</label>
                    <input type="text" name="deskripsi" value="<?= htmlspecialchars($kategori['description'] ?? '') ?>">
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='kategori.php'">Batal</button>
                    <button type="submit" class="btn btn-submit">Update Kategori</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>