<?php
require_once 'config.php';

// Zaten giriş yapmışsa
if (girisKontrol()) {
    header("Location: hesabim.php");
    exit;
}

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = temizle($_POST['email']);
    $sifre = $_POST['sifre'];
    $beni_hatirla = isset($_POST['beni_hatirla']);
    
    if (empty($email) || empty($sifre)) {
        $hata = 'Lütfen email ve şifrenizi girin!';
    } else {
        $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE email = ? AND aktif = 1");
        $stmt->execute([$email]);
        $kullanici = $stmt->fetch();
        
        if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
            // Giriş başarılı
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['kullanici_ad'] = $kullanici['ad_soyad'];
            $_SESSION['kullanici_email'] = $kullanici['email'];
            $_SESSION['kullanici_rol'] = $kullanici['rol'];
            
            // Son giriş zamanını güncelle
            $stmt = $db->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
            $stmt->execute([$kullanici['id']]);
            
            // Beni hatırla cookie
            if ($beni_hatirla) {
                setcookie('remember_token', base64_encode($email), time() + (86400 * 30), "/");
            }
            
            // Yönlendirme
            if ($kullanici['rol'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: hesabim.php");
            }
            exit;
        } else {
            $hata = 'Email veya şifre hatalı!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - <?php echo SITE_NAME; ?></title>
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
            max-width: 450px;
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
        
        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            font-size: 14px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
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
        
        .demo-info {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .demo-info strong {
            display: block;
            margin-bottom: 8px;
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
            
            .form-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .checkbox-group label {
                font-size: 13px;
            }
            
            .forgot-password {
                font-size: 13px;
            }
            
            .form-footer {
                font-size: 14px;
                padding-top: 20px;
            }
            
            .demo-info {
                padding: 12px;
                font-size: 12px;
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
            
            .demo-info {
                font-size: 11px;
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
            <h1 class="form-title">Hoş Geldiniz</h1>
            <p class="form-subtitle">Hesabınıza giriş yapın</p>
            
            <?php if ($hata): ?>
                <div class="alert alert-error">❌ <?php echo $hata; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" 
                           placeholder="ornek@email.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="sifre" class="form-input" 
                           placeholder="Şifreniz" required>
                </div>
                
                <div class="form-row">
                    <div class="checkbox-group">
                        <input type="checkbox" name="beni_hatirla" id="beni_hatirla">
                        <label for="beni_hatirla">Beni Hatırla</label>
                    </div>
                    <a href="sifre-sifirlama.php" class="forgot-password">Şifremi Unuttum</a>
                </div>
                
                <button type="submit" class="btn-submit">
                    Giriş Yap
                </button>
            </form>
            
            <div class="form-footer">
                Hesabınız yok mu? <a href="kayit.php">Hemen Üye Ol</a>
            </div>
        </div>
        
        <div class="demo-info">
            <strong>🎯 Demo Hesaplar:</strong>
            Admin: admin@elitgsm.com / admin123<br>
            Müşteri: ahmet@example.com / 123456
        </div>
        
        <div class="back-home">
            <a href="index.php">← Ana Sayfaya Dön</a>
        </div>
    </div>
</body>
</html>