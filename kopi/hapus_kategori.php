<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Karena kita sudah set ON DELETE SET NULL pada foreign key category_id di tabel products,
        // menghapus kategori tidak akan menghapus produk, hanya mengosongkan kategorinya.
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: kategori.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        die("Gagal menghapus: " . $e->getMessage());
    }
} else {
    header("Location: kategori.php");
    exit;
}
?>