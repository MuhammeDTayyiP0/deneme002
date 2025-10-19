<?php
require_once '../config.php';

if (!adminKontrol()) {
    header("Location: ../giris.php");
    exit;
}

// Durum filtreleme
$durum_filtre = isset($_GET['durum']) ? $_GET['durum'] : '';

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_guncelle'])) {
    $siparis_id = (int)$_POST['siparis_id'];
    $yeni_durum = $_POST['yeni_durum'];
    $kargo_takip = isset($_POST['kargo_takip']) ? temizle($_POST['kargo_takip']) : null;
    $not = isset($_POST['not']) ? temizle($_POST['not']) : '';
    
    $stmt = $db->prepare("UPDATE siparisler SET durum = ?, kargo_takip_no = ?, notlar = ? WHERE id = ?");
    if ($stmt->execute([$yeni_durum, $kargo_takip, $not, $siparis_id])) {
        // Durum geçmişine kaydet
        $stmt = $db->prepare("INSERT INTO siparis_durum_gecmisi (siparis_id, yeni_durum, aciklama) 
                             VALUES (?, ?, ?)");
        $stmt->execute([$siparis_id, $yeni_durum, "Admin tarafından güncellendi: " . $not]);
        
        // Email gönder
        $stmt = $db->prepare("SELECT k.email, k.ad_soyad, s.siparis_no FROM siparisler s 
                             LEFT JOIN kullanicilar k ON s.kullanici_id = k.id WHERE s.id = ?");
        $stmt->execute([$siparis_id]);
        $siparis = $stmt->fetch();
        
        $durum_mesajlari = [
            'onaylandi' => 'Siparişiniz onaylandı',
            'hazirlaniyor' => 'Siparişiniz hazırlanıyor',
            'kargoda' => 'Siparişiniz kargoya verildi',
            'teslim_edildi' => 'Siparişiniz teslim edildi'
        ];
        
        $konu = "Sipariş Durumu Güncellendi - " . $siparis['siparis_no'];
        $mesaj = "<html><body>
            <h2>Merhaba {$siparis['ad_soyad']},</h2>
            <p><strong>Sipariş No:</strong> {$siparis['siparis_no']}</p>
            <p><strong>Yeni Durum:</strong> {$durum_mesajlari[$yeni_durum]}</p>";
        
        if ($kargo_takip) {
            $mesaj .= "<p><strong>Kargo Takip No:</strong> {$kargo_takip}</p>";
        }
        
        $mesaj .= "<p>Teşekkürler,<br>" . SITE_NAME . "</p></body></html>";
        
        emailKaydet($db, null, $siparis['email'], $konu, $mesaj);
        
        $basari = "Sipariş durumu güncellendi!";
    }
}

// Siparişleri çek
$where = "1=1";
if (!empty($durum_filtre)) {
    $where .= " AND s.durum = " . $db->quote($durum_filtre);
}

$stmt = $db->query("SELECT s.*, k.ad_soyad, k.email, k.telefon 
                    FROM siparisler s 
                    LEFT JOIN kullanicilar k ON s.kullanici_id = k.id 
                    WHERE {$where}
                    ORDER BY s.siparis_tarihi DESC");
$siparisler = $stmt->fetchAll();

// İstatistikler
$stats = [
    'beklemede' => $db->query("SELECT COUNT(*) FROM siparisler WHERE durum = 'beklemede'")->fetchColumn(),
    'onaylandi' => $db->query("SELECT COUNT(*) FROM siparisler WHERE durum = 'onaylandi'")->fetchColumn(),
    'kargoda' => $db->query("SELECT COUNT(*) FROM siparisler WHERE durum = 'kargoda'")->fetchColumn(),
    'teslim_edildi' => $db->query("SELECT COUNT(*) FROM siparisler WHERE durum = 'teslim_edildi'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi - Admin Panel</title>
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
        }
        
        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
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
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #667eea;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 800;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .stat-title {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 900;
            color: #1a1a1a;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 12px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .filter-tab:hover,
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .content-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
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
        
        .status-beklemede { background: #fff3cd; color: #856404; }
        .status-onaylandi { background: #d1ecf1; color: #0c5460; }
        .status-hazirlaniyor { background: #e2e3e5; color: #383d41; }
        .status-kargoda { background: #cce5ff; color: #004085; }
        .status-teslim_edildi { background: #d4edda; color: #155724; }
        .status-iptal { background: #f8d7da; color: #721c24; }
        
        .btn-action {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-submit {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        
        .btn-cancel {
            padding: 12px 30px;
            background: #e0e0e0;
            color: #666;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">ElitGSM</div>
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item">📊 Dashboard</a>
                <a href="siparisler.php" class="menu-item active">📦 Siparişler</a>
                <a href="urunler.php" class="menu-item">📱 Ürünler</a>
                <a href="kategoriler.php" class="menu-item">📂 Kategoriler</a>
                <a href="musteriler.php" class="menu-item">👥 Müşteriler</a>
                <a href="yorumlar.php" class="menu-item">⭐ Yorumlar</a>
                <a href="kuponlar.php" class="menu-item">🎫 Kuponlar</a>
                <a href="slider.php" class="menu-item">🖼️ Slider</a>
                <a href="ayarlar.php" class="menu-item">⚙️ Ayarlar</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">📦 Sipariş Yönetimi</h1>
                <a href="index.php" class="btn-back">← Dashboard</a>
            </div>
            
            <?php if (isset($basari)): ?>
                <div class="alert-success">✓ <?php echo $basari; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Beklemede</div>
                    <div class="stat-value" style="color: #f39c12;"><?php echo $stats['beklemede']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Onaylandı</div>
                    <div class="stat-value" style="color: #3498db;"><?php echo $stats['onaylandi']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Kargoda</div>
                    <div class="stat-value" style="color: #9b59b6;"><?php echo $stats['kargoda']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Teslim Edildi</div>
                    <div class="stat-value" style="color: #27ae60;"><?php echo $stats['teslim_edildi']; ?></div>
                </div>
            </div>
            
            <div class="filter-tabs">
                <a href="siparisler.php" class="filter-tab <?php echo empty($durum_filtre) ? 'active' : ''; ?>">
                    Tümü (<?php echo count($siparisler); ?>)
                </a>
                <a href="?durum=beklemede" class="filter-tab <?php echo $durum_filtre === 'beklemede' ? 'active' : ''; ?>">
                    Beklemede (<?php echo $stats['beklemede']; ?>)
                </a>
                <a href="?durum=onaylandi" class="filter-tab <?php echo $durum_filtre === 'onaylandi' ? 'active' : ''; ?>">
                    Onaylandı
                </a>
                <a href="?durum=kargoda" class="filter-tab <?php echo $durum_filtre === 'kargoda' ? 'active' : ''; ?>">
                    Kargoda
                </a>
                <a href="?durum=teslim_edildi" class="filter-tab <?php echo $durum_filtre === 'teslim_edildi' ? 'active' : ''; ?>">
                    Teslim Edildi
                </a>
            </div>
            
            <div class="content-card">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Sipariş No</th>
                            <th>Müşteri</th>
                            <th>Telefon</th>
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
                                <td>
                                    <?php echo htmlspecialchars($siparis['ad_soyad']); ?><br>
                                    <small style="color: #666;"><?php echo $siparis['email']; ?></small>
                                </td>
                                <td><?php echo $siparis['telefon']; ?></td>
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
                                    <button onclick="openModal(<?php echo htmlspecialchars(json_encode($siparis)); ?>)" 
                                            class="btn-action">Düzenle</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <h2 class="modal-title">Sipariş Durumu Güncelle</h2>
            <form method="POST">
                <input type="hidden" name="siparis_id" id="siparis_id">
                
                <div class="form-group">
                    <label class="form-label">Sipariş No</label>
                    <input type="text" id="siparis_no" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Yeni Durum</label>
                    <select name="yeni_durum" id="yeni_durum" class="form-select" required>
                        <option value="beklemede">Beklemede</option>
                        <option value="onaylandi">Onaylandı</option>
                        <option value="hazirlaniyor">Hazırlanıyor</option>
                        <option value="kargoda">Kargoda</option>
                        <option value="teslim_edildi">Teslim Edildi</option>
                        <option value="iptal">İptal</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kargo Takip No (Opsiyonel)</label>
                    <input type="text" name="kargo_takip" id="kargo_takip" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Not</label>
                    <textarea name="not" id="not" class="form-textarea"></textarea>
                </div>
                
                <button type="submit" name="durum_guncelle" class="btn-submit">Güncelle</button>
                <button type="button" onclick="closeModal()" class="btn-cancel">İptal</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(siparis) {
            document.getElementById('siparis_id').value = siparis.id;
            document.getElementById('siparis_no').value = siparis.siparis_no;
            document.getElementById('yeni_durum').value = siparis.durum;
            document.getElementById('kargo_takip').value = siparis.kargo_takip_no || '';
            document.getElementById('not').value = siparis.notlar || '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
    </script>
</body>
</html>