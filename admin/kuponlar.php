<?php
require_once '../config.php';

if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

// Kupon ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kupon_ekle'])) {
    $kupon_kodu = strtoupper(temizle($_POST['kupon_kodu']));
    $aciklama = temizle($_POST['aciklama']);
    $indirim_tipi = $_POST['indirim_tipi'];
    $indirim_degeri = (float)$_POST['indirim_degeri'];
    $min_tutar = (float)$_POST['min_tutar'];
    $max_kullanim = (int)$_POST['max_kullanim'];
    $baslangic = $_POST['baslangic_tarihi'];
    $bitis = $_POST['bitis_tarihi'];
    
    $stmt = $db->prepare("INSERT INTO kuponlar (kupon_kodu, aciklama, indirim_tipi, indirim_degeri, min_tutar, max_kullanim, baslangic_tarihi, bitis_tarihi) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$kupon_kodu, $aciklama, $indirim_tipi, $indirim_degeri, $min_tutar, $max_kullanim, $baslangic, $bitis])) {
        $basari = "Kupon başarıyla oluşturuldu!";
    } else {
        $hata = "Kupon kodu zaten mevcut!";
    }
}

// Kupon silme
if (isset($_GET['sil'])) {
    $id = (int)$_GET['sil'];
    $stmt = $db->prepare("DELETE FROM kuponlar WHERE id = ?");
    if ($stmt->execute([$id])) {
        $basari = "Kupon silindi!";
    }
}

// Durum değiştirme
if (isset($_GET['durum_degistir'])) {
    $id = (int)$_GET['durum_degistir'];
    $stmt = $db->prepare("UPDATE kuponlar SET aktif = NOT aktif WHERE id = ?");
    if ($stmt->execute([$id])) {
        $basari = "Kupon durumu güncellendi!";
    }
}

// Kuponları çek
$stmt = $db->query("SELECT * FROM kuponlar ORDER BY olusturma_tarihi DESC");
$kuponlar = $stmt->fetchAll();

