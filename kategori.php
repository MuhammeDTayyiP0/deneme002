<?php
require_once 'config.php';

// Kategori slug'ını al
$slug = isset($_GET['slug']) ? temizle($_GET['slug']) : '';

if (empty($slug)) {
    header("Location: index.php");
    exit;
}

// Kategoriyi çek
$stmt = $db->prepare("SELECT * FROM kategoriler WHERE kategori_slug = ? AND aktif = 1");
$stmt->execute([$slug]);
$kategori = $stmt->fetch();

if (!$kategori) {
    header("Location: index.php");
    exit;
}

// Ürünleri çek
$stmt = $db->prepare("SELECT * FROM urunler WHERE kategori_id = ? AND aktif = 1 ORDER BY id DESC");
$stmt->execute([$kategori['id']]);
$urunler = $stmt->fetchAll();

// Sepete ekleme
if (isset($_POST['sepete_ekle'])) {
    $urun_id = (int)$_POST['urun_id'];
    $adet = isset($_POST['adet']) ? (int)$_POST['adet'] : 1;
    sepeteEkle($urun_id, $adet);
    header("Location: kategori.php?slug=$slug&sepet=eklendi");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($kategori['kategori_adi']); ?> - <?php echo SITE_NAME; ?></title>
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
        
        /* Category Header */
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            border-radius: 16px;
            margin-bottom: 50px;
            text-align: center;
        }
        
        .category-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .category-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .category-description {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .result-count {
            font-weight: 600;
            color: #666;
        }
        
        .sort-options select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            background: white;
            transition: all 0.3s;
        }
        
        .sort-options select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
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
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
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
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-name a {
            text-decoration: none;
            color: inherit;
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
            color: #667eea;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .back-btn-large {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s;
        }
        
        .back-btn-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
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
            .category-title {
                font-size: 32px;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .category-header {
                padding: 40px 20px;
            }
            
            .category-icon {
                font-size: 48px;
            }
            
            .category-description {
                font-size: 14px;
            }
            
            .result-count {
                font-size: 14px;
            }
            
            .sort-select {
                width: 100%;
                padding: 10px 15px;
            }
            
            .product-card {
                border-radius: 12px;
            }
            
            .product-info {
                padding: 12px;
            }
            
            .product-name {
                font-size: 13px;
            }
            
            .price-current {
                font-size: 18px;
            }
            
            .add-to-cart {
                padding: 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .category-title {
                font-size: 24px;
            }
            
            .category-icon {
                font-size: 40px;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .empty-state {
                padding: 40px 20px;
            }
            
            .empty-state h2 {
                font-size: 22px;
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
            <span><?php echo htmlspecialchars($kategori['kategori_adi']); ?></span>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_GET['sepet']) && $_GET['sepet'] == 'eklendi'): ?>
            <div class="success-message">
                ✓ Ürün sepete başarıyla eklendi!
            </div>
        <?php endif; ?>
        
        <!-- Category Header -->
        <div class="category-header">
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
                echo $icons[$kategori['ikon']] ?? '🔷';
                ?>
            </div>
            <h1 class="category-title"><?php echo htmlspecialchars($kategori['kategori_adi']); ?></h1>
            <p class="category-description">
                En yeni ve popüler <?php echo strtolower($kategori['kategori_adi']); ?> modellerini keşfedin
            </p>
        </div>
        
        <?php if (empty($urunler)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h2>Bu kategoride ürün bulunamadı</h2>
                <p>Şu anda bu kategoride ürün bulunmamaktadır. Lütfen daha sonra tekrar kontrol edin.</p>
                <a href="index.php" class="back-btn-large">Ana Sayfaya Dön</a>
            </div>
        <?php else: ?>
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="result-count">
                    <strong><?php echo count($urunler); ?></strong> ürün listeleniyor
                </div>
                <div class="sort-options">
                    <select onchange="sortProducts(this.value)">
                        <option value="default">Sıralama</option>
                        <option value="price_asc">Fiyat: Düşükten Yükseğe</option>
                        <option value="price_desc">Fiyat: Yüksekten Düşüğe</option>
                        <option value="name_asc">İsim: A-Z</option>
                        <option value="name_desc">İsim: Z-A</option>
                    </select>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="product-grid">
                <?php foreach ($urunler as $urun): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>">
                                <img src="images/<?php echo $urun['resim']; ?>" 
                                     alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x300?text=<?php echo urlencode($urun['urun_adi']); ?>'">
                            </a>
                            <?php if ($urun['indirimli_fiyat']): ?>
                                <div class="product-badge">
                                    -%<?php echo indirimYuzdesi($urun['fiyat'], $urun['indirimli_fiyat']); ?>
                                </div>
                            <?php elseif ($urun['yeni_urun']): ?>
                                <div class="product-badge new">YENİ</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name">
                                <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>">
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
        <?php endif; ?>
    </div>
    
    <script>
        function sortProducts(sortType) {
            // Bu fonksiyon AJAX ile sıralama yapabilir veya sayfayı yeniden yükleyebilir
            if (sortType !== 'default') {
                window.location.href = '?slug=<?php echo $slug; ?>&sort=' + sortType;
            }
        }
    </script>
</body>
</html>