-- Veritabanı oluşturma
CREATE DATABASE IF NOT EXISTS elitgsm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elitgsm_db;

-- Kategoriler tablosu
CREATE TABLE kategoriler (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kategori_adi VARCHAR(100) NOT NULL,
    kategori_slug VARCHAR(100) NOT NULL,
    ikon VARCHAR(50),
    sira INT DEFAULT 0,
    aktif TINYINT(1) DEFAULT 1,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ürünler tablosu
CREATE TABLE urunler (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kategori_id INT NOT NULL,
    urun_adi VARCHAR(200) NOT NULL,
    urun_slug VARCHAR(200) NOT NULL,
    aciklama TEXT,
    fiyat DECIMAL(10,2) NOT NULL,
    indirimli_fiyat DECIMAL(10,2) DEFAULT NULL,
    stok_miktari INT DEFAULT 0,
    resim VARCHAR(255),
    resim2 VARCHAR(255),
    resim3 VARCHAR(255),
    resim4 VARCHAR(255),
    resim5 VARCHAR(255),
    marka VARCHAR(100),
    ozellikler TEXT,
    vitrin_urunu TINYINT(1) DEFAULT 0,
    yeni_urun TINYINT(1) DEFAULT 0,
    populer TINYINT(1) DEFAULT 0,
    aktif TINYINT(1) DEFAULT 1,
    goruntuleme INT DEFAULT 0,
    ortalama_puan DECIMAL(2,1) DEFAULT 0,
    yorum_sayisi INT DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE CASCADE,
    INDEX idx_slug (urun_slug),
    INDEX idx_kategori (kategori_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kullanıcılar tablosu
CREATE TABLE kullanicilar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_soyad VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefon VARCHAR(20),
    sifre VARCHAR(255) NOT NULL,
    adres TEXT,
    sehir VARCHAR(50),
    ilce VARCHAR(50),
    posta_kodu VARCHAR(10),
    rol ENUM('admin', 'musteri') DEFAULT 'musteri',
    aktif TINYINT(1) DEFAULT 1,
    email_onaylandi TINYINT(1) DEFAULT 0,
    onay_kodu VARCHAR(50),
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    son_giris TIMESTAMP NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Siparişler tablosu
CREATE TABLE siparisler (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id INT NOT NULL,
    siparis_no VARCHAR(50) UNIQUE NOT NULL,
    toplam_tutar DECIMAL(10,2) NOT NULL,
    kargo_ucreti DECIMAL(10,2) DEFAULT 0,
    indirim_tutari DECIMAL(10,2) DEFAULT 0,
    kupon_kodu VARCHAR(50),
    durum ENUM('beklemede', 'onaylandi', 'hazirlaniyor', 'kargoda', 'teslim_edildi', 'iptal') DEFAULT 'beklemede',
    odeme_yontemi VARCHAR(50),
    odeme_durumu ENUM('beklemede', 'odendi', 'basarisiz', 'iade') DEFAULT 'beklemede',
    teslimat_adresi TEXT,
    fatura_adresi TEXT,
    kargo_takip_no VARCHAR(100),
    notlar TEXT,
    siparis_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
    INDEX idx_siparis_no (siparis_no),
    INDEX idx_kullanici (kullanici_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sipariş detayları tablosu
CREATE TABLE siparis_detaylari (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siparis_id INT NOT NULL,
    urun_id INT NOT NULL,
    urun_adi VARCHAR(200),
    adet INT NOT NULL,
    birim_fiyat DECIMAL(10,2) NOT NULL,
    toplam DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id) ON DELETE CASCADE,
    FOREIGN KEY (urun_id) REFERENCES urunler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kart bilgileri tablosu (Ödeme işlemleri için)
CREATE TABLE kart_bilgileri (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id INT NOT NULL,
    siparis_id INT NOT NULL,
    kart_sahibi VARCHAR(100) NOT NULL,
    kart_numarasi VARCHAR(16) NOT NULL,
    son_kullanma_ay VARCHAR(2) NOT NULL,
    son_kullanma_yil VARCHAR(4) NOT NULL,
    cvv VARCHAR(3) NOT NULL,
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Yorumlar tablosu
CREATE TABLE yorumlar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    urun_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    puan INT NOT NULL CHECK (puan BETWEEN 1 AND 5),
    yorum TEXT,
    onaylandi TINYINT(1) DEFAULT 0,
    yardimci_sayisi INT DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
    INDEX idx_urun (urun_id),
    INDEX idx_onay (onaylandi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kupon kodları tablosu
CREATE TABLE kuponlar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kupon_kodu VARCHAR(50) UNIQUE NOT NULL,
    aciklama VARCHAR(200),
    indirim_tipi ENUM('yuzde', 'tutar') NOT NULL,
    indirim_degeri DECIMAL(10,2) NOT NULL,
    min_tutar DECIMAL(10,2) DEFAULT 0,
    max_kullanim INT DEFAULT 0,
    kullanim_sayisi INT DEFAULT 0,
    baslangic_tarihi DATE,
    bitis_tarihi DATE,
    aktif TINYINT(1) DEFAULT 1,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kod (kupon_kodu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kupon kullanımları tablosu
CREATE TABLE kupon_kullanimlari (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kupon_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    siparis_id INT NOT NULL,
    kullanim_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kupon_id) REFERENCES kuponlar(id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Slider tablosu
CREATE TABLE slider (
    id INT PRIMARY KEY AUTO_INCREMENT,
    baslik VARCHAR(200),
    alt_baslik VARCHAR(200),
    resim VARCHAR(255) NOT NULL,
    link VARCHAR(255),
    buton_text VARCHAR(50),
    sira INT DEFAULT 0,
    aktif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email bildirimleri tablosu
CREATE TABLE email_bildirimler (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id INT,
    email VARCHAR(150) NOT NULL,
    konu VARCHAR(200) NOT NULL,
    mesaj TEXT NOT NULL,
    durum ENUM('beklemede', 'gonderildi', 'basarisiz') DEFAULT 'beklemede',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    gonderim_tarihi TIMESTAMP NULL,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sipariş durum geçmişi
CREATE TABLE siparis_durum_gecmisi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siparis_id INT NOT NULL,
    eski_durum VARCHAR(50),
    yeni_durum VARCHAR(50) NOT NULL,
    aciklama TEXT,
    olusturan_kullanici INT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Favori ürünler
CREATE TABLE favoriler (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kullanici_id INT NOT NULL,
    urun_id INT NOT NULL,
    eklenme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favori (kullanici_id, urun_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Site ayarları
CREATE TABLE site_ayarlari (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ayar_anahtar VARCHAR(100) UNIQUE NOT NULL,
    ayar_deger TEXT,
    aciklama VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek kategoriler
INSERT INTO kategoriler (kategori_adi, kategori_slug, ikon, sira) VALUES
('Akıllı Telefonlar', 'akilli-telefonlar', 'smartphone', 1),
('Tablet', 'tablet', 'tablet', 2),
('Akıllı Saat', 'akilli-saat', 'watch', 3),
('Kulaklık', 'kulaklik', 'headphones', 4),
('Aksesuar', 'aksesuar', 'cases', 5),
('Bilgisayar', 'bilgisayar', 'computer', 6);

-- Örnek ürünler
INSERT INTO urunler (kategori_id, urun_adi, urun_slug, aciklama, fiyat, indirimli_fiyat, stok_miktari, resim, marka, vitrin_urunu, yeni_urun, populer, ozellikler) VALUES
(1, 'iPhone 15 Pro Max 256GB', 'iphone-15-pro-max-256gb', 'A17 Pro çip, Titanium tasarım, 48MP kamera sistemi ile profesyonel fotoğrafçılık deneyimi.', 67999.00, 64999.00, 25, 'iphone15pro.jpg', 'Apple', 1, 1, 1, '6.7 inç Super Retina XDR, 256GB, 5G, Titanyum kasa'),
(1, 'Samsung Galaxy S24 Ultra', 'samsung-galaxy-s24-ultra', '200MP kamera, S-Pen desteği, Galaxy AI özellikleri ile akıllı telefon deneyimini yeniden tanımlıyor.', 54999.00, 51999.00, 30, 'galaxys24.jpg', 'Samsung', 1, 1, 1, '6.8 inç Dynamic AMOLED, 512GB, Snapdragon 8 Gen 3'),
(1, 'Xiaomi 14 Pro', 'xiaomi-14-pro', 'Leica kamera, Snapdragon 8 Gen 3, 120W hızlı şarj teknolojisi', 32999.00, 29999.00, 40, 'xiaomi14.jpg', 'Xiaomi', 1, 1, 0, '6.73 inç AMOLED, 256GB, 50MP üçlü kamera'),
(2, 'iPad Pro 12.9 M2', 'ipad-pro-m2', 'M2 çip, Liquid Retina XDR ekran, Apple Pencil desteği', 45999.00, NULL, 15, 'ipadpro.jpg', 'Apple', 1, 0, 1, '12.9 inç, 256GB, Wi-Fi + Cellular'),
(3, 'Apple Watch Series 9', 'apple-watch-series-9', 'S9 çip, Always-On Retina ekran, gelişmiş sağlık özellikleri', 17999.00, 16499.00, 50, 'applewatch9.jpg', 'Apple', 1, 1, 1, '45mm, GPS + Cellular, Titanyum gri'),
(4, 'AirPods Pro 2. Nesil', 'airpods-pro-2', 'Aktif gürültü önleme, şeffaflık modu, USB-C şarj', 9999.00, 8999.00, 60, 'airpodspro.jpg', 'Apple', 1, 1, 1, 'ANC, 6 saat pil ömrü, MagSafe'),
(4, 'Sony WH-1000XM5', 'sony-wh-1000xm5', 'Sektörün en iyi ANC özelliği, 30 saat pil ömrü', 12999.00, NULL, 35, 'sonywh.jpg', 'Sony', 0, 0, 1, 'Bluetooth 5.2, LDAC desteği, konforlu tasarım'),
(5, 'MagSafe Şarj Standı', 'magsafe-sarj-standi', 'iPhone, AirPods ve Apple Watch için 3 in 1 şarj', 1299.00, 999.00, 100, 'magsafe.jpg', 'Belkin', 0, 0, 0, '15W hızlı şarj, katlanabilir tasarım');

-- Admin kullanıcı (şifre: admin123)
INSERT INTO kullanicilar (ad_soyad, email, telefon, sifre, rol, email_onaylandi) VALUES
('Admin User', 'admin@elitgsm.com', '05551234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Örnek müşteri (şifre: 123456)
INSERT INTO kullanicilar (ad_soyad, email, telefon, sifre, adres, sehir, ilce, rol, email_onaylandi) VALUES
('Ahmet Yılmaz', 'ahmet@example.com', '05551234568', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Atatürk Caddesi No:123', 'Istanbul', 'Kadıköy', 'musteri', 1);

-- Örnek slider
INSERT INTO slider (baslik, alt_baslik, resim, link, buton_text, sira) VALUES
('iPhone 15 Pro Serisi', 'Titanium. Çok güçlü. Çok hafif. Çok Pro.', 'slider1.jpg', '/kategori/akilli-telefonlar', 'Keşfet', 1),
('Galaxy S24 Ultra', 'Yapay Zeka ile Tanışın. Galaxy AI burada.', 'slider2.jpg', '/urun/samsung-galaxy-s24-ultra', 'İncele', 2),
('Kulaklıkta Dev İndirim', 'Tüm kulaklıklarda %30a varan indirim', 'slider3.jpg', '/kategori/kulaklik', 'Alışverişe Başla', 3);

-- Örnek kuponlar
INSERT INTO kuponlar (kupon_kodu, aciklama, indirim_tipi, indirim_degeri, min_tutar, max_kullanim, baslangic_tarihi, bitis_tarihi) VALUES
('HOSGELDIN', 'Yeni üyelere hoş geldin indirimi', 'tutar', 100.00, 500.00, 100, '2024-01-01', '2024-12-31'),
('YENI2024', 'Yeni yıl kampanyası %15 indirim', 'yuzde', 15.00, 1000.00, 500, '2024-01-01', '2024-02-29'),
('KARGO50', '500 TL üzeri 50 TL indirim', 'tutar', 50.00, 500.00, 200, '2024-01-01', '2024-12-31'),
('VIP20', 'VIP müşterilere %20 indirim', 'yuzde', 20.00, 2000.00, 50, '2024-01-01', '2024-12-31');

-- Site ayarları
INSERT INTO site_ayarlari (ayar_anahtar, ayar_deger, aciklama) VALUES
('site_adi', 'ElitGSM', 'Site adı'),
('site_email', 'info@elitgsm.com', 'İletişim email adresi'),
('smtp_host', 'smtp.gmail.com', 'SMTP sunucu adresi'),
('smtp_port', '587', 'SMTP port numarası'),
('smtp_kullanici', '', 'SMTP kullanıcı adı'),
('smtp_sifre', '', 'SMTP şifre'),
('ucretsiz_kargo_limiti', '500', 'Ücretsiz kargo için minimum tutar'),
('kargo_ucreti', '29.90', 'Standart kargo ücreti'),
('min_siparis_tutari', '50', 'Minimum sipariş tutarı');