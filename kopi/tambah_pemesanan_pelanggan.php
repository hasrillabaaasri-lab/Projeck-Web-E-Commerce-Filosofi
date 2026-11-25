<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk Master - Filosofi Kopi</title>
    <link rel="stylesheet" href="tambah_pemesanan_pelanggan.css">
</head>
<body>

    <!-- Header / Navbar -->
    <header class="navbar">
        <div class="nav-left">
            <img src="image 24.png">
            <span class="brand-name">Filosofi Kopi</span>
            <span class="breadcrumb">> Profile > Alamat</span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2>Tambah Produk Master</h2>
                <p>Lengkapi informasi spesifikasi produk sistem ERP</p>
            </div>

            <form action="" method="POST" class="form-grid">
                <!-- Nama Produk (Full Width) -->
                <div class="form-group full-width">
                    <label for="nama_produk">Nama Produk*</label>
                    <input type="text" id="nama_produk" name="nama_produk" required>
                </div>

                <!-- Kode Barang & Kategori -->
                <div class="form-group">
                    <label for="kode_barang">Kode Barang*</label>
                    <input type="text" id="kode_barang" name="kode_barang" required>
                </div>
                <div class="form-group">
                    <label for="kategori">Kategori*</label>
                    <input type="text" id="kategori" name="kategori" required>
                </div>

                <!-- Stok & Supplier -->
                <div class="form-group">
                    <label for="stok">Stok*</label>
                    <input type="number" id="stok" name="stok" required>
                </div>
                <div class="form-group">
                    <label for="supplier">Supplier*</label>
                    <input type="text" id="supplier" name="supplier" required>
                </div>

                <!-- URL Gambar Produk (Full Width) -->
                <div class="form-group full-width">
                    <label for="url_gambar">URL Gambar Produk*</label>
                    <input type="url" id="url_gambar" name="url_gambar" required>
                </div>

                <!-- Action Buttons -->
                <div class="button-group">
                    <button type="button" class="btn btn-batal">Batal</button>
                    <button type="submit" class="btn btn-simpan">Simpan</button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-left">
            <div class="image 24.png circle"></div>
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        <div class="footer-right">
            <div class="footer-section">
                <span>Metode Pembayaran</span>
            </div>
            <div class="footer-section">
                <span>Ikuti Kami</span>
                <div class="social-icons">
                    <span class="icon facebook">f</span>
                    <span class="icon instagram">📷</span>
                </div>
            </div>
            <div class="footer-info">
                <p>Selalu Nikmati Keuntungan dari aplikasi</p>
                <p><strong>Terdapat Diskon 5% hanya terdapat di aplikasi</strong></p>
            </div>
            <div class="qr-code">
                <!-- Placeholder untuk QR Code -->
                <img src="WhatsApp Image 2026-09-13 at 13.38.34.jpeg">
            </div>
        </div>
    </footer>

</body>
</html>