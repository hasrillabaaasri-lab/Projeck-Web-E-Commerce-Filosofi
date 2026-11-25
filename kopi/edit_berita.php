<?php
require_once 'config.php';
$pesan = "";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && ($conn instanceof PDO)) {
        $pdo = $conn;
    } else {
        $pdo = new PDO(
            "mysql:host=127.0.0.1;dbname=db_filkop;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
}

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: berita.php");
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$berita = $stmt->fetch();

if (!$berita) {
    die("Berita tidak ditemukan.");
}

// Ambil daftar kategori dari news_categories jika tersedia
$categories_list = ['Menu Baru', 'Edukasi Kopi', 'Promo', 'Berita Kedai'];
try {
    $chkCat = $pdo->query("SHOW TABLES LIKE 'news_categories'");
    if ($chkCat && $chkCat->rowCount() > 0) {
        $rowsCat = $pdo->query("SELECT `name` FROM `news_categories` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (count($rowsCat) > 0) {
            $categories_list = $rowsCat;
        }
    }
} catch (PDOException $e) {
    // Gunakan daftar default jika tabel belum ada
}

$currentCategory = !empty($berita['category']) ? (string)$berita['category'] : 'Berita Kedai';
if (!in_array($currentCategory, $categories_list, true)) {
    array_unshift($categories_list, $currentCategory);
}

// Proses Update Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['judul_berita'] ?? '');
    $category  = trim($_POST['kategori_berita'] ?? $currentCategory);
    $content   = trim($_POST['isi_berita'] ?? '');
    $image_url = (string)($berita['image_url'] ?? '');

    $errors = [];
    if ($title === '') $errors[] = "Judul berita wajib diisi.";
    if ($category === '') $category = 'Berita Kedai';
    if ($content === '') $errors[] = "Isi berita wajib diisi.";

    // Handle Upload Foto Baru (Opsional)
    if (isset($_FILES['foto_berita']) && $_FILES['foto_berita']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES["foto_berita"]["name"], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_ext, $allowed, true)) {
            $errors[] = "Format file tidak diizinkan (gunakan JPG, JPEG, PNG, GIF, atau WEBP).";
        } elseif ($_FILES['foto_berita']['size'] > 3 * 1024 * 1024) {
            $errors[] = "Ukuran file maksimal 3 MB.";
        } else {
            $file_name   = time() . "_" . uniqid() . "." . $file_ext;
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["foto_berita"]["tmp_name"], $target_file)) {
                $oldRelPath = ltrim(str_replace('\\', '/', $image_url), '/');
                if ($oldRelPath !== '' && strpos($oldRelPath, 'uploads/') === 0 && is_file($oldRelPath)) {
                    @unlink($oldRelPath);
                }
                $image_url = $target_file;
            } else {
                $errors[] = "Gagal memindahkan file. Periksa izin folder uploads.";
            }
        }
    }

    // Eksekusi Update
    if (empty($errors)) {
        try {
            $sql  = "UPDATE news SET title = ?, category = ?, content = ?, image_url = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $category, $content, $image_url, $id]);
            header("Location: berita.php?status=updated");
            exit;
        } catch (PDOException $e) {
            $pesan = "<div class='alert alert-error'><i class='fa-solid fa-circle-exclamation'></i> Error DB: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $pesan = "<div class='alert alert-error'><i class='fa-solid fa-circle-exclamation'></i> " . implode("<br>", $errors) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Berita - Filosofi Kopi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dynalight&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tambah_berita.css">
    <style>
        @font-face {
            font-family: 'Dutch801 Rm BT';
            src: url('Dutch 801 Roman BT.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .top-header {
            background: #231109;
            color: #ffffff;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .header-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
        }
        .logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #ffffff;
            color: #231109;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            overflow: hidden;
        }
        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .brand-title {
            font-family: 'Dutch801 Rm BT', 'Times New Roman', serif;
            font-size: 24px;
            font-weight: normal;
            font-style: normal;
            color: #ffffff;
            letter-spacing: 0.3px;
        }
        .header-right {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
        }
        .user-badge-header {
            background: #ffffff;
            color: #231109;
            padding: 7px 16px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 500; display: flex; align-items: flex-start; gap: 10px; line-height: 1.6; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert i { font-size: 16px; flex-shrink: 0; margin-top: 2px; }

        /* Style untuk menampilkan gambar lama */
        .current-image-box { margin-bottom: 15px; }
        .current-image-box img { max-width: 100%; height: auto; max-height: 250px; border-radius: 10px; border: 2px solid #ddd; object-fit: cover; }

        .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #d4c4b8;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #2b170e;
            background: #ffffff;
            outline: none;
        }
        .form-group select:focus {
            border-color: #2b170e;
        }

        @media (max-width: 640px) {
            .top-header {
                flex-direction: column;
                padding: 14px 16px;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

    <header class="top-header">
        <a href="berita.php" class="header-logo" title="Kembali ke Manajemen Berita">
            <div class="logo-icon">
                <img src="image 24.png" alt="Logo Filosofi Kopi" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                <i class="fa-solid fa-mug-hot" style="display: none;"></i>
            </div>
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP</span>
            <div class="user-badge-header">
                <i class="fa-solid fa-user"></i> Administrator
            </div>
        </div>
    </header>

    <main class="main-container">
        <?= $pesan ?>

        <form action="" method="POST" enctype="multipart/form-data" class="form-card">
            
            <!-- Tampilkan Gambar Saat Ini -->
            <div class="current-image-box">
                <p style="font-size: 13px; color: #666; margin-bottom: 8px;">Gambar Saat Ini:</p>
                <img src="<?= htmlspecialchars((string)($berita['image_url'] ?? '')) ?>" alt="Current Image" onerror="this.src='https://via.placeholder.com/600x300?text=No+Image'">
            </div>

            <div class="photo-upload-box">
                <label for="foto_berita" class="upload-label">
                    <i class="fa-solid fa-cloud-arrow-up photo-icon"></i>
                    <span id="uploadText">Ganti Foto (Opsional)</span>
                </label>
                <input type="file" id="foto_berita" name="foto_berita" accept="image/*" class="file-input" onchange="previewImage(event)">
            </div>

            <div class="photo-preview" id="photoPreview">
                <img src="" alt="Preview">
                <small id="previewInfo"></small>
            </div>

            <div class="form-group">
                <label for="judul_berita">Judul Berita <span style="color:#e53935;">*</span></label>
                <input type="text" id="judul_berita" name="judul_berita" 
                       value="<?= htmlspecialchars((string)($berita['title'] ?? '')) ?>" required>
            </div>

            <div class="form-group">
                <label for="kategori_berita">Kategori Berita <span style="color:#e53935;">*</span></label>
                <select id="kategori_berita" name="kategori_berita" required>
                    <?php foreach ($categories_list as $catOption): ?>
                        <option value="<?= htmlspecialchars($catOption) ?>" <?= ($currentCategory === $catOption) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="isi_berita">Isi Berita <span style="color:#e53935;">*</span></label>
                <textarea id="isi_berita" name="isi_berita" rows="10" required><?= htmlspecialchars((string)($berita['content'] ?? '')) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-batal" onclick="window.location.href='berita.php'">
                    <i class="fa-solid fa-xmark"></i> Batal
                </button>
                <button type="submit" class="btn-upload">
                    <i class="fa-solid fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </main>

    <!-- Script Preview Gambar -->
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('photoPreview');
            const img = preview.querySelector('img');
            const info = document.getElementById('previewInfo');
            const uploadText = document.getElementById('uploadText');

            if (file) {
                if (file.size > 3 * 1024 * 1024) {
                    alert('Ukuran file maksimal 3 MB!');
                    event.target.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    info.textContent = `${file.name} — ${(file.size / 1024).toFixed(1)} KB`;
                    preview.style.display = 'block';
                    uploadText.textContent = 'Ganti Foto';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>