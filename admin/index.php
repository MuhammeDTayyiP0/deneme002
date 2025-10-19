<?php
require_once '../config.php';

// Admin kontrolü
if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

// İstatistikler
$stmt = $db->query("SELECT COUNT(*) FROM siparisler");
$toplam_siparis = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM siparisler WHERE durum = 'beklemede'");
$bekleyen_siparis = $stmt->fetchColumn();

$stmt = $db->query("SELECT SUM(toplam_tutar) FROM siparisler WHERE odeme_durumu = 'odendi'");
$toplam_ciro = $stmt->fetchColumn() ?: 0;

$stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'musteri'");
$toplam_musteri = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM urunler");
$toplam_urun = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM yorumlar WHERE onaylandi = 0");
$bekleyen_yorum = $stmt->fetchColumn();

// Son siparişler
$stmt = $db->query("SELECT s.*, k.ad_soyad FROM siparisler s 
                    LEFT JOIN kullanicilar k ON s.kullanici_id = k.id 
                    ORDER BY s.siparis_tarihi DESC LIMIT 10");
$son_siparisler = $stmt->fetchAll();

// Stok uyarısı
$stmt = $db->query("SELECT * FROM urunler WHERE stok_miktari < 10 AND aktif = 1 ORDER BY stok_miktari ASC LIMIT 5");
$dusuk_stok = $stmt->fetchAll();

// Bugünün istatistikleri
$stmt = $db->query("SELECT COUNT(*) FROM siparisler WHERE DATE(siparis_tarihi) = CURDATE()");
$bugun_siparis = $stmt->fetchColumn();

$stmt = $db->query("SELECT SUM(toplam_tutar) FROM siparisler 
                    WHERE DATE(siparis_tarihi) = CURDATE() AND odeme_durumu = 'odendi'");
$bugun_ciro = $stmt->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f6fa;
            color: #1a1a1a;
        }
        
        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            padding: 30px 0;
            position: fixed;
            width: 260px;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            font-size: 28px;
            font-weight: 900;
            padding: 0 30px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 30px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #667eea;
        }
        
        .menu-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }
        
        .badge {
            margin-left: auto;
            background: #ff4757;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 800;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        
        .logout-btn {
            padding: 10px 20px;
            background: #ff4757;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #ee3f4d;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 900;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 13px;
            color: #2ed573;
            font-weight: 600;
        }
        
        .stat-change.negative {
            color: #ff4757;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .orders-table {
            width: 100%;
        }
        
        .orders-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 700;
            font-size: 13px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .orders-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .orders-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        
        .status-beklemede {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-onaylandi {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-kargoda {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-teslim_edildi {
            background: #d4edda;
            color: #155724;
        }
        
        .status-iptal {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert-card {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .alert-icon {
            font-size: 24px;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            color: #856404;
            margin-bottom: 3px;
        }
        
        .alert-text {
            font-size: 13px;
            color: #856404;
        }
        
        .btn-view {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #5568d3;
        }
        
        @media (max-width: 1024px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .main-content {
                padding: 20px 15px;
            }
            
            .top-bar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-title {
                font-size: 12px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .stat-change {
                font-size: 11px;
            }
            
            .content-card {
                padding: 20px 15px;
            }
            
            .card-title {
                font-size: 18px;
                margin-bottom: 20px;
            }
            
            .orders-table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 10px 8px;
                white-space: nowrap;
            }
            
            .status-badge {
                font-size: 10px;
                padding: 4px 8px;
            }
            
            .btn-view {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .alert-card {
                padding: 12px;
            }
            
            .alert-icon {
                font-size: 20px;
            }
            
            .alert-title {
                font-size: 13px;
            }
            
            .alert-text {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                padding: 12px 15px;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
            
            .logout-btn {
                padding: 8px 15px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">ElitGSM</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item active">
                    <span class="menu-icon">📊</span>
                    Dashboard
                </a>
                <a href="siparisler.php" class="menu-item">
                    <span class="menu-icon">📦</span>
                    Siparişler
                    <?php if ($bekleyen_siparis > 0): ?>
                        <span class="badge"><?php echo $bekleyen_siparis; ?></span>
                    <?php endif; ?>
                </a>
                <a href="urunler.php" class="menu-item">
                    <span class="menu-icon">📱</span>
                    Ürünler
                </a>
                <a href="kategoriler.php" class="menu-item">
                    <span class="menu-icon">📂</span>
                    Kategoriler
                </a>
                <a href="musteriler.php" class="menu-item">
                    <span class="menu-icon">👥</span>
                    Müşteriler
                </a>
                <a href="yorumlar.php" class="menu-item">
                    <span class="menu-icon">⭐</span>
                    Yorumlar
                    <?php if ($bekleyen_yorum > 0): ?>
                        <span class="badge"><?php echo $bekleyen_yorum; ?></span>
                    <?php endif; ?>
                </a>
                <a href="kuponlar.php" class="menu-item">
                    <span class="menu-icon">🎫</span>
                    Kuponlar
                </a>
                <a href="slider.php" class="menu-item">
                    <span class="menu-icon">🖼️</span>
                    Slider
                </a>
                <a href="ayarlar.php" class="menu-item">
                    <span class="menu-icon">⚙️</span>
                    Ayarlar
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar">A</div>
                    <div>
                        <div style="font-weight: 600;">Admin</div>
                        <div style="font-size: 12px; color: #666;">Yönetici</div>
                    </div>
                    <a href="../cikis.php" class="logout-btn">Çıkış</a>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Bugünkü Sipariş</div>
                        <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">📦</div>
                    </div>
                    <div class="stat-value"><?php echo $bugun_siparis; ?></div>
                    <div class="stat-change">Bugün</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Bugünkü Ciro</div>
                        <div class="stat-icon" style="background: #e8f5e9; color: #388e3c;">💰</div>
                    </div>
                    <div class="stat-value"><?php echo fiyatFormat($bugun_ciro); ?></div>
                    <div class="stat-change">Bugün</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Toplam Sipariş</div>
                        <div class="stat-icon" style="background: #fff3e0; color: #f57c00;">📊</div>
                    </div>
                    <div class="stat-value"><?php echo $toplam_siparis; ?></div>
                    <div class="stat-change">Tüm Zamanlar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Toplam Ciro</div>
                        <div class="stat-icon" style="background: #f3e5f5; color: #7b1fa2;">💵</div>
                    </div>
                    <div class="stat-value"><?php echo fiyatFormat($toplam_ciro); ?></div>
                    <div class="stat-change">Tüm Zamanlar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Bekleyen Sipariş</div>
                        <div class="stat-icon" style="background: #fff3cd; color: #856404;">⏳</div>
                    </div>
                    <div class="stat-value"><?php echo $bekleyen_siparis; ?></div>
                    <div class="stat-change">İşlem Bekliyor</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Toplam Müşteri</div>
                        <div class="stat-icon" style="background: #fce4ec; color: #c2185b;">👥</div>
                    </div>
                    <div class="stat-value"><?php echo $toplam_musteri; ?></div>
                    <div class="stat-change">Kayıtlı Üye</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Toplam Ürün</div>
                        <div class="stat-icon" style="background: #e0f2f1; color: #00796b;">📱</div>
                    </div>
                    <div class="stat-value"><?php echo $toplam_urun; ?></div>
                    <div class="stat-change">Aktif Ürün</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Bekleyen Yorum</div>
                        <div class="stat-icon" style="background: #ede7f6; color: #5e35b1;">⭐</div>
                    </div>
                    <div class="stat-value"><?php echo $bekleyen_yorum; ?></div>
                    <div class="stat-change">Onay Bekliyor</div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Son Siparişler -->
                <div class="content-card">
                    <h2 class="card-title">📦 Son Siparişler</h2>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Müşteri</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($son_siparisler as $siparis): ?>
                                <tr>
                                    <td><strong><?php echo $siparis['siparis_no']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($siparis['ad_soyad']); ?></td>
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
                                        <a href="siparis-detay.php?id=<?php echo $siparis['id']; ?>" class="btn-view">Görüntüle</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Uyarılar -->
                <div class="content-card">
                    <h2 class="card-title">⚠️ Uyarılar</h2>
                    
                    <?php if ($bekleyen_siparis > 0): ?>
                        <div class="alert-card alert-warning">
                            <div class="alert-icon">📦</div>
                            <div class="alert-content">
                                <div class="alert-title">Bekleyen Siparişler</div>
                                <div class="alert-text"><?php echo $bekleyen_siparis; ?> adet sipariş işlem bekliyor</div>
                            </div>
                            <a href="siparisler.php?durum=beklemede" class="btn-view">Görüntüle</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($bekleyen_yorum > 0): ?>
                        <div class="alert-card alert-warning">
                            <div class="alert-icon">⭐</div>
                            <div class="alert-content">
                                <div class="alert-title">Onay Bekleyen Yorumlar</div>
                                <div class="alert-text"><?php echo $bekleyen_yorum; ?> adet yorum onay bekliyor</div>
                            </div>
                            <a href="yorumlar.php?durum=beklemede" class="btn-view">Görüntüle</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($dusuk_stok)): ?>
                        <div style="margin-top: 30px;">
                            <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 15px; color: #856404;">
                                📉 Düşük Stok Uyarısı
                            </h3>
                            <?php foreach ($dusuk_stok as $urun): ?>
                                <div class="alert-card alert-warning">
                                    <div class="alert-content">
                                        <div class="alert-title"><?php echo htmlspecialchars($urun['urun_adi']); ?></div>
                                        <div class="alert-text">Stok: <?php echo $urun['stok_miktari']; ?> adet</div>
                                    </div>
                                    <a href="urun-duzenle.php?id=<?php echo $urun['id']; ?>" class="btn-view">Düzenle</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>