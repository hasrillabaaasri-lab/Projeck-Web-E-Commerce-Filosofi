<?php
session_start();
require_once 'config.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Cek apakah ada ID yang dikirim
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_alamat = (int)$_GET['id'];

    // Hapus alamat dari database (Pastikan hanya user pemilik yang bisa hapus)
    $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id_alamat, $user_id]);

    // Kembali ke halaman alamat dengan status deleted
    header("Location: alamat.php?status=deleted");
    exit();
} else {
    // Jika tidak ada ID, kembalikan ke halaman alamat
    header("Location: alamat.php");
    exit();
}
?>