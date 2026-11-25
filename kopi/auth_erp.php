<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Daftar lengkap 12 menu sidebar ERP sesuai urutan tampilan
function getDaftarMenuErp(): array {
    return [
        'dashboard_admin.php'     => 'DATA PENJUALAN',
        'laporan_penjualan.php'   => 'LAPORAN PENJUALAN',
        'produk_terlaris.php'     => 'PRODUK TERLARIS',
        'stok_master.php'         => 'STOK BAHAN BAKU',
        'produk.php'              => 'KATEGORI MENU',
        'kategori.php'            => 'TAMBAH PRODUK',
        'pelanggan.php'           => 'DATA PENGGUNA',
        'pemesanan_pelanggan.php' => 'DATA PESANAN',
        'berita.php'              => 'BERITA',
        'testimony.php'           => 'TESTIMONY',
        'promosi.php'             => 'PROMOSI',
        'pesan_admin.php'         => 'PESAN',
    ];
}

// Sinkronisasi otomatis tabel user_roles & kolom allowed_menus
function initRoleSystem(PDO $pdo): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_roles` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `role_name` VARCHAR(50) NOT NULL UNIQUE,
                `can_access_erp` TINYINT(1) NOT NULL DEFAULT 1,
                `allowed_menus` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $cekErpCol = $pdo->query("SHOW COLUMNS FROM `user_roles` LIKE 'can_access_erp'")->fetch();
        if (!$cekErpCol) {
            $pdo->exec("ALTER TABLE `user_roles` ADD COLUMN `can_access_erp` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role_name`");
        }
        $cekMenuCol = $pdo->query("SHOW COLUMNS FROM `user_roles` LIKE 'allowed_menus'")->fetch();
        if (!$cekMenuCol) {
            $pdo->exec("ALTER TABLE `user_roles` ADD COLUMN `allowed_menus` TEXT DEFAULT NULL AFTER `can_access_erp`");
        }

        $semuaMenuJson = json_encode(array_keys(getDaftarMenuErp()));
        $kasirMenuJson = json_encode(['dashboard_admin.php', 'pemesanan_pelanggan.php', 'stok_master.php']);

        // Seed default role jika belum ada
        $stmtSeed = $pdo->prepare("
            INSERT IGNORE INTO `user_roles` (`role_name`, `can_access_erp`, `allowed_menus`)
            VALUES (:role, :erp, :menus)
        ");
        $stmtSeed->execute([':role' => 'Admin',     ':erp' => 1, ':menus' => $semuaMenuJson]);
        $stmtSeed->execute([':role' => 'Kasir',     ':erp' => 1, ':menus' => $kasirMenuJson]);
        $stmtSeed->execute([':role' => 'Pelanggan', ':erp' => 0, ':menus' => '[]']);

        // Pastikan Admin selalu memiliki akses penuh ke seluruh daftar menu ERP terbaru (termasuk produk_terlaris.php, promosi.php & pesan_admin.php)
        $pdo->prepare("UPDATE `user_roles` SET `can_access_erp` = 1, `allowed_menus` = :menus WHERE `role_name` = 'Admin'")
            ->execute([':menus' => $semuaMenuJson]);

        // Pastikan Kasir punya default menu jika allowed_menus masih NULL
        $pdo->prepare("UPDATE `user_roles` SET `can_access_erp` = 1, `allowed_menus` = :menus WHERE `role_name` = 'Kasir' AND (`allowed_menus` IS NULL OR `allowed_menus` = '')")
            ->execute([':menus' => $kasirMenuJson]);

        // Pastikan Pelanggan tidak masuk ke ERP
        $pdo->exec("UPDATE `user_roles` SET `can_access_erp` = 0, `allowed_menus` = '[]' WHERE `role_name` = 'Pelanggan'");

        // Sinkronkan role unik dari tabel users
        $pdo->exec("INSERT IGNORE INTO `user_roles` (`role_name`, `can_access_erp`, `allowed_menus`) SELECT DISTINCT `role`, 1, '[]' FROM `users` WHERE `role` IS NOT NULL AND `role` != ''");
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'Pelanggan'");
    } catch (PDOException $e) {
        // Abaikan jika struktur sudah sesuai
    }
}

initRoleSystem($pdo);

// Ambil data akun & role user yang sedang login
$currentUserName = $_SESSION['name'] ?? 'Administrator';
$currentUserRole = $_SESSION['role'] ?? 'Admin';

if (!empty($_SESSION['user_id'])) {
    $stmtMe = $pdo->prepare("SELECT name, role FROM users WHERE id = :id LIMIT 1");
    $stmtMe->execute([':id' => (int)$_SESSION['user_id']]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
    if ($me) {
        $currentUserName  = $me['name'];
        $currentUserRole  = $me['role'];
        $_SESSION['name'] = $me['name'];
        $_SESSION['role'] = $me['role'];
    }
}

// Ambil konfigurasi hak akses menu milik role yang sedang aktif
$stmtRoleCfg = $pdo->prepare("SELECT * FROM user_roles WHERE role_name = :role LIMIT 1");
$stmtRoleCfg->execute([':role' => $currentUserRole]);
$activeRoleData = $stmtRoleCfg->fetch(PDO::FETCH_ASSOC);

$canAccessErp = $activeRoleData ? (int)$activeRoleData['can_access_erp'] : ($currentUserRole === 'Pelanggan' ? 0 : 1);
$allowedMenus = [];

if ($activeRoleData && !empty($activeRoleData['allowed_menus'])) {
    $decoded = json_decode($activeRoleData['allowed_menus'], true);
    if (is_array($decoded)) {
        $allowedMenus = $decoded;
    }
}

// Jika akun ber-role Pelanggan (tidak punya akses ERP), arahkan ke halaman pelanggan
if ($canAccessErp === 0) {
    header('Location: beranda.php');
    exit();
}

// Cek apakah halaman saat ini diizinkan untuk role yang sedang login
$currentScript = basename($_SERVER['PHP_SELF']);
$daftarSemuaMenu = getDaftarMenuErp();

if (isset($daftarSemuaMenu[$currentScript]) && $currentUserRole !== 'Admin') {
    if (!in_array($currentScript, $allowedMenus, true)) {
        if (!empty($allowedMenus)) {
            header('Location: ' . $allowedMenus[0]);
            exit();
        } else {
            header('Location: beranda.php');
            exit();
        }
    }
}

// Fungsi render menu sidebar kiri sesuai hak akses role aktif
function renderSidebarMenuErp(string $activePage, string $roleName, array $allowedMenus): void {
    $allMenus = getDaftarMenuErp();
    echo '<nav class="sidebar-menu">';
    foreach ($allMenus as $file => $label) {
        if ($roleName === 'Admin' || in_array($file, $allowedMenus, true)) {
            $activeClass = ($activePage === $file) ? ' active' : '';
            echo '<a href="' . htmlspecialchars($file) . '" class="menu-item' . $activeClass . '">' . htmlspecialchars($label) . '</a>';
        }
    }
    echo '</nav>';
}