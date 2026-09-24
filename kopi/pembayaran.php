<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Filosofi Kopi</title>
    <link rel="stylesheet" href="pembayaran.css">
    <!-- FontAwesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navbar (Persis Beranda) -->
    <nav class="navbar">
        <div class="nav-left">
            <!-- GANTI SRC DI BAWAH INI DENGAN NAMA FILE LOGO ANDA -->
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <h1 class="nav-brand">Filosofi Kopi</h1>
            <span class="nav-menu">Kategori</span>
        </div>
        
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>

        <div class="nav-right">
            <a href="#"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-envelope"></i></a>
            <a href="#"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- Main Content (Pembayaran) -->
    <div class="main-container">
        
        <!-- Kolom Kiri -->
        <div class="left-column">
            
            <!-- Alamat Pengiriman -->
            <section class="section-block">
                <h2 class="section-title">Alamat Pengiriman</h2>
                <div class="card address-card">
                    <div class="address-header">
                        <div class="address-name">
                            <i class="fas fa-map-marker-alt"></i> Ibu Nya Anu
                        </div>
                        <a href="#" class="edit-link">Edit</a>
                    </div>
                    <p class="address-detail">kAGAK TAU DAH RT003/RW 010 KALO DI TANYA KOTA APA ONO UJUNG DAG POKOK NYA MAH</p>
                </div>
            </section>

            <!-- Produk -->
            <section class="section-block">
                <div class="card product-card">
                    <div class="product-image">
                        <img src="https://via.placeholder.com/100x120/8B5A2B/FFFFFF?text=Kopi" alt="Kopi Aing Macan">
                    </div>
                    <div class="product-info">
                        <h3 class="product-title">Sunset Brew</h3>
                        <p class="product-desc">Perpaduan cold drip yang smooth dengan pink grapefruit syrup yang manis-asam. Segarnya langsung nyapa dari tegukan pertama.</p>
                        <p class="product-variant">Ice Norma,Gula Normal 100%,Reguler, x1</p>
                        <div class="product-price">Rp15.000</div>
                    </div>
                </div>
            </section>

            <!-- Metode Pembayaran -->
            <section class="section-block">
                <h2 class="section-title">Metode Pembayaran</h2>
                <div class="card payment-card">
                    <div class="payment-option">
                        <div class="payment-logo">
                            <img src="https://via.placeholder.com/60x25/000000/FFFFFF?text=QRIS" alt="QRIS">
                            <span>Qris</span>
                        </div>
                        <input type="radio" name="payment" id="qris" class="radio-custom">
                    </div>
                    <div class="divider"></div>
                    <div class="payment-option">
                        <div class="payment-logo">
                            <i class="fas fa-money-bill-wave cod-icon"></i>
                            <span>COD</span>
                        </div>
                        <input type="radio" name="payment" id="cod" class="radio-custom">
                    </div>
                </div>
            </section>

        </div>

        <!-- Kolom Kanan -->
        <div class="right-column">
            <div class="card summary-card">
                <h3 class="summary-title">Ringkasan pesanan</h3>
                
                <div class="summary-row">
                    <span>1x Barang</span>
                    <span>Rp15000</span>
                </div>
                <div class="summary-row">
                    <span>Layanan Biaya</span>
                    <span>Rp1000</span>
                </div>
                <div class="summary-row">
                    <span>Pajak Penjualan</span>
                    <span>Rp-</span>
                </div>
                <div class="summary-row">
                    <span>Ongkir</span>
                    <span>Rp-</span>
                </div>

                <div class="summary-total">
                    <span>Total</span>
                    <span>Rp16000</span>
                </div>
            </div>

            <button class="btn-order">Pesan Sekarang</button>
        </div>

    </div>

    <!-- Footer (Persis Beranda) -->
    <footer>
        <div class="footer-left">
            <!-- GANTI SRC DI BAWAH INI DENGAN NAMA FILE LOGO ANDA -->
            <img src="image 24.png" alt="Logo" class="footer-logo">
            <h2 class="footer-brand">Filosofi Kopi</h2>
        </div>

        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col">
                <h4>Ikutin Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://via.placeholder.com/100x100/FFFFFF/000000?text=QR" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>