// İstatistikler
$toplam = $db->query("SELECT COUNT(*) FROM kuponlar")->fetchColumn();
$aktif = $db->query("SELECT COUNT(*) FROM kuponlar WHERE aktif = 1")->fetchColumn();
$kullanilan = $db->query("SELECT COUNT(DISTINCT kupon_id) FROM kupon_kullanimlari")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f6fa; }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar { background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 30px 0; position: fixed; width: 260px; height: 100vh; overflow-y: auto; }
        .sidebar-logo { font-size: 28px; font-weight: 900; padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 30px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); color: white; border-left-color: #667eea; }
        .main-content { margin-left: 260px; padding: 30px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .page-title { font-size: 28px; font-weight: 800; }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-title { font-size: 13px; color: #666; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 900; }
        .content-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .coupons-table { width: 100%; }
        .coupons-table th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 700; font-size: 13px; color: #666; border-bottom: 2px solid #e0e0e0; }
        .coupons-table td { padding: 15px 12px; border-bottom: 1px solid #f0f0f0; }
        .coupons-table tr:hover { background: #f8f9fa; }
        .coupon-code { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px; font-weight: 800; font-size: 16px; display: inline-block; letter-spacing: 1px; }
        .discount-badge { background: #e8f5e9; color: #27ae60; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; }
        .status-active { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; }
        .btn-toggle { background: #f39c12; color: white; }
        .btn-delete { background: #ff4757; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-title { font-size: 22px; font-weight: 800; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-submit { padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .btn-cancel { padding: 12px 30px; background: #e0e0e0; color: #666; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; margin-left: 10px; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
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
                <a href="yorumlar.php" class="menu-item">⭐ Yorumlar</a>
                <a href="kuponlar.php" class="menu-item active">🎫 Kuponlar</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">🎫 Kupon Yönetimi</h1>
                <button onclick="openModal()" class="btn-primary">+ Yeni Kupon</button>
            </div>
            
            <?php if (isset($basari)): ?>
                <div class="alert-success">✓ <?php echo $basari; ?></div>
            <?php endif; ?>
            
            <?php if (isset($hata)): ?>
                <div class="alert-error">❌ <?php echo $hata; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Toplam Kupon</div>
                    <div class="stat-value" style="color: #667eea;"><?php echo $toplam; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Aktif Kupon</div>
                    <div class="stat-value" style="color: #27ae60;"><?php echo $aktif; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Kullanılan</div>
                    <div class="stat-value" style="color: #f39c12;"><?php echo $kullanilan; ?></div>
                </div>
            </div>
            
            <div class="content-card">
                <table class="coupons-table">
                    <thead>
                        <tr>
                            <th>Kupon Kodu</th>
                            <th>Açıklama</th>
                            <th>İndirim</th>
                            <th>Min. Tutar</th>
                            <th>Kullanım</th>
                            <th>Geçerlilik</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kuponlar as $kupon): ?>
                            <tr>
                                <td><div class="coupon-code"><?php echo $kupon['kupon_kodu']; ?></div></td>
                                <td><?php echo htmlspecialchars($kupon['aciklama']); ?></td>
                                <td>
                                    <span class="discount-badge">
                                        <?php 
                                        if ($kupon['indirim_tipi'] === 'yuzde') {
                                            echo '%' . $kupon['indirim_degeri'];
                                        } else {
                                            echo fiyatFormat($kupon['indirim_degeri']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo fiyatFormat($kupon['min_tutar']); ?></td>
                                <td>
                                    <?php echo $kupon['kullanim_sayisi']; ?> / 
                                    <?php echo $kupon['max_kullanim'] > 0 ? $kupon['max_kullanim'] : '∞'; ?>
                                </td>
                                <td>
                                    <?php if ($kupon['baslangic_tarihi']): ?>
                                        <?php echo date('d.m.Y', strtotime($kupon['baslangic_tarihi'])); ?><br>
                                        <?php echo date('d.m.Y', strtotime($kupon['bitis_tarihi'])); ?>
                                    <?php else: ?>
                                        Sınırsız
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $kupon['aktif'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $kupon['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?durum_degistir=<?php echo $kupon['id']; ?>" class="btn-action btn-toggle">
                                        <?php echo $kupon['aktif'] ? '❌' : '✅'; ?>
                                    </a>
                                    <a href="?sil=<?php echo $kupon['id']; ?>" 
                                       onclick="return confirm('Bu kuponu silmek istediğinize emin misiniz?')" 
                                       class="btn-action btn-delete">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Yeni Kupon Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <h2 class="modal-title">Yeni Kupon Oluştur</h2>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Kupon Kodu</label>
                    <input type="text" name="kupon_kodu" class="form-input" 
                           placeholder="YENI2024" required style="text-transform: uppercase;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Açıklama</label>
                    <input type="text" name="aciklama" class="form-input" 
                           placeholder="Yeni yıl kampanyası">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">İndirim Tipi</label>
                        <select name="indirim_tipi" class="form-select" required>
                            <option value="yuzde">Yüzde (%)</option>
                            <option value="tutar">Tutar (₺)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">İndirim Değeri</label>
                        <input type="number" step="0.01" name="indirim_degeri" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Min. Sepet Tutarı (₺)</label>
                        <input type="number" step="0.01" name="min_tutar" class="form-input" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max. Kullanım (0=Sınırsız)</label>
                        <input type="number" name="max_kullanim" class="form-input" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="baslangic_tarihi" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="bitis_tarihi" class="form-input">
                    </div>
                </div>
                
                <button type="submit" name="kupon_ekle" class="btn-submit">Kupon Oluştur</button>
                <button type="button" onclick="closeModal()" class="btn-cancel">İptal</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('addModal').classList.remove('active');
        }
    </script>
</body>
</html>