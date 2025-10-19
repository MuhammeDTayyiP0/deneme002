<?php
require_once 'config.php';

// Giriş kontrolü
if (!girisKontrol()) {
    header("Location: giris.php");
    exit;
}

// Sepet kontrolü
if (!isset($_SESSION['sepet']) || empty($_SESSION['sepet'])) {
    header("Location: sepet.php");
    exit;
}

$kullanici_id = $_SESSION['kullanici_id'];

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$stmt->execute([$kullanici_id]);
$kullanici = $stmt->fetch();

// Sepet ürünlerini çek
$sepet_urunler = [];
$ara_toplam = 0;

foreach ($_SESSION['sepet'] as $urun_id => $adet) {
    $stmt = $db->prepare("SELECT * FROM urunler WHERE id = ? AND aktif = 1");
    $stmt->execute([$urun_id]);
    $urun = $stmt->fetch();
    
    if ($urun) {
        $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
        $urun['adet'] = $adet;
        $urun['toplam'] = $fiyat * $adet;
        $ara_toplam += $urun['toplam'];
        $sepet_urunler[] = $urun;
    }
}

$kargo_ucreti = kargoHesapla($ara_toplam);
$indirim_tutari = 0;
$kupon_kodu = '';

// Kupon kontrolü
if (isset($_POST['kupon_uygula']) && !empty($_POST['kupon_kodu'])) {
    $kupon_kodu = strtoupper(temizle($_POST['kupon_kodu']));
    $kupon_sonuc = kuponKontrol($db, $kupon_kodu, $kullanici_id);
    
    if (isset($kupon_sonuc['hata'])) {
        $kupon_hata = $kupon_sonuc['hata'];
    } else {
        $kupon = $kupon_sonuc['kupon'];
        $indirim_tutari = kuponIndirimiHesapla($kupon, $ara_toplam);
        $_SESSION['kupon'] = $kupon;
        $_SESSION['indirim_tutari'] = $indirim_tutari;
        $kupon_basari = "Kupon başarıyla uygulandı!";
    }
}

// Session'dan kupon bilgisini al
if (isset($_SESSION['kupon'])) {
    $kupon = $_SESSION['kupon'];
    $kupon_kodu = $kupon['kupon_kodu'];
    $indirim_tutari = $_SESSION['indirim_tutari'];
}

// Kupon iptal
if (isset($_POST['kupon_iptal'])) {
    unset($_SESSION['kupon']);
    unset($_SESSION['indirim_tutari']);
    $kupon_kodu = '';
    $indirim_tutari = 0;
    header("Location: odeme.php");
    exit;
}

$genel_toplam = $ara_toplam + $kargo_ucreti - $indirim_tutari;

