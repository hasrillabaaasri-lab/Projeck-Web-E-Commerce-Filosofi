<?php
// =========================================================
// HELPER TABEL PESAN & PESAN OTOMATIS PENGGUNA BARU
// =========================================================
function initSistemPesan(PDO $pdo): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `messages` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `sender_name` varchar(100) NOT NULL DEFAULT 'Admin Filosofi Kopi',
              `subject` varchar(180) NOT NULL,
              `message` text NOT NULL,
              `message_type` enum('welcome','manual','broadcast') NOT NULL DEFAULT 'manual',
              `is_read` tinyint(1) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_msg_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `message_auto_settings` (
              `id` int(11) NOT NULL PRIMARY KEY,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `subject` varchar(180) NOT NULL,
              `message_template` text NOT NULL,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $stmtSeed = $pdo->prepare("
            INSERT IGNORE INTO `message_auto_settings` (`id`, `is_active`, `subject`, `message_template`)
            VALUES (1, 1, :sub, :tpl)
        ");
        $stmtSeed->execute([
            ':sub' => 'Selamat Datang di Filosofi Kopi! ☕',
            ':tpl' => "Halo {nama},\n\nTerima kasih telah bergabung bersama Filosofi Kopi! Nikmati racikan kopi pilihan terbaik kami dan pantau terus promo menarik langsung dari halaman Beranda.\n\nSalam hangat,\nTim Filosofi Kopi"
        ]);
    } catch (PDOException $e) {
        // Abaikan jika struktur sudah terbentuk
    }
}

// Otomatis kirim pesan selamat datang jika pengguna baru belum pernah menerimanya
function kirimPesanOtomatisGabung(PDO $pdo, int $userId, string $userName = ''): void {
    if ($userId <= 0) {
        return;
    }

    initSistemPesan($pdo);

    try {
        $cfg = $pdo->query("SELECT * FROM `message_auto_settings` WHERE `id` = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$cfg || (int)$cfg['is_active'] !== 1) {
            return;
        }

        // Cek apakah user ini sudah pernah menerima pesan otomatis selamat datang
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM `messages` WHERE `user_id` = :uid AND `message_type` = 'welcome'");
        $stmtCek->execute([':uid' => $userId]);
        if ((int)$stmtCek->fetchColumn() > 0) {
            return;
        }

        if ($userName === '') {
            $stmtUser = $pdo->prepare("SELECT `name` FROM `users` WHERE `id` = :uid LIMIT 1");
            $stmtUser->execute([':uid' => $userId]);
            $userName = (string)($stmtUser->fetchColumn() ?: 'Pelanggan');
        }

        $subjectFinal = str_replace('{nama}', $userName, $cfg['subject']);
        $messageFinal = str_replace('{nama}', $userName, $cfg['message_template']);

        $stmtIns = $pdo->prepare("
            INSERT INTO `messages` (`user_id`, `sender_name`, `subject`, `message`, `message_type`, `is_read`)
            VALUES (:uid, 'Admin Filosofi Kopi', :sub, :msg, 'welcome', 0)
        ");
        $stmtIns->execute([
            ':uid' => $userId,
            ':sub' => $subjectFinal,
            ':msg' => $messageFinal
        ]);
    } catch (PDOException $e) {
        // Abaikan error non-kritis
    }
}