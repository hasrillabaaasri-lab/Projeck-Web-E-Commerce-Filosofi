<?php
require_once 'config.php';

// Token keamanan agar URL tidak dipicu sembarang orang
$secret_token = 'kopi123';
$token = $_REQUEST['token'] ?? '';

if ($token !== $secret_token) {
    http_response_code(403);
    echo "Token tidak valid";
    exit();
}

$notif_text = $_REQUEST['text'] ?? '';
if (empty($notif_text)) {
    echo "Teks notifikasi kosong";
    exit();
}

// Ambil nominal angka setelah tulisan "Rp" (contoh: "Rp1.001" atau "Rp 44.000")
if (preg_match('/Rp\s*([0-9.,]+)/i', $notif_text, $matches)) {
    $raw_amount   = rtrim($matches[1], '.,');
    $clean_amount = preg_replace('/[.,]00$/', '', $raw_amount);
    $nominal      = (int) preg_replace('/[^0-9]/', '', $clean_amount);

    if ($nominal > 0) {
        // Cari pesanan QRIS berstatus 'Pending' terbaru dengan nominal yang sama persis
        $stmt = $pdo->prepare("
            SELECT id, order_code 
            FROM orders 
            WHERE total_amount = ? AND payment_method = 'QRIS' AND status = 'Pending' 
            ORDER BY order_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$nominal]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $update = $pdo->prepare("UPDATE orders SET status = 'Diproses' WHERE id = ?");
            $update->execute([$order['id']]);
            echo "SUKSES: Pesanan " . $order['order_code'] . " diproses.";
            exit();
        }
    }
}

echo "Tidak ada pesanan Pending yang cocok dengan teks: " . htmlspecialchars($notif_text);
?>