<?php
require_once 'auth_erp.php';

$notif_sukses = '';
$notif_error  = '';

// =========================================================================
// FUNGSI POTONG STOK BAHAN BAKU DI STOCK_MASTER BERDASARKAN RESEP HPP
// =========================================================================
function potongBahanBakuDariHppKategori(PDO $pdo, int $productId, int $qtyProduksi): array {
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

        if ($qtyPakai <= 0 && !empty($item['takaran'])) {
            $qtyPakai = (float) preg_replace('/[^0-9.]/', '', $item['takaran']);
        }

        if ($qtyPakai <= 0) {
            continue;
        }

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
        $pdo->prepare("UPDATE stock_master SET current_stock = ? WHERE id = ?")
            ->execute([$stokBaru, (int) $bahan['id']]);

        if (empty($item['stock_id'])) {
            $pdo->prepare("UPDATE product_hpp_items SET stock_id = ?, qty_pemakaian = ? WHERE id = ?")
                ->execute([(int) $bahan['id'], $qtyPakai, (int) $item['id']]);
        }

        $fmtPotong = rtrim(rtrim(number_format($totalKebutuhan, 2, ',', '.'), '0'), ',');
        $logPengurangan[] = "{$bahan['name']} (-{$fmtPotong} {$bahan['unit']})";
    }

    return $logPengurangan;
}

// =========================================================================
// AKSI POST: UPDATE QUANTITY PRODUK LANGSUNG + OTOMATIS POTONG BAHAN HPP
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_update_qty_produk'])) {
    $productId   = (int) ($_POST['product_id'] ?? 0);
    $modeUpdate  = $_POST['mode_qty'] ?? 'tambah'; // 'tambah' atau 'set'
    $jumlahInput = (int) ($_POST['jumlah_qty'] ?? 0);

    if ($productId <= 0) {
        $notif_error = "Produk tidak valid.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtProd = $pdo->prepare("
                SELECT p.id, p.name, p.sku, p.image_url, s.id AS stock_row_id, COALESCE(s.current_stock, 0) AS current_stock
                FROM products p
                LEFT JOIN stock_master s ON p.sku = s.sku AND p.sku != ''
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmtProd->execute([$productId]);
            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Data produk tidak ditemukan.");
            }

            $stokLama = (int) $prod['current_stock'];

            if ($modeUpdate === 'set') {
                if ($jumlahInput < 0) {
                    throw new Exception("Stok akhir tidak boleh kurang dari 0.");
                }
                $stokBaru       = $jumlahInput;
                $selisihTambah  = max(0, $stokBaru - $stokLama);
            } else {
                if ($jumlahInput <= 0) {
                    throw new Exception("Jumlah penambahan produksi harus lebih dari 0.");
                }
                $stokBaru       = $stokLama + $jumlahInput;
                $selisihTambah  = $jumlahInput;
            }

            // Pastikan produk memiliki SKU dan baris di stock_master
            $sku = $prod['sku'];
            if (empty($sku)) {
                $sku = 'PRD-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
                $pdo->prepare("UPDATE products SET sku = ? WHERE id = ?")->execute([$sku, $productId]);
            }

            if (!empty($prod['stock_row_id'])) {
                $pdo->prepare("UPDATE stock_master SET current_stock = ?, name = ? WHERE id = ?")
                    ->execute([$stokBaru, $prod['name'], (int) $prod['stock_row_id']]);
            } else {
                $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku, image_url) VALUES (?, 5, 'CUP', ?, ?, ?)")
                    ->execute([$stokBaru, $prod['name'], $sku, $prod['image_url']]);
            }

            // Potong otomatis bahan baku di stock_master jika stok produk bertambah
            $logBahan = [];
            if ($selisihTambah > 0) {
                $logBahan = potongBahanBakuDariHppKategori($pdo, $productId, $selisihTambah);
            }

            $pdo->commit();

            $infoPotong = count($logBahan) > 0
                ? "<br><small style='font-weight:500;'>Bahan baku otomatis berkurang: <strong>" . htmlspecialchars(implode(', ', $logBahan)) . "</strong></small>"
                : "<br><small style='font-weight:500;'>Catatan: Menu ini belum memiliki resep HPP atau stok tidak bertambah.</small>";

            $notif_sukses = "Stok <strong>" . htmlspecialchars($prod['name']) . "</strong> berhasil diperbarui dari <strong>{$stokLama}</strong> menjadi <strong>{$stokBaru} CUP</strong>.{$infoPotong}";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $notif_error = $e->getMessage();
        }
    }
}

