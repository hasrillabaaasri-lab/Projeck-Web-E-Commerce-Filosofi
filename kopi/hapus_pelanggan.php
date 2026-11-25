<?php
require_once 'config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // Hapus user hanya jika role = 'Pelanggan' (pengaman agar admin tidak bisa terhapus)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Pelanggan'");
        $stmt->execute([$id]);

        header("Location: pemantauan_pelanggan.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        die("Gagal menghapus data pelanggan: " . $e->getMessage());
    }
} else {
    header("Location: pemantauan_pelanggan.php");
    exit;
}
?>