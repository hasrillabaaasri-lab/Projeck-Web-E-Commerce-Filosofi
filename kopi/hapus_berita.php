<?php
require_once 'config.php';

// Cek apakah ID ada di URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // 1. Ambil nama gambar terlebih dahulu sebelum menghapus data
        $stmt = $pdo->prepare("SELECT image_url FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $berita = $stmt->fetch();

        if ($berita) {
            // 2. Hapus file fisik gambar dari folder uploads (jika ada)
            if (!empty($berita['image_url']) && file_exists($berita['image_url'])) {
                unlink($berita['image_url']);
            }

            // 3. Hapus data dari database
            $delete_stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $delete_stmt->execute([$id]);
        }

        // 4. Redirect kembali ke halaman berita dengan status berhasil
        header("Location: berita.php?status=deleted");
        exit;
        
    } catch (PDOException $e) {
        die("Gagal menghapus berita: " . $e->getMessage());
    }
} else {
    // Jika tidak ada ID, kembalikan ke halaman berita
    header("Location: berita.php");
    exit;
}
?>