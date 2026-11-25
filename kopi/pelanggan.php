<?php
require_once 'auth_erp.php';

$pesan_sukses = '';
$pesan_error  = '';
$all_menus    = getDaftarMenuErp();

// =========================================================
// 1. TAMBAH ROLE BARU + HAK AKSES MENU SIDEBAR
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_role'])) {
    $role_baru      = trim($_POST['role_name'] ?? '');
    $can_erp        = isset($_POST['can_access_erp']) ? 1 : 0;
    $selected_menus = $_POST['menus'] ?? [];

    // Filter hanya nama file menu yang valid
    $valid_menus = array_values(array_intersect(array_keys($all_menus), $selected_menus));
    if ($can_erp === 0) {
        $valid_menus = [];
    }

    if ($role_baru !== '') {
        try {
            $stmtRole = $pdo->prepare("
                INSERT INTO `user_roles` (`role_name`, `can_access_erp`, `allowed_menus`)
                VALUES (:role, :erp, :menus)
            ");
            $stmtRole->execute([
                ':role'  => $role_baru,
                ':erp'   => $can_erp,
                ':menus' => json_encode($valid_menus)
            ]);
            $pesan_sukses = "Role baru <strong>" . htmlspecialchars($role_baru) . "</strong> berhasil ditambahkan beserta hak akses menunya.";
        } catch (PDOException $e) {
            $pesan_error = "Role <strong>" . htmlspecialchars($role_baru) . "</strong> sudah terdaftar.";
        }
    } else {
        $pesan_error = "Nama role tidak boleh kosong.";
    }
}

// =========================================================
// 2. SIMPAN PERUBAHAN HAK AKSES MENU UNTUK ROLE YANG ADA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_hak_akses'])) {
    $role_id        = (int) ($_POST['role_id'] ?? 0);
    $role_name      = trim($_POST['role_name'] ?? '');
    $can_erp        = isset($_POST['can_access_erp']) ? 1 : 0;
    $selected_menus = $_POST['menus'] ?? [];

    // Admin utama wajib tetap punya akses penuh
    if ($role_name === 'Admin') {
        $can_erp     = 1;
        $valid_menus = array_keys($all_menus);
    } else {
        $valid_menus = ($can_erp === 1)
            ? array_values(array_intersect(array_keys($all_menus), $selected_menus))
            : [];
    }

    if ($role_id > 0) {
        $stmtUpdRole = $pdo->prepare("
            UPDATE `user_roles`
            SET `can_access_erp` = :erp, `allowed_menus` = :menus
            WHERE `id` = :id
        ");
        $stmtUpdRole->execute([
            ':erp'   => $can_erp,
            ':menus' => json_encode($valid_menus),
            ':id'    => $role_id
        ]);
        $pesan_sukses = "Hak akses menu untuk role <strong>" . htmlspecialchars($role_name) . "</strong> berhasil diperbarui.";
    }
}

// =========================================================
// 3. HAPUS ROLE CUSTOM (SELAIN ADMIN, KASIR, PELANGGAN)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_role'])) {
    $role_hapus  = trim($_POST['role_name_del'] ?? '');
    $role_bawaan = ['Admin', 'Kasir', 'Pelanggan'];
    if ($role_hapus !== '' && !in_array($role_hapus, $role_bawaan, true)) {
        $pdo->prepare("UPDATE `users` SET `role` = 'Pelanggan' WHERE `role` = :role")
            ->execute([':role' => $role_hapus]);
        $pdo->prepare("DELETE FROM `user_roles` WHERE `role_name` = :role")
            ->execute([':role' => $role_hapus]);
        $pesan_sukses = "Role <strong>" . htmlspecialchars($role_hapus) . "</strong> berhasil dihapus.";
    }
}

