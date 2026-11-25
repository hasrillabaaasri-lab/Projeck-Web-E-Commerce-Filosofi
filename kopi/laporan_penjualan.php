<?php
require_once 'auth_erp.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

// Pastikan kolom penghubung bahan baku (stock_id & qty_pemakaian) tersedia di product_hpp_items
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_hpp_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `stock_id` INT(11) DEFAULT NULL,
            `nama_bahan` VARCHAR(150) NOT NULL,
            `takaran` VARCHAR(50) DEFAULT '-',
            `qty_pemakaian` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `biaya` DECIMAL(12,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_hpp_product` (`product_id`),
            KEY `idx_hpp_stock` (`stock_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    $hppCols = $pdo->query("SHOW COLUMNS FROM `product_hpp_items`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('stock_id', $hppCols, true)) {
        $pdo->exec("ALTER TABLE `product_hpp_items` ADD COLUMN `stock_id` INT(11) DEFAULT NULL AFTER `product_id`");
    }
    if (!in_array('qty_pemakaian', $hppCols, true)) {
        $pdo->exec("ALTER TABLE `product_hpp_items` ADD COLUMN `qty_pemakaian` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `takaran`");
    }
} catch (PDOException $e) {
    // Abaikan jika struktur tabel sudah sesuai
}

