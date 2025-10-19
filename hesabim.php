<?php
require_once 'config.php';

if (!girisKontrol()) {
    header("Location: giris.php");
    exit;
}

$kullanici_id = $_SESSION['kullanici_id'];
$sayfa = isset($_GET['sayfa']) ? $_GET['sayfa'] : 'genel';

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$stmt->execute([$kullanici_id]);
$kullanici = $stmt->fetch();

// Bilgileri güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bilgi_guncelle'])) {
    $ad_soyad = temizle($_POST['ad_soyad']);
    $telefon = temizle($_POST['telefon']);
    $adres = temizle($_POST['adres']);
    $sehir = temizle($_POST['sehir']);
    $ilce = temizle($_POST['ilce']);
    $posta_kodu = temizle($_POST['posta_kodu']);
    
    $stmt = $db->prepare("UPDATE kullanicilar SET ad_soyad = ?, telefon = ?, adres = ?, sehir = ?, ilce = ?, posta_kodu = ? WHERE id = ?");
    if ($stmt->execute([$ad_soyad, $telefon, $adres, $sehir, $ilce, $posta_kodu, $kullanici_id])) {
        $basari = "Bilgileriniz başarıyla güncellendi!";
        $kullanici['ad_soyad'] = $ad_soyad;
        $kullanici['telefon'] = $telefon;
        $kullanici['adres'] = $adres;
        $kullanici['sehir'] = $sehir;
        $kullanici['ilce'] = $ilce;
        $kullanici['posta_kodu'] = $posta_kodu;
    }
}

// Şifre değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre_degistir'])) {
    $eski_sifre = $_POST['eski_sifre'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
    
    if (password_verify($eski_sifre, $kullanici['sifre'])) {
        if ($yeni_sifre === $yeni_sifre_tekrar && strlen($yeni_sifre) >= 6) {
            $sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
            if ($stmt->execute([$sifre_hash, $kullanici_id])) {
                $basari = "Şifreniz başarıyla değiştirildi!";
            }
        } else {
            $hata = "Yeni şifreler eşleşmiyor veya 6 karakterden kısa!";
        }
    } else {
        $hata = "Eski şifreniz hatalı!";
    }
}

// Siparişleri çek
if ($sayfa === 'siparislerim') {
    $stmt = $db->prepare("SELECT * FROM siparisler WHERE kullanici_id = ? ORDER BY siparis_tarihi DESC");
    $stmt->execute([$kullanici_id]);
    $siparisler = $stmt->fetchAll();
}

