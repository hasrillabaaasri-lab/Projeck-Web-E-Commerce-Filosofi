<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - Filosofi Kopi</title>
    <link rel="stylesheet" href="detail_produk.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- NAVBAR (SAMA DENGAN BERANDA) -->
    <nav class="navbar">
        <div class="nav-left">
            <img src="image 24.png" alt="Logo" class="nav-logo">
            <span class="nav-brand">Filosofi Kopi</span>
            <span class="nav-menu">Kategori</span>
        </div>
        <div class="nav-center">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari produk...">
            </div>
        </div>
        <div class="nav-right">
            <!-- BAGIAN INI DIUBAH: href diarahkan ke halaman_keranjang.php -->
            <a href="halaman_keranjang.php"><i class="fas fa-shopping-cart"></i></a>
            
            <a href="#"><i class="fas fa-envelope"></i></a>
            <a href="#"><i class="fas fa-user"></i></a>
        </div>
    </nav>

    <!-- KONTEN UTAMA -->
    <main class="detail-container">
        
        <!-- DETAIL PRODUK -->
        <section class="product-detail">
            <div class="product-left">
                <img src="imaage 20.png" alt="Sunset Brew" class="product-image">
                
                <!-- TOMBOL BELI SEKARANG -->
                <button class="btn-primary" onclick="window.location.href='pembayaran.php'">Beli Sekarang</button>
                
                <button class="btn-secondary">
                    <i class="fas fa-shopping-cart"></i> Tambah Keranjang
                </button>
            </div>

            <div class="product-right">
                <div class="product-header">
                    <h2>Sunset Brew</h2>
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                        <span class="score">5.0/5</span>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="description">
                    <h3>Deskripsi :</h3>
                    <p>Perpaduan cold drip yang smooth dengan pink grapefruit syrup yang manis-asam. Segarnya langsung nyapa dari tegukan pertama.</p>
                    
                    <h3>Komposisi Utama :</h3>
                    <p>Kopi Ekstraksi Dingin (Cold Drip Coffee)<br>
                    Sirup Jeruk Bali Merah (Pink Grapefruit Syrup)<br>
                    Es batu (opsional sesuai pilihan)</p>
                </div>

                <div class="divider"></div>

                <!-- Opsi Suhu -->
                <div class="options-group">
                    <h4>Pilihan Suhu Es ( Ice Level ) :</h4>
                    <div class="options-row">
                        <label class="option-btn"><input type="radio" name="ice" checked> Ice (Normal)</label>
                        <label class="option-btn"><input type="radio" name="ice"> Less Ice (Sedikit Es)</label>
                        <label class="option-btn"><input type="radio" name="ice"> No Ice (Tanpa Es)</label>
                    </div>
                </div>

                <!-- Opsi Gula -->
                <div class="options-group">
                    <h4>Pilihan Gula (Sugar Level) :</h4>
                    <div class="options-row">
                        <label class="option-btn"><input type="radio" name="sugar" checked> Normal Gula 100%</label>
                        <label class="option-btn"><input type="radio" name="sugar"> Sedikit Gula 70%</label>
                        <label class="option-btn"><input type="radio" name="sugar"> Setengah Gula 50%</label>
                        <label class="option-btn"><input type="radio" name="sugar"> Tanpa Gula 0%</label>
                    </div>
                </div>

                <!-- Opsi Ukuran (Hanya Reguler) -->
                <div class="options-group">
                    <h4>Ukuran Gelas (Size) :</h4>
                    <div class="options-row">
                        <label class="option-btn"><input type="radio" name="size" checked> Reguler</label>
                    </div>
                </div>

                <!-- Kuantitas -->
                <div class="quantity-wrapper">
                    <div class="quantity-box">
                        <button>-</button>
                        <input type="text" value="1" readonly>
                        <button>+</button>
                    </div>
                </div>
            </div>
        </section>

        <div class="divider-full"></div>

        <!-- KOMENTAR & RATING -->
        <section class="comment-section">
            <div class="comment-title">
                <h3>Komentar<br>&<br>Rating</h3>
            </div>
            <div class="comment-form">
                <textarea placeholder=""></textarea>
                <div class="form-actions">
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" />
                        <label for="star5" class="far fa-star"></label>
                        <input type="radio" id="star4" name="rating" value="4" />
                        <label for="star4" class="far fa-star"></label>
                        <input type="radio" id="star3" name="rating" value="3" />
                        <label for="star3" class="far fa-star"></label>
                        <input type="radio" id="star2" name="rating" value="2" />
                        <label for="star2" class="far fa-star"></label>
                        <input type="radio" id="star1" name="rating" value="1" />
                        <label for="star1" class="far fa-star"></label>
                    </div>

                    <div class="upload-btns">
                        <button class="btn-upload">
                            <i class="fas fa-camera"></i><br>Foto/Video
                        </button>
                        <button class="btn-upload">
                            <i class="fas fa-plus"></i><br>Tambah
                        </button>
                    </div>
                    <button class="btn-submit">Kirim</button>
                </div>
            </div>
        </section>

        <div class="divider-full"></div>

        <!-- DAFTAR KOMENTAR (REVIEW) -->
        <section class="review-list">
            <div class="review-card">
                <div class="review-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="review-content">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <h4>Ryandhi Kumala Irsa</h4>
                            <span class="stars">★★★★★</span>
                        </div>
                        <span class="review-date">16-09-2026</span>
                    </div>
                    <p class="review-text">Wahhhh Enak Dan gurih Kopi Nya SEDAP NYEEE</p>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER (SAMA DENGAN BERANDA) -->
    <footer>
        <div class="footer-left">
            <img src="image 24.png" alt="Logo" class="footer-logo">
            <span class="footer-brand">Filosofi Kopi</span>
        </div>
        
        <div class="footer-right">
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
            </div>
            <div class="footer-col">
                <h4>Ikuti Kami</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="app-info">
                <h4>Selalu Nikmatin Keuntungan dari aplikasi:</h4>
                <p class="discount-text">$ Terdapat Diskon 5% hanya terdapat di aplikasi</p>
                <img src="https://via.placeholder.com/100x100/ffffff/000000?text=QR+Code" alt="QR Code" class="qr-code">
            </div>
        </div>
    </footer>

</body>
</html>