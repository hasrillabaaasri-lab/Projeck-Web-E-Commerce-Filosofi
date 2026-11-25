<?php
require_once 'config.php';
if (isset($_GET['id'])) {
    // Hanya menghapus dari tabel stock_master
    $stmt = $pdo->prepare("DELETE FROM stock_master WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: stok_master.php?status=deleted");
    exit;
}
header("Location: stok_master.php");
?>