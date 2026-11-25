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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['judul_berita'] ?? '');
    $category  = trim($_POST['kategori_berita'] ?? 'Berita Kedai');
    $content   = trim($_POST['isi_berita'] ?? '');
    $image_url = "";

    // ============================================
    // 1. AUTHOR ID (robust: cek session, fallback ke admin pertama di DB)
    // ============================================
    $author_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if (!$author_id) {
        $cek = $pdo->query("SELECT id FROM users WHERE role = 'Admin' LIMIT 1")->fetch();
        $author_id = $cek ? (int)$cek['id'] : null;
    }

    // ============================================
    // 2. VALIDASI INPUT TEXT
    // ============================================
    $errors = [];
    if (empty($title))    $errors[] = "Judul berita wajib diisi.";
    if (empty($category)) $category = 'Berita Kedai';
    if (empty($content))  $errors[] = "Isi berita wajib diisi.";

    // ============================================
    // 3. HANDLE UPLOAD FOTO
    // ============================================
    if (!isset($_FILES['foto_berita']) || $_FILES['foto_berita']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Foto berita wajib diunggah.";
    } elseif ($_FILES['foto_berita']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal upload file (kode error: " . $_FILES['foto_berita']['error'] . ").";
    } else {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                $errors[] = "Folder uploads/ tidak bisa dibuat. Periksa izin folder.";
            }
        }

        if (empty($errors)) {
            $file_ext = strtolower(pathinfo($_FILES["foto_berita"]["name"], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($file_ext, $allowed, true)) {
                $errors[] = "Format file tidak diizinkan. Gunakan JPG, PNG, GIF, atau WEBP.";
            } elseif ($_FILES['foto_berita']['size'] > 3 * 1024 * 1024) {
                $errors[] = "Ukuran file maksimal 3 MB.";
            } else {
                $file_name   = time() . "_" . uniqid() . "." . $file_ext;
                $target_file = $target_dir . $file_name;

                if (move_uploaded_file($_FILES["foto_berita"]["tmp_name"], $target_file)) {
                    $image_url = $target_file;
                } else {
                    $errors[] = "Gagal memindahkan file. Periksa izin folder uploads/.";
                }
            }
        }
    }

    // ============================================
    // 4. INSERT JIKA TIDAK ADA ERROR
    // ============================================
    if (!empty($errors)) {
        $pesan = "<div class='alert alert-error'>
                    <i class='fa-solid fa-circle-exclamation'></i> 
                    " . implode("<br>", array_map('htmlspecialchars', $errors)) . "
                  </div>";
    } else {
        try {
            $colsNewsStmt = $pdo->query("SHOW COLUMNS FROM `news`");
            $newsCols     = array_column($colsNewsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

            if (in_array('category', $newsCols, true)) {
                $sql  = "INSERT INTO news (author_id, title, category, content, image_url) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$author_id, $title, $category, $content, $image_url]);
            } else {
                $sql  = "INSERT INTO news (author_id, title, content, image_url) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$author_id, $title, $content, $image_url]);
            }

            header("Location: berita.php?status=success");
            exit;

        } catch (PDOException $e) {
            $pesan = "<div class='alert alert-error'>
                        <i class='fa-solid fa-circle-exclamation'></i> 
                        Error DB: " . htmlspecialchars($e->getMessage()) . "
                      </div>";
        }
    }
}

$selectedCat = $_POST['kategori_berita'] ?? ($categories_list[0] ?? 'Berita Kedai');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Berita - Filosofi Kopi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dynalight&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tambah_berita.css">
    <style>
        .alert {
            padding: 14px 20px; border-radius: 10px; margin-bottom: 20px;
            font-size: 13px; font-weight: 500;
            display: flex; align-items: flex-start; gap: 10px;
            line-height: 1.6;
        }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert i { font-size: 16px; flex-shrink: 0; margin-top: 2px; }

        .photo-preview {
            margin-top: 15px; border-radius: 10px; overflow: hidden;
            display: none; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .photo-preview img {
            width: 100%; max-height: 280px; object-fit: cover; display: block;
        }
        .photo-preview small {
            display: block; padding: 8px 12px; background: #f8f5f2;
            color: #666; font-size: 11px;
        }

        .form-group select {
            width: 100%;
            border-radius: 30px;
            border: none;
            background-color: #ffffff;
            padding: 15px 25px;
            font-size: 14px;
            color: #1a1a1a;
            outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        .form-group select:focus {
            box-shadow: 0 0 0 2px #2b170e;
        }
    </style>
</head>
<body>

    <header class="top-header">
        <a href="berita.php" class="header-logo" title="Kembali ke Manajemen Berita">
            <div class="logo-icon"><i class="fa-solid fa-mug-hot"></i></div>
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
            <div class="photo-upload-box">
                <label for="foto_berita" class="upload-label">
                    <i class="fa-solid fa-cloud-arrow-up photo-icon"></i>
                    <span id="uploadText">Pilih Foto Berita</span>
                </label>
                <input type="file" id="foto_berita" name="foto_berita" accept="image/*" class="file-input" required onchange="previewImage(event)">
            </div>

            <div class="photo-preview" id="photoPreview">
                <img src="" alt="Preview">
                <small id="previewInfo"></small>
            </div>

            <div class="form-group">
                <label for="judul_berita">Judul Berita <span style="color:#e53935;">*</span></label>
                <input type="text" id="judul_berita" name="judul_berita" 
                       placeholder="Contoh: Promo Spesial Akhir Tahun 2025" 
                       value="<?= htmlspecialchars($_POST['judul_berita'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="kategori_berita">Kategori Berita <span style="color:#e53935;">*</span></label>
                <select id="kategori_berita" name="kategori_berita" required>
                    <?php foreach ($categories_list as $catOpt): ?>
                        <option value="<?= htmlspecialchars($catOpt) ?>" <?= ($selectedCat === $catOpt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catOpt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="isi_berita">Isi Berita <span style="color:#e53935;">*</span></label>
                <textarea id="isi_berita" name="isi_berita" rows="10" 
                          placeholder="Tulis isi berita lengkap di sini..." required><?= htmlspecialchars($_POST['isi_berita'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-batal" onclick="window.location.href='berita.php'">
                    <i class="fa-solid fa-xmark"></i> Batal
                </button>
                <button type="submit" class="btn-upload">
                    <i class="fa-solid fa-upload"></i> Publikasikan
                </button>
            </div>
        </form>
    </main>

    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Filosofi Kopi" class="footer-logo" onerror="this.style.display='none'">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        <div class="footer-right">
            <div class="footer-col"><h4>Metode Pembayaran</h4></div>
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmati Keuntungan dari aplikasi:</h4>
                <p class="discount-text">Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://filosofikopi.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

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
            } else {
                preview.style.display = 'none';
                uploadText.textContent = 'Pilih Foto Berita';
            }
        }
    </script>
</body>
</html>