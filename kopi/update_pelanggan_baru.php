<?php
require_once 'config.php';

header('Content-Type: application/json');

// Ambil ID pelanggan terakhir yang dikirim dari JS
$since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

try {
    // Hitung pelanggan baru (role = Pelanggan) yang ID-nya lebih besar dari ID terakhir di layar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'Pelanggan' AND id > ?");
    $stmt->execute([$since_id]);
    $new_count = (int)$stmt->fetchColumn();

    // Ambil info pelanggan terbaru untuk notifikasi
    $latest_stmt = $pdo->prepare("SELECT name, email, no_telp 
                                  FROM users 
                                  WHERE role = 'Pelanggan' AND id > ? 
                                  ORDER BY id DESC LIMIT 1");
    $latest_stmt->execute([$since_id]);
    $latest = $latest_stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'new_count' => $new_count,
        'latest_name' => $latest ? $latest['name'] : null,
        'latest_email' => $latest ? $latest['email'] : null,
        'server_time' => date('H:i:s')
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}