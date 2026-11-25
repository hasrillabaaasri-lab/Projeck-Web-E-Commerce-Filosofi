<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data Pribadi Pelanggan - Filosofi Kopi</title>
    <!-- Link ke CSS terpisah -->
    <link rel="stylesheet" href="style.css">
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Top Header -->
    <header class="top-header">
        <div class="header-logo">
            <div class="logo-icon"><i class="fa-solid fa-mug-hot"></i></div>
            <span class="brand-title">Filosofi Kopi</span>
        </div>
        <div class="header-right">
            <span>Dashboard ERP</span>
            <div class="user-badge-header">
                <i class="fa-solid fa-user"></i>
                <span>Administrator</span>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="main-container">
        
        <!-- Top Control Panel Card -->
        <div class="control-card">
            <div class="control-header">
                <div class="title-section">
                    <h2>Manajemen Data Pribadi Pelanggan</h2>
                    <p>Kelola Data Diri Pelanggan & Tersedia penambahan & hapus data diri pelanggan serta manipulasi dan edit</p>
                </div>
                <button class="btn-add">
                    <i class="fa-solid fa-plus"></i> Tambah Data Pelanggan
                </button>
            </div>
            
            <div class="search-section">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Cari berdasarkan nama produk status kode SKU">
                </div>
            </div>
        </div>

        <!-- Customer Data Card -->
        <div class="table-card">
            <div class="table-header-bar">
                <div class="action-header-buttons">
                    <button class="btn-action btn-edit">
                        <i class="fa-solid fa-pen"></i> Ubah Data Pribadi
                    </button>
                    <button class="btn-action btn-delete" title="Hapus Data">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <?php
            // Simulation of customer data
            $customer = [
                'nama' => 'Cristina Siringo',
                'password' => '*********',
                'no_hp' => '+62 897-7855-8187'
            ];
            ?>

            <table class="customer-table">
                <tbody>
                    <tr>
                        <td class="field-label">NAMA</td>
                        <td class="field-value"><?php echo htmlspecialchars($customer['nama']); ?></td>
                    </tr>
                    <tr>
                        <td class="field-label">PASSWORD</td>
                        <td class="field-value"><?php echo htmlspecialchars($customer['password']); ?></td>
                    </tr>
                    <tr>
                        <td class="field-label">NO HP</td>
                        <td class="field-value"><?php echo htmlspecialchars($customer['no_hp']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </main>

    <!-- Footer Area -->
    <footer class="footer">
        <div class="footer-left">
            <div class="footer-logo">
                <i class="fa-solid fa-mug-hot"></i>
                <span>Filosofi Kopi</span>
            </div>
        </div>
        <div class="footer-center">
            <p>Selalu Nikmati Keuntungan dari aplikasi:</p>
            <p><strong>S Terdapat Diskon 5% hanya terdapat di aplikasi</strong></p>
            <div class="qr-placeholder">
                <i class="fa-solid fa-qrcode"></i>
            </div>
        </div>
        <div class="footer-right">
            <div class="social-icons">
                <a href="#"><i class="fa-brands fa-facebook"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>
    </footer>

</body>
</html>