// =========================================================
// 4. TAMBAH DATA PENGGUNA BARU
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $no_telp  = trim($_POST['no_telp'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'Pelanggan');

    if ($nama !== '' && $email !== '' && $password !== '') {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtIns = $pdo->prepare("
                INSERT INTO `users` (`name`, `email`, `no_telp`, `password`, `role`)
                VALUES (:name, :email, :no_telp, :password, :role)
            ");
            $stmtIns->execute([
                ':name'     => $nama,
                ':email'    => $email,
                ':no_telp'  => $no_telp !== '' ? $no_telp : null,
                ':password' => $hashed,
                ':role'     => $role
            ]);
            $pesan_sukses = "Akun <strong>" . htmlspecialchars($nama) . "</strong> dengan role <strong>" . htmlspecialchars($role) . "</strong> berhasil ditambahkan.";
        } catch (PDOException $e) {
            $pesan_error = "Gagal menambahkan akun: Email sudah terdaftar.";
        }
    } else {
        $pesan_error = "Nama, Email, dan Password wajib diisi.";
    }
}

// =========================================================
// 5. UBAH DATA & ROLE PENGGUNA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id       = (int) ($_POST['id'] ?? 0);
    $nama     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $no_telp  = trim($_POST['no_telp'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'Pelanggan');

    if ($id > 0 && $nama !== '' && $email !== '') {
        try {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("
                    UPDATE `users`
                    SET `name` = :name, `email` = :email, `no_telp` = :no_telp, `password` = :password, `role` = :role
                    WHERE `id` = :id
                ");
                $stmtUpd->execute([
                    ':name'     => $nama,
                    ':email'    => $email,
                    ':no_telp'  => $no_telp !== '' ? $no_telp : null,
                    ':password' => $hashed,
                    ':role'     => $role,
                    ':id'       => $id
                ]);
            } else {
                $stmtUpd = $pdo->prepare("
                    UPDATE `users`
                    SET `name` = :name, `email` = :email, `no_telp` = :no_telp, `role` = :role
                    WHERE `id` = :id
                ");
                $stmtUpd->execute([
                    ':name'    => $nama,
                    ':email'   => $email,
                    ':no_telp' => $no_telp !== '' ? $no_telp : null,
                    ':role'    => $role,
                    ':id'      => $id
                ]);
            }
            $pesan_sukses = "Data pengguna <strong>" . htmlspecialchars($nama) . "</strong> berhasil diperbarui.";
        } catch (PDOException $e) {
            $pesan_error = "Gagal memperbarui data: Email sudah digunakan akun lain.";
        }
    }
}

// =========================================================
// 6. UBAH ROLE CEPAT DARI DROPDOWN TABEL
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_change_role'])) {
    $id        = (int) ($_POST['id'] ?? 0);
    $role_baru = trim($_POST['role'] ?? '');
    if ($id > 0 && $role_baru !== '') {
        $pdo->prepare("UPDATE `users` SET `role` = :role WHERE `id` = :id")
            ->execute([':role' => $role_baru, ':id' => $id]);
        $pesan_sukses = "Role akun berhasil diubah menjadi <strong>" . htmlspecialchars($role_baru) . "</strong>.";
    }
}

// =========================================================
// 7. HAPUS DATA PENGGUNA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_user'])) {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === 1) {
        $pesan_error = "Akun Administrator utama tidak dapat dihapus.";
    } elseif ($id > 0) {
        $pdo->prepare("DELETE FROM `users` WHERE `id` = :id")->execute([':id' => $id]);
        $pesan_sukses = "Data pengguna berhasil dihapus.";
    }
}

