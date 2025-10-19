<?php
require_once 'config.php';

// Ürün slug'ını al
$slug = isset($_GET['slug']) ? temizle($_GET['slug']) : '';

if (empty($slug)) {
    header("Location: index.php");
    exit;
}

// Ürünü çek
$stmt = $db->prepare("SELECT u.*, k.kategori_adi, k.kategori_slug 
                      FROM urunler u 
                      LEFT JOIN kategoriler k ON u.kategori_id = k.id 
                      WHERE u.urun_slug = ? AND u.aktif = 1");
$stmt->execute([$slug]);
$urun = $stmt->fetch();

if (!$urun) {
    header("Location: index.php");
    exit;
}

// Görüntüleme sayısını artır
$stmt = $db->prepare("UPDATE urunler SET goruntuleme = goruntuleme + 1 WHERE id = ?");
$stmt->execute([$urun['id']]);

// Ürün resimlerini çek (yeni tablo)
$stmt = $db->prepare("SELECT * FROM urun_resimleri WHERE urun_id = ? ORDER BY sira ASC");
$stmt->execute([$urun['id']]);
$urun_resimleri = $stmt->fetchAll();

// Eğer yeni tabloda resim yoksa eski sütunlardan al
if (empty($urun_resimleri)) {
    $urun_resimleri = [];
    if ($urun['resim']) {
        $urun_resimleri[] = ['resim_yolu' => $urun['resim'], 'ana_resim' => 1];
    }
    if ($urun['resim2']) {
        $urun_resimleri[] = ['resim_yolu' => $urun['resim2'], 'ana_resim' => 0];
    }
    if ($urun['resim3']) {
        $urun_resimleri[] = ['resim_yolu' => $urun['resim3'], 'ana_resim' => 0];
    }
    if ($urun['resim4']) {
        $urun_resimleri[] = ['resim_yolu' => $urun['resim4'], 'ana_resim' => 0];
    }
    if ($urun['resim5']) {
        $urun_resimleri[] = ['resim_yolu' => $urun['resim5'], 'ana_resim' => 0];
    }
}

// Benzer ürünleri çek
$stmt = $db->prepare("SELECT * FROM urunler 
                      WHERE kategori_id = ? AND id != ? AND aktif = 1 
                      ORDER BY RAND() LIMIT 4");
$stmt->execute([$urun['kategori_id'], $urun['id']]);
$benzer_urunler = $stmt->fetchAll();

// Sepete ekleme
if (isset($_POST['sepete_ekle'])) {
    $adet = isset($_POST['adet']) ? (int)$_POST['adet'] : 1;
    sepeteEkle($urun['id'], $adet);
    header("Location: sepet.php");
    exit;
}

$fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
$indirim_yuzdesi = indirimYuzdesi($urun['fiyat'], $urun['indirimli_fiyat']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($urun['urun_adi']); ?> - <?php echo SITE_NAME; ?></title>
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
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px 0;
            margin-bottom: 40px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .header-actions {
            display: flex;
            gap: 20px;
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
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb span {
            color: #999;
        }
        
        /* Product Layout */
        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            background: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 60px;
        }
        
        /* Image Gallery */
        .image-gallery {
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .main-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 16px;
            overflow: hidden;
            background: #f8f9fa;
            margin-bottom: 20px;
            position: relative;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 20px;
        }
        
        .slider-controls {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 10px;
            z-index: 10;
        }
        
        .slider-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: none;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s;
        }
        
        .slider-btn:hover {
            background: white;
            transform: scale(1.1);
        }
        
        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .slider-indicators {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .indicator.active {
            background: white;
            width: 30px;
            border-radius: 5px;
        }
        
        .discount-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ff4757;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 800;
            z-index: 10;
        }
        
        .image-thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .thumbnail {
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 5px;
        }
        
        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .product-category {
            color: #667eea;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-title {
            font-size: 36px;
            font-weight: 800;
            color: #1a1a1a;
            line-height: 1.3;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stars {
            color: #ffc107;
            font-size: 20px;
        }
        
        .rating-text {
            color: #666;
            font-size: 14px;
        }
        
        .product-price-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 16px;
        }
        
        .price-row {
            display: flex;
            align-items: baseline;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .current-price {
            font-size: 42px;
            font-weight: 900;
            color: #667eea;
        }
        
        .old-price {
            font-size: 24px;
            color: #999;
            text-decoration: line-through;
        }
        
        .save-amount {
            background: #2ed573;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
        }
        
        .stock-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
        }
        
        .stock-badge {
            background: #2ed573;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        
        .product-description {
            color: #666;
            font-size: 16px;
            line-height: 1.8;
        }
        
        .product-features {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
        }
        
        .features-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .features-list {
            list-style: none;
        }
        
        .features-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .features-list li:last-child {
            border-bottom: none;
        }
        
        /* Add to Cart Section */
        .add-to-cart-section {
            background: white;
            border: 2px solid #e0e0e0;
            padding: 30px;
            border-radius: 16px;
            position: sticky;
            top: 20px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .quantity-label {
            font-weight: 700;
            font-size: 16px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .quantity-control button {
            background: #f8f9fa;
            border: none;
            width: 45px;
            height: 45px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quantity-control button:hover {
            background: #667eea;
            color: white;
        }
        
        .quantity-control input {
            width: 70px;
            text-align: center;
            border: none;
            font-size: 18px;
            font-weight: 700;
        }
        
        .add-to-cart-btn {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .buy-now-btn {
            width: 100%;
            padding: 18px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .buy-now-btn:hover {
            background: #f8f9fa;
        }
        
        .delivery-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .info-icon {
            font-size: 24px;
        }
        
        /* Similar Products */
        .similar-products {
            margin-top: 80px;
        }
        
        .section-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 35px;
        }
        
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
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        
        .product-image {
            position: relative;
            padding-top: 100%;
            background: #f8f9fa;
        }
        
        .product-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-card-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a1a;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 800;
            color: #667eea;
        }
        
        @media (max-width: 968px) {
            .product-layout {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 20px;
            }
            
            .product-title {
                font-size: 24px;
            }
            
            .current-price {
                font-size: 28px;
            }
            
            .image-gallery {
                position: static;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .breadcrumb {
                font-size: 12px;
                flex-wrap: wrap;
            }
            
            .product-layout {
                padding: 15px;
            }
            
            .product-title {
                font-size: 20px;
            }
            
            .product-category {
                font-size: 12px;
            }
            
            .product-rating {
                font-size: 12px;
            }
            
            .current-price {
                font-size: 24px;
            }
            
            .old-price {
                font-size: 18px;
            }
            
            .save-amount {
                font-size: 12px;
                padding: 6px 12px;
            }
            
            .product-description {
                font-size: 14px;
            }
            
            .features-title {
                font-size: 16px;
            }
            
            .features-list li {
                font-size: 13px;
                padding: 8px 0;
            }
            
            .add-to-cart-section {
                padding: 20px;
            }
            
            .quantity-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quantity-control {
                justify-content: center;
            }
            
            .add-to-cart-btn,
            .buy-now-btn {
                font-size: 14px;
                padding: 14px;
            }
            
            .delivery-info {
                padding-top: 20px;
            }
            
            .info-item {
                font-size: 12px;
            }
            
            .slider-btn {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .indicator {
                width: 8px;
                height: 8px;
            }
            
            .indicator.active {
                width: 24px;
            }
            
            .image-thumbnails {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 8px;
            }
            
            .section-title {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .product-title {
                font-size: 18px;
            }
            
            .current-price {
                font-size: 22px;
            }
            
            .main-image {
                border-radius: 12px;
            }
            
            .product-features {
                padding: 20px;
            }
            
            .add-to-cart-section {
                padding: 15px;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">ElitGSM</a>
                <div class="header-actions">
                    <a href="index.php" class="header-btn">← Ana Sayfa</a>
                    <a href="sepet.php" class="header-btn">
                        <span>🛒</span> Sepet
                        <?php if (sepetAdet() > 0): ?>
                            <span class="cart-badge"><?php echo sepetAdet(); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Ana Sayfa</a>
            <span>></span>
            <a href="kategori.php?slug=<?php echo $urun['kategori_slug']; ?>">
                <?php echo htmlspecialchars($urun['kategori_adi']); ?>
            </a>
            <span>></span>
            <span><?php echo htmlspecialchars($urun['urun_adi']); ?></span>
        </div>
        
        <!-- Product Detail -->
        <div class="product-layout">
            <!-- Image Gallery -->
            <div class="image-gallery">
                <div class="main-image" id="mainImage">
                    <?php if ($indirim_yuzdesi > 0): ?>
                        <div class="discount-badge">-%<?php echo $indirim_yuzdesi; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($urun_resimleri)): ?>
                        <img src="images/<?php echo $urun_resimleri[0]['resim_yolu']; ?>" 
                             alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                             id="currentImage"
                             onerror="this.src='https://via.placeholder.com/600x600?text=<?php echo urlencode($urun['urun_adi']); ?>'">
                    <?php endif; ?>
                    
                    <?php if (count($urun_resimleri) > 1): ?>
                        <div class="slider-controls">
                            <button class="slider-btn" onclick="prevImage()" id="prevBtn">‹</button>
                            <button class="slider-btn" onclick="nextImage()" id="nextBtn">›</button>
                        </div>
                        
                        <div class="slider-indicators" id="indicators">
                            <?php foreach ($urun_resimleri as $index => $resim): ?>
                                <div class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     onclick="goToImage(<?php echo $index; ?>)"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($urun_resimleri) > 1): ?>
                    <div class="image-thumbnails">
                        <?php foreach ($urun_resimleri as $index => $resim): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 onclick="goToImage(<?php echo $index; ?>)"
                                 data-index="<?php echo $index; ?>">
                                <img src="images/<?php echo $resim['resim_yolu']; ?>" 
                                     alt="Görsel <?php echo $index + 1; ?>"
                                     onerror="this.src='https://via.placeholder.com/200x200?text=<?php echo $index + 1; ?>'">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div class="product-info">
                <div class="product-category">
                    <?php echo htmlspecialchars($urun['kategori_adi']); ?>
                </div>
                
                <h1 class="product-title">
                    <?php echo htmlspecialchars($urun['urun_adi']); ?>
                </h1>
                
                <div class="product-rating">
                    <div class="stars">★★★★★</div>
                    <span class="rating-text">(<?php echo $urun['goruntuleme']; ?> değerlendirme)</span>
                </div>
                
                <div class="product-price-section">
                    <div class="price-row">
                        <div class="current-price">
                            <?php echo fiyatFormat($fiyat); ?>
                        </div>
                        <?php if ($urun['indirimli_fiyat']): ?>
                            <div class="old-price">
                                <?php echo fiyatFormat($urun['fiyat']); ?>
                            </div>
                            <div class="save-amount">
                                <?php echo fiyatFormat($urun['fiyat'] - $urun['indirimli_fiyat']); ?> tasarruf
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stock-info">
                        <span class="stock-badge">✓ Stokta</span>
                        <span style="color: #666; font-size: 14px;">
                            <?php echo $urun['stok_miktari']; ?> adet mevcut
                        </span>
                    </div>
                </div>
                
                <?php if ($urun['aciklama']): ?>
                <div class="product-description">
                    <?php echo nl2br(htmlspecialchars($urun['aciklama'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($urun['ozellikler']): ?>
                <div class="product-features">
                    <h3 class="features-title">Ürün Özellikleri</h3>
                    <ul class="features-list">
                        <?php 
                        $ozellikler = explode(',', $urun['ozellikler']);
                        foreach ($ozellikler as $ozellik): 
                        ?>
                            <li>
                                <span style="color: #667eea;">✓</span>
                                <?php echo trim(htmlspecialchars($ozellik)); ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($urun['marka']): ?>
                            <li>
                                <span style="color: #667eea;">✓</span>
                                Marka: <strong><?php echo htmlspecialchars($urun['marka']); ?></strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="add-to-cart-section">
                    <form method="POST">
                        <div class="quantity-selector">
                            <span class="quantity-label">Adet:</span>
                            <div class="quantity-control">
                                <button type="button" onclick="decreaseQuantity()">-</button>
                                <input type="number" id="quantity" name="adet" value="1" min="1" max="<?php echo $urun['stok_miktari']; ?>" readonly>
                                <button type="button" onclick="increaseQuantity(<?php echo $urun['stok_miktari']; ?>)">+</button>
                            </div>
                        </div>
                        
                        <button type="submit" name="sepete_ekle" class="add-to-cart-btn">
                            <span>🛒</span> Sepete Ekle
                        </button>
                    </form>
                    
                    <button class="buy-now-btn" onclick="document.querySelector('form').submit();">
                        Hemen Al
                    </button>
                    
                    <div class="delivery-info">
                        <div class="info-item">
                            <span class="info-icon">🚚</span>
                            <div>
                                <strong>Ücretsiz Kargo</strong><br>
                                500₺ ve üzeri alışverişlerde
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">⚡</span>
                            <div>
                                <strong>Hızlı Teslimat</strong><br>
                                Aynı gün kargo
                            </div>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">🔄</span>
                            <div>
                                <strong>Kolay İade</strong><br>
                                14 gün içinde ücretsiz iade
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Similar Products -->
        <?php if (!empty($benzer_urunler)): ?>
        <div class="similar-products">
            <h2 class="section-title">Benzer Ürünler</h2>
            <div class="product-grid">
                <?php foreach ($benzer_urunler as $benzer): ?>
                    <div class="product-card">
                        <a href="urun.php?slug=<?php echo $benzer['urun_slug']; ?>">
                            <div class="product-image">
                                <img src="images/<?php echo $benzer['resim']; ?>" 
                                     alt="<?php echo htmlspecialchars($benzer['urun_adi']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x300?text=<?php echo urlencode($benzer['urun_adi']); ?>'">
                            </div>
                        </a>
                        <div class="product-card-info">
                            <div class="product-name">
                                <a href="urun.php?slug=<?php echo $benzer['urun_slug']; ?>" 
                                   style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($benzer['urun_adi']); ?>
                                </a>
                            </div>
                            <div class="product-price">
                                <?php 
                                $benzer_fiyat = $benzer['indirimli_fiyat'] ? $benzer['indirimli_fiyat'] : $benzer['fiyat'];
                                echo fiyatFormat($benzer_fiyat); 
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Resim slider sistemi
        const images = [
            <?php foreach ($urun_resimleri as $resim): ?>
                'images/<?php echo $resim['resim_yolu']; ?>',
            <?php endforeach; ?>
        ];
        
        let currentIndex = 0;
        
        function goToImage(index) {
            currentIndex = index;
            updateImage();
        }
        
        function nextImage() {
            currentIndex = (currentIndex + 1) % images.length;
            updateImage();
        }
        
        function prevImage() {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            updateImage();
        }
        
        function updateImage() {
            const img = document.getElementById('currentImage');
            img.src = images[currentIndex];
            
            // Thumbnail'leri güncelle
            document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
                thumb.classList.toggle('active', index === currentIndex);
            });
            
            // Indicator'ları güncelle
            document.querySelectorAll('.indicator').forEach((indicator, index) => {
                indicator.classList.toggle('active', index === currentIndex);
            });
            
            // Buton durumlarını güncelle
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            if (prevBtn) prevBtn.disabled = false;
            if (nextBtn) nextBtn.disabled = false;
        }
        
        // Klavye kontrolleri
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
        
        // Touch/Swipe desteği
        let touchStartX = 0;
        let touchEndX = 0;
        
        const mainImage = document.getElementById('mainImage');
        if (mainImage) {
            mainImage.addEventListener('touchstart', e => {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            mainImage.addEventListener('touchend', e => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
        }
        
        function handleSwipe() {
            if (touchEndX < touchStartX - 50) nextImage();
            if (touchEndX > touchStartX + 50) prevImage();
        }
        
        // Adet artır/azalt
        function increaseQuantity(max) {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue < max) {
                input.value = currentValue + 1;
            }
        }
        
        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        }
    </script>
</body>
</html>