// Favorileri çek
if ($sayfa === 'favoriler') {
    $stmt = $db->prepare("SELECT u.*, f.eklenme_tarihi FROM favoriler f 
                          LEFT JOIN urunler u ON f.urun_id = u.id 
                          WHERE f.kullanici_id = ? ORDER BY f.eklenme_tarihi DESC");
    $stmt->execute([$kullanici_id]);
    $favoriler = $stmt->fetchAll();
}

// Yorumlarımı çek
if ($sayfa === 'yorumlarim') {
    $stmt = $db->prepare("SELECT y.*, u.urun_adi, u.urun_slug FROM yorumlar y 
                          LEFT JOIN urunler u ON y.urun_id = u.id 
                          WHERE y.kullanici_id = ? ORDER BY y.olusturma_tarihi DESC");
    $stmt->execute([$kullanici_id]);
    $yorumlar = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım - <?php echo SITE_NAME; ?></title>
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
        
        .header-actions {
            display: flex;
            gap: 20px;
        }
        
        .header-btn {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
        }
        
        .account-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .sidebar {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .user-profile {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 25px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 800;
            margin: 0 auto 15px;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .user-email {
            font-size: 13px;
            color: #666;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .menu-item {
            margin-bottom: 5px;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .menu-link:hover,
        .menu-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .menu-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }
        
        .content-area {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .orders-table {
            width: 100%;
            margin-top: 20px;
        }
        
        .orders-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f8f9fa;
            font-weight: 700;
            font-size: 14px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .orders-table td {
            padding: 18px 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .orders-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        
        .status-beklemede { background: #fff3cd; color: #856404; }
        .status-onaylandi { background: #d1ecf1; color: #0c5460; }
        .status-hazirlaniyor { background: #e2e3e5; color: #383d41; }
        .status-kargoda { background: #cce5ff; color: #004085; }
        .status-teslim_edildi { background: #d4edda; color: #155724; }
        .status-iptal { background: #f8d7da; color: #721c24; }
        
        .btn-view {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #5568d3;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 25px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
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
            padding: 10px;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-name {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .product-price {
            color: #667eea;
            font-weight: 800;
            font-size: 18px;
        }
        
        .review-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .review-product {
            font-weight: 700;
            color: #667eea;
        }
        
        .review-date {
            font-size: 13px;
            color: #666;
        }
        
        .review-stars {
            color: #ffc107;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .review-text {
            color: #666;
            line-height: 1.6;
        }
        
        .review-status {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .review-status.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .review-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        @media (max-width: 968px) {
            .account-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .content-area {
                padding: 25px 20px;
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
            
            .sidebar {
                padding: 20px 15px;
            }
            
            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .user-name {
                font-size: 16px;
            }
            
            .user-email {
                font-size: 12px;
            }
            
            .menu-link {
                padding: 12px 14px;
                font-size: 14px;
            }
            
            .content-area {
                padding: 20px 15px;
            }
            
            .page-title {
                font-size: 22px;
                margin-bottom: 20px;
            }
            
            .form-input,
            .form-textarea {
                padding: 12px 14px;
                font-size: 14px;
            }
            
            .btn-submit {
                padding: 14px 30px;
                font-size: 14px;
            }
            
            .orders-table {
                font-size: 13px;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 10px 8px;
            }
            
            .status-badge {
                font-size: 10px;
                padding: 4px 8px;
            }
            
            .btn-view {
                padding: 6px 12px;
                font-size: 11px;
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
                font-size: 16px;
            }
            
            .review-card {
                padding: 15px;
            }
            
            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .review-user {
                font-size: 14px;
            }
            
            .review-product {
                font-size: 12px;
            }
            
            .review-date {
                font-size: 11px;
            }
            
            .review-stars {
                font-size: 16px;
            }
            
            .review-text {
                font-size: 13px;
            }
            
            .review-status {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .empty-state {
                padding: 40px 15px;
            }
            
            .empty-icon {
                font-size: 48px;
            }
            
            .empty-state h3 {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                font-size: 22px;
            }
            
            .header-btn {
                font-size: 12px;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .form-grid {
                gap: 15px;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
            
            .orders-table th,
            .orders-table td {
                white-space: nowrap;
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
                    <a href="sepet.php" class="header-btn">🛒 Sepet</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="account-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($kullanici['ad_soyad'], 0, 1)); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($kullanici['ad_soyad']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($kullanici['email']); ?></div>
                </div>
                
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="?sayfa=genel" class="menu-link <?php echo $sayfa === 'genel' ? 'active' : ''; ?>">
                            <span class="menu-icon">👤</span>
                            Hesap Bilgilerim
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="?sayfa=siparislerim" class="menu-link <?php echo $sayfa === 'siparislerim' ? 'active' : ''; ?>">
                            <span class="menu-icon">📦</span>
                            Siparişlerim
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="?sayfa=favoriler" class="menu-link <?php echo $sayfa === 'favoriler' ? 'active' : ''; ?>">
                            <span class="menu-icon">❤️</span>
                            Favorilerim
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="?sayfa=yorumlarim" class="menu-link <?php echo $sayfa === 'yorumlarim' ? 'active' : ''; ?>">
                            <span class="menu-icon">⭐</span>
                            Yorumlarım
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="?sayfa=sifre" class="menu-link <?php echo $sayfa === 'sifre' ? 'active' : ''; ?>">
                            <span class="menu-icon">🔒</span>
                            Şifre Değiştir
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="cikis.php" class="menu-link">
                            <span class="menu-icon">🚪</span>
                            Çıkış Yap
                        </a>
                    </li>
                </ul>
            </aside>
            
            <!-- Content Area -->
            <main class="content-area">
                <?php if (isset($basari)): ?>
                    <div class="alert alert-success">✓ <?php echo $basari; ?></div>
                <?php endif; ?>
                
                <?php if (isset($hata)): ?>
                    <div class="alert alert-error">❌ <?php echo $hata; ?></div>
                <?php endif; ?>
                
                <?php if ($sayfa === 'genel'): ?>
                    <h1 class="page-title">👤 Hesap Bilgilerim</h1>
                    
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Ad Soyad</label>
                                <input type="text" name="ad_soyad" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['ad_soyad']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="telefon" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['telefon']); ?>" required>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['email']); ?>" disabled>
                                <small style="color: #666;">Email adresi değiştirilemez</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Adres</label>
                                <textarea name="adres" class="form-textarea"><?php echo htmlspecialchars($kullanici['adres']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Şehir</label>
                                <input type="text" name="sehir" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['sehir']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">İlçe</label>
                                <input type="text" name="ilce" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['ilce']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Posta Kodu</label>
                                <input type="text" name="posta_kodu" class="form-input" 
                                       value="<?php echo htmlspecialchars($kullanici['posta_kodu']); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" name="bilgi_guncelle" class="btn-submit">
                            Bilgileri Güncelle
                        </button>
                    </form>
                
                <?php elseif ($sayfa === 'siparislerim'): ?>
                    <h1 class="page-title">📦 Siparişlerim</h1>
                    
                    <?php if (empty($siparisler)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>Henüz siparişiniz yok</h3>
                            <p>İlk siparişinizi oluşturmak için alışverişe başlayın!</p>
                            <a href="index.php" class="btn-submit">Alışverişe Başla</a>
                        </div>
                    <?php else: ?>
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Tarih</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siparisler as $siparis): ?>
                                    <tr>
                                        <td><strong><?php echo $siparis['siparis_no']; ?></strong></td>
                                        <td><?php echo tarihFormat($siparis['siparis_tarihi']); ?></td>
                                        <td><strong><?php echo fiyatFormat($siparis['toplam_tutar']); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $siparis['durum']; ?>">
                                                <?php 
                                                $durumlar = [
                                                    'beklemede' => 'Beklemede',
                                                    'onaylandi' => 'Onaylandı',
                                                    'hazirlaniyor' => 'Hazırlanıyor',
                                                    'kargoda' => 'Kargoda',
                                                    'teslim_edildi' => 'Teslim Edildi',
                                                    'iptal' => 'İptal'
                                                ];
                                                echo $durumlar[$siparis['durum']];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="siparis-detay.php?id=<?php echo $siparis['id']; ?>" class="btn-view">Detay</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                
                <?php elseif ($sayfa === 'sifre'): ?>
                    <h1 class="page-title">🔒 Şifre Değiştir</h1>
                    
                    <form method="POST" action="" style="max-width: 500px;">
                        <div class="form-group">
                            <label class="form-label">Mevcut Şifre</label>
                            <input type="password" name="eski_sifre" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="yeni_sifre" class="form-input" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Yeni Şifre Tekrar</label>
                            <input type="password" name="yeni_sifre_tekrar" class="form-input" required minlength="6">
                        </div>
                        
                        <button type="submit" name="sifre_degistir" class="btn-submit">
                            Şifreyi Değiştir
                        </button>
                    </form>
                
                <?php elseif ($sayfa === 'favoriler'): ?>
                    <h1 class="page-title">❤️ Favorilerim</h1>
                    
                    <?php if (empty($favoriler)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">❤️</div>
                            <h3>Favori ürününüz yok</h3>
                            <p>Beğendiğiniz ürünleri favorilere ekleyerek hızlıca ulaşabilirsiniz.</p>
                            <a href="index.php" class="btn-submit">Ürünleri İncele</a>
                        </div>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($favoriler as $urun): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>">
                                            <img src="images/<?php echo $urun['resim']; ?>" alt="">
                                        </a>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name">
                                            <a href="urun.php?slug=<?php echo $urun['urun_slug']; ?>" style="text-decoration: none; color: inherit;">
                                                <?php echo htmlspecialchars($urun['urun_adi']); ?>
                                            </a>
                                        </div>
                                        <div class="product-price">
                                            <?php 
                                            $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
                                            echo fiyatFormat($fiyat);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                
                <?php elseif ($sayfa === 'yorumlarim'): ?>
                    <h1 class="page-title">⭐ Yorumlarım</h1>
                    
                    <?php if (empty($yorumlar)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">⭐</div>
                            <h3>Henüz yorum yapmadınız</h3>
                            <p>Satın aldığınız ürünler hakkında yorum yaparak diğer müşterilere yardımcı olabilirsiniz.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($yorumlar as $yorum): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <a href="urun.php?slug=<?php echo $yorum['urun_slug']; ?>" class="review-product">
                                        <?php echo htmlspecialchars($yorum['urun_adi']); ?>
                                    </a>
                                    <span class="review-date"><?php echo tarihFormat($yorum['olusturma_tarihi']); ?></span>
                                </div>
                                <div class="review-stars">
                                    <?php echo yildizlar($yorum['puan'], '18px'); ?>
                                </div>
                                <div class="review-text">
                                    <?php echo nl2br(htmlspecialchars($yorum['yorum'])); ?>
                                </div>
                                <div class="review-status <?php echo $yorum['onaylandi'] ? 'approved' : 'pending'; ?>">
                                    <?php echo $yorum['onaylandi'] ? '✓ Onaylandı' : '⏳ Onay Bekliyor'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>