// =========================================================================
// QUERY DATA PRODUK + RESEP HPP TERHUBUNG
// =========================================================================
$query = "SELECT 
            p.id, 
            p.sku,
            p.name, 
            p.description, 
            p.image_url, 
            p.price,
            c.name AS category_name,
            COALESCE(s.current_stock, 0) AS current_stock,
            (SELECT COUNT(*) FROM product_hpp_items h WHERE h.product_id = p.id) AS total_resep
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN stock_master s ON p.sku = s.sku AND p.sku != ''
          ORDER BY p.id DESC";
$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil detail resep HPP per produk untuk simulasi langsung di modal
$hppByProduct = [];
try {
    $stmtAllHpp = $pdo->query("
        SELECT h.product_id, h.nama_bahan, h.takaran, h.qty_pemakaian,
               COALESCE(s.current_stock, s_nm.current_stock, 0) AS stok_bahan,
               COALESCE(s.unit, s_nm.unit, '') AS unit_bahan
        FROM product_hpp_items h
        LEFT JOIN stock_master s ON h.stock_id = s.id
        LEFT JOIN stock_master s_nm ON h.stock_id IS NULL AND UPPER(TRIM(h.nama_bahan)) = UPPER(TRIM(s_nm.name))
        ORDER BY h.id ASC
    ");
    while ($rHpp = $stmtAllHpp->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int) $rHpp['product_id'];
        $qtyCup = (float) ($rHpp['qty_pemakaian'] ?? 0);
        if ($qtyCup <= 0 && !empty($rHpp['takaran'])) {
            $qtyCup = (float) preg_replace('/[^0-9.]/', '', $rHpp['takaran']);
        }
        $hppByProduct[$pid][] = [
            'nama'       => $rHpp['nama_bahan'],
            'qty_cup'    => $qtyCup,
            'stok_bahan' => (float) ($rHpp['stok_bahan'] ?? 0),
            'unit'       => $rHpp['unit_bahan'] ?: 'UNIT'
        ];
    }
} catch (PDOException $e) {
    $hppByProduct = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="kategori.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-qty-prod {
            background-color: #2e7d32;
            color: #fff;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            transition: 0.2s;
        }
        .btn-qty-prod:hover { background-color: #1b5e20; }

        .hpp-badge-ok {
            display: inline-block;
            margin-top: 4px;
            font-size: 10.5px;
            font-weight: 700;
            color: #2e7d32;
            background: #e8f5e9;
            padding: 2px 7px;
            border-radius: 8px;
        }
        .hpp-badge-empty {
            display: inline-block;
            margin-top: 4px;
            font-size: 10.5px;
            font-weight: 600;
            color: #e65100;
            background: #fff3e0;
            padding: 2px 7px;
            border-radius: 8px;
        }

        .qty-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .qty-modal-overlay.active { display: flex; }
        .qty-modal-box {
            background: #fff;
            width: 100%;
            max-width: 500px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
        }
        .qty-modal-header {
            background: #2b170e;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .qty-modal-header h3 { font-size: 15px; margin: 0; }
        .qty-modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }
        .qty-modal-body { padding: 20px; }
        .qty-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }
        .qty-field label {
            font-size: 12px;
            font-weight: 700;
            color: #2b170e;
        }
        .qty-field select, .qty-field input {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        .simulasi-hpp-box {
            background: #faf6f3;
            border: 1px solid #dfd3cb;
            border-left: 4px solid #2e7d32;
            border-radius: 9px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #2b170e;
        }
        .simulasi-hpp-box ul {
            margin: 6px 0 0 0;
            padding-left: 18px;
        }
        .simulasi-hpp-box li { margin-bottom: 4px; }
        .qty-modal-footer {
            padding: 14px 20px;
            background: #fafafa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
        </div>
    </header>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>
            <?php renderSidebarMenuErp('kategori.php', $currentUserRole, $allowedMenus); ?>
        </aside>

        <!-- KONTEN UTAMA -->
        <main class="content-area">
            
            <!-- NOTIFIKASI -->
            <?php if ($notif_sukses !== ''): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600;">
                    <i class="fa-solid fa-circle-check"></i> <?= $notif_sukses ?>
                </div>
            <?php endif; ?>

            <?php if ($notif_error !== ''): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-weight: 600;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($notif_error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> Produk baru berhasil ditambahkan!
                    </div>
                <?php elseif ($_GET['status'] === 'updated'): ?>
                    <div style="background-color: #cce5ff; color: #004085; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #b8daff; font-weight: 600;">
                        <i class="fa-solid fa-pen"></i> Produk & stok berhasil diperbarui!
                    </div>
                <?php elseif ($_GET['status'] === 'deleted'): ?>
                    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-weight: 600;">
                        <i class="fa-solid fa-trash"></i> Produk berhasil dihapus!
                    </div>
                <?php elseif ($_GET['status'] === 'error'): ?>
                    <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeeba; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Terjadi kesalahan saat memproses data produk.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- BANNER COKLAT -->
            <div class="control-card">
                <div class="control-header">
                    <div class="title-section">
                        <h2>Manajemen Daftar Produk</h2>
                        <p>Kelola daftar produk & update quantity produksi (otomatis memotong bahan baku di Stok Master sesuai resep HPP)</p>
                    </div>
                    <a href="tambah_produk.php" class="btn-add">
                        <i class="fa-solid fa-plus"></i> Tambah Produk
                    </a>
                </div>
                
                <div class="search-section">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Cari berdasarkan nama produk atau kategori..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>

            <!-- TABEL PRODUK -->
            <div class="table-wrapper">
                <table class="custom-table" id="produkTable">
                    <thead>
                        <tr>
                            <th class="col-foto">Foto</th>
                            <th class="col-nama">Nama Produk</th>
                            <th class="col-desc">Deskripsi</th>
                            <th class="col-kategori">Kategori</th>
                            <th class="col-stock">Stok (CUP)</th>
                            <th class="col-harga">Harga Satuan</th>
                            <th class="col-edit">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $item): ?>
                                <?php $hasResep = ((int)$item['total_resep'] > 0); ?>
                                <tr class="produk-row">
                                    <td class="td-foto">
                                        <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/50' ?>" 
                                             alt="Foto" 
                                             class="img-produk" 
                                             onerror="this.src='https://via.placeholder.com/50'">
                                    </td>
                                    <td class="td-nama">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                        <?php if ($hasResep): ?>
                                            <span class="hpp-badge-ok"><i class="fa-solid fa-link"></i> Terhubung HPP (<?= (int)$item['total_resep'] ?> bahan)</span>
                                        <?php else: ?>
                                            <span class="hpp-badge-empty"><i class="fa-solid fa-triangle-exclamation"></i> Belum ada resep HPP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-desc"><?= !empty($item['description']) ? htmlspecialchars($item['description']) : '-' ?></td>
                                    <td class="td-kategori"><?= htmlspecialchars($item['category_name'] ?? 'Umum') ?></td>
                                    <td class="td-stock"><strong><?= (int)$item['current_stock'] ?></strong></td>
                                    <td class="td-harga">Rp <?= number_format((float)$item['price'], 0, ',', '.') ?></td>
                                    <td class="td-edit">
                                        <div class="action-box">
                                            <button type="button"
                                                    class="btn-qty-prod"
                                                    title="Tambah / Update Stok Produksi (Potong Bahan HPP)"
                                                    onclick="openUpdateQtyModal(<?= (int)$item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)$item['current_stock'] ?>)">
                                                <i class="fa-solid fa-plus"></i> Stok
                                            </button>
                                            <a href="tambah_produk.php?id=<?= (int)$item['id'] ?>" class="btn-edit" title="Edit Produk">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="hapus_produk.php?id=<?= (int)$item['id'] ?>" 
                                               class="btn-delete" 
                                               title="Hapus Produk" 
                                               onclick="return confirm('Yakin ingin menghapus produk <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noSearchResultRow" style="display: none;">
                                <td colspan="7" style="text-align:center; padding: 30px; color: #777;">
                                    Produk yang dicari tidak ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 30px;">Belum ada data produk.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- MODAL UPDATE QUANTITY PRODUK & SIMULASI POTONG BAHAN HPP -->
    <div id="modalUpdateQty" class="qty-modal-overlay">
        <div class="qty-modal-box">
            <div class="qty-modal-header">
                <h3 id="modalQtyTitle"><i class="fa-solid fa-mug-hot"></i> Update Quantity Produk</h3>
                <button type="button" class="qty-modal-close" onclick="closeUpdateQtyModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi_update_qty_produk" value="1">
                <input type="hidden" name="product_id" id="modalQtyProductId" value="0">
                <div class="qty-modal-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="qty-field">
                            <label>Metode Update Stok</label>
                            <select name="mode_qty" id="modalModeQty" onchange="updateSimulasiQty()">
                                <option value="tambah">Tambah Produksi (+ CUP)</option>
                                <option value="set">Atur Total Stok (= CUP)</option>
                            </select>
                        </div>
                        <div class="qty-field">
                            <label id="modalLabelInputQty">Jumlah Tambah (CUP)</label>
                            <input type="number" name="jumlah_qty" id="modalInputQty" min="1" value="1" oninput="updateSimulasiQty()" required>
                        </div>
                    </div>

                    <div class="simulasi-hpp-box" id="modalSimulasiBox">
                        Memuat simulasi pemakaian bahan baku...
                    </div>
                </div>
                <div class="qty-modal-footer">
                    <button type="button" onclick="closeUpdateQtyModal()" style="padding:8px 14px; border:1px solid #ccc; background:#fff; border-radius:8px; cursor:pointer;">Batal</button>
                    <button type="submit" id="btnSimpanQtyModal" style="padding:8px 16px; border:none; background:#2b170e; color:#fff; border-radius:8px; font-weight:700; cursor:pointer;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan & Potong Bahan
                    </button>
                </div>
            </form>
        </div>
    </div>

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
        const hppResepMap = <?= json_encode($hppByProduct, JSON_UNESCAPED_UNICODE) ?>;
        let currentModalProductId = 0;
        let currentModalStokLama  = 0;

        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#produkTable tbody tr.produk-row');
            const noResultRow = document.getElementById('noSearchResultRow');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(input)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResultRow) {
                noResultRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
            }
        }

        function openUpdateQtyModal(productId, productName, stokLama) {
            currentModalProductId = parseInt(productId) || 0;
            currentModalStokLama  = parseInt(stokLama) || 0;

            document.getElementById('modalQtyProductId').value = currentModalProductId;
            document.getElementById('modalQtyTitle').innerHTML = `<i class="fa-solid fa-mug-hot"></i> Update Stok: ${productName}`;
            document.getElementById('modalModeQty').value = 'tambah';
            document.getElementById('modalInputQty').value = 1;

            updateSimulasiQty();
            document.getElementById('modalUpdateQty').classList.add('active');
        }

        function closeUpdateQtyModal() {
            document.getElementById('modalUpdateQty').classList.remove('active');
        }

        function updateSimulasiQty() {
            const mode     = document.getElementById('modalModeQty').value;
            const inputEl  = document.getElementById('modalInputQty');
            const labelEl  = document.getElementById('modalLabelInputQty');
            const boxEl    = document.getElementById('modalSimulasiBox');
            const btnSave  = document.getElementById('btnSimpanQtyModal');

            const val = parseInt(inputEl.value) || 0;
            let stokBaru = currentModalStokLama;
            let tambahCup = 0;

            if (mode === 'set') {
                labelEl.textContent = 'Stok Akhir Produk (CUP)';
                inputEl.min = '0';
                stokBaru = Math.max(0, val);
                tambahCup = Math.max(0, stokBaru - currentModalStokLama);
            } else {
                labelEl.textContent = 'Jumlah Tambah Produksi (CUP)';
                inputEl.min = '1';
                tambahCup = Math.max(0, val);
                stokBaru = currentModalStokLama + tambahCup;
            }

            const resep = hppResepMap[currentModalProductId] || [];
            let html = `Stok Produk: <strong>${currentModalStokLama} CUP</strong> &rarr; <strong style="color:#2e7d32;">${stokBaru} CUP</strong>`;

            if (resep.length === 0) {
                html += `<div style="margin-top:8px; color:#e65100;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Produk ini belum memiliki resep HPP di <b>Laporan Penjualan & HPP</b>, sehingga stok bahan baku tidak otomatis berkurang.
                         </div>`;
                btnSave.disabled = false;
                btnSave.style.opacity = '1';
            } else if (tambahCup <= 0) {
                html += `<div style="margin-top:8px; color:#666;">Tidak ada penambahan produksi, bahan baku tidak dikurangi.</div>`;
                btnSave.disabled = false;
                btnSave.style.opacity = '1';
            } else {
                let adaKurang = false;
                html += `<div style="margin-top:8px; font-weight:700;">Otomatis memotong bahan baku di Stok Master:</div><ul>`;
                resep.forEach(item => {
                    const butuh = item.qty_cup * tambahCup;
                    const sisa  = item.stok_bahan - butuh;
                    if (sisa < 0) {
                        adaKurang = true;
                        html += `<li style="color:#c62828;">
                                    <b>${item.nama}</b>: butuh ${butuh.toLocaleString('id-ID')} ${item.unit} (Tersedia: ${item.stok_bahan.toLocaleString('id-ID')} ${item.unit} &mdash; <b>STOK KURANG!</b>)
                                 </li>`;
                    } else {
                        html += `<li>
                                    <b>${item.nama}</b>: -${butuh.toLocaleString('id-ID')} ${item.unit}
                                    <span style="color:#2e7d32;">(Sisa: ${sisa.toLocaleString('id-ID')} ${item.unit})</span>
                                 </li>`;
                    }
                });
                html += `</ul>`;

                btnSave.disabled = adaKurang;
                btnSave.style.opacity = adaKurang ? '0.5' : '1';
            }

            boxEl.innerHTML = html;
        }

        document.addEventListener('click', function (e) {
            const modal = document.getElementById('modalUpdateQty');
            if (e.target === modal) {
                closeUpdateQtyModal();
            }
        });
    </script>
</body>
</html>