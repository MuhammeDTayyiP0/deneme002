<?php
require_once '../config.php';

if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

// Ürün ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urun_ekle'])) {
    $kategori_id = (int)$_POST['kategori_id'];
    $urun_adi = temizle($_POST['urun_adi']);
    $urun_slug = slugOlustur($urun_adi);
    $aciklama = temizle($_POST['aciklama']);
    $fiyat = (float)$_POST['fiyat'];
    $indirimli_fiyat = !empty($_POST['indirimli_fiyat']) ? (float)$_POST['indirimli_fiyat'] : null;
    $stok_miktari = (int)$_POST['stok_miktari'];
    $marka = temizle($_POST['marka']);
    $ozellikler = temizle($_POST['ozellikler']);
    
    $upload_dir = '../images/';
    
    // Klasör kontrolü
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $hata = "Images klasörü oluşturulamadı. Lütfen manuel olarak oluşturup 755 izni verin.";
        }
    }
    
    if (!is_writable($upload_dir)) {
        $hata = "Images klasörüne yazma izni yok. Lütfen chmod 755 images/ komutu ile izin verin.";
    }
    
    if (!isset($hata)) {
        try {
            $db->beginTransaction();
            
            // Önce ana resim olmadan ürün ekle
            $resim = 'default.jpg';
            $stmt = $db->prepare("INSERT INTO urunler (kategori_id, urun_adi, urun_slug, aciklama, fiyat, indirimli_fiyat, stok_miktari, marka, ozellikler, resim) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt->execute([$kategori_id, $urun_adi, $urun_slug, $aciklama, $fiyat, $indirimli_fiyat, $stok_miktari, $marka, $ozellikler, $resim])) {
                throw new Exception("Ürün eklenemedi!");
            }
            
            $urun_id = $db->lastInsertId();
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ilk_resim = null;
            
            // Resimleri yükle
            if (isset($_FILES['resimler'])) {
                $file_count = count($_FILES['resimler']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['resimler']['error'][$i] === 0) {
                        $filename = $_FILES['resimler']['name'][$i];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, $allowed)) {
                            $resim_adi = uniqid() . '_' . time() . '_' . $i . '.' . $ext;
                            
                            if (move_uploaded_file($_FILES['resimler']['tmp_name'][$i], $upload_dir . $resim_adi)) {
                                // İlk resmi ana resim yap
                                $ana_resim = ($i === 0) ? 1 : 0;
                                
                                // Resim tablosuna ekle
                                $stmt = $db->prepare("INSERT INTO urun_resimleri (urun_id, resim_yolu, sira, ana_resim) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$urun_id, $resim_adi, $i, $ana_resim]);
                                
                                // İlk resmi ürün tablosunda da güncelle (uyumluluk için)
                                if ($i === 0) {
                                    $ilk_resim = $resim_adi;
                                }
                            }
                        }
                    }
                }
                
                // İlk resmi ürün tablosuna da kaydet
                if ($ilk_resim) {
                    $stmt = $db->prepare("UPDATE urunler SET resim = ? WHERE id = ?");
                    $stmt->execute([$ilk_resim, $urun_id]);
                }
            }
            
            $db->commit();
            $basari = "Ürün başarıyla eklendi!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $hata = "Hata: " . $e->getMessage();
        }
    }
}

// Ürün silme
if (isset($_GET['sil'])) {
    $id = (int)$_GET['sil'];
    $stmt = $db->prepare("DELETE FROM urunler WHERE id = ?");
    if ($stmt->execute([$id])) {
        $basari = "Ürün silindi!";
    }
}

// Durum değiştirme
if (isset($_GET['durum_degistir'])) {
    $id = (int)$_GET['durum_degistir'];
    $stmt = $db->prepare("UPDATE urunler SET aktif = NOT aktif WHERE id = ?");
    if ($stmt->execute([$id])) {
        $basari = "Ürün durumu güncellendi!";
    }
}

// Ürünleri çek
$kategori_filtre = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$where = "1=1";
if ($kategori_filtre > 0) {
    $where .= " AND u.kategori_id = {$kategori_filtre}";
}