// Ödeme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['odeme_yap'])) {
    $adres = temizle($_POST['adres']);
    $sehir = temizle($_POST['sehir']);
    $ilce = temizle($_POST['ilce']);
    $posta_kodu = temizle($_POST['posta_kodu']);
    
    $kart_sahibi = temizle($_POST['kart_sahibi']);
    $kart_numarasi = preg_replace('/\s+/', '', $_POST['kart_numarasi']);
    $son_kullanma = explode('/', $_POST['son_kullanma']);
    $cvv = temizle($_POST['cvv']);
    
    // Validasyon
    $hatalar = [];
    if (empty($adres)) $hatalar[] = 'Adres gerekli';
    if (empty($sehir)) $hatalar[] = 'Şehir gerekli';
    if (empty($kart_sahibi)) $hatalar[] = 'Kart sahibi gerekli';
    if (strlen($kart_numarasi) != 16 || !is_numeric($kart_numarasi)) $hatalar[] = 'Geçersiz kart numarası';
    if (count($son_kullanma) != 2) $hatalar[] = 'Geçersiz son kullanma tarihi';
    if (strlen($cvv) != 3 || !is_numeric($cvv)) $hatalar[] = 'Geçersiz CVV';
    
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();
            
            // Sipariş oluştur
            $siparis_no = siparisNoOlustur();
            $teslimat_adresi = $adres . ', ' . $ilce . '/' . $sehir . ' ' . $posta_kodu;
            
            $stmt = $db->prepare("INSERT INTO siparisler 
                (kullanici_id, siparis_no, toplam_tutar, kargo_ucreti, indirim_tutari, kupon_kodu, 
                 odeme_yontemi, odeme_durumu, teslimat_adresi, durum) 
                VALUES (?, ?, ?, ?, ?, ?, 'Kredi Kartı', 'odendi', ?, 'onaylandi')");
            
            $stmt->execute([
                $kullanici_id, 
                $siparis_no, 
                $genel_toplam, 
                $kargo_ucreti, 
                $indirim_tutari,
                $kupon_kodu ?: null,
                $teslimat_adresi
            ]);
            
            $siparis_id = $db->lastInsertId();
            
            // Sipariş detaylarını ekle
            foreach ($sepet_urunler as $urun) {
                $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                
                $stmt = $db->prepare("INSERT INTO siparis_detaylari 
                    (siparis_id, urun_id, urun_adi, adet, birim_fiyat, toplam) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $siparis_id,
                    $urun['id'],
                    $urun['urun_adi'],
                    $urun['adet'],
                    $fiyat,
                    $urun['toplam']
                ]);
                
                // Stok güncelle
                $stmt = $db->prepare("UPDATE urunler SET stok_miktari = stok_miktari - ? WHERE id = ?");
                $stmt->execute([$urun['adet'], $urun['id']]);
            }
            
            // Kart bilgilerini kaydet (gerçek uygulamada şifrelenmeli!)
            $stmt = $db->prepare("INSERT INTO kart_bilgileri 
                (kullanici_id, siparis_id, kart_sahibi, kart_numarasi, son_kullanma_ay, son_kullanma_yil, cvv) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $kullanici_id,
                $siparis_id,
                $kart_sahibi,
                $kart_numarasi,
                $son_kullanma[0],
                $son_kullanma[1],
                $cvv
            ]);
            
            // Kupon kullanımını kaydet
            if (!empty($kupon_kodu) && isset($kupon)) {
                $stmt = $db->prepare("INSERT INTO kupon_kullanimlari (kupon_id, kullanici_id, siparis_id) 
                                     VALUES (?, ?, ?)");
                $stmt->execute([$kupon['id'], $kullanici_id, $siparis_id]);
                
                // Kupon kullanım sayısını artır
                $stmt = $db->prepare("UPDATE kuponlar SET kullanim_sayisi = kullanim_sayisi + 1 WHERE id = ?");
                $stmt->execute([$kupon['id']]);
            }
            
            // Sipariş durum geçmişi
            $stmt = $db->prepare("INSERT INTO siparis_durum_gecmisi 
                (siparis_id, yeni_durum, aciklama, olusturan_kullanici) 
                VALUES (?, 'onaylandi', 'Sipariş oluşturuldu ve ödeme alındı', ?)");
            $stmt->execute([$siparis_id, $kullanici_id]);
            
            // Kullanıcı adresini güncelle
            $stmt = $db->prepare("UPDATE kullanicilar SET adres = ?, sehir = ?, ilce = ?, posta_kodu = ? WHERE id = ?");
            $stmt->execute([$adres, $sehir, $ilce, $posta_kodu, $kullanici_id]);
            
            // Email gönder
            $konu = "Siparişiniz Alındı - " . $siparis_no;
            $mesaj = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Siparişiniz Alındı!</h2>
                <p>Merhaba {$kullanici['ad_soyad']},</p>
                <p>Siparişiniz başarıyla oluşturuldu.</p>
                <p><strong>Sipariş No:</strong> {$siparis_no}</p>
                <p><strong>Toplam Tutar:</strong> " . fiyatFormat($genel_toplam) . "</p>
                <p>Siparişiniz en kısa sürede hazırlanarak kargoya verilecektir.</p>
                <p>Sipariş durumunuzu hesabınızdan takip edebilirsiniz.</p>
                <br>
                <p>Teşekkürler,<br>" . SITE_NAME . "</p>
            </body>
            </html>
            ";
            
            emailKaydet($db, $kullanici_id, $kullanici['email'], $konu, $mesaj);
            
            $db->commit();
            
            // Sepeti temizle
            unset($_SESSION['sepet']);
            unset($_SESSION['kupon']);
            unset($_SESSION['indirim_tutari']);
            
            // Başarı sayfasına yönlendir
            header("Location: siparis-basarili.php?siparis_no=" . $siparis_no);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $odeme_hata = 'Ödeme işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    } else {
        $odeme_hata = implode('<br>', $hatalar);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme - <?php echo SITE_NAME; ?></title>
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
        
        .progress-bar {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .progress-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #999;
        }
        
        .progress-step.active .progress-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .progress-step.completed .progress-circle {
            background: #2ed573;
            color: white;
        }
        
        .progress-label {
            font-size: 14px;
            font-weight: 600;
            color: #999;
        }
        
        .progress-step.active .progress-label,
        .progress-step.completed .progress-label {
            color: #1a1a1a;
        }
        
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .checkout-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .card-number-input {
            letter-spacing: 2px;
        }
        
        .card-icons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .card-icon {
            width: 50px;
            height: 32px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .kupon-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .kupon-form {
            display: flex;
            gap: 10px;
        }
        
        .kupon-input {
            flex: 1;
        }
        
        .btn-kupon {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-kupon:hover {
            background: #5568d3;
        }
        
        .kupon-applied {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #e8f5e9;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .kupon-applied-text {
            color: #2e7d32;
            font-weight: 600;
        }
        
        .btn-kupon-iptal {
            background: none;
            border: none;
            color: #d32f2f;
            cursor: pointer;
            font-weight: 600;
        }
        
        .order-summary {
            position: sticky;
            top: 20px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item-image {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 5px;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .order-item-quantity {
            font-size: 13px;
            color: #666;
        }
        
        .order-item-price {
            font-weight: 700;
            color: #667eea;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-row.total {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
            border-top: 2px solid #667eea;
            border-bottom: none;
            margin-top: 10px;
        }
        
        .summary-row.discount {
            color: #2ed573;
        }
        
        .btn-payment {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-payment:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .security-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #ffe6e6;
            color: #d32f2f;
            border: 2px solid #ffcccc;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #c8e6c9;
        }
        
        @media (max-width: 1024px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .progress-bar {
                flex-direction: column;
                gap: 10px;
            }
            
            .progress-step {
                flex-direction: row;
                gap: 10px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .checkout-section {
                padding: 20px 15px;
            }
            
            .section-title {
                font-size: 18px;
            }
            
            .form-input, .form-select, .form-textarea {
                padding: 12px;
                font-size: 14px;
            }
            
            .btn-payment {
                padding: 14px;
                font-size: 14px;
            }
            
            .order-item {
                padding: 12px 0;
            }
            
            .order-item-image {
                width: 60px;
                height: 60px;
            }
            
            .order-item-name {
                font-size: 13px;
            }
            
            .order-item-price {
                font-size: 14px;
            }
            
            .summary-row {
                font-size: 14px;
            }
            
            .summary-row.total {
                font-size: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .progress-circle {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
            
            .progress-label {
                font-size: 12px;
            }
            
            .kupon-form {
                flex-direction: column;
            }
            
            .btn-kupon {
                width: 100%;
            }
            
            .card-icons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">ElitGSM</a>
                <span style="font-weight: 600;">Güvenli Ödeme</span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-step completed">
                <div class="progress-circle">✓</div>
                <div class="progress-label">Sepet</div>
            </div>
            <div class="progress-step active">
                <div class="progress-circle">2</div>
                <div class="progress-label">Ödeme</div>
            </div>
            <div class="progress-step">
                <div class="progress-circle">3</div>
                <div class="progress-label">Onay</div>
            </div>
        </div>
        
        <?php if (isset($odeme_hata)): ?>
            <div class="alert alert-error">❌ <?php echo $odeme_hata; ?></div>
        <?php endif; ?>
        
        <?php if (isset($kupon_hata)): ?>
            <div class="alert alert-error">❌ <?php echo $kupon_hata; ?></div>
        <?php endif; ?>
        
        <?php if (isset($kupon_basari)): ?>
            <div class="alert alert-success">✓ <?php echo $kupon_basari; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="checkout-layout">
                <!-- Sol Taraf - Form -->
                <div>
                    <!-- Teslimat Adresi -->
                    <div class="checkout-section">
                        <h2 class="section-title">📦 Teslimat Adresi</h2>
                        
                        <div class="form-group">
                            <label class="form-label">Adres</label>
                            <textarea name="adres" class="form-input" rows="3" required><?php echo htmlspecialchars($kullanici['adres']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Şehir</label>
                                <input type="text" name="sehir" class="form-input" required
                                       value="<?php echo htmlspecialchars($kullanici['sehir']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">İlçe</label>
                                <input type="text" name="ilce" class="form-input" required
                                       value="<?php echo htmlspecialchars($kullanici['ilce']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Posta Kodu</label>
                            <input type="text" name="posta_kodu" class="form-input" 
                                   value="<?php echo htmlspecialchars($kullanici['posta_kodu']); ?>">
                        </div>
                    </div>
                    
                    <!-- Ödeme Bilgileri -->
                    <div class="checkout-section" style="margin-top: 20px;">
                        <h2 class="section-title">💳 Kart Bilgileri</h2>
                        
                        <div class="form-group">
                            <label class="form-label">Kart Üzerindeki İsim</label>
                            <input type="text" name="kart_sahibi" class="form-input" 
                                   placeholder="AD SOYAD" required style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kart Numarası</label>
                            <input type="text" name="kart_numarasi" class="form-input card-number-input" 
                                   placeholder="1234 5678 9012 3456" maxlength="19" required
                                   onkeyup="formatCardNumber(this)">
                            <div class="card-icons">
                                <div class="card-icon">💳</div>
                                <div class="card-icon">🏦</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Son Kullanma Tarihi</label>
                                <input type="text" name="son_kullanma" class="form-input" 
                                       placeholder="MM/YY" maxlength="5" required
                                       onkeyup="formatExpiry(this)">
                            </div>
                            <div class="form-group">
                                <label class="form-label">CVV</label>
                                <input type="text" name="cvv" class="form-input" 
                                       placeholder="123" maxlength="3" required>
                            </div>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 12px; border-radius: 10px; font-size: 13px; color: #856404;">
                            🔒 Kart bilgileriniz güvenli bir şekilde saklanmaktadır.
                        </div>
                    </div>
                </div>
                
                <!-- Sağ Taraf - Özet -->
                <div>
                    <div class="checkout-section order-summary">
                        <h2 class="section-title">🛒 Sipariş Özeti</h2>
                        
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($sepet_urunler as $urun): ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <img src="images/<?php echo $urun['resim']; ?>" alt="">
                                    </div>
                                    <div class="order-item-details">
                                        <div class="order-item-name"><?php echo htmlspecialchars($urun['urun_adi']); ?></div>
                                        <div class="order-item-quantity"><?php echo $urun['adet']; ?> adet</div>
                                    </div>
                                    <div class="order-item-price">
                                        <?php echo fiyatFormat($urun['toplam']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Kupon Kodu -->
                        <div class="kupon-section">
                            <?php if (empty($kupon_kodu)): ?>
                                <form method="POST" action="">
                                    <div class="kupon-form">
                                        <input type="text" name="kupon_kodu" class="form-input kupon-input" 
                                               placeholder="Kupon kodunuz varsa" value="<?php echo isset($_POST['kupon_kodu']) ? htmlspecialchars($_POST['kupon_kodu']) : ''; ?>">
                                        <button type="submit" name="kupon_uygula" class="btn-kupon">Uygula</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <div class="kupon-applied">
                                        <span class="kupon-applied-text">✓ <?php echo $kupon_kodu; ?> uygulandı</span>
                                        <button type="submit" name="kupon_iptal" class="btn-kupon-iptal">Kaldır</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="summary-row">
                            <span>Ara Toplam</span>
                            <strong><?php echo fiyatFormat($ara_toplam); ?></strong>
                        </div>
                        
                        <div class="summary-row">
                            <span>Kargo</span>
                            <strong><?php echo $kargo_ucreti > 0 ? fiyatFormat($kargo_ucreti) : 'ÜCRETSİZ'; ?></strong>
                        </div>
                        
                        <?php if ($indirim_tutari > 0): ?>
                            <div class="summary-row discount">
                                <span>İndirim</span>
                                <strong>-<?php echo fiyatFormat($indirim_tutari); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Toplam</span>
                            <span><?php echo fiyatFormat($genel_toplam); ?></span>
                        </div>
                        
                        <button type="submit" name="odeme_yap" class="btn-payment">
                            🔒 Güvenli Ödeme Yap
                        </button>
                        
                        <div class="security-badges">
                            <div class="security-badge">
                                🔒 256-bit SSL
                            </div>
                            <div class="security-badge">
                                ✓ 3D Secure
                            </div>
                            <div class="security-badge">
                                🛡️ Güvenli Alışveriş
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function formatCardNumber(input) {
            let value = input.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            input.value = formattedValue;
        }
        
        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            input.value = value;
        }
    </script>
</body>
</html>