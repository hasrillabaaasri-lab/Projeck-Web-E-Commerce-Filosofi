<?php
session_start();
require_once 'config.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pesan     = "";
$is_edit   = false;
$id_produk = null;

// Variabel default untuk form
$nama        = '';
$deskripsi   = '';
$kategori_id = '';
$harga       = '';
$stok        = 0;
$gambar_path = 'https://via.placeholder.com/150?text=No+Image';
$resep_hpp   = [];

// Ambil data kategori untuk dropdown
$kategori_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$kategori_list = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// CEK MODE EDIT (Jika ada ID di URL)
// ==========================================
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit   = true;
    $id_produk = (int) $_GET['id'];

    // Ambil data produk & stok produk jadi dari database
    $stmt = $pdo->prepare("
        SELECT p.*, COALESCE(s.current_stock, 0) AS current_stock 
        FROM products p 
        LEFT JOIN stock_master s ON p.sku = s.sku 
        WHERE p.id = ?
    ");
    $stmt->execute([$id_produk]);
    $produk_lama = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produk_lama) {
        echo "<script>alert('Produk tidak ditemukan!'); window.location.href='kategori.php';</script>";
        exit();
    }

    $nama        = $produk_lama['name'];
    $deskripsi   = $produk_lama['description'];
    $kategori_id = $produk_lama['category_id'];
    $harga       = $produk_lama['price'];
    $stok        = (int) ($produk_lama['current_stock'] ?? 0);

    if (!empty($produk_lama['image_url'])) {
        $gambar_path = $produk_lama['image_url'];
    }

    // Ambil resep HPP yang terhubung ke produk ini untuk ditampilkan sebagai info
    try {
        $stmtHpp = $pdo->prepare("
            SELECT h.id, h.stock_id, h.nama_bahan, h.takaran, h.qty_pemakaian,
                   COALESCE(s.id, s_nm.id) AS resolved_stock_id,
                   COALESCE(s.current_stock, s_nm.current_stock, 0) AS stok_bahan,
                   COALESCE(s.unit, s_nm.unit, '') AS unit_bahan
            FROM product_hpp_items h
            LEFT JOIN stock_master s ON h.stock_id = s.id
            LEFT JOIN stock_master s_nm ON h.stock_id IS NULL AND UPPER(TRIM(h.nama_bahan)) = UPPER(TRIM(s_nm.name))
            WHERE h.product_id = ?
            ORDER BY h.id ASC
        ");
        $stmtHpp->execute([$id_produk]);
        $resep_hpp = $stmtHpp->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $resep_hpp = [];
    }
}

