<?php
session_start();
require_once 'config.php';

// PROTEKSI HALAMAN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Simpan konteks jika user datang dari halaman pembayaran
if (isset($_GET['redirect']) && $_GET['redirect'] === 'pembayaran') {
    $_SESSION['checkout_redirect'] = [
        'id'  => isset($_GET['id']) ? (int) $_GET['id'] : 0,
        'qty' => isset($_GET['qty']) ? (int) $_GET['qty'] : 1
    ];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['status'])) {
    unset($_SESSION['checkout_redirect']);
}

$from_checkout = isset($_SESSION['checkout_redirect']);
$checkout_id   = $from_checkout ? (int) ($_SESSION['checkout_redirect']['id'] ?? 0) : 0;
$checkout_qty  = $from_checkout ? (int) ($_SESSION['checkout_redirect']['qty'] ?? 1) : 1;

// Query string tambahan untuk diteruskan ke tambah_alamat.php (Tambah / Edit)
$query_tambah_alamat = '';
if ($from_checkout) {
    $params_link = ['redirect' => 'pembayaran'];
    if ($checkout_id > 0) {
        $params_link['id'] = $checkout_id;
    }
    if ($checkout_qty > 1) {
        $params_link['qty'] = $checkout_qty;
    }
    $query_tambah_alamat = '?' . http_build_query($params_link);
}

// ==========================================
// PROSES SIMPAN PILIHAN ALAMAT UTAMA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_alamat'])) {
    $selected_id = !empty($_POST['primary_address_id']) ? (int) $_POST['primary_address_id'] : 0;

    // Reset semua alamat user menjadi bukan utama terlebih dahulu
    $stmtReset = $pdo->prepare("UPDATE addresses SET is_primary = 0 WHERE user_id = ?");
    $stmtReset->execute([$user_id]);

    // Jika ada alamat yang dipilih, set is_primary = 1
    if ($selected_id > 0) {
        $stmtSet = $pdo->prepare("UPDATE addresses SET is_primary = 1 WHERE id = ? AND user_id = ?");
        $stmtSet->execute([$selected_id, $user_id]);
    }

    // Jika berasal dari halaman pembayaran, langsung arahkan kembali ke pembayaran.php
    $post_redirect = $_POST['redirect'] ?? '';
    if ($post_redirect === 'pembayaran' || isset($_SESSION['checkout_redirect'])) {
        $target_id  = !empty($_POST['checkout_id']) ? (int) $_POST['checkout_id'] : $checkout_id;
        $target_qty = !empty($_POST['checkout_qty']) ? (int) $_POST['checkout_qty'] : $checkout_qty;

        unset($_SESSION['checkout_redirect']);

        $params_back = [];
        if ($target_id > 0) {
            $params_back['id'] = $target_id;
        }
        if ($target_qty > 1) {
            $params_back['qty'] = $target_qty;
        }
        if ($selected_id > 0) {
            $params_back['address_id'] = $selected_id;
        }

        $redirect_url = 'pembayaran.php' . (!empty($params_back) ? '?' . http_build_query($params_back) : '');
        header("Location: " . $redirect_url);
        exit();
    }

    header("Location: alamat.php?status=saved");
    exit();
}

