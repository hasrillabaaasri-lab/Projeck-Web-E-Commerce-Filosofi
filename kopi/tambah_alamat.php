<?php
session_start();
require_once 'config.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';

// Variabel penampung data
$id_alamat = null;
$nama = '';
$alamat = '';
$no_telp = '';
$is_edit = false;
$page_title = 'Tambah Alamat';
$breadcrumb_active = 'Tambah Alamat';

// ==========================================
// TANGKAP PARAMETER REDIRECT (DARI PEMBAYARAN)
// ==========================================
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$product_id_param = isset($_GET['product_id']) ? $_GET['product_id'] : '';

$url_suffix = '';
if ($redirect == 'pembayaran' && !empty($product_id_param)) {
    // Jika dari pembayaran, tambahkan parameter ini ke URL redirect
    $url_suffix = "&redirect=pembayaran&id=" . $product_id_param;
}

// CEK MODE EDIT
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit = true;
    $id_alamat = (int)$_GET['id'];
    $page_title = 'Edit Alamat';
    $breadcrumb_active = 'Edit Alamat';

    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id_alamat, $user_id]);
    $existing_address = $stmt->fetch();

    if (!$existing_address) {
        header("Location: alamat.php");
        exit();
    }

    $nama = $existing_address['recipient_name'];
    $alamat = $existing_address['full_address'];
    $no_telp = $existing_address['phone'];
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama']);
    $alamat  = trim($_POST['alamat']);
    $no_telp = trim($_POST['no_telp']);

    if (empty($nama) || empty($alamat) || empty($no_telp)) {
        $error = "Semua kolom wajib diisi!";
    } else {
        if ($is_edit) {
            $sql = "UPDATE addresses SET recipient_name = ?, phone = ?, full_address = ? WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nama, $no_telp, $alamat, $id_alamat, $user_id])) {
                // Tambahkan $url_suffix di sini
                header("Location: alamat.php?status=updated" . $url_suffix);
                exit();
            }
        } else {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ?");
            $stmt_check->execute([$user_id]);
            $is_first = ($stmt_check->fetchColumn() == 0) ? 1 : 0;

            $sql = "INSERT INTO addresses (user_id, recipient_name, phone, full_address, is_primary) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$user_id, $nama, $no_telp, $alamat, $is_first])) {
                // Tambahkan $url_suffix di sini
                header("Location: alamat.php?status=success" . $url_suffix);
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_alamat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- TOP NAVBAR -->
    <header class="top-navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo" onerror="this.style.display='none'">
            <span class="nav-brand">Filosofi Kopi</span>
            <span class="nav-separator">&gt;</span>
            <a href="profile.php" class="nav-page" style="text-decoration: none; color: inherit;">Profile</a>
            <span class="nav-separator">&gt;</span>
            <a href="alamat.php" class="nav-page" style="text-decoration: none; color: inherit;">Alamat</a>
            <span class="nav-separator">&gt;</span>
            <span class="nav-page active"><?= $breadcrumb_active ?></span>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="main-container">
        
        <?php if($error): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Action form dikosongkan agar submit ke URL saat ini (mempertahankan parameter GET) -->
        <form action="" method="POST" class="address-form">
            
            <!-- INPUT NAMA -->
            <div class="form-group">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" class="input-pill" value="<?= htmlspecialchars($nama) ?>" required autocomplete="off">
            </div>

            <!-- INPUT ALAMAT -->
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" class="input-area" required><?= htmlspecialchars($alamat) ?></textarea>
            </div>

            <!-- INPUT NO TELP -->
            <div class="form-group">
                <label for="no_telp">No.Telp</label>
                <input type="text" id="no_telp" name="no_telp" class="input-pill" value="<?= htmlspecialchars($no_telp) ?>" required autocomplete="off">
            </div>

            <!-- BAGIAN PETA (MAPS) -->
            <div class="map-container">
                <!-- ID "map-frame" ditambahkan agar bisa diubah oleh Javascript -->
                <iframe 
                    id="map-frame"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126748.56347861231!2d106.94944987825968!3d-6.237363202089404!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e698d76b7c243eb%3A0x301576d14feb940!2sBekasi%2C%20Bekasi%20City%2C%20West%20Java!5e0!3m2!1sen!2sid!4v1700000000000!5m2!1sen!2sid" 
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>

            <!-- TOMBOL LOKASI -->
            <div class="location-btn-wrapper">
                <button type="button" class="btn-location" id="btn-lokasi" onclick="getLocation()">
                    <i class="fas fa-location-dot"></i> Mencari Lokasi Anda Klik
                </button>
            </div>

            <!-- TOMBOL SIMPAN -->
            <div class="form-actions">
                <button type="submit" class="btn-simpan">Simpan</button>
            </div>

        </form>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <div class="footer-logo-circle">
                <img src="image 24.png" alt="Logo Footer" onerror="this.style.display='none'">
            </div>
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        
        <div class="footer-right">
            <div class="footer-col payment-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col social-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#" class="fb"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="ig"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col app-col">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://samsoncoffee.com/app" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

    <!-- SCRIPT OTOMATISASI LOKASI & PIN PETA -->
    <script>
        function getLocation() {
            const btn = document.getElementById('btn-lokasi');
            const alamatInput = document.getElementById('alamat');
            const mapFrame = document.getElementById('map-frame'); // Ambil elemen peta

            if (navigator.geolocation) {
                // Ubah teks tombol jadi loading
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari Lokasi...';
                btn.disabled = true;

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        // 1. UBAH PETA GOOGLE MAPS (Memunculkan Pin Merah)
                        mapFrame.src = `https://maps.google.com/maps?q=${lat},${lon}&z=17&output=embed`;

                        // 2. AMBIL NAMA JALAN (Reverse Geocoding via OpenStreetMap)
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`, {
                            headers: { 'Accept-Language': 'id' }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.display_name) {
                                // Isi otomatis textarea alamat
                                alamatInput.value = data.display_name;
                            } else {
                                alert('Alamat tidak ditemukan untuk lokasi ini.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Gagal mengambil data alamat dari peta.');
                        })
                        .finally(() => {
                            // Kembalikan tombol ke semula
                            btn.innerHTML = '<i class="fas fa-location-dot"></i> Mencari Lokasi Anda Klik';
                            btn.disabled = false;
                        });
                    },
                    function(error) {
                        let errorMsg = 'Gagal mendapatkan lokasi. ';
                        if (error.code === 1) errorMsg += 'Izin lokasi ditolak.';
                        else if (error.code === 2) errorMsg += 'Informasi lokasi tidak tersedia.';
                        else if (error.code === 3) errorMsg += 'Waktu pencarian habis.';
                        
                        alert(errorMsg);
                        btn.innerHTML = '<i class="fas fa-location-dot"></i> Mencari Lokasi Anda Klik';
                        btn.disabled = false;
                    }
                );
            } else {
                alert("Geolocation tidak didukung oleh browser ini.");
            }
        }
    </script>
</body>
</html>