<?php
// ==========================================
// SESSION (harus paling atas, sebelum output apapun)
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// KONFIGURASI DATABASE
// ==========================================
$host     = 'localhost';
$dbname   = 'db_filkop';
$username = 'root';
$password = '';

// ==========================================
// KONEKSI PDO
// ==========================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi ke database gagal: " . $e->getMessage());
}

// ==========================================
// FUNGSI BANTUAN (HELPER)
// ==========================================

/**
 * Format angka menjadi Rupiah: 100000000 -> Rp. 100.000.000
 */
function formatRupiah($angka) {
    if ($angka === null || $angka === '') return 'Rp. 0';
    return 'Rp. ' . number_format($angka, 0, ',', '.');
}

/**
 * Cek apakah user yang sedang login adalah Admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ==========================================
// ZONA WAKTU
// ==========================================
date_default_timezone_set('Asia/Jakarta');
?>