<?php
require_once 'config.php';

// Slider'ları çek
$stmt = $db->query("SELECT * FROM slider WHERE aktif = 1 ORDER BY sira ASC");
$sliders = $stmt->fetchAll();

// Vitrin ürünlerini çek
$stmt = $db->query("SELECT u.*, k.kategori_adi FROM urunler u 
                    LEFT JOIN kategoriler k ON u.kategori_id = k.id 
                    WHERE u.vitrin_urunu = 1 AND u.aktif = 1 
                    ORDER BY u.id DESC LIMIT 8");
$vitrin_urunler = $stmt->fetchAll();

// Yeni ürünleri çek
$stmt = $db->query("SELECT u.*, k.kategori_adi FROM urunler u 
                    LEFT JOIN kategoriler k ON u.kategori_id = k.id 
                    WHERE u.yeni_urun = 1 AND u.aktif = 1 
                    ORDER BY u.olusturma_tarihi DESC LIMIT 4");
$yeni_urunler = $stmt->fetchAll();

// Kategorileri çek
$stmt = $db->query("SELECT * FROM kategoriler WHERE aktif = 1 ORDER BY sira ASC");
$kategoriler = $stmt->fetchAll();

// Sepet işlemleri
if (isset($_POST['sepete_ekle'])) {
    $urun_id = (int)$_POST['urun_id'];
    $adet = isset($_POST['adet']) ? (int)$_POST['adet'] : 1;
    sepeteEkle($urun_id, $adet);
    header("Location: index.php?sepet=eklendi");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        /* Header */
        .top-bar {
            background: #1a1a1a;
            color: white;
            padding: 8px 0;
            font-size: 13px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .search-bar {
            flex: 1;
            max-width: 600px;
            margin: 0 40px;
            position: relative;
        }
        
        .search-bar input {
            width: 100%;
            padding: 14px 50px 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 30px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .header-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }
        
        .header-btn:hover {
            color: #667eea;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 700;
        }
        
        /* Navigation */
        nav {
            background: white;
            border-top: 1px solid #f0f0f0;
            padding: 0;
        }
        
        .nav-container {
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .nav-link {
            padding: 16px 24px;
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s;
            position: relative;
        }
        
        .nav-link:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }
        
        .nav-link:hover::after {
            width: 80%;
        }
        
        /* Slider */
        .slider {
            position: relative;
            height: 500px;
            overflow: hidden;
            margin-bottom: 60px;
        }
        
        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .slide.active {
            opacity: 1;
        }
        
        .slide-content {
            text-align: center;
            color: white;
            max-width: 800px;
            padding: 0 20px;
        }
        
        .slide-content h2 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 2px 2px 20px rgba(0,0,0,0.2);
        }
        
        .slide-content p {
            font-size: 22px;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        
        .slide-btn {
            display: inline-block;
            padding: 16px 40px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .slide-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        /* Sections */
        .section {
            margin-bottom: 60px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }
        
        .section-title {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a1a;
        }
        
        .view-all {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        
        .product-image {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .product-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            padding: 10px;
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff4757;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            z-index: 10;
        }
        
        .product-badge.new {
            background: #2ed573;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            color: #667eea;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .product-price {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .price-current {
            font-size: 24px;
            font-weight: 800;
            color: #1a1a1a;
        }
        
        .price-old {
            font-size: 16px;
            color: #999;
            text-decoration: line-through;
        }
        
        .add-to-cart {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-to-cart:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Categories */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .category-card {
            background: white;
            padding: 35px 25px;
            border-radius: 16px;
            text-align: center;
            text-decoration: none;
            color: #1a1a1a;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .category-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .category-name {
            font-size: 16px;
            font-weight: 700;
        }
        
        /* Footer */
        footer {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 12px;
        }
        
        .footer-section a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: #667eea;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #333;
            color: #999;
        }
        
        /* Success Message */
        .success-message {
            background: #2ed573;
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .header-main {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-bar {
                margin: 0;
                max-width: 100%;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .nav-container {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .nav-link {
                padding: 10px 15px;
                font-size: 13px;
            }
            
            .slide-content h2 {
                font-size: 32px;
            }
            
            .slide-content p {
                font-size: 16px;
            }
            
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .product-card {
                border-radius: 12px;
            }
            
            .product-info {
                padding: 12px;
            }
            
            .product-name {
                font-size: 13px;
                margin-bottom: 8px;
            }
            
            .price-current {
                font-size: 18px;
            }
            
            .price-old {
                font-size: 13px;
            }
            
            .add-to-cart {
                padding: 10px;
                font-size: 13px;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .category-card {
                padding: 25px 15px;
            }
            
            .category-icon {
                font-size: 36px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .top-bar .container {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                font-size: 24px;
            }
            
            .search-bar input {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            .search-btn {
                padding: 12px 20px;
                font-size: 14px;
            }
            
            .slider {
                height: 350px;
            }
            
            .slide-content h2 {
                font-size: 24px;
            }
            
            .slide-content p {
                font-size: 14px;
            }
            
            .slide-btn {
                padding: 12px 24px;
                font-size: 14px;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .section {
                margin-bottom: 40px;
            }
            
            .header-btn {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div>📞 Müşteri Hizmetleri: 0850 123 45 67</div>
            <div>🚚 Ücretsiz Kargo 500₺ Üzeri | 🎁 Kapıda Ödeme İmkanı</div>
        </div>
    </div>
    
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-main">
                <a href="index.php" class="logo">ElitGSM</a>
                
                <div class="search-bar">
                    <form action="arama.php" method="GET">
                        <input type="text" name="q" placeholder="Ürün, marka veya kategori ara...">
                        <button type="submit" class="search-btn">Ara</button>
                    </form>
                </div>
                
                <div class="header-actions">
                    <a href="hesabim.php" class="header-btn">
                        <span>👤</span> Hesabım
                    </a>
                    <a href="sepet.php" class="header-btn">
                        <span>🛒</span> Sepet
                        <?php if (sepetAdet() > 0): ?>
                            <span class="cart-badge"><?php echo sepetAdet(); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav>
        <div class="container">
            <div class="nav-container">
                <a href="index.php" class="nav-link">Ana Sayfa</a>
                <?php foreach ($kategoriler as $kat): ?>
                    <a href="kategori.php?slug=<?php echo $kat['kategori_slug']; ?>" class="nav-link">
                        <?php echo $kat['kategori_adi']; ?>
                    </a>
                <?php endforeach; ?>
                <a href="kampanyalar.php" class="nav-link">🔥 Kampanyalar</a>
            </div>
        </div>
    </nav>
    
    <!-- Success Message -->
    <?php if (isset($_GET['sepet']) && $_GET['sepet'] == 'eklendi'): ?>
        <div class="container" style="margin-top: 20px;">
            <div class="success-message">
                ✓ Ürün sepete başarıyla eklendi!
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Slider -->
    <div class="slider">
        <?php foreach ($sliders as $index => $slider): ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <div class="slide-content">
                    <h2><?php echo htmlspecialchars($slider['baslik']); ?></h2>
                    <p><?php echo htmlspecialchars($slider['alt_baslik']); ?></p>
                    <?php if ($slider['link'] && $slider['buton_text']): ?>
                        <a href="<?php echo $slider['link']; ?>" class="slide-btn">
                            <?php echo htmlspecialchars($slider['buton_text']); ?> →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Categories Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Kategoriler</h2>
            </div>
            <div class="categories-grid">
                <?php foreach ($kategoriler as $kat): ?>
                    <a href="kategori.php?slug=<?php echo $kat['kategori_slug']; ?>" class="category-card">
                        <div class="category-icon">
                            <?php 
                            $icons = [
                                'smartphone' => '📱',
                                'tablet' => '📱',
                                'watch' => '⌚',
                                'headphones' => '🎧',
                                'cases' => '💼',
                                'computer' => '💻'
                            ];
                            echo $icons[$kat['ikon']] ?? '🔷';
                            ?>
                        </div>
                        <div class="category-name"><?php echo $kat['kategori_adi']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- Featured Products -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Öne Çıkan Ürünler</h2>
                <a href="tum-urunler.php" class="view-all">Tümünü Gör →</a>
            </div>
            <div class="product-grid">
                <?php foreach ($vitrin_urunler as $urun): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="images/<?php echo $urun['resim']; ?>" 
                                 alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                 onerror="this.src='https://via.placeholder.com/300x300?text=<?php echo urlencode($urun['urun_adi']); ?>'">
                            <?php if ($urun['indirimli_fiyat']): ?>
                                <div class="product-badge">
                                    -%<?php echo indirimYuzdesi($urun['fiyat'], $urun['indirimli_fiyat']); ?>
                                </div>
                            <?php elseif ($urun['yeni_urun']): ?>
                                <div class="product-badge new">YENİ</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?php echo $urun['kategori_adi']; ?></div>
                            <div class="product-name">
                                <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>" 
                                   style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($urun['urun_adi']); ?>
                                </a>
                            </div>
                            <div class="product-price">
                                <span class="price-current">
                                    <?php 
                                    $gosterilecek_fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                                    echo fiyatFormat($gosterilecek_fiyat); 
                                    ?>
                                </span>
                                <?php if ($urun['indirimli_fiyat']): ?>
                                    <span class="price-old"><?php echo fiyatFormat($urun['fiyat']); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="urun_id" value="<?php echo $urun['id']; ?>">
                                <input type="hidden" name="adet" value="1">
                                <button type="submit" name="sepete_ekle" class="add-to-cart">
                                    🛒 Sepete Ekle
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- New Products -->
        <?php if (!empty($yeni_urunler)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Yeni Ürünler</h2>
                <a href="yeni-urunler.php" class="view-all">Tümünü Gör →</a>
            </div>
            <div class="product-grid">
                <?php foreach ($yeni_urunler as $urun): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="images/<?php echo $urun['resim']; ?>" 
                                 alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                 onerror="this.src='https://via.placeholder.com/300x300?text=<?php echo urlencode($urun['urun_adi']); ?>'">
                            <div class="product-badge new">YENİ</div>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?php echo $urun['kategori_adi']; ?></div>
                            <div class="product-name">
                                <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>" 
                                   style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($urun['urun_adi']); ?>
                                </a>
                            </div>
                            <div class="product-price">
                                <span class="price-current">
                                    <?php 
                                    $gosterilecek_fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                                    echo fiyatFormat($gosterilecek_fiyat); 
                                    ?>
                                </span>
                                <?php if ($urun['indirimli_fiyat']): ?>
                                    <span class="price-old"><?php echo fiyatFormat($urun['fiyat']); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="urun_id" value="<?php echo $urun['id']; ?>">
                                <input type="hidden" name="adet" value="1">
                                <button type="submit" name="sepete_ekle" class="add-to-cart">
                                    🛒 Sepete Ekle
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>ElitGSM</h3>
                    <p>Türkiye'nin en güvenilir teknoloji alışveriş platformu. Orijinal ürünler, uygun fiyatlar ve hızlı teslimat.</p>
                </div>
                <div class="footer-section">
                    <h3>Kurumsal</h3>
                    <ul>
                        <li><a href="#">Hakkımızda</a></li>
                        <li><a href="#">İletişim</a></li>
                        <li><a href="#">Mağazalarımız</a></li>
                        <li><a href="#">Kariyer</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Müşteri Hizmetleri</h3>
                    <ul>
                        <li><a href="#">Sıkça Sorulan Sorular</a></li>
                        <li><a href="#">Kargo ve Teslimat</a></li>
                        <li><a href="#">İptal ve İade</a></li>
                        <li><a href="#">Garanti Koşulları</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>İletişim</h3>
                    <ul>
                        <li>📞 0850 123 45 67</li>
                        <li>📧 info@elitgsm.com</li>
                        <li>📍 İstanbul, Türkiye</li>
                        <li>🕐 Pzt-Cmt: 09:00 - 21:00</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 ElitGSM. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Slider
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        
        function showSlide(n) {
            slides.forEach(slide => slide.classList.remove('active'));
            currentSlide = (n + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
        }
        
        function nextSlide() {
            showSlide(currentSlide + 1);
        }
        
        // Auto slide
        if (slides.length > 1) {
            setInterval(nextSlide, 5000);
        }
    </script>
</body>
</html>