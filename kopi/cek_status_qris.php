<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$order_code = trim($_GET['order_code'] ?? ($_SESSION['qris_payment']['order_code'] ?? ''));

if (empty($order_code)) {
    echo json_encode(['paid' => false]);
    exit();
}

$stmt = $pdo->prepare("SELECT status FROM orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$order_code]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order && in_array($order['status'], ['Diproses', 'Dikirim', 'Selesai'])) {
    unset($_SESSION['qris_payment']);
    echo json_encode(['paid' => true]);
} else {
    echo json_encode(['paid' => false]);
}
?>