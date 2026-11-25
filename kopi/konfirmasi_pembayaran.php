<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak valid.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$order_code = $_SESSION['qris_payment']['order_code'] ?? ($_POST['order_code'] ?? '');

if (empty($order_code)) {
    echo json_encode(['success' => false, 'message' => 'Kode pesanan tidak ditemukan.']);
    exit();
}

try {
    // Update status menjadi 'Diproses' agar tampil sebagai "Akan Di Kirim (Diproses)" di pesanan.php
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Diproses' WHERE order_code = ? AND user_id = ?");
    $stmt->execute([$order_code, $user_id]);

    // Bersihkan sesi QRIS setelah transaksi selesai
    unset($_SESSION['qris_payment']);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pesanan: ' . $e->getMessage()]);
}
?>