// AMBIL DATA DARI DATABASE (Milik user yang login)
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, id DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Cari ID alamat utama saat ini untuk nilai awal input hidden
$current_primary_id = '';
foreach ($addresses as $addr) {
    if ((int)$addr['is_primary'] === 1) {
        $current_primary_id = (int)$addr['id'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alamat Pengiriman - Filosofi Kopi</title>
    <link rel="stylesheet" href="alamat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .primary-toggle-btn {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            border-radius: 50%;
            background: transparent;
            border: none;
            transition: transform 0.15s ease;
        }
        .primary-toggle-btn:hover {
            transform: scale(1.12);
        }
        .primary-toggle-btn:active {
            transform: scale(0.95);
        }
        .radio-selected {
            width: 24px;
            height: 24px;
            background-color: #000000;
            border: 2px solid #000000;
            border-radius: 50%;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .radio-unselected {
            width: 24px;
            height: 24px;
            background-color: transparent;
            border: 2px solid #3b2316;
            border-radius: 50%;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .radio-unselected:hover {
            background-color: rgba(59, 35, 22, 0.15);
        }

        /* Wrapper Tombol Tambah & Simpan */
        .action-bottom-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .btn-simpan-alamat {
            background-color: #3b2316;
            color: #ffffff;
            border: none;
            padding: 14px 40px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(59, 35, 22, 0.2);
            transition: background-color 0.25s ease, transform 0.15s ease;
        }
        .btn-simpan-alamat:hover {
            background-color: #523220;
            transform: translateY(-1px);
        }
        .btn-simpan-alamat:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>

    <!-- TOP NAVBAR -->
    <header class="top-navbar">
        <div class="nav-left">
            <a href="beranda.php" style="display: flex; align-items: center; gap: 15px;">
                <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
                <span class="nav-brand">Filosofi Kopi</span>
            </a>
            <span class="nav-separator">&gt;</span>
            <?php if ($from_checkout): ?>
                <a href="pembayaran.php<?= $checkout_id > 0 ? '?id=' . $checkout_id . ($checkout_qty > 1 ? '&qty=' . $checkout_qty : '') : '' ?>" class="nav-page">Pembayaran</a>
            <?php else: ?>
                <a href="profile.php" class="nav-page">Profile</a>
            <?php endif; ?>
            <span class="nav-separator">&gt;</span>
            <span class="nav-page active">Alamat</span>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        
        <!-- Notifikasi Tambah Sukses -->
        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600;">
                <i class="fas fa-check-circle"></i> Alamat baru berhasil ditambahkan!
            </div>
        <?php endif; ?>

        <!-- Notifikasi Edit Sukses -->
        <?php if(isset($_GET['status']) && $_GET['status'] == 'updated'): ?>
            <div style="background-color: #cce5ff; color: #004085; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #b8daff; font-weight: 600;">
                <i class="fas fa-edit"></i> Alamat berhasil diperbarui!
            </div>
        <?php endif; ?>

        <!-- Notifikasi Hapus Sukses -->
        <?php if(isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-weight: 600;">
                <i class="fas fa-trash"></i> Alamat berhasil dihapus!
            </div>
        <?php endif; ?>

        <!-- Notifikasi Simpan Pilihan Alamat Utama -->
        <?php if(isset($_GET['status']) && $_GET['status'] == 'saved'): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600;">
                <i class="fas fa-check-circle"></i> Pilihan alamat pengiriman berhasil disimpan!
            </div>
        <?php endif; ?>

        <h2 class="section-title">Alamat Pengiriman</h2>

        <form method="POST" action="alamat.php" id="formAlamatUtama">
            <input type="hidden" name="primary_address_id" id="primaryAddressInput" value="<?= htmlspecialchars((string)$current_primary_id) ?>">
            <input type="hidden" name="redirect" value="<?= $from_checkout ? 'pembayaran' : '' ?>">
            <input type="hidden" name="checkout_id" value="<?= $checkout_id ?>">
            <input type="hidden" name="checkout_qty" value="<?= $checkout_qty ?>">

            <!-- LOOPING DAFTAR ALAMAT -->
            <?php if(count($addresses) > 0): ?>
                <?php foreach($addresses as $alamat): ?>
                    <div class="address-card">
                        <div class="card-body">
                            <div class="address-header">
                                <div class="recipient-name">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($alamat['recipient_name']) ?>
                                </div>
                                
                                <!-- BAGIAN EDIT DAN HAPUS -->
                                <div class="action-links">
                                    <?php
                                        $edit_params = ['id' => $alamat['id']];
                                        if ($from_checkout) {
                                            $edit_params['redirect'] = 'pembayaran';
                                            if ($checkout_id > 0) {
                                                $edit_params['product_id'] = $checkout_id;
                                            }
                                            if ($checkout_qty > 1) {
                                                $edit_params['qty'] = $checkout_qty;
                                            }
                                        }
                                    ?>
                                    <a href="tambah_alamat.php?<?= http_build_query($edit_params) ?>" class="edit-link">Edit</a>
                                    <a href="hapus_alamat.php?id=<?= $alamat['id'] ?>" class="delete-link" onclick="return confirm('Apakah Anda yakin ingin menghapus alamat ini?');">Hapus</a>
                                </div>
                            </div>
                            
                            <!-- Menampilkan No Telp jika ada -->
                            <?php if(!empty($alamat['phone'])): ?>
                                <p class="address-text" style="margin-bottom: 5px; font-weight: 600;">
                                    <i class="fas fa-phone-alt" style="font-size: 12px; margin-right: 5px;"></i> 
                                    <?= htmlspecialchars($alamat['phone']) ?>
                                </p>
                            <?php endif; ?>

                            <p class="address-text">
                                <?= nl2br(htmlspecialchars($alamat['full_address'])) ?>
                            </p>
                        </div>

                        <div class="card-footer">
                            <span class="address-label"><?= htmlspecialchars($alamat['label']) ?></span>
                            
                            <!-- BULATAN PILIH ALAMAT UTAMA (BISA DIKLIK) -->
                            <button type="button"
                                    class="primary-toggle-btn"
                                    data-id="<?= (int)$alamat['id'] ?>"
                                    title="Klik untuk memilih alamat ini">
                                <?php if($alamat['is_primary'] == 1): ?>
                                    <span class="radio-selected"></span>
                                <?php else: ?>
                                    <span class="radio-unselected"></span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Tampilan Jika Belum Punya Alamat -->
                <div style="text-align: center; padding: 40px; background: rgba(255,255,255,0.5); border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-map-marked-alt" style="font-size: 40px; color: #3b2316; margin-bottom: 15px;"></i>
                    <p style="color: #555;">Anda belum menambahkan alamat pengiriman.</p>
                </div>
            <?php endif; ?>

            <!-- TOMBOL TAMBAH ALAMAT & SIMPAN -->
            <div class="action-bottom-wrapper">
                <a href="tambah_alamat.php<?= htmlspecialchars($query_tambah_alamat) ?>" class="add-address-btn">(+) Tambahkan Alamat</a>

                <?php if(count($addresses) > 0): ?>
                    <button type="submit" name="simpan_alamat" class="btn-simpan-alamat">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                <?php endif; ?>
            </div>
        </form>

    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <a href="beranda.php" style="display: flex; align-items: center; gap: 20px;">
                <div class="footer-logo-circle">
                    <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
                </div>
                <span class="footer-brand">Filosofi Kopi</span>
            </a>
        </div>
        
        <div class="footer-right">
            <div class="footer-col payment-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col social-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="https://www.facebook.com/share/14vhccvdQ5P/" target="_blank" rel="noopener noreferrer" class="fb" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/filosofikopi?hvrq=MWpyNTdpYnp5czF3OA==" target="_blank" rel="noopener noreferrer" class="ig" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col app-col">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButtons = document.querySelectorAll('.primary-toggle-btn');
            const primaryInput = document.getElementById('primaryAddressInput');

            toggleButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const addressId = this.getAttribute('data-id');
                    const currentDot = this.querySelector('span');
                    const isAlreadySelected = currentDot.classList.contains('radio-selected');

                    // Reset semua bulatan menjadi kosong terlebih dahulu
                    toggleButtons.forEach(b => {
                        b.querySelector('span').className = 'radio-unselected';
                    });

                    if (isAlreadySelected) {
                        // Jika diklik ulang, batalkan pilihan
                        primaryInput.value = '';
                    } else {
                        // Tandai bulatan yang diklik menjadi hitam penuh
                        currentDot.className = 'radio-selected';
                        primaryInput.value = addressId;
                    }
                });
            });
        });
    </script>

</body>
</html>