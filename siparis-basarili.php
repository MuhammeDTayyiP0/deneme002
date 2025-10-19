<?php
require_once 'config.php';

if (!girisKontrol() || !isset($_GET['siparis_no'])) {
    header("Location: index.php");
    exit;
}

$siparis_no = temizle($_GET['siparis_no']);

// Sipariş bilgilerini çek
$stmt = $db->prepare("SELECT s.*, k.ad_soyad, k.email FROM siparisler s 
                      LEFT JOIN kullanicilar k ON s.kullanici_id = k.id 
                      WHERE s.siparis_no = ? AND s.kullanici_id = ?");
$stmt->execute([$siparis_no, $_SESSION['kullanici_id']]);
$siparis = $stmt->fetch();

if (!$siparis) {
    header("Location: index.php");
    exit;
}

// Sipariş detaylarını çek
$stmt = $db->prepare("SELECT * FROM siparis_detaylari WHERE siparis_id = ?");
$stmt->execute([$siparis['id']]);
$detaylar = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Başarılı - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 700px;
            width: 100%;
        }
        
        .success-card {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #2ed573 0%, #27ae60 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .success-title {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .success-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin: 30px 0;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .order-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 800;
            margin: 20px 0;
            letter-spacing: 1px;
        }
        
        .order-items {
            margin: 30px 0;
            text-align: left;
        }
        
        .order-items-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-name {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .item-quantity {
            color: #666;
            font-size: 14px;
        }
        
        .item-price {
            font-weight: 700;
            color: #667eea;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
        }
        
        .next-steps {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
            text-align: left;
        }
        
        .next-steps-title {
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .next-steps ul {
            list-style: none;
            padding: 0;
        }
        
        .next-steps li {
            padding: 8px 0;
            color: #666;
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .next-steps li:before {
            content: "✓";
            color: #2ed573;
            font-weight: 700;
            font-size: 18px;
        }
        
        @media (max-width: 600px) {
            .success-card {
                padding: 40px 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .success-title {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-card">
            <div class="success-icon">✓</div>
            
            <h1 class="success-title">Siparişiniz Alındı!</h1>
            <p class="success-message">
                Teşekkür ederiz! Siparişiniz başarıyla oluşturuldu ve ödemeniz alındı.<br>
                Sipariş durumunuzu email ve hesabınızdan takip edebilirsiniz.
            </p>
            
            <div class="order-number">
                Sipariş No: <?php echo $siparis['siparis_no']; ?>
            </div>
            
            <div class="order-info">
                <div class="info-row">
                    <span class="info-label">Müşteri</span>
                    <span class="info-value"><?php echo htmlspecialchars($siparis['ad_soyad']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($siparis['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sipariş Tarihi</span>
                    <span class="info-value"><?php echo tarihFormat($siparis['siparis_tarihi']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ödeme Yöntemi</span>
                    <span class="info-value"><?php echo $siparis['odeme_yontemi']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Toplam Tutar</span>
                    <span class="info-value" style="color: #667eea; font-size: 20px;">
                        <?php echo fiyatFormat($siparis['toplam_tutar']); ?>
                    </span>
                </div>
            </div>
            
            <div class="order-items">
                <h3 class="order-items-title">Sipariş İçeriği</h3>
                <?php foreach ($detaylar as $detay): ?>
                    <div class="order-item">
                        <div>
                            <div class="item-name"><?php echo htmlspecialchars($detay['urun_adi']); ?></div>
                            <div class="item-quantity"><?php echo $detay['adet']; ?> adet</div>
                        </div>
                        <div class="item-price"><?php echo fiyatFormat($detay['toplam']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="next-steps">
                <div class="next-steps-title">
                    📦 Sonraki Adımlar
                </div>
                <ul>
                    <li>Siparişiniz onaylandı ve ödemeniz alındı</li>
                    <li>Email adresinize sipariş detayları gönderildi</li>
                    <li>Siparişiniz 1-2 iş günü içinde hazırlanacak</li>
                    <li>Kargoya verildiğinde email ile bilgilendirileceksiniz</li>
                    <li>Kargo takip numaranız ile teslimatı takip edebilirsiniz</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="hesabim.php?sayfa=siparislerim" class="btn btn-primary">
                    Siparişlerimi Görüntüle
                </a>
                <a href="index.php" class="btn btn-secondary">
                    Alışverişe Devam Et
                </a>
            </div>
        </div>
    </div>
</body>
</html>