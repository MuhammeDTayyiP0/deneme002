<?php
require_once 'config.php';

$arama = isset($_GET['q']) ? temizle($_GET['q']) : '';
$kategori_id = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$min_fiyat = isset($_GET['min_fiyat']) ? (float)$_GET['min_fiyat'] : 0;
$max_fiyat = isset($_GET['max_fiyat']) ? (float)$_GET['max_fiyat'] : 0;
$siralama = isset($_GET['siralama']) ? $_GET['siralama'] : 'yeni';

// SQL sorgusu oluştur
$where = ["u.aktif = 1"];
$params = [];

if (!empty($arama)) {
    $where[] = "(u.urun_adi LIKE ? OR u.aciklama LIKE ? OR u.marka LIKE ?)";
    $arama_param = "%{$arama}%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

if ($kategori_id > 0) {
    $where[] = "u.kategori_id = ?";
    $params[] = $kategori_id;
}

if ($min_fiyat > 0) {
    $where[] = "COALESCE(u.indirimli_fiyat, u.fiyat) >= ?";
    $params[] = $min_fiyat;
}

if ($max_fiyat > 0) {
    $where[] = "COALESCE(u.indirimli_fiyat, u.fiyat) <= ?";
    $params[] = $max_fiyat;
}

$where_clause = implode(" AND ", $where);

// Sıralama
$order_by = match($siralama) {
    'fiyat_asc' => "COALESCE(u.indirimli_fiyat, u.fiyat) ASC",
    'fiyat_desc' => "COALESCE(u.indirimli_fiyat, u.fiyat) DESC",
    'isim_asc' => "u.urun_adi ASC",
    'isim_desc' => "u.urun_adi DESC",
    'populer' => "u.goruntuleme DESC",
    'puan' => "u.ortalama_puan DESC",
    default => "u.id DESC"
};

$sql = "SELECT u.*, k.kategori_adi FROM urunler u 
        LEFT JOIN kategoriler k ON u.kategori_id = k.id 
        WHERE {$where_clause} 
        ORDER BY {$order_by}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$urunler = $stmt->fetchAll();

// Kategorileri çek
$kategoriler = $db->query("SELECT * FROM kategoriler WHERE aktif = 1 ORDER BY sira ASC")->fetchAll();

// Sepete ekleme
if (isset($_POST['sepete_ekle'])) {
    $urun_id = (int)$_POST['urun_id'];
    sepeteEkle($urun_id, 1);
    header("Location: arama.php?" . $_SERVER['QUERY_STRING'] . "&sepet=eklendi");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arama: <?php echo htmlspecialchars($arama); ?> - <?php echo SITE_NAME; ?></title>
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
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
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
        
        .search-bar {
            flex: 1;
            max-width: 600px;
            margin: 0 40px;
            position: relative;
        }
        
        .search-bar form {
            display: flex;
            gap: 10px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
        }
        
        .search-btn {
            padding: 14px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
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
            position: relative;
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
        
        .search-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .filter-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .filter-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .filter-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .filter-label {
            font-weight: 700;
            margin-bottom: 12px;
            display: block;
        }
        
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .category-item:hover {
            background: #f8f9fa;
        }
        
        .category-item input[type="radio"] {
            margin-right: 8px;
        }
        
        .btn-filter {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-clear {
            width: 100%;
            padding: 12px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .results {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .results-info {
            font-size: 18px;
            font-weight: 700;
        }
        
        .results-info span {
            color: #667eea;
        }
        
        .sort-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
            object-fit: contain;
            object-position: center;
            padding: 10px;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4757;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            color: #667eea;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .product-name {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .product-price {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .price-current {
            font-size: 22px;
            font-weight: 800;
            color: #667eea;
        }
        
        .price-old {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        
        .add-to-cart {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-to-cart:hover {
            transform: scale(1.02);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-results-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .no-results h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .no-results p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .success-message {
            background: #2ed573;
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        @media (max-width: 968px) {
            .search-layout {
                grid-template-columns: 1fr;
            }
            
            .filters {
                position: static;
            }
            
            .search-bar {
                margin: 20px 0;
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .filters {
                padding: 20px 15px;
            }
            
            .filter-title {
                font-size: 18px;
            }
            
            .filter-label {
                font-size: 14px;
            }
            
            .filter-input {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .btn-filter,
            .btn-clear {
                padding: 12px;
                font-size: 14px;
            }
            
            .results {
                padding: 20px 15px;
            }
            
            .results-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .results-info {
                font-size: 16px;
            }
            
            .sort-select {
                width: 100%;
                padding: 10px 15px;
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
            }
            
            .product-price {
                font-size: 14px;
            }
            
            .price-current {
                font-size: 18px;
            }
            
            .add-to-cart {
                padding: 10px;
                font-size: 13px;
            }
            
            .no-results {
                padding: 40px 20px;
            }
            
            .no-results h2 {
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                font-size: 24px;
            }
            
            .search-bar form {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-btn {
                width: 100%;
            }
            
            .filter-section {
                margin-bottom: 20px;
                padding-bottom: 20px;
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
                
                <div class="search-bar">
                    <form method="GET" action="arama.php">
                        <input type="text" name="q" placeholder="Ürün, marka ara..." value="<?php echo htmlspecialchars($arama); ?>">
                        <button type="submit" class="search-btn">Ara</button>
                    </form>
                </div>
                
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
        <?php if (isset($_GET['sepet']) && $_GET['sepet'] == 'eklendi'): ?>
            <div class="success-message">✓ Ürün sepete eklendi!</div>
        <?php endif; ?>
        
        <div class="search-layout">
            <!-- Filtreler -->
            <aside class="filters">
                <h2 class="filter-title">🔍 Filtrele</h2>
                
                <form method="GET" action="arama.php">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($arama); ?>">
                    
                    <div class="filter-section">
                        <label class="filter-label">Kategori</label>
                        <ul class="category-list">
                            <li class="category-item">
                                <input type="radio" name="kategori" value="0" id="kat_0" 
                                       <?php echo $kategori_id == 0 ? 'checked' : ''; ?>>
                                <label for="kat_0">Tüm Kategoriler</label>
                            </li>
                            <?php foreach ($kategoriler as $kat): ?>
                                <li class="category-item">
                                    <input type="radio" name="kategori" value="<?php echo $kat['id']; ?>" 
                                           id="kat_<?php echo $kat['id']; ?>"
                                           <?php echo $kategori_id == $kat['id'] ? 'checked' : ''; ?>>
                                    <label for="kat_<?php echo $kat['id']; ?>">
                                        <?php echo htmlspecialchars($kat['kategori_adi']); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label">Fiyat Aralığı</label>
                        <input type="number" name="min_fiyat" class="filter-input" 
                               placeholder="Min fiyat" value="<?php echo $min_fiyat; ?>">
                        <input type="number" name="max_fiyat" class="filter-input" 
                               placeholder="Max fiyat" value="<?php echo $max_fiyat; ?>">
                    </div>
                    
                    <button type="submit" class="btn-filter">Filtrele</button>
                    <button type="button" onclick="window.location.href='arama.php?q=<?php echo urlencode($arama); ?>'" 
                            class="btn-clear">Filtreleri Temizle</button>
                </form>
            </aside>
            
            <!-- Sonuçlar -->
            <div class="results">
                <div class="results-header">
                    <div class="results-info">
                        <span><?php echo count($urunler); ?></span> ürün bulundu
                        <?php if (!empty($arama)): ?>
                            : "<?php echo htmlspecialchars($arama); ?>"
                        <?php endif; ?>
                    </div>
                    
                    <select class="sort-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?' + new URLSearchParams({...Object.fromEntries(new URLSearchParams(window.location.search)), siralama: this.value})">
                        <option value="yeni" <?php echo $siralama == 'yeni' ? 'selected' : ''; ?>>En Yeni</option>
                        <option value="populer" <?php echo $siralama == 'populer' ? 'selected' : ''; ?>>En Popüler</option>
                        <option value="puan" <?php echo $siralama == 'puan' ? 'selected' : ''; ?>>En Yüksek Puan</option>
                        <option value="fiyat_asc" <?php echo $siralama == 'fiyat_asc' ? 'selected' : ''; ?>>Fiyat: Düşük-Yüksek</option>
                        <option value="fiyat_desc" <?php echo $siralama == 'fiyat_desc' ? 'selected' : ''; ?>>Fiyat: Yüksek-Düşük</option>
                        <option value="isim_asc" <?php echo $siralama == 'isim_asc' ? 'selected' : ''; ?>>İsim: A-Z</option>
                        <option value="isim_desc" <?php echo $siralama == 'isim_desc' ? 'selected' : ''; ?>>İsim: Z-A</option>
                    </select>
                </div>
                
                <?php if (empty($urunler)): ?>
                    <div class="no-results">
                        <div class="no-results-icon">🔍</div>
                        <h2>Sonuç Bulunamadı</h2>
                        <p>Aramanızla eşleşen ürün bulunamadı. Lütfen farklı anahtar kelimeler deneyin.</p>
                        <a href="index.php" class="add-to-cart" style="max-width: 300px; margin: 0 auto; display: block;">
                            Ana Sayfaya Dön
                        </a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($urunler as $urun): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>">
                                        <img src="images/<?php echo $urun['resim']; ?>" alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                             onerror="this.src='https://via.placeholder.com/300x300?text=Ürün'">
                                    </a>
                                    <?php if ($urun['indirimli_fiyat']): ?>
                                        <div class="product-badge">
                                            -%<?php echo indirimYuzdesi($urun['fiyat'], $urun['indirimli_fiyat']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($urun['kategori_adi']); ?></div>
                                    <div class="product-name">
                                        <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>" style="text-decoration: none; color: inherit;">
                                            <?php echo htmlspecialchars($urun['urun_adi']); ?>
                                        </a>
                                    </div>
                                    <?php if ($urun['ortalama_puan'] > 0): ?>
                                        <div class="product-rating">
                                            <span class="stars"><?php echo yildizlar($urun['ortalama_puan'], '14px'); ?></span>
                                            <span>(<?php echo $urun['yorum_sayisi']; ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-price">
                                        <span class="price-current">
                                            <?php 
                                            $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                                            echo fiyatFormat($fiyat); 
                                            ?>
                                        </span>
                                        <?php if ($urun['indirimli_fiyat']): ?>
                                            <span class="price-old"><?php echo fiyatFormat($urun['fiyat']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="urun_id" value="<?php echo $urun['id']; ?>">
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
        </div>
    </div>
</body>
</html>