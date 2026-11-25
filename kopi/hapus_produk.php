<?php
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kategori.php");
    exit;
}

$id = (int) $_GET['id'];

try {
    $pdo->beginTransaction();

    // 1. Ambil SKU produk sebelum dihapus untuk sinkronisasi ke stock_master
    $stmtProd = $pdo->prepare("SELECT sku FROM products WHERE id = ?");
    $stmtProd->execute([$id]);
    $sku = $stmtProd->fetchColumn();

    // 2. Hapus semua relasi anak di tabel lain agar tidak terkena error foreign key
    $pdo->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_hpp_items WHERE product_id = ?")->execute([$id]);

    if (!empty($sku)) {
        $pdo->prepare("DELETE FROM stock_master WHERE sku = ?")->execute([$sku]);
    }

    // 3. Hapus produk dari tabel utama products
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    // Redirect kembali ke kategori.php dengan status berhasil
    header("Location: kategori.php?status=deleted");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Gagal menghapus data: " . $e->getMessage());
}
?>