<?php
require_once 'config.php';
require_once 'midtrans_config.php';

// Baca data JSON mentah yang dikirim server Midtrans
$json_result = file_get_contents('php://input');
$notif = json_decode($json_result, true);

if (!$notif) {
    http_response_code(400);
    exit('Invalid payload');
}

$order_id     = $notif['order_id'] ?? '';
$status_code  = $notif['status_code'] ?? '';
$gross_amount = $notif['gross_amount'] ?? '';
$signature    = $notif['signature_key'] ?? '';
$transaction  = $notif['transaction_status'] ?? '';
$fraud        = $notif['fraud_status'] ?? '';

// Validasi keaslian pengirim menggunakan Server Key Production Anda
$expected_signature = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);
if ($signature !== $expected_signature) {
    http_response_code(403);
    exit('Invalid signature');
}

// Jika pembayaran sukses (settlement / capture), ubah status pesanan menjadi Diproses
if ($transaction === 'settlement' || ($transaction === 'capture' && $fraud === 'accept')) {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Diproses' WHERE order_code = ? AND status = 'Pending'");
    $stmt->execute([$order_id]);
} elseif (in_array($transaction, ['cancel', 'deny', 'expire'])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Batal', catatan_admin = 'Pembayaran QRIS kedaluwarsa/dibatalkan' WHERE order_code = ? AND status = 'Pending'");
    $stmt->execute([$order_id]);
}

http_response_code(200);
echo 'OK';
?>