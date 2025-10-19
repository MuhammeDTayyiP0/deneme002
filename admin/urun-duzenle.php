<?php
require_once '../config.php';

if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

$urun_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ürünü çek
$stmt = $db->prepare("SELECT * FROM urunler WHERE id = ?");
$stmt->execute([$urun_id]);
$urun = $stmt->fetch();

if (!$urun) {
    header("Location: urunler.php");
    exit;
}

// Ürün güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urun_guncelle'])) {
    $kategori_id = (int)$_POST['kategori_id'];
    $urun_adi = temizle($_POST['urun_adi']);
    $urun_slug = slugOlustur($urun_adi);
    $aciklama = temizle($_POST['aciklama']);
    $fiyat = (float)$_POST['fiyat'];
    $indirimli_fiyat = !empty($_POST['indirimli_fiyat']) ? (float)$_POST['indirimli_fiyat'] : null;
    $stok_miktari = (int)$_POST['stok_miktari'];
    $marka = temizle($_POST['marka']);
    $ozellikler = temizle($_POST['ozellikler']);
    $vitrin = isset($_POST['vitrin_urunu']) ? 1 : 0;
    $yeni = isset($_POST['yeni_urun']) ? 1 : 0;
    $populer = isset($_POST['populer']) ? 1 : 0;
    
    $upload_dir = '../images/';
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Mevcut resimleri al
    $resimler = [
        'resim' => $urun['resim'],
        'resim2' => $urun['resim2'],
        'resim3' => $urun['resim3'],
        'resim4' => $urun['resim4'],
        'resim5' => $urun['resim5']
    ];
    
    // Yeni resim yüklemeleri
    foreach ($resimler as $key => $value) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
            $filename = $_FILES[$key]['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                // Eski resmi sil
                if ($value && $value !== 'default.jpg' && file_exists($upload_dir . $value)) {
                    unlink($upload_dir . $value);
                }
                
                $resim_adi = uniqid() . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES[$key]['tmp_name'], $upload_dir . $resim_adi);
                $resimler[$key] = $resim_adi;
            }
        }
    }
    
    $stmt = $db->prepare("UPDATE urunler SET 
        kategori_id = ?, urun_adi = ?, urun_slug = ?, aciklama = ?, 
        fiyat = ?, indirimli_fiyat = ?, stok_miktari = ?, marka = ?, ozellikler = ?,
        resim = ?, resim2 = ?, resim3 = ?, resim4 = ?, resim5 = ?,
        vitrin_urunu = ?, yeni_urun = ?, populer = ?
        WHERE id = ?");
    
    if ($stmt->execute([
        $kategori_id, $urun_adi, $urun_slug, $aciklama,
        $fiyat, $indirimli_fiyat, $stok_miktari, $marka, $ozellikler,
        $resimler['resim'], $resimler['resim2'], $resimler['resim3'], 
        $resimler['resim4'], $resimler['resim5'],
        $vitrin, $yeni, $populer, $urun_id
    ])) {
        $basari = "Ürün başarıyla güncellendi!";
        // Güncellenen veriyi tekrar çek
        $stmt = $db->prepare("SELECT * FROM urunler WHERE id = ?");
        $stmt->execute([$urun_id]);
        $urun = $stmt->fetch();
    }
}

// Resim silme
if (isset($_GET['resim_sil'])) {
    $resim_key = $_GET['resim_sil'];
    if (in_array($resim_key, ['resim2', 'resim3', 'resim4', 'resim5'])) {
        if ($urun[$resim_key] && file_exists('../images/' . $urun[$resim_key])) {
            unlink('../images/' . $urun[$resim_key]);
        }
        $stmt = $db->prepare("UPDATE urunler SET {$resim_key} = NULL WHERE id = ?");
        $stmt->execute([$urun_id]);
        header("Location: urun-duzenle.php?id={$urun_id}");
        exit;
    }
}

