<?php
require_once 'config.php';

// Sepetten ürün çıkar
if (isset($_GET['sil'])) {
    $urun_id = (int)$_GET['sil'];
    if (isset($_SESSION['sepet'][$urun_id])) {
        unset($_SESSION['sepet'][$urun_id]);
    }
    header("Location: sepet.php");
    exit;
}

// Adet güncelle
if (isset($_POST['adet_guncelle'])) {
    $urun_id = (int)$_POST['urun_id'];
    $yeni_adet = (int)$_POST['adet'];
    if ($yeni_adet > 0) {
        $_SESSION['sepet'][$urun_id] = $yeni_adet;
    } else {
        unset($_SESSION['sepet'][$urun_id]);
    }
    header("Location: sepet.php");
    exit;
}

// Sepeti temizle
if (isset($_GET['temizle'])) {
    unset($_SESSION['sepet']);
    header("Location: sepet.php");
    exit;
}

// Sepet ürünlerini çek
$sepet_urunler = [];
$toplam = 0;

if (isset($_SESSION['sepet']) && !empty($_SESSION['sepet'])) {
    foreach ($_SESSION['sepet'] as $urun_id => $adet) {
        $stmt = $db->prepare("SELECT * FROM urunler WHERE id = ? AND aktif = 1");
        $stmt->execute([$urun_id]);
        $urun = $stmt->fetch();
        
        if ($urun) {
            $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
            $urun['adet'] = $adet;
            $urun['toplam'] = $fiyat * $adet;
            $toplam += $urun['toplam'];
            $sepet_urunler[] = $urun;
        }
    }
}

$kargo = 0;
if ($toplam > 0 && $toplam < 500) {
    $kargo = 29.90;
}

