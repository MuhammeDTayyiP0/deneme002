<?php
require_once '../config.php';

if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

// Yorum onaylama
if (isset($_GET['onayla'])) {
    $id = (int)$_GET['onayla'];
    $stmt = $db->prepare("UPDATE yorumlar SET onaylandi = 1 WHERE id = ?");
    if ($stmt->execute([$id])) {
        // Ürün puanını güncelle
        $stmt = $db->prepare("SELECT urun_id FROM yorumlar WHERE id = ?");
        $stmt->execute([$id]);
        $urun_id = $stmt->fetchColumn();
        urunPuanGuncelle($db, $urun_id);
        
        $basari = "Yorum onaylandı!";
    }
}

// Yorum silme
if (isset($_GET['sil'])) {
    $id = (int)$_GET['sil'];
    $stmt = $db->prepare("SELECT urun_id FROM yorumlar WHERE id = ?");
    $stmt->execute([$id]);
    $urun_id = $stmt->fetchColumn();
    
    $stmt = $db->prepare("DELETE FROM yorumlar WHERE id = ?");
    if ($stmt->execute([$id])) {
        urunPuanGuncelle($db, $urun_id);
        $basari = "Yorum silindi!";
    }
}

// Yorumları çek
$durum = isset($_GET['durum']) ? $_GET['durum'] : 'tumu';
$where = "1=1";
if ($durum === 'beklemede') {
    $where = "y.onaylandi = 0";
} elseif ($durum === 'onaylandi') {
    $where = "y.onaylandi = 1";
}

$stmt = $db->query("SELECT y.*, u.urun_adi, u.urun_slug, k.ad_soyad 
                    FROM yorumlar y
                    LEFT JOIN urunler u ON y.urun_id = u.id
                    LEFT JOIN kullanicilar k ON y.kullanici_id = k.id
                    WHERE {$where}
                    ORDER BY y.olusturma_tarihi DESC");
$yorumlar = $stmt->fetchAll();

// İstatistikler
$toplam = $db->query("SELECT COUNT(*) FROM yorumlar")->fetchColumn();
$beklemede = $db->query("SELECT COUNT(*) FROM yorumlar WHERE onaylandi = 0")->fetchColumn();
$onaylandi = $db->query("SELECT COUNT(*) FROM yorumlar WHERE onaylandi = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yorum Yönetimi - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f6fa; }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar { background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 30px 0; position: fixed; width: 260px; height: 100vh; overflow-y: auto; }
        .sidebar-logo { font-size: 28px; font-weight: 900; padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 30px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); color: white; border-left-color: #667eea; }
        .main-content { margin-left: 260px; padding: 30px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .page-title { font-size: 28px; font-weight: 800; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-title { font-size: 13px; color: #666; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 900; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-tab { padding: 10px 20px; border: 2px solid #e0e0e0; border-radius: 8px; text-decoration: none; color: #666; font-weight: 600; transition: all 0.3s; }
        .filter-tab:hover, .filter-tab.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-color: transparent; }
        .content-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .review-card { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 15px; }
        .review-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .review-user { font-weight: 700; color: #1a1a1a; }
        .review-product { color: #667eea; font-size: 14px; margin-top: 5px; }
        .review-date { font-size: 12px; color: #999; }
        .review-stars { color: #ffc107; font-size: 18px; margin-bottom: 10px; }
        .review-text { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .review-actions { display: flex; gap: 10px; }
        .btn-approve { padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-delete { padding: 8px 16px; background: #ff4757; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 700; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 64px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">ElitGSM</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item">📊 Dashboard</a>
                <a href="siparisler.php" class="menu-item">📦 Siparişler</a>
                <a href="urunler.php" class="menu-item">📱 Ürünler</a>
                <a href="kategoriler.php" class="menu-item">📂 Kategoriler</a>
                <a href="musteriler.php" class="menu-item">👥 Müşteriler</a>
                <a href="yorumlar.php" class="menu-item active">⭐ Yorumlar</a>
                <a href="kuponlar.php" class="menu-item">🎫 Kuponlar</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">⭐ Yorum Yönetimi</h1>
            </div>
            
            <?php if (isset($basari)): ?>
                <div class="alert-success">✓ <?php echo $basari; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Toplam Yorum</div>
                    <div class="stat-value" style="color: #667eea;"><?php echo $toplam; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Onay Bekleyen</div>
                    <div class="stat-value" style="color: #f39c12;"><?php echo $beklemede; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Onaylanmış</div>
                    <div class="stat-value" style="color: #27ae60;"><?php echo $onaylandi; ?></div>
                </div>
            </div>
            
            <div class="filter-tabs">
                <a href="?durum=tumu" class="filter-tab <?php echo $durum === 'tumu' ? 'active' : ''; ?>">Tümü</a>
                <a href="?durum=beklemede" class="filter-tab <?php echo $durum === 'beklemede' ? 'active' : ''; ?>">Onay Bekleyen</a>
                <a href="?durum=onaylandi" class="filter-tab <?php echo $durum === 'onaylandi' ? 'active' : ''; ?>">Onaylanmış</a>
            </div>
            
            <div class="content-card">
                <?php if (empty($yorumlar)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">⭐</div>
                        <h3>Yorum bulunamadı</h3>
                        <p>Bu filtre ile eşleşen yorum bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($yorumlar as $yorum): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div>
                                    <div class="review-user"><?php echo htmlspecialchars($yorum['ad_soyad']); ?></div>
                                    <a href="../urun.php?slug=<?php echo $yorum['urun_slug']; ?>" class="review-product">
                                        <?php echo htmlspecialchars($yorum['urun_adi']); ?>
                                    </a>
                                </div>
                                <div>
                                    <div class="review-date"><?php echo tarihFormat($yorum['olusturma_tarihi']); ?></div>
                                    <span class="status-badge <?php echo $yorum['onaylandi'] ? 'badge-approved' : 'badge-pending'; ?>">
                                        <?php echo $yorum['onaylandi'] ? 'Onaylandı' : 'Beklemede'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="review-stars">
                                <?php echo yildizlar($yorum['puan'], '18px'); ?>
                            </div>
                            <div class="review-text">
                                <?php echo nl2br(htmlspecialchars($yorum['yorum'])); ?>
                            </div>
                            <div class="review-actions">
                                <?php if (!$yorum['onaylandi']): ?>
                                    <a href="?onayla=<?php echo $yorum['id']; ?>" class="btn-approve">✓ Onayla</a>
                                <?php endif; ?>
                                <a href="?sil=<?php echo $yorum['id']; ?>" 
                                   onclick="return confirm('Bu yorumu silmek istediğinize emin misiniz?')" 
                                   class="btn-delete">🗑️ Sil</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>