// Kategorileri çek
$kategoriler = $db->query("SELECT * FROM kategoriler WHERE aktif = 1 ORDER BY sira ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Düzenle - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f6fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .page-title { font-size: 28px; font-weight: 800; }
        .btn-back { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .content-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 25px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a1a1a; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: all 0.3s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-textarea { resize: vertical; min-height: 120px; }
        .checkbox-group { display: flex; gap: 25px; margin-top: 15px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .checkbox-item input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .btn-submit { padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
        .alert-success { background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; }
        .images-section { margin-bottom: 30px; }
        .images-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 15px; }
        .image-item { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: #f8f9fa; border: 2px solid #e0e0e0; }
        .image-item img { width: 100%; height: 100%; object-fit: cover; }
        .image-item.empty { display: flex; align-items: center; justify-content: center; color: #999; font-size: 40px; }
        .image-remove { position: absolute; top: 5px; right: 5px; background: #ff4757; color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }
        .image-badge { position: absolute; bottom: 5px; left: 5px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .upload-section { grid-column: 1 / -1; }
        .file-input-wrapper { position: relative; margin-bottom: 10px; }
        .file-input-wrapper input[type="file"] { width: 100%; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .images-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h1 class="page-title">✏️ Ürün Düzenle</h1>
            <a href="urunler.php" class="btn-back">← Ürünlere Dön</a>
        </div>
        
        <?php if (isset($basari)): ?>
            <div class="alert-success">✓ <?php echo $basari; ?></div>
        <?php endif; ?>
        
        <div class="content-card">
            <!-- Mevcut Resimler -->
            <div class="images-section">
                <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 15px;">📸 Ürün Resimleri</h3>
                <div class="images-grid">
                    <div class="image-item">
                        <?php if ($urun['resim']): ?>
                            <img src="../images/<?php echo $urun['resim']; ?>" alt="Ana Resim">
                            <div class="image-badge">Ana Resim</div>
                        <?php else: ?>
                            <div class="empty">📷</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php for ($i = 2; $i <= 5; $i++): ?>
                        <div class="image-item">
                            <?php if ($urun["resim{$i}"]): ?>
                                <img src="../images/<?php echo $urun["resim{$i}"]; ?>" alt="Resim <?php echo $i; ?>">
                                <a href="?id=<?php echo $urun_id; ?>&resim_sil=resim<?php echo $i; ?>" 
                                   class="image-remove" 
                                   onclick="return confirm('Bu resmi silmek istediğinize emin misiniz?')">×</a>
                                <div class="image-badge">Resim <?php echo $i; ?></div>
                            <?php else: ?>
                                <div class="empty">📷</div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <?php foreach ($kategoriler as $kat): ?>
                                <option value="<?php echo $kat['id']; ?>" <?php echo $urun['kategori_id'] == $kat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat['kategori_adi']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Marka</label>
                        <input type="text" name="marka" class="form-input" value="<?php echo htmlspecialchars($urun['marka']); ?>">
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Ürün Adı</label>
                        <input type="text" name="urun_adi" class="form-input" value="<?php echo htmlspecialchars($urun['urun_adi']); ?>" required>
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Açıklama</label>
                        <textarea name="aciklama" class="form-textarea"><?php echo htmlspecialchars($urun['aciklama']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Normal Fiyat (₺)</label>
                        <input type="number" step="0.01" name="fiyat" class="form-input" value="<?php echo $urun['fiyat']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">İndirimli Fiyat (₺)</label>
                        <input type="number" step="0.01" name="indirimli_fiyat" class="form-input" value="<?php echo $urun['indirimli_fiyat']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok Miktarı</label>
                        <input type="number" name="stok_miktari" class="form-input" value="<?php echo $urun['stok_miktari']; ?>" required>
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Özellikler (virgülle ayırın)</label>
                        <textarea name="ozellikler" class="form-textarea"><?php echo htmlspecialchars($urun['ozellikler']); ?></textarea>
                    </div>
                    
                    <div class="form-group full upload-section">
                        <label class="form-label">Yeni Resim Yükle</label>
                        <div class="file-input-wrapper">
                            <label style="font-size: 13px; color: #666;">Ana Resim</label>
                            <input type="file" name="resim" class="form-input" accept="image/*">
                        </div>
                        <div class="file-input-wrapper">
                            <label style="font-size: 13px; color: #666;">Ek Resim 2</label>
                            <input type="file" name="resim2" class="form-input" accept="image/*">
                        </div>
                        <div class="file-input-wrapper">
                            <label style="font-size: 13px; color: #666;">Ek Resim 3</label>
                            <input type="file" name="resim3" class="form-input" accept="image/*">
                        </div>
                        <div class="file-input-wrapper">
                            <label style="font-size: 13px; color: #666;">Ek Resim 4</label>
                            <input type="file" name="resim4" class="form-input" accept="image/*">
                        </div>
                        <div class="file-input-wrapper">
                            <label style="font-size: 13px; color: #666;">Ek Resim 5</label>
                            <input type="file" name="resim5" class="form-input" accept="image/*">
                        </div>
                        <small style="color: #666; font-size: 12px;">JPG, PNG, GIF, WEBP formatları desteklenir. Sadece değiştirmek istediğiniz resimleri seçin.</small>
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Ürün Etiketleri</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="vitrin_urunu" id="vitrin" <?php echo $urun['vitrin_urunu'] ? 'checked' : ''; ?>>
                                <label for="vitrin">Vitrin Ürünü</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="yeni_urun" id="yeni" <?php echo $urun['yeni_urun'] ? 'checked' : ''; ?>>
                                <label for="yeni">Yeni Ürün</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="populer" id="populer" <?php echo $urun['populer'] ? 'checked' : ''; ?>>
                                <label for="populer">Popüler Ürün</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e0e0e0;">
                    <button type="submit" name="urun_guncelle" class="btn-submit">
                        💾 Değişiklikleri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>