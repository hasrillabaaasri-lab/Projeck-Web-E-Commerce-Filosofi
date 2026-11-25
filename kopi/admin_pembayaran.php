<?php
session_start();
require_once 'config.php';

// Pastikan tabel product_activity_logs tersedia
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_activity_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `event_type` ENUM('detail_click', 'add_to_cart', 'buy_now') NOT NULL,
            `session_id` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_event_date` (`event_type`, `created_at`),
            KEY `idx_product_event` (`product_id`, `event_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
} catch (PDOException $e) {
    // Abaikan jika tabel sudah ada
}

// Proses konfirmasi pembayaran oleh admin/penjual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi_order_id'])) {
    $order_id = (int) $_POST['konfirmasi_order_id'];

    try {
        $pdo->beginTransaction();

        // Kunci dan ambil detail pesanan QRIS yang masih Pending
        $stmtOrder = $pdo->prepare("
            SELECT id, payment_method, status 
            FROM orders 
            WHERE id = ? AND payment_method = 'QRIS' AND status = 'Pending'
            FOR UPDATE
        ");
        $stmtOrder->execute([$order_id]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // 1. Ubah status pesanan menjadi Diproses
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'Diproses' WHERE id = ? AND status = 'Pending'");
            $stmtUpdate->execute([$order_id]);

            // 2. Baru catat ke aktivitas 'buy_now' (Langsung Beli) setelah pembayaran QRIS dikonfirmasi
            if ($stmtUpdate->rowCount() > 0) {
                $orderRef = 'QRIS-ORDER-' . $order_id;

                // Cegah duplikasi log untuk order yang sama
                $stmtCekLog = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM product_activity_logs 
                    WHERE session_id = ? AND event_type = 'buy_now'
                ");
                $stmtCekLog->execute([$orderRef]);

                if ((int) $stmtCekLog->fetchColumn() === 0) {
                    $stmtItems = $pdo->prepare("
                        SELECT product_id 
                        FROM order_items 
                        WHERE order_id = ? AND product_id IS NOT NULL
                    ");
                    $stmtItems->execute([$order_id]);
                    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                    $stmtLogBuy = $pdo->prepare("
                        INSERT INTO product_activity_logs (product_id, event_type, session_id)
                        VALUES (?, 'buy_now', ?)
                    ");

                    foreach ($items as $item) {
                        $pid = (int) $item['product_id'];
                        if ($pid > 0) {
                            $stmtLogBuy->execute([$pid, $orderRef]);
                        }
                    }
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    header("Location: admin_pembayaran.php?sukses=1");
    exit();
}

// Ambil semua pesanan QRIS yang masih menunggu pembayaran (Pending)
$sql = "
    SELECT 
        o.id,
        o.order_code,
        o.total_amount,
        o.payment_method,
        o.status,
        o.order_date,
        GROUP_CONCAT(CONCAT(COALESCE(p.name, 'Produk'), ' (x', oi.quantity, ')') SEPARATOR ', ') AS daftar_item
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.payment_method = 'QRIS' AND o.status = 'Pending'
    GROUP BY o.id
    ORDER BY o.order_date DESC
";
$stmt = $pdo->query($sql);
$pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Konfirmasi QRIS - Filosofi Kopi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f5ebe4; color: #3b2316; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; font-size: 22px; border-bottom: 2px solid #e6d5c8; padding-bottom: 12px; }
        .alert { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; }
        .order-item { border: 1px solid #d7c3b5; border-radius: 10px; padding: 16px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; }
        .order-info h3 { margin: 0 0 6px 0; font-size: 16px; }
        .order-info p { margin: 2px 0; font-size: 13.5px; color: #5c4542; }
        .btn-confirm { background-color: #1e7e34; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-confirm:hover { background-color: #166327; }
        .empty { text-align: center; padding: 30px; color: #7a635b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Konfirmasi Pembayaran QRIS Masuk</h1>

        <?php if (isset($_GET['sukses'])): ?>
            <div class="alert">Pembayaran berhasil dikonfirmasi! Data otomatis masuk ke statistik Langsung Beli &amp; layar pembeli berpindah ke halaman pesanan.</div>
        <?php endif; ?>

        <?php if (empty($pending_orders)): ?>
            <div class="empty">Tidak ada pesanan QRIS yang menunggu pembayaran.</div>
        <?php else: ?>
            <?php foreach ($pending_orders as $order): ?>
                <div class="order-item">
                    <div class="order-info">
                        <h3><?= htmlspecialchars($order['order_code']) ?> — Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></h3>
                        <p><strong>Item:</strong> <?= htmlspecialchars($order['daftar_item'] ?? '-') ?></p>
                        <p><strong>Waktu Order:</strong> <?= htmlspecialchars($order['order_date']) ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="konfirmasi_order_id" value="<?= (int)$order['id'] ?>">
                        <button type="submit" class="btn-confirm">Konfirmasi Sudah Dibayar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>