$stmt = $db->query("SELECT u.*, k.kategori_adi FROM urunler u 
                    LEFT JOIN kategoriler k ON u.kategori_id = k.id 
                    WHERE {$where}
                    ORDER BY u.id DESC");
$urunler = $stmt->fetchAll();

// Kategorileri çek
$kategoriler = $db->query("SELECT * FROM kategoriler WHERE aktif = 1 ORDER BY sira ASC")->fetchAll();

// İstatistikler
$toplam_urun = $db->query("SELECT COUNT(*) FROM urunler")->fetchColumn();
$aktif_urun = $db->query("SELECT COUNT(*) FROM urunler WHERE aktif = 1")->fetchColumn();
$dusuk_stok = $db->query("SELECT COUNT(*) FROM urunler WHERE stok_miktari < 10")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Yönetimi - Admin Panel</title>
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
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-title { font-size: 13px; color: #666; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 900; }
        .content-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; }
        .filter-select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-weight: 600; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .product-card { background: white; border: 1px solid #f0f0f0; border-radius: 12px; overflow: hidden; transition: all 0.3s; }
        .product-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .product-image { position: relative; padding-top: 100%; background: #f8f9fa; }
        .product-image img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; padding: 10px; }
        .product-badge { position: absolute; top: 10px; right: 10px; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .product-info { padding: 15px; }
        .product-name { font-weight: 700; margin-bottom: 8px; font-size: 14px; }
        .product-category { color: #667eea; font-size: 12px; margin-bottom: 8px; }
        .product-price { display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px; }
        .price-current { font-size: 18px; font-weight: 800; color: #667eea; }
        .price-old { font-size: 13px; color: #999; text-decoration: line-through; }
        .product-stock { font-size: 12px; color: #666; margin-bottom: 12px; }
        .stock-low { color: #ff4757; font-weight: 700; }
        .product-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .btn-edit, .btn-delete, .btn-toggle { padding: 8px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .btn-edit { background: #667eea; color: white; }
        .btn-delete { background: #ff4757; color: white; }
        .btn-toggle { background: #f39c12; color: white; grid-column: 1 / -1; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-title { font-size: 22px; font-weight: 800; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-textarea { resize: vertical; min-height: 100px; }
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
                <a href="urunler.php" class="menu-item active">📱 Ürünler</a>
                <a href="kategoriler.php" class="menu-item">📂 Kategoriler</a>
                <a href="musteriler.php" class="menu-item">👥 Müşteriler</a>
                <a href="yorumlar.php" class="menu-item">⭐ Yorumlar</a>
                <a href="kuponlar.php" class="menu-item">🎫 Kuponlar</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">📱 Ürün Yönetimi</h1>
                <button onclick="openAddModal()" class="btn-primary">+ Yeni Ürün</button>
            </div>
            
            <?php if (isset($basari)): ?>
                <div class="alert-success">✓ <?php echo $basari; ?></div>
            <?php endif; ?>
            
            <?php if (isset($hata)): ?>
                <div class="alert-error">❌ <?php echo $hata; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Toplam Ürün</div>
                    <div class="stat-value" style="color: #667eea;"><?php echo $toplam_urun; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Aktif Ürün</div>
                    <div class="stat-value" style="color: #27ae60;"><?php echo $aktif_urun; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Düşük Stok</div>
                    <div class="stat-value" style="color: #ff4757;"><?php echo $dusuk_stok; ?></div>
                </div>
            </div>
            
            <div class="content-card">
                <div class="filter-bar">
                    <select class="filter-select" onchange="window.location.href='?kategori='+this.value">
                        <option value="0">Tüm Kategoriler</option>
                        <?php foreach ($kategoriler as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>" <?php echo $kategori_filtre == $kat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kat['kategori_adi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="products-grid">
                    <?php foreach ($urunler as $urun): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="../images/<?php echo $urun['resim']; ?>" 
                                     alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x300?text=Ürün'">
                                <div class="product-badge <?php echo $urun['aktif'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $urun['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                </div>
                            </div>
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($urun['kategori_adi']); ?></div>
                                <div class="product-name"><?php echo htmlspecialchars($urun['urun_adi']); ?></div>
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
                                <div class="product-stock <?php echo $urun['stok_miktari'] < 10 ? 'stock-low' : ''; ?>">
                                    Stok: <?php echo $urun['stok_miktari']; ?> adet
                                </div>
                                <div class="product-actions">
                                    <a href="urun-duzenle.php?id=<?php echo $urun['id']; ?>" class="btn-edit">✏️ Düzenle</a>
                                    <a href="?sil=<?php echo $urun['id']; ?>" 
                                       onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')" 
                                       class="btn-delete">🗑️ Sil</a>
                                    <a href="?durum_degistir=<?php echo $urun['id']; ?>" class="btn-toggle">
                                        <?php echo $urun['aktif'] ? '❌ Pasif Yap' : '✅ Aktif Yap'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Yeni Ürün Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <h2 class="modal-title">Yeni Ürün Ekle</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori_id" class="form-select" required>
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($kategoriler as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>">
                                <?php echo htmlspecialchars($kat['kategori_adi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ürün Adı</label>
                    <input type="text" name="urun_adi" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Açıklama</label>
                    <textarea name="aciklama" class="form-textarea"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fiyat (₺)</label>
                        <input type="number" step="0.01" name="fiyat" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">İndirimli Fiyat (₺)</label>
                        <input type="number" step="0.01" name="indirimli_fiyat" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stok Miktarı</label>
                        <input type="number" name="stok_miktari" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marka</label>
                        <input type="text" name="marka" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Özellikler (virgülle ayırın)</label>
                    <textarea name="ozellikler" class="form-textarea" 
                              placeholder="6.7 inç ekran, 256GB, 5G"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ana Resim *</label>
                    <input type="file" name="resim" class="form-input" accept="image/*" required>
                    <small style="color: #666; font-size: 12px;">JPG, PNG, GIF, WEBP formatları desteklenir</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ek Resimler (Opsiyonel)</label>
                    <input type="file" name="resim2" class="form-input" accept="image/*" style="margin-bottom: 10px;">
                    <input type="file" name="resim3" class="form-input" accept="image/*" style="margin-bottom: 10px;">
                    <input type="file" name="resim4" class="form-input" accept="image/*" style="margin-bottom: 10px;">
                    <input type="file" name="resim5" class="form-input" accept="image/*">
                    <small style="color: #666; font-size: 12px;">Ürün başına 5 resim yükleyebilirsiniz</small>
                </div>
                
                <button type="submit" name="urun_ekle" class="btn-submit">Ürün Ekle</button>
                <button type="button" onclick="closeModal()" class="btn-cancel">İptal</button>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('addModal').classList.remove('active');
        }
    </script>
</body>
</html>