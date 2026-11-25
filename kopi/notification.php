<?php
// notification.php
require_once 'config.php'; // Koneksi database Anda

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    exit;
}

$order_code = $data['order_id'];
$transaction_status = $data['transaction_status'];
$fraud_status = $data['fraud_status'];

// Cari pesanan di database
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit;
}

// Tentukan status baru
$new_status = 'Pending';
if ($transaction_status == 'capture' || $transaction_status == 'settlement') {
    if ($fraud_status == 'accept') {
        $new_status = 'Diproses'; // Atau 'Selesai' tergantung alur Anda
    }
} elseif ($transaction_status == 'deny' || $transaction_status == 'cancel' || $transaction_status == 'expire') {
    $new_status = 'Batal';
}

// Update status di database jika berubah
if ($new_status != $order['status']) {
    $update = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update->execute([$new_status, $order['id']]);
}

http_response_code(200);
echo "OK";
?>