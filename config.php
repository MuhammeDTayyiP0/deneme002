<?php
// Veritabanı bağlantı ayarları
define('DB_HOST', 'localhost');
define('DB_USER', 'elitgsm_db');
define('DB_PASS', 'Cen4tay4MussqlVeRi56');
define('DB_NAME', 'elitgsm_db');

// Site ayarları
define('SITE_URL', 'https://hakki.geldesat.com');
define('SITE_NAME', 'ElitGSM');
define('SITE_TITLE', 'ElitGSM - Teknoloji Alışverişin Adresi');
define('SITE_EMAIL', 'info@elitgsm.com');

// Veritabanı bağlantısı
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Yardımcı fonksiyonlar
function temizle($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function fiyatFormat($fiyat) {
    return number_format($fiyat, 2, ',', '.') . ' ₺';
}

function slugOlustur($text) {
    $turkce = array('Ç','ç','Ğ','ğ','ı','İ','Ö','ö','Ş','ş','Ü','ü',' ');
    $ingilizce = array('c','c','g','g','i','i','o','o','s','s','u','u','-');
    $text = str_replace($turkce, $ingilizce, $text);
    $text = preg_replace('/[^a-z0-9\-]/', '', strtolower($text));
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function indirimYuzdesi($normal_fiyat, $indirimli_fiyat) {
    if ($indirimli_fiyat && $indirimli_fiyat < $normal_fiyat) {
        $oran = (($normal_fiyat - $indirimli_fiyat) / $normal_fiyat) * 100;
        return round($oran);
    }
    return 0;
}

function sepeteEkle($urun_id, $adet = 1) {
    if (!isset($_SESSION['sepet'])) {
        $_SESSION['sepet'] = array();
    }
    
    if (isset($_SESSION['sepet'][$urun_id])) {
        $_SESSION['sepet'][$urun_id] += $adet;
    } else {
        $_SESSION['sepet'][$urun_id] = $adet;
    }
    return true;
}

function sepetToplam($db) {
    if (!isset($_SESSION['sepet']) || empty($_SESSION['sepet'])) {
        return 0;
    }
    
    $toplam = 0;
    foreach ($_SESSION['sepet'] as $urun_id => $adet) {
        $stmt = $db->prepare("SELECT fiyat, indirimli_fiyat FROM urunler WHERE id = ? AND aktif = 1");
        $stmt->execute([$urun_id]);
        $urun = $stmt->fetch();
        
        if ($urun) {
            $fiyat = $urun['indirimli_fiyat'] ? $urun['indirimli_fiyat'] : $urun['fiyat'];
            $toplam += $fiyat * $adet;
        }
    }
    return $toplam;
}

function sepetAdet() {
    if (!isset($_SESSION['sepet']) || empty($_SESSION['sepet'])) {
        return 0;
    }
    return array_sum($_SESSION['sepet']);
}

function girisKontrol() {
    return isset($_SESSION['kullanici_id']) && !empty($_SESSION['kullanici_id']);
}

function adminKontrol() {
    return isset($_SESSION['kullanici_rol']) && $_SESSION['kullanici_rol'] === 'admin';
}

function kullaniciCikis() {
    session_destroy();
    header("Location: giris.php");
    exit;
}

function siparisNoOlustur() {
    return 'SP' . date('Ymd') . rand(1000, 9999);
}

function emailGonder($alici, $konu, $mesaj) {
    // Basit email gönderimi - gerçek uygulamada PHPMailer kullanılmalı
    $headers = "From: " . SITE_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($alici, $konu, $mesaj, $headers);
}

function emailKaydet($db, $kullanici_id, $email, $konu, $mesaj) {
    $stmt = $db->prepare("INSERT INTO email_bildirimler (kullanici_id, email, konu, mesaj) 
                         VALUES (?, ?, ?, ?)");
    return $stmt->execute([$kullanici_id, $email, $konu, $mesaj]);
}

function kuponKontrol($db, $kupon_kodu, $kullanici_id = null) {
    $stmt = $db->prepare("SELECT * FROM kuponlar 
                          WHERE kupon_kodu = ? AND aktif = 1 
                          AND (baslangic_tarihi IS NULL OR baslangic_tarihi <= CURDATE())
                          AND (bitis_tarihi IS NULL OR bitis_tarihi >= CURDATE())");
    $stmt->execute([$kupon_kodu]);
    $kupon = $stmt->fetch();
    
    if (!$kupon) {
        return ['hata' => 'Geçersiz kupon kodu'];
    }
    
    // Kullanım limiti kontrolü
    if ($kupon['max_kullanim'] > 0 && $kupon['kullanim_sayisi'] >= $kupon['max_kullanim']) {
        return ['hata' => 'Bu kupon kullanım limitine ulaşmış'];
    }
    
    // Kullanıcının daha önce kullanıp kullanmadığını kontrol et
    if ($kullanici_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM kupon_kullanimlari 
                             WHERE kupon_id = ? AND kullanici_id = ?");
        $stmt->execute([$kupon['id'], $kullanici_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['hata' => 'Bu kuponu daha önce kullandınız'];
        }
    }
    
    return ['basarili' => true, 'kupon' => $kupon];
}

function kuponIndirimiHesapla($kupon, $tutar) {
    if ($tutar < $kupon['min_tutar']) {
        return 0;
    }
    
    if ($kupon['indirim_tipi'] === 'yuzde') {
        return ($tutar * $kupon['indirim_degeri']) / 100;
    } else {
        return min($kupon['indirim_degeri'], $tutar);
    }
}

function urunPuanGuncelle($db, $urun_id) {
    $stmt = $db->prepare("SELECT AVG(puan) as ortalama, COUNT(*) as toplam 
                          FROM yorumlar WHERE urun_id = ? AND onaylandi = 1");
    $stmt->execute([$urun_id]);
    $sonuc = $stmt->fetch();
    
    $ortalama = $sonuc['ortalama'] ? round($sonuc['ortalama'], 1) : 0;
    $toplam = $sonuc['toplam'];
    
    $stmt = $db->prepare("UPDATE urunler SET ortalama_puan = ?, yorum_sayisi = ? WHERE id = ?");
    return $stmt->execute([$ortalama, $toplam, $urun_id]);
}

function tarihFormat($tarih) {
    $ts = strtotime($tarih);
    return date('d.m.Y H:i', $ts);
}

function kargoHesapla($tutar) {
    $stmt = $GLOBALS['db']->query("SELECT ayar_deger FROM site_ayarlari 
                                    WHERE ayar_anahtar = 'ucretsiz_kargo_limiti'");
    $limit = $stmt->fetchColumn() ?: 500;
    
    if ($tutar >= $limit) {
        return 0;
    }
    
    $stmt = $GLOBALS['db']->query("SELECT ayar_deger FROM site_ayarlari 
                                    WHERE ayar_anahtar = 'kargo_ucreti'");
    return $stmt->fetchColumn() ?: 29.90;
}

function yildizlar($puan, $boyut = '16px') {
    $html = '<div class="yildizlar" style="display: inline-flex; gap: 2px; font-size: ' . $boyut . ';">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($puan)) {
            $html .= '<span style="color: #ffc107;">★</span>';
        } elseif ($i <= ceil($puan) && $puan - floor($puan) >= 0.5) {
            $html .= '<span style="color: #ffc107;">★</span>';
        } else {
            $html .= '<span style="color: #e0e0e0;">★</span>';
        }
    }
    $html .= '</div>';
    return $html;
}

function guvenliKartNo($kart_no) {
    return str_repeat('*', 12) . substr($kart_no, -4);
}

function onayKoduOlustur() {
    return bin2hex(random_bytes(16));
}

function hataGoster($mesaj) {
    return '<div class="alert alert-error">' . htmlspecialchars($mesaj) . '</div>';
}

function basariGoster($mesaj) {
    return '<div class="alert alert-success">' . htmlspecialchars($mesaj) . '</div>';
}
?>