// Validasi format tanggal filter
$start_date = (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']))
    ? $_GET['start_date']
    : date('Y-m-01');

$end_date = (isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']))
    ? $_GET['end_date']
    : date('Y-m-d');

if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

// Daftar bahan baku dari stock_master (yang bukan produk minuman jadi)
$bahan_baku_list = $pdo->query("
    SELECT s.id, s.name, s.sku, s.current_stock, s.unit
    FROM stock_master s
    LEFT JOIN products p ON p.sku = s.sku
    WHERE p.id IS NULL
    ORDER BY s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$stockById   = [];
$stockByName = [];
foreach ($bahan_baku_list as $bb) {
    $stockById[(int)$bb['id']] = $bb;
    $stockByName[strtoupper(trim($bb['name']))] = $bb;
}

// Fungsi hitung data ringkasan, HPP, & grafik bulat real-time
function hitungDataLaporanRealTime(PDO $pdo, string $start_date, string $end_date, array $stockById = [], array $stockByName = []): array {
    $start_sql = $start_date . ' 00:00:00';
    $end_sql   = $end_date . ' 23:59:59';

    $sql_summary = "SELECT COUNT(id) AS total_transaksi, COALESCE(SUM(total_amount), 0) AS total_pendapatan 
                    FROM orders 
                    WHERE order_date BETWEEN ? AND ? 
                    AND status NOT IN ('Batal', 'Dihentikan')";
    $stmt = $pdo->prepare($sql_summary);
    $stmt->execute([$start_sql, $end_sql]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_transaksi  = (int) ($summary['total_transaksi'] ?? 0);
    $total_pendapatan = (float) ($summary['total_pendapatan'] ?? 0);

    $hpp_items_by_product = [];
    $stmtHppAll = $pdo->query("SELECT id, product_id, stock_id, nama_bahan, takaran, qty_pemakaian, biaya FROM product_hpp_items ORDER BY id ASC");
    while ($rowHpp = $stmtHppAll->fetch(PDO::FETCH_ASSOC)) {
        $pid     = (int) $rowHpp['product_id'];
        $stockId = !empty($rowHpp['stock_id']) ? (int) $rowHpp['stock_id'] : 0;
        $nmUpper = strtoupper(trim($rowHpp['nama_bahan']));

        if ($stockId === 0 && isset($stockByName[$nmUpper])) {
            $stockId = (int) $stockByName[$nmUpper]['id'];
        }

        $unit       = $stockId > 0 && isset($stockById[$stockId]) ? $stockById[$stockId]['unit'] : '';
        $qtyPakai   = (float) ($rowHpp['qty_pemakaian'] ?? 0);
        if ($qtyPakai <= 0 && !empty($rowHpp['takaran'])) {
            $qtyPakai = (float) preg_replace('/[^0-9.]/', '', $rowHpp['takaran']);
        }

        $hpp_items_by_product[$pid][] = [
            'stock_id'      => $stockId,
            'nama'          => $rowHpp['nama_bahan'],
            'takaran'       => $rowHpp['takaran'],
            'qty_pemakaian' => $qtyPakai,
            'unit'          => $unit,
            'harga'         => (float) $rowHpp['biaya'],
        ];
    }

    $sql_prod_sales = "SELECT 
                            p.id AS product_id,
                            p.name AS nama_menu,
                            p.price AS harga_katalog,
                            CASE 
                                WHEN p.price > 1 THEN p.price 
                                ELSE COALESCE(
                                    MAX(CASE 
                                        WHEN o.id IS NOT NULL AND o.status NOT IN ('Batal', 'Dihentikan') AND oi.price_at_purchase > 1 
                                        THEN oi.price_at_purchase ELSE NULL 
                                    END),
                                    (SELECT oi2.price_at_purchase 
                                     FROM order_items oi2 
                                     JOIN orders o2 ON o2.id = oi2.order_id 
                                     WHERE oi2.product_id = p.id 
                                       AND o2.status NOT IN ('Batal', 'Dihentikan') 
                                       AND oi2.price_at_purchase > 1 
                                     ORDER BY oi2.id DESC LIMIT 1),
                                    p.price
                                )
                            END AS harga_jual_efektif,
                            COALESCE(SUM(CASE 
                                WHEN o.id IS NOT NULL AND o.status NOT IN ('Batal', 'Dihentikan') 
                                THEN oi.quantity ELSE 0 
                            END), 0) AS qty_terjual,
                            COALESCE(SUM(CASE 
                                WHEN o.id IS NOT NULL AND o.status NOT IN ('Batal', 'Dihentikan') 
                                THEN (oi.quantity * oi.price_at_purchase) ELSE 0 
                            END), 0) AS omzet_produk
                       FROM products p
                       LEFT JOIN order_items oi ON oi.product_id = p.id
                       LEFT JOIN orders o ON o.id = oi.order_id AND o.order_date BETWEEN ? AND ?
                       GROUP BY p.id, p.name, p.price
                       ORDER BY p.id ASC";
    $stmt = $pdo->prepare($sql_prod_sales);
    $stmt->execute([$start_sql, $end_sql]);

    $master_hpp_menu   = [];
    $total_hpp_terjual = 0;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int) $r['product_id'];
        if (!isset($hpp_items_by_product[$pid]) && (int)$r['qty_terjual'] === 0) {
            continue;
        }

        $bahanList    = $hpp_items_by_product[$pid] ?? [];
        $total_hpp    = 0;
        $namaBahanArr = [];
        foreach ($bahanList as $b) {
            $total_hpp += $b['harga'];
            $namaBahanArr[] = ucwords(strtolower($b['nama']));
        }

        $harga_jual   = (float) $r['harga_jual_efektif'];
        $qty_terjual  = (int) $r['qty_terjual'];
        $omzet_produk = (float) $r['omzet_produk'];

        $margin_rp   = ($harga_jual > 0) ? ($harga_jual - $total_hpp) : 0;
        $margin_pct  = ($harga_jual > 0) ? (($margin_rp / $harga_jual) * 100) : 0;
        $hpp_terjual = $qty_terjual * $total_hpp;
        $laba_kotor  = ($omzet_produk > 0) ? ($omzet_produk - $hpp_terjual) : 0;

        $total_hpp_terjual += $hpp_terjual;

        $master_hpp_menu[] = [
            'product_id'      => $pid,
            'nama_menu'       => $r['nama_menu'],
            'komposisi_utama' => count($namaBahanArr) > 0 ? implode(', ', $namaBahanArr) : 'Belum ada rincian bahan',
            'bahan'           => $bahanList,
            'total_hpp'       => $total_hpp,
            'harga_jual'      => $harga_jual,
            'margin_rp'       => $margin_rp,
            'margin_pct'      => $margin_pct,
            'qty_terjual'     => $qty_terjual,
            'laba_kotor'      => $laba_kotor,
        ];
    }

    $estimasi_laba_kotor = $total_pendapatan - $total_hpp_terjual;
    $persen_laba_kotor   = ($total_pendapatan > 0) ? (($estimasi_laba_kotor / $total_pendapatan) * 100) : 0;

    return [
        'total_pendapatan'     => $total_pendapatan,
        'total_pendapatan_fmt' => formatRupiah($total_pendapatan),
        'total_hpp_terjual'    => $total_hpp_terjual,
        'total_hpp_fmt'        => formatRupiah($total_hpp_terjual),
        'estimasi_laba_kotor'  => $estimasi_laba_kotor,
        'laba_kotor_fmt'       => formatRupiah($estimasi_laba_kotor),
        'persen_laba_kotor'    => number_format($persen_laba_kotor, 1, ',', '.'),
        'total_transaksi'      => $total_transaksi,
        'transaksi_fmt'        => number_format($total_transaksi, 0, ',', '.'),
        'master_hpp_menu'      => $master_hpp_menu,
        'donut_labels'         => [
            'Total Pendapatan',
            'Total HPP Terjual',
            'Estimasi Laba Kotor',
            'Transaksi Berhasil',
        ],
        'donut_values'         => [
            max(0, $total_pendapatan),
            max(0, $total_hpp_terjual),
            max(0, $estimasi_laba_kotor),
            max(0, $total_transaksi),
        ],
    ];
}

// Endpoint AJAX real-time untuk grafik bulat & ringkasan
if (isset($_GET['ajax_laporan_realtime'])) {
    header('Content-Type: application/json');
    $live = hitungDataLaporanRealTime($pdo, $start_date, $end_date, $stockById, $stockByName);
    echo json_encode([
        'total_pendapatan_fmt' => $live['total_pendapatan_fmt'],
        'total_hpp_fmt'        => $live['total_hpp_fmt'],
        'laba_kotor_fmt'       => $live['laba_kotor_fmt'],
        'persen_laba_kotor'    => $live['persen_laba_kotor'],
        'transaksi_fmt'        => $live['transaksi_fmt'],
        'donut_labels'         => $live['donut_labels'],
        'donut_values'         => $live['donut_values'],
    ]);
    exit;
}

// =========================================================================
// 1. PROSES SIMPAN / UPDATE / HAPUS HPP MENU KE DATABASE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_hpp'])) {
    $redirectUrl = "laporan_penjualan.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date);

    if ($_POST['action_hpp'] === 'save') {
        $mode_menu  = $_POST['mode_menu'] ?? 'existing';
        $harga_jual = (float) preg_replace('/[^0-9.]/', '', $_POST['harga_jual'] ?? '0');
        $stock_ids  = $_POST['stock_id'] ?? [];
        $bahan_nama = $_POST['nama_bahan'] ?? [];
        $bahan_qty  = $_POST['qty_pemakaian'] ?? [];
        $bahan_rp   = $_POST['biaya'] ?? [];

        try {
            $pdo->beginTransaction();

            if ($mode_menu === 'new') {
                $nama_baru   = trim($_POST['nama_menu_baru'] ?? '');
                $category_id = (int) ($_POST['category_id'] ?? 6);
                if ($nama_baru === '') {
                    throw new Exception('Nama menu baru tidak boleh kosong.');
                }

                $stmtCek = $pdo->prepare("SELECT id, sku FROM products WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                $stmtCek->execute([$nama_baru]);
                $existingProd = $stmtCek->fetch(PDO::FETCH_ASSOC);

                if ($existingProd) {
                    $product_id = (int) $existingProd['id'];
                    $stmtUpd = $pdo->prepare("UPDATE products SET price = ?, category_id = ? WHERE id = ?");
                    $stmtUpd->execute([$harga_jual, $category_id, $product_id]);
                } else {
                    $sku = 'PRD-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
                    $stmtIns = $pdo->prepare("INSERT INTO products (category_id, name, sku, price, description) VALUES (?, ?, ?, ?, ?)");
                    $stmtIns->execute([$category_id, $nama_baru, $sku, $harga_jual, 'Menu baru dari pengaturan HPP']);
                    $product_id = (int) $pdo->lastInsertId();

                    // Sinkronkan otomatis sebagai minuman jadi ke stock_master
                    $stmtStock = $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku) VALUES (0, 5, 'CUP', ?, ?)");
                    $stmtStock->execute([$nama_baru, $sku]);
                }
            } else {
                $product_id = (int) ($_POST['product_id'] ?? 0);
                if ($product_id <= 0) {
                    throw new Exception('Pilih menu terlebih dahulu.');
                }
                $stmtUpd = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
                $stmtUpd->execute([$harga_jual, $product_id]);

                // Pastikan produk ini sudah terdaftar di stock_master sebagai minuman jadi
                $stmtProdInfo = $pdo->prepare("SELECT name, sku FROM products WHERE id = ?");
                $stmtProdInfo->execute([$product_id]);
                $pInfo = $stmtProdInfo->fetch(PDO::FETCH_ASSOC);
                if ($pInfo && !empty($pInfo['sku'])) {
                    $stmtCekSm = $pdo->prepare("SELECT id FROM stock_master WHERE sku = ? LIMIT 1");
                    $stmtCekSm->execute([$pInfo['sku']]);
                    if (!$stmtCekSm->fetchColumn()) {
                        $stmtInsSm = $pdo->prepare("INSERT INTO stock_master (current_stock, min_stock, unit, name, sku) VALUES (0, 5, 'CUP', ?, ?)");
                        $stmtInsSm->execute([$pInfo['name'], $pInfo['sku']]);
                    }
                }
            }

            // Perbarui rincian bahan baku di product_hpp_items (terhubung ke stock_master)
            $stmtDel = $pdo->prepare("DELETE FROM product_hpp_items WHERE product_id = ?");
            $stmtDel->execute([$product_id]);

            $stmtInsItem = $pdo->prepare("
                INSERT INTO product_hpp_items (product_id, stock_id, nama_bahan, takaran, qty_pemakaian, biaya)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $count = max(count($stock_ids), count($bahan_nama));
            for ($i = 0; $i < $count; $i++) {
                $sid = (int) ($stock_ids[$i] ?? 0);
                $qty = (float) ($bahan_qty[$i] ?? 0);
                $by  = (float) preg_replace('/[^0-9.]/', '', $bahan_rp[$i] ?? '0');

                if ($sid > 0 && isset($stockById[$sid])) {
                    $nm   = strtoupper(trim($stockById[$sid]['name']));
                    $unit = strtoupper(trim($stockById[$sid]['unit'] ?: 'UNIT'));
                    $qtyFmt = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
                    $tk   = ($qtyFmt !== '' ? $qtyFmt : '0') . ' ' . $unit;
                    $stmtInsItem->execute([$product_id, $sid, $nm, $tk, $qty, $by]);
                } else {
                    $nm = strtoupper(trim($bahan_nama[$i] ?? ''));
                    if ($nm !== '') {
                        $matchedSid = isset($stockByName[$nm]) ? (int)$stockByName[$nm]['id'] : null;
                        $unit       = $matchedSid && isset($stockById[$matchedSid]) ? strtoupper($stockById[$matchedSid]['unit']) : '';
                        $qtyFmt     = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
                        $tk         = ($qty > 0) ? trim($qtyFmt . ' ' . $unit) : '-';
                        $stmtInsItem->execute([$product_id, $matchedSid, $nm, $tk, $qty, $by]);
                    }
                }
            }

            $pdo->commit();
            header("Location: " . $redirectUrl . "&hpp_status=saved");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: " . $redirectUrl . "&hpp_status=error&msg=" . urlencode($e->getMessage()));
            exit;
        }
    } elseif ($_POST['action_hpp'] === 'delete') {
        $del_product_id = (int) ($_POST['product_id'] ?? 0);
        if ($del_product_id > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM product_hpp_items WHERE product_id = ?");
            $stmtDel->execute([$del_product_id]);
        }
        header("Location: " . $redirectUrl . "&hpp_status=deleted");
        exit;
    }
}

// =========================================================================
// 2 & 3. DATA RINGKASAN, HPP, & GRAFIK BULAT REAL-TIME
// =========================================================================
$categories   = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_products = $pdo->query("SELECT id, name, price, category_id FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$dataLap             = hitungDataLaporanRealTime($pdo, $start_date, $end_date, $stockById, $stockByName);
$total_transaksi     = $dataLap['total_transaksi'];
$total_pendapatan    = $dataLap['total_pendapatan'];
$total_hpp_terjual   = $dataLap['total_hpp_terjual'];
$estimasi_laba_kotor = $dataLap['estimasi_laba_kotor'];
$persen_laba_kotor   = (float) str_replace(',', '.', $dataLap['persen_laba_kotor']);
$master_hpp_menu     = $dataLap['master_hpp_menu'];
$donut_labels        = $dataLap['donut_labels'];
$donut_values        = $dataLap['donut_values'];

// =========================================================================
// 4. QUERY RINCIAN TRANSAKSI PERIODE TERPILIH
// =========================================================================
$start_sql = $start_date . ' 00:00:00';
$end_sql   = $end_date . ' 23:59:59';

$sql_orders = "SELECT o.*, u.name AS customer_name, u.email AS customer_email 
               FROM orders o 
               LEFT JOIN users u ON o.user_id = u.id 
               WHERE o.order_date BETWEEN ? AND ? 
               ORDER BY o.order_date DESC";
$stmt = $pdo->prepare($sql_orders);
$stmt->execute([$start_sql, $end_sql]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan & HPP - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alert-notif {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error   { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        .filter-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            width: 100%;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: bold;
            color: #555;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }
        .btn-filter {
            background-color: #2b170e;
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-filter:hover { background-color: #4a2d1e; }

        .btn-print {
            background-color: #1976d2;
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { background-color: #1565c0; }

        .report-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-box {
            background: #fff;
            padding: 18px 20px;
            border-radius: 12px;
            border-left: 5px solid #2b170e;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .summary-box.hpp-box    { border-left-color: #e65100; }
        .summary-box.margin-box { border-left-color: #2e7d32; }
        .summary-box h4 {
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .summary-box .value {
            font-size: 21px;
            font-weight: bold;
            color: #111;
        }
        .summary-box .sub-value {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }

        .combined-donut-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            border: 1px solid #e6ddd6;
        }
        .combined-donut-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        .combined-donut-title {
            font-size: 15px;
            font-weight: 700;
            color: #2b170e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .realtime-pill {
            font-size: 11px;
            font-weight: 700;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .combined-donut-body {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: center;
        }
        .combined-donut-canvas-wrap {
            position: relative;
            width: 100%;
            height: 250px;
        }
        .manual-color-panel {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .color-control-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #faf7f5;
            border: 1px solid #e6ddd6;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .color-control-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .color-control-label {
            font-size: 12px;
            font-weight: 700;
            color: #2b170e;
        }
        .color-control-val {
            font-size: 12px;
            font-weight: 600;
            color: #555;
        }
        .color-picker-input {
            width: 34px;
            height: 34px;
            padding: 0;
            border: none;
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
        }
        .btn-reset-colors {
            margin-top: 12px;
            background: #f5f0ec;
            color: #2b170e;
            border: 1px solid #d5c7bc;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reset-colors:hover { background: #ebe2da; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-selesai      { background: #e8f5e9; color: #2e7d32; }
        .status-batal        { background: #ffebee; color: #c62828; }
        .status-pending      { background: #fff3e0; color: #e65100; }
        .status-dikembalikan { background: #eceff1; color: #455a64; }
        .status-lainnya      { background: #e3f2fd; color: #1565c0; }

        .section-banner-hpp {
            background: linear-gradient(90deg, #2b170e 0%, #4a2d1e 100%);
            color: #fff;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            border-left: 6px solid #f5b041;
        }
        .btn-add-hpp {
            background-color: #f5b041;
            color: #1c110a;
            border: none;
            padding: 7px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .btn-add-hpp:hover { background-color: #f8c471; }

        .btn-edit-sm {
            background: rgba(255,255,255,0.16);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 6px;
        }
        .btn-edit-sm:hover { background: #f5b041; color: #1c110a; }

        .hpp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 16px;
        }
        .hpp-product-card {
            border: 1px solid #e0d6cf;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            break-inside: avoid;
        }
        .hpp-product-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .hpp-product-table thead th {
            background-color: #1c110a;
            color: #fff;
            padding: 9px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
        }
        .hpp-product-table thead th:last-child { text-align: right; }
        .hpp-product-table tbody td {
            background-color: #fff6ed;
            color: #2b170e;
            padding: 7px 12px;
            border-bottom: 1px solid #f0dfd1;
            font-weight: 500;
        }
        .hpp-product-table tbody tr:nth-child(even) td { background-color: #ffedd9; }
        .hpp-product-table tbody td:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .hpp-product-table tfoot td {
            background-color: #e8f5e9;
            color: #1b5e20;
            font-weight: 800;
            padding: 9px 12px;
            border-top: 2px solid #81c784;
        }
        .hpp-product-table tfoot td:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .margin-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
        }
        .margin-good { background: #e8f5e9; color: #2e7d32; }
        .margin-low  { background: #fff3e0; color: #e65100; }
        .margin-none { background: #eceff1; color: #607d8b; }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            width: 100%;
            max-width: 720px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            background: #2b170e;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { font-size: 16px; margin: 0; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body {
            padding: 20px;
            overflow-y: auto;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 12px;
        }
        .form-field label {
            font-size: 12px;
            font-weight: 700;
            color: #333;
        }
        .form-field input, .form-field select {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
        }
        .bahan-row {
            display: grid;
            grid-template-columns: 2.2fr 1.3fr 1.2fr 36px;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
        }
        .bahan-row select, .bahan-row input {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 12.5px;
            width: 100%;
            background: #fff;
        }
        .qty-unit-wrap {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 7px;
            overflow: hidden;
            background: #fff;
        }
        .qty-unit-wrap input {
            border: none !important;
            border-radius: 0 !important;
            padding: 8px 8px !important;
            width: 100%;
            outline: none;
        }
        .unit-tag {
            background: #f3ece7;
            color: #2b170e;
            font-size: 11px;
            font-weight: 700;
            padding: 0 8px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            border-left: 1px solid #ddd;
            white-space: nowrap;
        }
        .btn-del-row {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            border-radius: 7px;
            height: 34px;
            cursor: pointer;
        }
        .btn-add-row {
            background: #f5f5f5;
            color: #2b170e;
            border: 1px dashed #999;
            padding: 7px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
        }
        .live-calc-bar {
            margin-top: 15px;
            padding: 12px 15px;
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 9px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            color: #2b170e;
        }
        .modal-footer {
            padding: 14px 20px;
            background: #fafafa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .print-only-header { display: none; }

        @media (max-width: 1024px) {
            .report-summary { grid-template-columns: repeat(2, 1fr); }
            .combined-donut-body { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .report-summary { grid-template-columns: 1fr; }
            .manual-color-panel { grid-template-columns: 1fr; }
            .btn-print { margin-left: 0; }
            .form-row-2 { grid-template-columns: 1fr; }
            .bahan-row { grid-template-columns: 1fr; }
        }
        @media print {
            .sidebar, .top-header, .filter-card, footer, .btn-print, .welcome-banner, .btn-add-hpp, .btn-edit-sm, .modal-overlay, .alert-notif, .color-picker-input, .btn-reset-colors {
                display: none !important;
            }
            .print-only-header {
                display: block;
                margin-bottom: 18px;
                padding-bottom: 12px;
                border-bottom: 2px solid #2b170e;
            }
            .print-only-header h2 { font-size: 18px; color: #2b170e; margin-bottom: 4px; }
            .print-only-header p  { font-size: 12px; color: #444; }
            .main-layout { display: block; }
            .content-area { padding: 0; background: #fff; }
            .card, .summary-box, .hpp-product-card, .combined-donut-card { box-shadow: none; border: 1px solid #ddd; }
            .hpp-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>
            <?php renderSidebarMenuErp('laporan_penjualan.php', $currentUserRole, $allowedMenus); ?>
        </aside>

        <main class="content-area">

            <div class="print-only-header">
                <h2>Laporan Penjualan & Ringkasan HPP - Filosofi Kopi</h2>
                <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> s/d <?= date('d/m/Y', strtotime($end_date)) ?> &bull; Dicetak oleh: <?= htmlspecialchars($currentUserName) ?> (<?= htmlspecialchars($currentUserRole) ?>)</p>
            </div>

            <?php if (isset($_GET['hpp_status'])): ?>
                <?php if ($_GET['hpp_status'] === 'saved'): ?>
                    <div class="alert-notif alert-success">
                        <i class="fa-solid fa-circle-check"></i> Data HPP & komposisi bahan baku berhasil dihubungkan ke Stok Master!
                    </div>
                <?php elseif ($_GET['hpp_status'] === 'deleted'): ?>
                    <div class="alert-notif alert-success">
                        <i class="fa-solid fa-trash"></i> Rincian HPP menu berhasil dihapus dari database.
                    </div>
                <?php elseif ($_GET['hpp_status'] === 'error'): ?>
                    <div class="alert-notif alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i> Gagal menyimpan: <?= htmlspecialchars($_GET['msg'] ?? 'Terjadi kesalahan') ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="welcome-banner" style="margin-bottom: 20px;">
                <h2>Laporan Penjualan & HPP</h2>
                <p>Atur resep HPP yang terhubung ke Stok Master agar bahan baku otomatis berkurang saat minuman jadi diproduksi.</p>
            </div>

            <div class="filter-card">
                <form action="" method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="filter-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter Data</button>
                    
                    <button type="button" class="btn-print" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Cetak Laporan
                    </button>
                </form>
            </div>

            <!-- RINGKASAN KPI -->
            <div class="report-summary">
                <div class="summary-box">
                    <h4>Total Pendapatan</h4>
                    <div class="value" id="kpiTotalPendapatan"><?= formatRupiah($total_pendapatan) ?></div>
                    <div class="sub-value">Periode <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></div>
                </div>
                <div class="summary-box hpp-box">
                    <h4>Total HPP Terjual</h4>
                    <div class="value" id="kpiTotalHpp"><?= formatRupiah($total_hpp_terjual) ?></div>
                    <div class="sub-value">Akumulasi biaya bahan produk terjual</div>
                </div>
                <div class="summary-box margin-box">
                    <h4>Estimasi Laba Kotor</h4>
                    <div class="value" id="kpiLabaKotor"><?= formatRupiah($estimasi_laba_kotor) ?></div>
                    <div class="sub-value" id="kpiMarginKotor">Margin Kotor: <?= number_format($persen_laba_kotor, 1, ',', '.') ?>%</div>
                </div>
                <div class="summary-box">
                    <h4>Transaksi Berhasil</h4>
                    <div class="value" id="kpiTransaksi"><?= number_format($total_transaksi, 0, ',', '.') ?></div>
                    <div class="sub-value">Status aktif / selesai</div>
                </div>
            </div>

            <!-- GRAFIK BULAT GABUNGAN REAL-TIME + UBAH WARNA MANUAL -->
            <div class="combined-donut-card">
                <div class="combined-donut-top">
                    <div class="combined-donut-title">
                        <i class="fa-solid fa-chart-pie"></i> Grafik Bulat Ringkasan Periode (Real-Time)
                    </div>
                    <span class="realtime-pill">
                        <i class="fa-solid fa-rotate"></i> Auto-Sync 5 Detik
                    </span>
                </div>

                <div class="combined-donut-body">
                    <div class="combined-donut-canvas-wrap">
                        <canvas id="combinedDonutChart"></canvas>
                    </div>

                    <div>
                        <div class="manual-color-panel">
                            <div class="color-control-item">
                                <div class="color-control-info">
                                    <span class="color-control-label">Total Pendapatan</span>
                                    <span class="color-control-val" id="legendVal0"><?= formatRupiah($total_pendapatan) ?></span>
                                </div>
                                <input type="color" class="color-picker-input" data-idx="0" id="colorPicker0" value="#2b170e" title="Ubah warna Total Pendapatan">
                            </div>

                            <div class="color-control-item">
                                <div class="color-control-info">
                                    <span class="color-control-label">Total HPP Terjual</span>
                                    <span class="color-control-val" id="legendVal1"><?= formatRupiah($total_hpp_terjual) ?></span>
                                </div>
                                <input type="color" class="color-picker-input" data-idx="1" id="colorPicker1" value="#e65100" title="Ubah warna Total HPP Terjual">
                            </div>

                            <div class="color-control-item">
                                <div class="color-control-info">
                                    <span class="color-control-label">Estimasi Laba Kotor</span>
                                    <span class="color-control-val" id="legendVal2"><?= formatRupiah($estimasi_laba_kotor) ?></span>
                                </div>
                                <input type="color" class="color-picker-input" data-idx="2" id="colorPicker2" value="#2e7d32" title="Ubah warna Estimasi Laba Kotor">
                            </div>

                            <div class="color-control-item">
                                <div class="color-control-info">
                                    <span class="color-control-label">Transaksi Berhasil</span>
                                    <span class="color-control-val" id="legendVal3"><?= number_format($total_transaksi, 0, ',', '.') ?></span>
                                </div>
                                <input type="color" class="color-picker-input" data-idx="3" id="colorPicker3" value="#1976d2" title="Ubah warna Transaksi Berhasil">
                            </div>
                        </div>

                        <button type="button" class="btn-reset-colors" id="btnResetColors">
                            <i class="fa-solid fa-palette"></i> Reset Warna Default
                        </button>
                    </div>
                </div>
            </div>

            <!-- =========================================================
                 BAGIAN 1: RINGKASAN HPP & MARGIN PER PORSI
                 ========================================================= -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="section-banner-hpp">
                    <span><i class="fa-solid fa-calculator"></i> Ringkasan HPP & Margin per Porsi (<?= count($master_hpp_menu) ?> Menu)</span>
                    <button type="button" class="btn-add-hpp" onclick="openHppModalNew()">
                        <i class="fa-solid fa-plus"></i> Tambah / Atur HPP Menu
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Menu</th>
                                <th>Komposisi Utama</th>
                                <th>HPP / Porsi</th>
                                <th>Harga Jual</th>
                                <th>Margin / Porsi</th>
                                <th>Margin (%)</th>
                                <th>Terjual</th>
                                <th>Total Laba Kotor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no_menu = 1; foreach ($master_hpp_menu as $m): ?>
                                <tr>
                                    <td><?= $no_menu++ ?></td>
                                    <td><b><?= htmlspecialchars($m['nama_menu']) ?></b></td>
                                    <td style="font-size: 12px; color: #555;"><?= htmlspecialchars($m['komposisi_utama']) ?></td>
                                    <td><b><?= formatRupiah($m['total_hpp']) ?></b></td>
                                    <td>
                                        <?= $m['harga_jual'] > 1 ? formatRupiah($m['harga_jual']) : '<span style="color:#e65100;font-size:11px;font-weight:600;">Belum diatur</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($m['harga_jual'] > 1): ?>
                                            <b style="color: <?= $m['margin_rp'] >= 0 ? '#2e7d32' : '#c62828' ?>;">
                                                <?= formatRupiah($m['margin_rp']) ?>
                                            </b>
                                        <?php else: ?>
                                            <span style="color:#999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['harga_jual'] > 1): ?>
                                            <span class="margin-pill <?= $m['margin_pct'] >= 50 ? 'margin-good' : 'margin-low' ?>">
                                                <?= number_format($m['margin_pct'], 1, ',', '.') ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="margin-pill margin-none">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($m['qty_terjual'], 0, ',', '.') ?> cup</td>
                                    <td><b><?= formatRupiah($m['laba_kotor']) ?></b></td>
                                    <td>
                                        <button type="button"
                                                style="background:#2b170e;color:#fff;border:none;padding:5px 10px;border-radius:6px;font-size:11px;cursor:pointer;"
                                                onclick='openHppModalEdit(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- =========================================================
                 BAGIAN 2: HPP PER PRODUK (RINCIAN KOMPOSISI BAHAN BAKU)
                 ========================================================= -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="section-banner-hpp">
                    <span><i class="fa-solid fa-mug-hot"></i> HPP PER PRODUK</span>
                    <button type="button" class="btn-add-hpp" onclick="openHppModalNew()">
                        <i class="fa-solid fa-plus"></i> Tambah Produk Baru
                    </button>
                </div>

                <div class="hpp-grid">
                    <?php foreach ($master_hpp_menu as $m): ?>
                        <div class="hpp-product-card">
                            <table class="hpp-product-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <?= htmlspecialchars($m['nama_menu']) ?>
                                            <button type="button" class="btn-edit-sm" title="Edit Komposisi & Harga"
                                                    onclick='openHppModalEdit(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        </th>
                                        <th>Komposisi</th>
                                        <th>Harga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($m['bahan']) > 0): ?>
                                        <?php foreach ($m['bahan'] as $b): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($b['nama']) ?>
                                                    <?php if (!empty($b['stock_id'])): ?>
                                                        <i class="fa-solid fa-link" style="color:#2e7d32;font-size:10px;" title="Terhubung ke Stok Master"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($b['takaran']) ?></td>
                                                <td><?= formatRupiah($b['harga']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align:center;color:#888;">Belum ada rincian bahan baku</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2">TOTAL HPP</td>
                                        <td><?= formatRupiah($m['total_hpp']) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- =========================================================
                 BAGIAN 3: RINCIAN TRANSAKSI PERIODE TERPILIH
                 ========================================================= -->
            <div class="card">
                <div class="card-title" style="margin-bottom: 15px; font-size: 16px;">
                    <i class="fa-solid fa-list"></i> Rincian Transaksi
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Kode Order</th>
                                <th>Pelanggan</th>
                                <th>Channel</th>
                                <th>Metode Bayar</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php $no = 1; foreach ($orders as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></td>
                                        <td><b><?= htmlspecialchars($row['order_code']) ?></b></td>
                                        <td><?= htmlspecialchars($row['customer_name'] ?? 'Guest') ?></td>
                                        <td><?= htmlspecialchars($row['channel']) ?></td>
                                        <td><?= htmlspecialchars($row['payment_method'] ?? '-') ?></td>
                                        <td>
                                            <?php 
                                                $status = $row['status'] ?? 'Pending';
                                                $badgeClass = 'status-lainnya';
                                                if ($status === 'Selesai') $badgeClass = 'status-selesai';
                                                elseif ($status === 'Batal' || $status === 'Dihentikan') $badgeClass = 'status-batal';
                                                elseif ($status === 'Pending') $badgeClass = 'status-pending';
                                                elseif ($status === 'Dikembalikan') $badgeClass = 'status-dikembalikan';
                                            ?>
                                            <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                        </td>
                                        <td><b><?= formatRupiah($row['total_amount']) ?></b></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #777;">
                                        Tidak ada data transaksi pada periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- =========================================================
         MODAL TAMBAH / EDIT HPP & HUBUNGKAN BAHAN BAKU STOK MASTER
         ========================================================= -->
    <div class="modal-overlay" id="hppModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-calculator"></i> Tambah / Edit HPP Menu</h3>
                <button type="button" class="modal-close" onclick="closeHppModal()">&times;</button>
            </div>
            <form method="POST" action="laporan_penjualan.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">
                <input type="hidden" name="action_hpp" id="actionHppInput" value="save">
                <div class="modal-body">
                    <div class="form-field">
                        <label>Pilih Sumber Menu</label>
                        <select name="mode_menu" id="modeMenuSelect" onchange="toggleMenuMode()">
                            <option value="existing">Pilih dari Daftar Produk di Database</option>
                            <option value="new">+ Buat Menu Baru Sekaligus</option>
                        </select>
                    </div>

                    <div id="existingMenuGroup" class="form-field">
                        <label>Nama Menu (Tabel Products)</label>
                        <select name="product_id" id="productIdSelect" onchange="syncSelectedPrice()">
                            <option value="">-- Pilih Menu --</option>
                            <?php foreach ($all_products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['price'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (Harga saat ini: <?= formatRupiah($p['price']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="newMenuGroup" style="display:none;">
                        <div class="form-row-2">
                            <div class="form-field" style="margin-bottom:0;">
                                <label>Nama Menu Baru</label>
                                <input type="text" name="nama_menu_baru" id="namaMenuBaru" placeholder="Contoh: Caramel Macchiato">
                            </div>
                            <div class="form-field" style="margin-bottom:0;">
                                <label>Kategori Menu</label>
                                <select name="category_id">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Harga Jual per Porsi (Rp)</label>
                        <input type="number" name="harga_jual" id="hargaJualInput" min="0" step="100" placeholder="Contoh: 25000" oninput="recalcModalHpp()" required>
                    </div>

                    <div class="form-field" style="margin-bottom:4px;">
                        <label>Komposisi Bahan Baku (Terhubung ke Stok Master)</label>
                        <div style="display:grid;grid-template-columns:2.2fr 1.3fr 1.2fr 36px;gap:8px;font-size:11px;color:#666;font-weight:700;margin-bottom:4px;">
                            <span>BAHAN BAKU (STOK MASTER)</span>
                            <span>TAKARAN / CUP</span>
                            <span>BIAYA (RP)</span>
                            <span></span>
                        </div>
                        <div id="bahanContainer"></div>
                        <button type="button" class="btn-add-row" onclick="addBahanRow(0, '', 0, 0)">
                            <i class="fa-solid fa-plus"></i> Tambah Baris Bahan
                        </button>
                    </div>

                    <div class="live-calc-bar">
                        <span>Total HPP: <span id="liveTotalHpp">Rp 0</span></span>
                        <span>Estimasi Margin: <span id="liveMargin">Rp 0 (0%)</span></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <div>
                        <button type="button" id="btnDeleteHpp" onclick="submitDeleteHpp()" style="display:none;padding:9px 14px;border:1px solid #ef9a9a;background:#ffebee;color:#c62828;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                            <i class="fa-solid fa-trash"></i> Hapus HPP
                        </button>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" onclick="closeHppModal()" style="padding:9px 16px;border:1px solid #ccc;background:#fff;border-radius:8px;cursor:pointer;">Batal</button>
                        <button type="submit" onclick="document.getElementById('actionHppInput').value='save'" class="btn-filter"><i class="fa-solid fa-floppy-disk"></i> Simpan ke Database</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const bahanBakuMaster = <?= json_encode($bahan_baku_list, JSON_UNESCAPED_UNICODE) ?>;

        document.addEventListener('DOMContentLoaded', function () {
            const COLOR_STORAGE_KEY = 'filosofi_kopi_donut_colors_v1';
            const DEFAULT_COLORS = ['#2b170e', '#e65100', '#2e7d32', '#1976d2'];

            function loadSavedColors() {
                try {
                    const raw = localStorage.getItem(COLOR_STORAGE_KEY);
                    if (raw) {
                        const parsed = JSON.parse(raw);
                        if (Array.isArray(parsed) && parsed.length === 4) {
                            return parsed;
                        }
                    }
                } catch (e) {}
                return [...DEFAULT_COLORS];
            }

            function saveColors(colors) {
                try {
                    localStorage.setItem(COLOR_STORAGE_KEY, JSON.stringify(colors));
                } catch (e) {}
            }

            let activeColors = loadSavedColors();
            const pickers = [
                document.getElementById('colorPicker0'),
                document.getElementById('colorPicker1'),
                document.getElementById('colorPicker2'),
                document.getElementById('colorPicker3')
            ];

            pickers.forEach((picker, idx) => {
                if (picker) picker.value = activeColors[idx];
            });

            const donutCanvas = document.getElementById('combinedDonutChart');
            const initialLabels = <?= json_encode($donut_labels, JSON_UNESCAPED_UNICODE) ?>;
            const initialValues = <?= json_encode($donut_values) ?>;
            let combinedChart = null;

            if (donutCanvas && typeof Chart !== 'undefined') {
                combinedChart = new Chart(donutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: initialLabels,
                        datasets: [{
                            data: initialValues,
                            backgroundColor: [...activeColors],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 11 }, boxWidth: 12 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const label = ctx.label || '';
                                        const val = Number(ctx.parsed || 0);
                                        if (ctx.dataIndex === 3) {
                                            return label + ': ' + val.toLocaleString('id-ID') + ' transaksi';
                                        }
                                        return label + ': Rp ' + val.toLocaleString('id-ID');
                                    }
                                }
                            }
                        }
                    }
                });
            }

            pickers.forEach((picker, idx) => {
                if (!picker) return;
                picker.addEventListener('input', function () {
                    activeColors[idx] = this.value;
                    saveColors(activeColors);
                    if (combinedChart) {
                        combinedChart.data.datasets[0].backgroundColor = [...activeColors];
                        combinedChart.update();
                    }
                });
            });

            const resetBtn = document.getElementById('btnResetColors');
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    activeColors = [...DEFAULT_COLORS];
                    saveColors(activeColors);
                    pickers.forEach((picker, idx) => {
                        if (picker) picker.value = activeColors[idx];
                    });
                    if (combinedChart) {
                        combinedChart.data.datasets[0].backgroundColor = [...activeColors];
                        combinedChart.update();
                    }
                });
            }

            const startDate = <?= json_encode($start_date) ?>;
            const endDate = <?= json_encode($end_date) ?>;

            function syncRealtimeReport() {
                const url = `laporan_penjualan.php?ajax_laporan_realtime=1&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (document.getElementById('kpiTotalPendapatan')) {
                            document.getElementById('kpiTotalPendapatan').textContent = data.total_pendapatan_fmt;
                        }
                        if (document.getElementById('kpiTotalHpp')) {
                            document.getElementById('kpiTotalHpp').textContent = data.total_hpp_fmt;
                        }
                        if (document.getElementById('kpiLabaKotor')) {
                            document.getElementById('kpiLabaKotor').textContent = data.laba_kotor_fmt;
                        }
                        if (document.getElementById('kpiMarginKotor')) {
                            document.getElementById('kpiMarginKotor').textContent = 'Margin Kotor: ' + data.persen_laba_kotor + '%';
                        }
                        if (document.getElementById('kpiTransaksi')) {
                            document.getElementById('kpiTransaksi').textContent = data.transaksi_fmt;
                        }
                        if (document.getElementById('legendVal0')) {
                            document.getElementById('legendVal0').textContent = data.total_pendapatan_fmt;
                        }
                        if (document.getElementById('legendVal1')) {
                            document.getElementById('legendVal1').textContent = data.total_hpp_fmt;
                        }
                        if (document.getElementById('legendVal2')) {
                            document.getElementById('legendVal2').textContent = data.laba_kotor_fmt;
                        }
                        if (document.getElementById('legendVal3')) {
                            document.getElementById('legendVal3').textContent = data.transaksi_fmt;
                        }
                        if (combinedChart && Array.isArray(data.donut_values)) {
                            combinedChart.data.datasets[0].data = data.donut_values;
                            combinedChart.data.datasets[0].backgroundColor = [...activeColors];
                            combinedChart.update();
                        }
                    })
                    .catch(() => {});
            }

            setInterval(syncRealtimeReport, 5000);
        });

        function formatIdr(num) {
            return 'Rp ' + Math.round(num).toLocaleString('id-ID');
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function toggleMenuMode() {
            const mode = document.getElementById('modeMenuSelect').value;
            document.getElementById('existingMenuGroup').style.display = (mode === 'existing') ? 'block' : 'none';
            document.getElementById('newMenuGroup').style.display = (mode === 'new') ? 'block' : 'none';
            document.getElementById('btnDeleteHpp').style.display = (mode === 'existing' && document.getElementById('productIdSelect').value) ? 'inline-block' : 'none';
        }

        function syncSelectedPrice() {
            const sel = document.getElementById('productIdSelect');
            const opt = sel.options[sel.selectedIndex];
            if (opt && opt.dataset.price) {
                document.getElementById('hargaJualInput').value = Math.round(parseFloat(opt.dataset.price));
                recalcModalHpp();
            }
            document.getElementById('btnDeleteHpp').style.display = sel.value ? 'inline-block' : 'none';
        }

        function onSelectBahanRow(selectEl) {
            const opt     = selectEl.options[selectEl.selectedIndex];
            const row     = selectEl.closest('.bahan-row');
            const unitTag = row.querySelector('.unit-tag');
            const nameInp = row.querySelector('.input-nama-bahan');

            if (opt && opt.value !== '0' && opt.value !== '') {
                unitTag.textContent = opt.dataset.unit || 'UNIT';
                nameInp.value = opt.dataset.name || '';
            } else {
                unitTag.textContent = 'UNIT';
            }
        }

        function addBahanRow(selectedStockId = 0, fallbackName = '', qtyPakai = 0, biaya = 0) {
            const container = document.getElementById('bahanContainer');
            const row = document.createElement('div');
            row.className = 'bahan-row';

            let matchedId = parseInt(selectedStockId) || 0;
            let activeUnit = 'UNIT';

            if (matchedId === 0 && fallbackName) {
                const found = bahanBakuMaster.find(b => b.name.trim().toUpperCase() === fallbackName.trim().toUpperCase());
                if (found) matchedId = parseInt(found.id);
            }

            let optionsHtml = `<option value="0">-- Pilih Bahan Baku --</option>`;
            bahanBakuMaster.forEach(b => {
                const isSel = parseInt(b.id) === matchedId;
                if (isSel) activeUnit = b.unit || 'UNIT';
                optionsHtml += `<option value="${b.id}" data-unit="${escapeHtml(b.unit || 'UNIT')}" data-name="${escapeHtml(b.name)}" ${isSel ? 'selected' : ''}>
                    ${escapeHtml(b.name)} (Stok: ${Number(b.current_stock).toLocaleString('id-ID')} ${escapeHtml(b.unit || '')})
                </option>`;
            });

            row.innerHTML = `
                <div>
                    <select name="stock_id[]" onchange="onSelectBahanRow(this)" required>
                        ${optionsHtml}
                    </select>
                    <input type="hidden" name="nama_bahan[]" class="input-nama-bahan" value="${escapeHtml(fallbackName)}">
                </div>
                <div class="qty-unit-wrap">
                    <input type="number" name="qty_pemakaian[]" value="${qtyPakai}" min="0" step="0.01" placeholder="0" required>
                    <span class="unit-tag">${escapeHtml(activeUnit)}</span>
                </div>
                <input type="number" name="biaya[]" class="input-biaya" value="${biaya}" min="0" step="1" oninput="recalcModalHpp()" required>
                <button type="button" class="btn-del-row" title="Hapus baris" onclick="this.parentElement.remove(); recalcModalHpp();">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            container.appendChild(row);
            recalcModalHpp();
        }

        function recalcModalHpp() {
            let totalHpp = 0;
            document.querySelectorAll('.input-biaya').forEach(inp => {
                totalHpp += parseFloat(inp.value) || 0;
            });
            const hargaJual = parseFloat(document.getElementById('hargaJualInput').value) || 0;
            const marginRp  = hargaJual - totalHpp;
            const marginPct = hargaJual > 0 ? ((marginRp / hargaJual) * 100).toFixed(1) : '0.0';

            document.getElementById('liveTotalHpp').textContent = formatIdr(totalHpp);
            document.getElementById('liveMargin').textContent   = `${formatIdr(marginRp)} (${marginPct}%)`;
        }

        function openHppModalNew() {
            document.getElementById('actionHppInput').value = 'save';
            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Menu & Hubungkan Bahan Baku';
            document.getElementById('modeMenuSelect').value = 'new';
            toggleMenuMode();
            document.getElementById('namaMenuBaru').value = '';
            document.getElementById('hargaJualInput').value = '';
            const container = document.getElementById('bahanContainer');
            container.innerHTML = '';
            addBahanRow(0, 'KOPI', 20, 5000);
            addBahanRow(0, 'SUSU', 120, 3000);
            addBahanRow(0, 'ES BATU', 100, 251);
            addBahanRow(0, 'PACKAGING', 1, 1500);
            document.getElementById('hppModal').classList.add('active');
        }

        function openHppModalEdit(menuData) {
            document.getElementById('actionHppInput').value = 'save';
            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen"></i> Edit HPP: ' + menuData.nama_menu;
            document.getElementById('modeMenuSelect').value = 'existing';
            document.getElementById('productIdSelect').value = menuData.product_id;
            toggleMenuMode();
            document.getElementById('hargaJualInput').value = Math.round(parseFloat(menuData.harga_jual) || 0);

            const container = document.getElementById('bahanContainer');
            container.innerHTML = '';
            if (menuData.bahan && menuData.bahan.length > 0) {
                menuData.bahan.forEach(b => addBahanRow(b.stock_id || 0, b.nama, b.qty_pemakaian || 0, Math.round(b.harga)));
            } else {
                addBahanRow(0, 'ES BATU', 100, 251);
                addBahanRow(0, 'PACKAGING', 1, 1500);
            }
            recalcModalHpp();
            document.getElementById('hppModal').classList.add('active');
        }

        function submitDeleteHpp() {
            if (confirm('Hapus seluruh rincian bahan HPP untuk menu ini?')) {
                document.getElementById('actionHppInput').value = 'delete';
                document.querySelector('#hppModal form').submit();
            }
        }

        function closeHppModal() {
            document.getElementById('hppModal').classList.remove('active');
        }
    </script>

</body>
</html>