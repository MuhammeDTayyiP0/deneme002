<?php
require_once 'config.php';

$hata = '';
$basari = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = temizle($_POST['ad_soyad']);
    $email = temizle($_POST['email']);
    $telefon = temizle($_POST['telefon']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    
    // Validasyon
    if (empty($ad_soyad) || empty($email) || empty($telefon) || empty($sifre)) {
        $hata = 'Lütfen tüm alanları doldurun!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir email adresi girin!';
    } elseif (strlen($sifre) < 6) {
        $hata = 'Şifre en az 6 karakter olmalıdır!';
    } elseif ($sifre !== $sifre_tekrar) {
        $hata = 'Şifreler eşleşmiyor!';
    } else {
        // Email kontrolü
        $stmt = $db->prepare("SELECT id FROM kullanicilar WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $hata = 'Bu email adresi zaten kayıtlı!';
        } else {
            // Kayıt işlemi
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $onay_kodu = onayKoduOlustur();
            
            $stmt = $db->prepare("INSERT INTO kullanicilar (ad_soyad, email, telefon, sifre, onay_kodu) 
                                 VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$ad_soyad, $email, $telefon, $sifre_hash, $onay_kodu])) {
                $kullanici_id = $db->lastInsertId();
                
                // Hoş geldin emaili gönder
                $konu = "Hoş Geldiniz - " . SITE_NAME;
                $mesaj = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Merhaba {$ad_soyad},</h2>
                    <p>{SITE_NAME} ailesine hoş geldiniz!</p>
                    <p>Hesabınız başarıyla oluşturuldu.</p>
                    <p>Hemen alışverişe başlayabilirsiniz!</p>
                    <p><strong>İlk alışverişinizde HOSGELDIN kupon koduyla 100₺ indirim!</strong></p>
                    <br>
                    <p>Teşekkürler,<br>{SITE_NAME}</p>
                </body>
                </html>
                ";
                
                emailKaydet($db, $kullanici_id, $email, $konu, $mesaj);
                
                $basari = 'Kayıt başarılı! Giriş yapabilirsiniz.';
                
                // 2 saniye sonra giriş sayfasına yönlendir
                header("refresh:2;url=giris.php");
            } else {
                $hata = 'Kayıt sırasında bir hata oluştu!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 48px;
            font-weight: 900;
            color: white;
            text-shadow: 0 4px 20px rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }
        
        .logo-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }
        
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #1a1a1a;
        }
        
        .form-subtitle {
            color: #666;
            margin-bottom: 30px;
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
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
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
        
        .form-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-home a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-home a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .logo {
                font-size: 32px;
            }
            
            .logo-subtitle {
                font-size: 14px;
            }
            
            .form-card {
                padding: 30px 20px;
            }
            
            .form-title {
                font-size: 24px;
            }
            
            .form-subtitle {
                font-size: 14px;
            }
            
            .form-label {
                font-size: 14px;
            }
            
            .form-input {
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .btn-submit {
                padding: 14px;
                font-size: 14px;
            }
            
            .password-strength {
                font-size: 12px;
            }
            
            .form-footer {
                font-size: 14px;
            }
            
            .back-home {
                margin-top: 15px;
            }
            
            .back-home a {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .container {
                max-width: 100%;
            }
            
            .logo-section {
                margin-bottom: 30px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .form-card {
                padding: 25px 15px;
                border-radius: 16px;
            }
            
            .form-title {
                font-size: 22px;
            }
            
            .alert {
                padding: 12px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <div class="logo">ElitGSM</div>
            <div class="logo-subtitle">Teknoloji Alışverişin Adresi</div>
        </div>
        
        <div class="form-card">
            <h1 class="form-title">Hesap Oluştur</h1>
            <p class="form-subtitle">Hemen üye ol, alışverişe başla!</p>
            
            <?php if ($hata): ?>
                <div class="alert alert-error">❌ <?php echo $hata; ?></div>
            <?php endif; ?>
            
            <?php if ($basari): ?>
                <div class="alert alert-success">✓ <?php echo $basari; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" name="ad_soyad" class="form-input" 
                           placeholder="Adınız ve soyadınız" required
                           value="<?php echo isset($_POST['ad_soyad']) ? htmlspecialchars($_POST['ad_soyad']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" 
                           placeholder="ornek@email.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="telefon" class="form-input" 
                           placeholder="0555 123 45 67" required
                           value="<?php echo isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="sifre" id="sifre" class="form-input" 
                           placeholder="En az 6 karakter" required
                           onkeyup="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthBar"></div>
                        </div>
                        <span id="strengthText"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Şifre Tekrar</label>
                    <input type="password" name="sifre_tekrar" class="form-input" 
                           placeholder="Şifrenizi tekrar girin" required>
                </div>
                
                <button type="submit" class="btn-submit">
                    Kayıt Ol
                </button>
            </form>
            
            <div class="form-footer">
                Zaten hesabınız var mı? <a href="giris.php">Giriş Yap</a>
            </div>
        </div>
        
        <div class="back-home">
            <a href="index.php">← Ana Sayfaya Dön</a>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            const colors = ['#d32f2f', '#ff9800', '#ffc107', '#8bc34a', '#4caf50'];
            const texts = ['Çok Zayıf', 'Zayıf', 'Orta', 'İyi', 'Güçlü'];
            const widths = ['20%', '40%', '60%', '80%', '100%'];
            
            bar.style.width = widths[strength];
            bar.style.background = colors[strength];
            text.textContent = texts[strength];
            text.style.color = colors[strength];
        }
    </script>
</body>
</html>