$genel_toplam = $toplam + $kargo;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepetim - <?php echo SITE_NAME; ?></title>
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
        
        /* Header (simplified) */
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
        
        .back-btn {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Page Title */
        .page-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 40px;
        }
        
        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-bottom: 60px;
        }
        
        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            padding: 25px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:first-child {
            padding-top: 0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .cart-item-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 5px;
        }
        
        .cart-item-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .cart-item-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .cart-item-price {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        
        .cart-item-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 8px;
        }
        
        .quantity-control button {
            background: white;
            border: 1px solid #e0e0e0;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s;
        }
        
        .quantity-control button:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .quantity-control input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 700;
            font-size: 16px;
        }
        
        .remove-btn {
            color: #ff4757;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .remove-btn:hover {
            background: #fff5f5;
        }
        
        .cart-item-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }
        
        .item-total {
            font-size: 24px;
            font-weight: 800;
            color: #1a1a1a;
        }
        
        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .summary-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 25px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.total {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid #667eea;
        }
        
        .free-shipping {
            background: #e8f5e9;
            color: #2ed573;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            margin: 20px 0;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .continue-shopping {
            width: 100%;
            padding: 15px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .continue-shopping:hover {
            background: #f8f9fa;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-cart h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .empty-cart p {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .clear-cart {
            color: #ff4757;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-top: 20px;
        }
        
        @media (max-width: 968px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            
            .cart-summary {
                position: static;
            }
            
            .cart-item {
                grid-template-columns: 100px 1fr;
                gap: 15px;
            }
            
            .cart-item-right {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .cart-items {
                padding: 20px 15px;
            }
            
            .cart-summary {
                padding: 20px 15px;
            }
            
            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 12px;
                padding: 15px 0;
            }
            
            .cart-item-image {
                width: 80px;
                height: 80px;
            }
            
            .cart-item-name {
                font-size: 14px;
            }
            
            .cart-item-price {
                font-size: 16px;
            }
            
            .item-total {
                font-size: 18px;
            }
            
            .quantity-control {
                padding: 6px 12px;
            }
            
            .quantity-control button {
                width: 28px;
                height: 28px;
                font-size: 16px;
            }
            
            .quantity-control input {
                width: 40px;
                font-size: 14px;
            }
            
            .remove-btn {
                font-size: 13px;
                padding: 6px 12px;
            }
            
            .summary-title {
                font-size: 18px;
            }
            
            .summary-row {
                padding: 12px 0;
                font-size: 14px;
            }
            
            .summary-row.total {
                font-size: 20px;
            }
            
            .checkout-btn {
                padding: 14px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
            }
            
            .cart-item {
                grid-template-columns: 70px 1fr;
            }
            
            .cart-item-image {
                width: 70px;
                height: 70px;
            }
            
            .cart-item-name {
                font-size: 13px;
            }
            
            .cart-item-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quantity-control {
                width: 100%;
                justify-content: center;
            }
            
            .empty-cart {
                padding: 40px 20px;
            }
            
            .empty-cart h2 {
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
                <a href="index.php" class="back-btn">← Alışverişe Devam Et</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <h1 class="page-title">Sepetim</h1>
        
        <?php if (empty($sepet_urunler)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h2>Sepetiniz Boş</h2>
                <p>Sepetinizde henüz ürün bulunmamaktadır.</p>
                <a href="index.php" class="checkout-btn" style="max-width: 300px; margin: 0 auto;">
                    Alışverişe Başla
                </a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($sepet_urunler as $urun): ?>
                        <div class="cart-item">
                            <div class="cart-item-image">
                                <img src="images/<?php echo $urun['resim']; ?>" 
                                     alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                     onerror="this.src='https://via.placeholder.com/120x120?text=Ürün'">
                            </div>
                            <div class="cart-item-details">
                                <div class="cart-item-name">
                                    <?php echo htmlspecialchars($urun['urun_adi']); ?>
                                </div>
                                <div class="cart-item-price">
                                    <?php 
                                    $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                                    echo fiyatFormat($fiyat); 
                                    ?>
                                </div>
                                <div class="cart-item-actions">
                                    <form method="POST" class="quantity-control">
                                        <input type="hidden" name="urun_id" value="<?php echo $urun['id']; ?>">
                                        <button type="submit" name="adet_guncelle" 
                                                onclick="this.form.adet.value = Math.max(1, parseInt(this.form.adet.value) - 1)">-</button>
                                        <input type="number" name="adet" value="<?php echo $urun['adet']; ?>" min="1" readonly>
                                        <button type="submit" name="adet_guncelle" 
                                                onclick="this.form.adet.value = parseInt(this.form.adet.value) + 1">+</button>
                                    </form>
                                    <a href="sepet.php?sil=<?php echo $urun['id']; ?>" 
                                       class="remove-btn"
                                       onclick="return confirm('Bu ürünü sepetten çıkarmak istediğinize emin misiniz?')">
                                        🗑️ Kaldır
                                    </a>
                                </div>
                            </div>
                            <div class="cart-item-right">
                                <div class="item-total">
                                    <?php echo fiyatFormat($urun['toplam']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <a href="sepet.php?temizle=1" class="clear-cart" 
                       onclick="return confirm('Sepeti tamamen temizlemek istediğinize emin misiniz?')">
                        Sepeti Temizle
                    </a>
                </div>
                
                <div class="cart-summary">
                    <h3 class="summary-title">Sipariş Özeti</h3>
                    
                    <div class="summary-row">
                        <span>Ara Toplam</span>
                        <strong><?php echo fiyatFormat($toplam); ?></strong>
                    </div>
                    
                    <div class="summary-row">
                        <span>Kargo</span>
                        <strong><?php echo $kargo > 0 ? fiyatFormat($kargo) : 'ÜCRETSİZ'; ?></strong>
                    </div>
                    
                    <?php if ($toplam >= 500): ?>
                        <div class="free-shipping">
                            ✓ Ücretsiz Kargo Kazandınız!
                        </div>
                    <?php else: ?>
                        <div class="free-shipping" style="background: #fff3cd; color: #f39c12;">
                            <?php echo fiyatFormat(500 - $toplam); ?> değerinde ürün ekleyin, kargo bedava!
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Toplam</span>
                        <span><?php echo fiyatFormat($genel_toplam); ?></span>
                    </div>
                    
                    <a href="odeme.php" class="checkout-btn">
                        Ödemeye Geç
                    </a>
                    
                    <a href="index.php" class="continue-shopping">
                        Alışverişe Devam Et
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>