// ==========================================
// FUNGSI POTONG STOK BAHAN BAKU BERDASARKAN HPP
// ==========================================
function potongBahanBakuDariHpp(PDO $pdo, int $productId, int $qtyProduksi): array {
    if ($qtyProduksi <= 0) {
        return [];
    }

    $stmtResep = $pdo->prepare("
        SELECT id, stock_id, nama_bahan, takaran, qty_pemakaian
        FROM product_hpp_items
        WHERE product_id = ?
    ");
    $stmtResep->execute([$productId]);
    $items = $stmtResep->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        return [];
    }

    $logPengurangan = [];

    foreach ($items as $item) {
        $stockId  = !empty($item['stock_id']) ? (int) $item['stock_id'] : 0;
        $qtyPakai = (float) ($item['qty_pemakaian'] ?? 0);

        // Fallback jika qty_pemakaian belum terisi, ambil angka dari kolom takaran (misal: "20 GRAM" -> 20)
        if ($qtyPakai <= 0 && !empty($item['takaran'])) {
            $qtyPakai = (float) preg_replace('/[^0-9.]/', '', $item['takaran']);
        }

        if ($qtyPakai <= 0) {
            continue;
        }

        // Cari baris bahan baku di stock_master (prioritas stock_id, fallback nama_bahan)
        if ($stockId > 0) {
            $stmtBahan = $pdo->prepare("SELECT id, name, current_stock, unit FROM stock_master WHERE id = ? FOR UPDATE");
            $stmtBahan->execute([$stockId]);
        } else {
            $stmtBahan = $pdo->prepare("
                SELECT s.id, s.name, s.current_stock, s.unit
                FROM stock_master s
                LEFT JOIN products p ON p.sku = s.sku AND s.sku != ''
                WHERE UPPER(TRIM(s.name)) = UPPER(TRIM(?))
                  AND p.id IS NULL
                LIMIT 1
                FOR UPDATE
            ");
            $stmtBahan->execute([$item['nama_bahan']]);
        }

        $bahan = $stmtBahan->fetch(PDO::FETCH_ASSOC);
        if (!$bahan) {
            continue;
        }

        $totalKebutuhan = $qtyPakai * $qtyProduksi;
        $stokSaatIni    = (float) $bahan['current_stock'];

        if ($stokSaatIni < $totalKebutuhan) {
            $unitTxt = $bahan['unit'] ?: 'unit';
            throw new Exception(
                "Stok bahan baku '{$bahan['name']}' tidak mencukupi untuk produksi {$qtyProduksi} cup! " .
                "(Butuh: {$totalKebutuhan} {$unitTxt}, Tersedia: {$stokSaatIni} {$unitTxt})"
            );
        }

        $stokBaru = max(0, $stokSaatIni - $totalKebutuhan);
        $stmtUpdBahan = $pdo->prepare("UPDATE stock_master SET current_stock = ? WHERE id = ?");
        $stmtUpdBahan->execute([$stokBaru, (int) $bahan['id']]);

        // Sinkronkan stock_id di product_hpp_items jika sebelumnya masih NULL
        if (empty($item['stock_id'])) {
            $pdo->prepare("UPDATE product_hpp_items SET stock_id = ?, qty_pemakaian = ? WHERE id = ?")
                ->execute([(int) $bahan['id'], $qtyPakai, (int) $item['id']]);
        }

        $logPengurangan[] = "{$bahan['name']} (-{$totalKebutuhan} {$bahan['unit']})";
    }

    return $logPengurangan;
}

// ==========================================
// PROSES SIMPAN DATA (INSERT / UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = trim($_POST['nama_produk']);
    $deskripsi = trim($_POST['deskripsi']);
    $kategori  = !empty($_POST['kategori']) ? (int) $_POST['kategori'] : null;
    $harga     = (int) $_POST['harga'];
    $stok_baru = max(0, (int) $_POST['stok']);

    // Handle Upload Gambar Baru
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp    = $_FILES['gambar']['tmp_name'];
        $file_name   = time() . '_' . basename($_FILES['gambar']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $gambar_path = $target_file;
        }
    }

    if (empty($nama)) {
        $pesan = "<div class='alert alert-error'><i class='fa-solid fa-circle-exclamation'></i> Nama Produk wajib diisi!</div>";
    } else {
        try {
            $pdo->beginTransaction();

            if ($is_edit) {
                // ================= PROSES UPDATE =================
                $stok_lama = (int) ($produk_lama['current_stock'] ?? 0);
                $selisih_tambah = $stok_baru - $stok_lama; // Hanya potong bahan jika stok produk bertambah

                $sql = "UPDATE products SET name = ?, category_id = ?, price = ?, description = ?, image_url = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $kategori, $harga, $deskripsi, $gambar_path, $id_produk]);

                // Cek apakah baris produk jadi sudah ada di stock_master
                $stmtCekSm = $pdo->prepare("SELECT id FROM stock_master WHERE sku = ? LIMIT 1");
                $stmtCekSm->execute([$produk_lama['sku']]);
                if ($stmtCekSm->fetchColumn()) {
                    $stmt_stok = $pdo->prepare("UPDATE stock_master SET current_stock = ?, name = ?, image_url = ? WHERE sku = ?");
                    $stmt_stok->execute([$stok_baru, $nama, $gambar_path, $produk_lama['sku']]);
                } else {
                    $stmt_stok = $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku, image_url) VALUES (?, 5, 'CUP', ?, ?, ?)");
                    $stmt_stok->execute([$stok_baru, $nama, $produk_lama['sku'], $gambar_path]);
                }

                // Jika stok produk ditambah, otomatis kurangi bahan baku sesuai resep HPP
                if ($selisih_tambah > 0) {
                    potongBahanBakuDariHpp($pdo, $id_produk, $selisih_tambah);
                }

                $pdo->commit();
                header("Location: kategori.php?status=updated");
                exit();

            } else {
                // ================= PROSES INSERT (BARU) =================
                // Cek apakah nama menu sudah pernah dibuat lewat modal HPP di laporan_penjualan.php
                $stmtCekExisting = $pdo->prepare("SELECT id, sku FROM products WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                $stmtCekExisting->execute([$nama]);
                $existingProd = $stmtCekExisting->fetch(PDO::FETCH_ASSOC);

                if ($existingProd) {
                    $new_product_id = (int) $existingProd['id'];
                    $sku = $existingProd['sku'];

                    $stmt = $pdo->prepare("UPDATE products SET category_id = ?, price = ?, description = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$kategori, $harga, $deskripsi, $gambar_path, $new_product_id]);

                    $stmtCekSm = $pdo->prepare("SELECT id, current_stock FROM stock_master WHERE sku = ? LIMIT 1");
                    $stmtCekSm->execute([$sku]);
                    $smRow = $stmtCekSm->fetch(PDO::FETCH_ASSOC);

                    if ($smRow) {
                        $stok_lama = (int) $smRow['current_stock'];
                        $selisih_tambah = max(0, $stok_baru - $stok_lama);
                        $pdo->prepare("UPDATE stock_master SET current_stock = ?, name = ?, image_url = ? WHERE id = ?")
                            ->execute([$stok_baru, $nama, $gambar_path, (int) $smRow['id']]);
                    } else {
                        $selisih_tambah = $stok_baru;
                        $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku, image_url) VALUES (?, 5, 'CUP', ?, ?, ?)")
                            ->execute([$stok_baru, $nama, $sku, $gambar_path]);
                    }

                    if ($selisih_tambah > 0) {
                        potongBahanBakuDariHpp($pdo, $new_product_id, $selisih_tambah);
                    }
                } else {
                    $sku = "PRD-" . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

                    $sql = "INSERT INTO products (name, sku, category_id, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nama, $sku, $kategori, $harga, $deskripsi, $gambar_path]);

                    $stmt_stok = $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku, image_url) VALUES (?, 5, 'CUP', ?, ?, ?)");
                    $stmt_stok->execute([$stok_baru, $nama, $sku, $gambar_path]);
                }

                $pdo->commit();
                header("Location: kategori.php?status=success");
                exit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pesan = "<div class='alert alert-error'><i class='fa-solid fa-circle-exclamation'></i> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit Produk' : 'Tambah Produk' ?> - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_produk.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .hpp-sync-box {
            background: #faf6f3;
            border: 1px solid #dfd3cb;
            border-left: 4px solid #2b170e;
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 14px;
            font-size: 13px;
            color: #2b170e;
        }
        .hpp-sync-box h4 {
            margin: 0 0 8px 0;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .hpp-sync-list {
            margin: 0;
            padding-left: 18px;
            font-size: 12.5px;
            color: #4a2d1e;
        }
        .hpp-sync-list li { margin-bottom: 4px; }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="top-header">
        <div class="header-left">
            <div class="logo-icon">
                <img src="image 24.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <span class="brand-title">Filosofi Kopi</span>
            <span class="breadcrumb-separator">&gt;</span>
            <span class="breadcrumb-text active"><?= $is_edit ? 'Edit Produk' : 'Tambah Produk' ?></span>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="main-container">
        <div class="form-card">
            <h1 class="form-title"><?= $is_edit ? 'Edit Produk' : 'Tambah Produk' ?></h1>
            <p class="form-subtitle">Lengkapi informasi spesifikasi produk sistem ERP</p>

            <?= $pesan ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-body">
                    
                    <!-- Upload Foto Section -->
                    <div class="upload-section">
                        <div class="upload-box">
                            <i class="fa-solid fa-image"></i>
                            <span><?= $is_edit ? 'Ganti foto' : 'Tambah foto' ?></span>
                        </div>
                        <div class="upload-input-group">
                            <label for="file-upload" class="custom-file-upload">
                                Pilih File
                            </label>
                            <input id="file-upload" type="file" name="gambar" accept="image/*" onchange="updateFileName(this)">
                            <span id="file-name" class="file-name-display">Pilih file gambar...</span>
                        </div>
                    </div>

                    <!-- Nama Produk -->
                    <div class="form-group">
                        <label for="nama_produk">Nama Produk</label>
                        <input type="text" id="nama_produk" name="nama_produk" value="<?= htmlspecialchars($nama) ?>" required>
                    </div>

                    <!-- Deskripsi -->
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="5"><?= htmlspecialchars($deskripsi) ?></textarea>
                    </div>

                    <!-- Row: Stock, Kategori, Harga -->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="stok">Stock (CUP)</label>
                            <input type="number" id="stok" name="stok" value="<?= htmlspecialchars((string)$stok) ?>" min="0" data-stok-lama="<?= (int)$stok ?>" oninput="hitungSimulasiBahan()">
                        </div>
                        <div class="form-group">
                            <label for="kategori">Kategori</label>
                            <select id="kategori" name="kategori">
                                <option value="">Pilih</option>
                                <?php foreach ($kategori_list as $kat): ?>
                                    <option value="<?= $kat['id'] ?>" <?= ($kategori_id == $kat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga Satuan</label>
                            <div class="price-input-wrapper">
                                <span class="currency-symbol">Rp</span>
                                <input type="number" id="harga" name="harga" value="<?= htmlspecialchars((string)$harga) ?>" required min="0">
                            </div>
                        </div>
                    </div>

                    <!-- INFO OTOMATIS RESEP HPP & PENGURANGAN STOK BAHAN BAKU -->
                    <?php if ($is_edit): ?>
                        <div class="hpp-sync-box">
                            <?php if (count($resep_hpp) > 0): ?>
                                <h4><i class="fa-solid fa-link" style="color:#2e7d32;"></i> Terhubung ke Resep HPP & Stok Master</h4>
                                <p style="margin:0 0 8px 0; font-size:12px; color:#555;">
                                    Setiap penambahan <strong>Stock (CUP)</strong> di atas <strong><?= (int)$stok ?> CUP</strong> akan otomatis mengurangi bahan baku berikut:
                                </p>
                                <ul class="hpp-sync-list" id="listSimulasiBahan">
                                    <?php foreach ($resep_hpp as $rh): ?>
                                        <?php
                                            $qtyPerCup = (float) ($rh['qty_pemakaian'] ?? 0);
                                            if ($qtyPerCup <= 0 && !empty($rh['takaran'])) {
                                                $qtyPerCup = (float) preg_replace('/[^0-9.]/', '', $rh['takaran']);
                                            }
                                            $sisaBahan = (float) ($rh['stok_bahan'] ?? 0);
                                            $unitBahan = $rh['unit_bahan'] ?: '';
                                        ?>
                                        <li data-qty-cup="<?= $qtyPerCup ?>" data-stok-bahan="<?= $sisaBahan ?>" data-unit="<?= htmlspecialchars($unitBahan) ?>" data-nama="<?= htmlspecialchars($rh['nama_bahan']) ?>">
                                            <strong><?= htmlspecialchars($rh['nama_bahan']) ?></strong>:
                                            <?= rtrim(rtrim(number_format($qtyPerCup, 2, ',', '.'), '0'), ',') ?> <?= htmlspecialchars($unitBahan) ?> / cup
                                            <span style="color:#666;">(Tersedia di Stok Master: <b><?= rtrim(rtrim(number_format($sisaBahan, 2, ',', '.'), '0'), ',') ?> <?= htmlspecialchars($unitBahan) ?></b>)</span>
                                            <span class="simulasi-potong" style="font-weight:700; color:#c62828;"></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <h4><i class="fa-solid fa-circle-info" style="color:#e65100;"></i> Resep HPP Belum Diatur</h4>
                                <p style="margin:0; font-size:12px; color:#555;">
                                    Produk ini belum memiliki komposisi bahan baku di menu <strong>Laporan Penjualan &amp; HPP</strong>, sehingga penambahan stok belum mengurangi bahan baku secara otomatis.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Tombol Batal & Simpan -->
                <div class="button-group">
                    <button type="button" class="btn btn-batal" onclick="window.location.href='kategori.php'">Batal</button>
                    <button type="submit" class="btn btn-simpan">Simpan</button>
                </div>
            </form>
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
            <div class="footer-top-row">
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
            </div>

            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <script>
        function updateFileName(input) {
            const fileNameDisplay = document.getElementById('file-name');
            if (input.files && input.files.length > 0) {
                fileNameDisplay.textContent = input.files[0].name;
                fileNameDisplay.style.color = '#333';
            } else {
                fileNameDisplay.textContent = 'Pilih file gambar...';
                fileNameDisplay.style.color = '#888';
            }
        }

        function hitungSimulasiBahan() {
            const stokInp = document.getElementById('stok');
            const listEl  = document.getElementById('listSimulasiBahan');
            if (!stokInp || !listEl) return;

            const stokLama = parseInt(stokInp.getAttribute('data-stok-lama')) || 0;
            const stokBaru = parseInt(stokInp.value) || 0;
            const tambahCup = Math.max(0, stokBaru - stokLama);

            listEl.querySelectorAll('li').forEach(li => {
                const qtyCup    = parseFloat(li.getAttribute('data-qty-cup')) || 0;
                const stokBahan = parseFloat(li.getAttribute('data-stok-bahan')) || 0;
                const unit      = li.getAttribute('data-unit') || '';
                const spanEl    = li.querySelector('.simulasi-potong');

                if (tambahCup > 0 && qtyCup > 0) {
                    const totalPotong = tambahCup * qtyCup;
                    const sisaSetelah = stokBahan - totalPotong;
                    if (sisaSetelah < 0) {
                        spanEl.style.color = '#c62828';
                        spanEl.textContent = ` -> Akan terpakai ${totalPotong.toLocaleString('id-ID')} ${unit} (STOK KURANG!)`;
                    } else {
                        spanEl.style.color = '#2e7d32';
                        spanEl.textContent = ` -> Akan berkurang -${totalPotong.toLocaleString('id-ID')} ${unit} (Sisa: ${sisaSetelah.toLocaleString('id-ID')} ${unit})`;
                    }
                } else {
                    spanEl.textContent = '';
                }
            });
        }
    </script>
</body>
</html>