// =========================================================
// 8. AMBIL DATA ROLE & PENGGUNA
// =========================================================
$roles_list = $pdo->query("
    SELECT r.id, r.role_name, r.can_access_erp, r.allowed_menus, COUNT(u.id) AS total_user
    FROM `user_roles` r
    LEFT JOIN `users` u ON u.role = r.role_name
    GROUP BY r.id, r.role_name, r.can_access_erp, r.allowed_menus
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$total_semua_user = (int) $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

$filter_role = $_GET['role'] ?? 'Semua';
$keyword     = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filter_role !== 'Semua' && $filter_role !== '') {
    $where[] = "`role` = :role";
    $params[':role'] = $filter_role;
}
if ($keyword !== '') {
    $where[] = "(`name` LIKE :kw OR `email` LIKE :kw OR `no_telp` LIKE :kw OR `role` LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$stmtUsers = $pdo->prepare("SELECT * FROM `users` $whereSql ORDER BY `id` ASC");
$stmtUsers->execute($params);
$users_list = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna & Hak Akses Role - Filosofi Kopi</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar Role Box */
        .sidebar-role-box {
            margin-top: 10px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #e6d7cf;
            font-size: 11px;
            font-weight: 700;
            padding: 0 4px;
        }
        .btn-sidebar-add-role {
            background-color: #d4a373;
            color: #2b170e;
            border: none;
            border-radius: 14px;
            padding: 3px 9px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .role-nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .role-nav-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.08);
            color: #f3ece8;
            padding: 7px 12px;
            border-radius: 18px;
            font-size: 11px;
            font-weight: 600;
            transition: 0.2s;
        }
        .role-nav-item:hover,
        .role-nav-item.active {
            background-color: #ffffff;
            color: #2b170e;
        }
        .role-count-pill {
            background-color: rgba(0, 0, 0, 0.28);
            color: #fff;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 10px;
        }
        .role-nav-item.active .role-count-pill,
        .role-nav-item:hover .role-count-pill {
            background-color: #3b2316;
            color: #fff;
        }

        /* Header Control Card */
        .control-card {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 22px 26px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .control-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 16px;
        }
        .title-section h2 { font-size: 20px; color: var(--dark-brown); margin-bottom: 4px; }
        .title-section p { font-size: 12.5px; color: #666; }
        .header-action-group { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn-add {
            background-color: var(--dark-brown);
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-add:hover { background-color: #563422; }
        .btn-add-secondary {
            background-color: #f2e6e6;
            color: var(--dark-brown);
            border: 1px solid var(--dark-brown);
        }
        .btn-add-secondary:hover { background-color: #e4d0d0; }

        /* Search */
        .search-form { display: flex; gap: 10px; align-items: center; }
        .search-box {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #f8f5f3;
            border: 1px solid #d5c7c0;
            border-radius: 25px;
            padding: 8px 16px;
        }
        .search-box input { border: none; outline: none; background: transparent; width: 100%; font-size: 13px; }
        .btn-search {
            background-color: var(--dark-brown);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Alert */
        .alert-msg { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
        .alert-success { background-color: #e8f5e9; border-left: 4px solid #2e7d32; color: #1b5e20; }
        .alert-error { background-color: #ffebee; border-left: 4px solid #c62828; color: #b71c1c; }

        /* Kartu Hak Akses Menu per Role */
        .role-matrix-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 16px;
            margin-top: 12px;
        }
        .role-permission-card {
            border: 1px solid #e4d8d2;
            border-radius: 12px;
            padding: 15px 16px;
            background: #fcfaf9;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
        }
        .role-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ece2dd;
            padding-bottom: 8px;
        }
        .role-card-top h4 {
            font-size: 14px;
            color: var(--dark-brown);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .menu-checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px 10px;
            margin-top: 8px;
        }
        .menu-chk-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
        }
        .menu-chk-item input[type="checkbox"] {
            accent-color: #3b2316;
            width: 14px;
            height: 14px;
            cursor: pointer;
        }
        .erp-access-toggle {
            background: #f2e6e6;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--dark-brown);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .btn-save-perm {
            background: var(--dark-brown);
            color: #fff;
            border: none;
            padding: 7px 14px;
            border-radius: 18px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        .btn-save-perm:hover { background: #5a3724; }

        /* Quick Role Select & Action Buttons */
        .quick-role-form select {
            padding: 5px 10px;
            border-radius: 14px;
            border: 1px solid #cbbcb5;
            font-size: 11.5px;
            font-weight: 700;
            background: #fff;
            color: var(--dark-brown);
            cursor: pointer;
        }
        .action-btns { display: flex; gap: 6px; align-items: center; }
        .btn-edit-row {
            background-color: #3b2316;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-delete-row {
            background-color: #c62828;
            color: #fff;
            border: none;
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 11px;
            cursor: pointer;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 470px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        .modal-header {
            background-color: var(--dark-brown);
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 15px; }
        .modal-close { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
        .modal-body {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 75vh;
            overflow-y: auto;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #333; }
        .form-group input, .form-group select {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 13px;
        }
        .modal-footer { padding: 12px 20px 18px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel {
            background: #eee;
            color: #333;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- HEADER (KLIK LOGO / FILOSOFI KOPI MENUJU KE BERANDA.PHP) -->
    <header class="top-header">
        <a href="beranda.php" class="header-logo" title="Kembali ke Beranda" style="text-decoration: none; color: inherit; cursor: pointer;">
            <img src="image 24.png" class="logo-img" onerror="this.style.display='none'" alt="Logo">
            <span class="brand-title">Filosofi Kopi</span>
        </a>
        <div class="header-right">
            <span>Dashboard ERP (<?= htmlspecialchars($currentUserRole) ?>)</span>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <div class="user-badge"><i class="fa-solid fa-user"></i> <span><?= htmlspecialchars($currentUserName) ?></span></div>

            <!-- 9 MENU SIDEBAR UTAMA (OTOMATIS MENGIKUTI ROLE AKUN) -->
            <?php renderSidebarMenuErp('pelanggan.php', $currentUserRole, $allowedMenus); ?>

            <!-- FITUR ROLE AKUN DI BAGIAN KIRI -->
            <div class="sidebar-role-box">
                <div class="sidebar-role-header">
                    <span><i class="fa-solid fa-user-shield"></i> ROLE AKUN</span>
                    <button type="button" class="btn-sidebar-add-role" onclick="openModal('modalTambahRole')">
                        <i class="fa-solid fa-plus"></i> Role
                    </button>
                </div>

                <div class="role-nav-list">
                    <a href="pelanggan.php?role=Semua&q=<?= urlencode($keyword) ?>" 
                       class="role-nav-item <?= $filter_role === 'Semua' ? 'active' : '' ?>">
                        <span>Semua Akun</span>
                        <span class="role-count-pill"><?= $total_semua_user ?></span>
                    </a>

                    <?php foreach ($roles_list as $rl): ?>
                        <a href="pelanggan.php?role=<?= urlencode($rl['role_name']) ?>&q=<?= urlencode($keyword) ?>" 
                           class="role-nav-item <?= $filter_role === $rl['role_name'] ? 'active' : '' ?>">
                            <span><?= htmlspecialchars($rl['role_name']) ?></span>
                            <span class="role-count-pill"><?= (int)$rl['total_user'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <main class="content-area">
            <?php if ($pesan_sukses): ?>
                <div class="alert-msg alert-success"><i class="fa-solid fa-circle-check"></i> <?= $pesan_sukses ?></div>
            <?php endif; ?>
            <?php if ($pesan_error): ?>
                <div class="alert-msg alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $pesan_error ?></div>
            <?php endif; ?>

            <!-- CONTROL PANEL -->
            <div class="control-card">
                <div class="control-header">
                    <div class="title-section">
                        <h2>Manajemen Data Pengguna & Pengelola Role Menu</h2>
                        <p>Atur akun pengguna serta tentukan menu sidebar mana saja yang dapat dikelola oleh setiap Role Akun.</p>
                    </div>
                    <div class="header-action-group">
                        <button type="button" class="btn-add btn-add-secondary" onclick="openModal('modalTambahRole')">
                            <i class="fa-solid fa-user-shield"></i> Tambah Role Baru
                        </button>
                        <button type="button" class="btn-add" onclick="openModal('modalTambahUser')">
                            <i class="fa-solid fa-plus"></i> Tambah Data Pengguna
                        </button>
                    </div>
                </div>

                <form method="GET" class="search-form">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="q" placeholder="Cari berdasarkan nama, email, no HP, atau role..." value="<?= htmlspecialchars($keyword) ?>">
                    </div>
                    <button type="submit" class="btn-search">Cari</button>
                </form>
            </div>

            <!-- BAGIAN PENGELOLA HAK AKSES MENU SIDEBAR PER ROLE -->
            <div class="card">
                <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-sliders"></i> Pengaturan Hak Akses Menu Sidebar per Role Akun</span>
                    <small style="color:#666; font-weight:normal;">Centang menu di bawah untuk mengatur tampilan sidebar kiri tiap role</small>
                </div>

                <div class="role-matrix-grid">
                    <?php foreach ($roles_list as $rl): 
                        $roleMenus = json_decode($rl['allowed_menus'] ?? '[]', true);
                        if (!is_array($roleMenus)) $roleMenus = [];
                        $isAdminRole = ($rl['role_name'] === 'Admin');
                    ?>
                        <form method="POST" class="role-permission-card">
                            <div>
                                <div class="role-card-top">
                                    <h4>
                                        <i class="fa-solid fa-user-tag"></i> 
                                        <?= htmlspecialchars($rl['role_name']) ?>
                                        <small style="font-weight:normal; color:#777;">(<?= (int)$rl['total_user'] ?> akun)</small>
                                    </h4>
                                    <?php if (!in_array($rl['role_name'], ['Admin', 'Kasir', 'Pelanggan'], true)): ?>
                                        <button type="submit" name="hapus_role" value="1"
                                                onclick="this.form.role_name_del.value='<?= htmlspecialchars($rl['role_name'], ENT_QUOTES, 'UTF-8') ?>'; return confirm('Hapus role <?= htmlspecialchars($rl['role_name'], ENT_QUOTES, 'UTF-8') ?>?');"
                                                style="background:#c62828; color:#fff; border:none; padding:3px 9px; border-radius:10px; font-size:10px; cursor:pointer;">
                                            Hapus Role
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <input type="hidden" name="role_id" value="<?= (int)$rl['id'] ?>">
                                <input type="hidden" name="role_name" value="<?= htmlspecialchars($rl['role_name']) ?>">
                                <input type="hidden" name="role_name_del" value="">

                                <div style="margin-top:10px;">
                                    <label class="erp-access-toggle">
                                        <input type="checkbox" name="can_access_erp" value="1" 
                                               <?= (int)$rl['can_access_erp'] === 1 ? 'checked' : '' ?>
                                               <?= $isAdminRole ? 'disabled' : '' ?>>
                                        <span>Boleh Akses Halaman Dashboard ERP</span>
                                    </label>
                                    <?php if ($isAdminRole): ?>
                                        <input type="hidden" name="can_access_erp" value="1">
                                    <?php endif; ?>
                                </div>

                                <div class="menu-checkbox-grid">
                                    <?php foreach ($all_menus as $file => $label): 
                                        $checked = $isAdminRole || in_array($file, $roleMenus, true);
                                    ?>
                                        <label class="menu-chk-item">
                                            <input type="checkbox" name="menus[]" value="<?= htmlspecialchars($file) ?>"
                                                   <?= $checked ? 'checked' : '' ?>
                                                   <?= $isAdminRole ? 'disabled' : '' ?>>
                                            <span><?= htmlspecialchars($label) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if (!$isAdminRole): ?>
                                <button type="submit" name="simpan_hak_akses" class="btn-save-perm">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Akses <?= htmlspecialchars($rl['role_name']) ?>
                                </button>
                            <?php else: ?>
                                <div style="text-align:center; font-size:11px; color:#666; padding:6px; background:#eee; border-radius:12px;">
                                    Akses Penuh Seluruh Menu ERP
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TABEL DAFTAR PENGGUNA -->
            <div class="card">
                <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>
                        <i class="fa-solid fa-users"></i> Daftar Akun Pengguna
                        <?= $filter_role !== 'Semua' ? '(Filter Role: ' . htmlspecialchars($filter_role) . ')' : '' ?>
                    </span>
                    <span style="font-size:12px; color:#666;">Total: <?= count($users_list) ?> Akun</span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NAMA</th>
                                <th>EMAIL</th>
                                <th>NO HP</th>
                                <th>PASSWORD</th>
                                <th>ROLE AKUN</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users_list)): ?>
                                <tr><td colspan="7" style="text-align:center; padding: 25px;">Tidak ada data pengguna.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users_list as $u): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$u['id'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['no_telp'] ?? '-') ?></td>
                                        <td><code>*********</code></td>
                                        <td>
                                            <form method="POST" class="quick-role-form">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="quick_change_role" value="1">
                                                <select name="role" onchange="this.form.submit()" title="Ganti Role Akun">
                                                    <?php foreach ($roles_list as $rl): ?>
                                                        <option value="<?= htmlspecialchars($rl['role_name']) ?>" <?= $u['role'] === $rl['role_name'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($rl['role_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" class="btn-edit-row" 
                                                        onclick='openEditUserModal(<?= json_encode([
                                                            "id"      => (int)$u["id"],
                                                            "name"    => $u["name"],
                                                            "email"   => $u["email"],
                                                            "no_telp" => $u["no_telp"] ?? "",
                                                            "role"    => $u["role"]
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="fa-solid fa-pen"></i> Ubah Data
                                                </button>

                                                <?php if ((int)$u['id'] !== 1): ?>
                                                    <form method="POST" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>?');" style="display:inline;">
                                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                        <button type="submit" name="hapus_user" class="btn-delete-row" title="Hapus Akun">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL TAMBAH ROLE BARU + PILIH MENU SIDEBAR -->
    <div id="modalTambahRole" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-shield"></i> Tambah Role Akun Baru</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalTambahRole')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Role Baru</label>
                        <input type="text" name="role_name" placeholder="Contoh: Barista, Gudang, Supervisor" required>
                    </div>

                    <label class="erp-access-toggle">
                        <input type="checkbox" name="can_access_erp" value="1" checked>
                        <span>Izinkan Login ke Dashboard ERP</span>
                    </label>

                    <div class="form-group">
                        <label>Pilih Menu Sidebar yang Dapat Dikelola Role Ini:</label>
                        <div class="menu-checkbox-grid" style="background:#f9f6f4; padding:12px; border-radius:10px; border:1px solid #e2d6d0;">
                            <?php foreach ($all_menus as $file => $label): ?>
                                <label class="menu-chk-item">
                                    <input type="checkbox" name="menus[]" value="<?= htmlspecialchars($file) ?>">
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalTambahRole')">Batal</button>
                    <button type="submit" name="tambah_role" class="btn-add">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH PENGGUNA -->
    <div id="modalTambahUser" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Tambah Data Pengguna</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalTambahUser')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="name" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="contoh@gmail.com" required>
                    </div>
                    <div class="form-group">
                        <label>No HP</label>
                        <input type="text" name="no_telp" placeholder="+62 812-xxxx-xxxx">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Masukkan password" required>
                    </div>
                    <div class="form-group">
                        <label>Role Akun</label>
                        <select name="role" required>
                            <?php foreach ($roles_list as $rl): ?>
                                <option value="<?= htmlspecialchars($rl['role_name']) ?>" <?= $rl['role_name'] === 'Pelanggan' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rl['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalTambahUser')">Batal</button>
                    <button type="submit" name="tambah_user" class="btn-add">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT PENGGUNA -->
    <div id="modalEditUser" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-pen"></i> Ubah Data & Role Akun</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalEditUser')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label>No HP</label>
                        <input type="text" name="no_telp" id="edit_no_telp">
                    </div>
                    <div class="form-group">
                        <label>Password Baru <small style="font-weight:400; color:#777;">(Kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" placeholder="*********">
                    </div>
                    <div class="form-group">
                        <label>Role Akun</label>
                        <select name="role" id="edit_role" required>
                            <?php foreach ($roles_list as $rl): ?>
                                <option value="<?= htmlspecialchars($rl['role_name']) ?>">
                                    <?= htmlspecialchars($rl['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalEditUser')">Batal</button>
                    <button type="submit" name="edit_user" class="btn-add">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-left">
            <div class="footer-logo-circle">
                <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
            </div>
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        <div class="footer-right">
            <div class="footer-top-row">
                <div class="footer-col"><h4>Metode Pembayaran</h4></div>
                <div class="footer-col">
                    <h4>Ikuti Kami</h4>
                    <div class="social-icons">
                        <a href="#" class="fb"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="ig"><i class="fa-brands fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        function openEditUserModal(user) {
            document.getElementById('edit_id').value      = user.id;
            document.getElementById('edit_name').value    = user.name;
            document.getElementById('edit_email').value   = user.email;
            document.getElementById('edit_no_telp').value = user.no_telp;
            document.getElementById('edit_role').value    = user.role;
            openModal('modalEditUser');
        }
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
        });
    </script>

</body>
</html>