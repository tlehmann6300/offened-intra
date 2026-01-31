<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> - Willkommen</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/img/cropped_maskottchen_32x32.webp">
    
    <!-- Performance optimizations: Preconnect to CDNs for faster resource loading on 3G -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    
    <!-- Preload critical above-the-fold resources -->
    <link rel="preload" href="<?= SITE_URL ?>/assets/css/theme.min.css" as="style">
    <link rel="preload" href="<?= SITE_URL ?>/assets/img/ibc_logo_original.webp" as="image">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome - Deferred for better above-the-fold performance -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/assets/css/theme.min.css" rel="stylesheet">
    
    <style>
        .landing-hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #1a1f3a 0%, #2d3561 30%, #3b8dd6 100%);
            position: relative;
            overflow: hidden;
        }
        
        .landing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('<?= SITE_URL ?>/assets/img/awp_ibc_foto_zuhoeren.webp');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: 0;
        }
        
        .landing-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(124, 58, 237, 0.2) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(59, 141, 214, 0.2) 0%, transparent 50%);
            z-index: 0;
        }
        
        .landing-hero-content {
            position: relative;
            z-index: 1;
        }
        
        .logo-large {
            max-width: 300px;
            margin-bottom: 2rem;
            filter: drop-shadow(0 15px 40px rgba(0,0,0,0.4));
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: #ffffff; /* Fallback for older browsers */
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }
        
        /* Support for older browsers */
        @supports not (background-clip: text) {
            .hero-title {
                color: #ffffff !important;
            }
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            color: rgba(255,255,255,0.95);
            margin-bottom: 3rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            line-height: 1.6;
        }
        
        .btn-cta {
            padding: 1.2rem 3.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            color: #3b8dd6;
            border: none;
            border-radius: 50px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-cta::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(59, 141, 214, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-cta:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .btn-cta:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            color: #2d3561;
        }
        
        .features-section {
            padding: 6rem 0;
            background: linear-gradient(180deg, #ffffff 0%, #f5f7fa 100%);
        }
        
        .feature-card {
            padding: 2.5rem;
            text-align: center;
            border-radius: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(59, 141, 214, 0.1);
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3b8dd6 0%, #7c3aed 100%);
            transform: scaleX(0);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 60px -10px rgba(59, 141, 214, 0.3);
            border-color: rgba(59, 141, 214, 0.2);
        }
        
        .feature-icon {
            font-size: 3.5rem;
            color: #3b8dd6; /* Fallback for older browsers */
            background: linear-gradient(135deg, #3b8dd6 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @supports not (background-clip: text) {
            .feature-icon {
                color: #3b8dd6 !important;
            }
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.2) rotate(5deg);
        }
        
        .feature-card h3 {
            color: #1a1f3a;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .features-section .display-5 {
            color: #1a1f3a; /* Fallback for older browsers */
            background: linear-gradient(135deg, #1a1f3a 0%, #3b8dd6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        @supports not (background-clip: text) {
            .features-section .display-5 {
                color: #1a1f3a !important;
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-subtitle {
                font-size: 1.2rem;
            }
            .logo-large {
                max-width: 200px;
            }
            .btn-cta {
                padding: 1rem 2.5rem;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center landing-hero-content">
                    <!-- IBC Logo - Critical above-the-fold image -->
                    <img src="<?= SITE_URL ?>/assets/img/ibc_logo_original.webp" 
                         alt="IBC Logo" 
                         class="logo-large"
                         width="300"
                         height="300">
                    
                    <!-- Hero Title -->
                    <h1 class="hero-title">
                        Institut für Business Consulting e.V.
                    </h1>
                    
                    <!-- Hero Subtitle -->
                    <p class="hero-subtitle">
                        Willkommen im Mitgliederbereich des IBC-Intra.<br>
                        Vernetzen Sie sich mit Alumni, entdecken Sie Projekte und bleiben Sie informiert.
                    </p>
                    
                    <!-- CTA Button -->
                    <a href="index.php?page=login" class="btn btn-cta">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Mitglieds-Login
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h2 class="display-5 fw-bold mb-3">Was bietet das IBC-Intra?</h2>
                    <p class="lead text-muted">Ihr zentraler Hub für Networking, Projekte und Information</p>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="h5 mb-3">Alumni-Netzwerk</h3>
                        <p class="text-muted">
                            Vernetzen Sie sich mit Alumni und aktiven Mitgliedern unserer Community.
                        </p>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3 class="h5 mb-3">Projekt-Marktplatz</h3>
                        <p class="text-muted">
                            Entdecken Sie spannende Consulting-Projekte und bringen Sie Ihre Expertise ein.
                        </p>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <h3 class="h5 mb-3">News & Updates</h3>
                        <p class="text-muted">
                            Bleiben Sie über aktuelle Entwicklungen und Neuigkeiten informiert.
                        </p>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="h5 mb-3">Events</h3>
                        <p class="text-muted">
                            Nehmen Sie an Workshops, Networking-Events und Weiterbildungen teil.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer mt-0">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h5 class="text-white mb-3">IBC-Intra</h5>
                    <p class="text-white-50">
                        Internes Portal des Instituts für Business Consulting e.V.
                    </p>
                </div>
                
                <div class="col-md-6 mb-4">
                    <h5 class="text-white mb-3">Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="https://www.business-consulting.de" target="_blank" class="text-white-50 text-decoration-none">Öffentliche Website</a></li>
                        <li><a href="index.php?page=impressum" class="text-white-50 text-decoration-none">Impressum</a></li>
                        <li><a href="index.php?page=datenschutz" class="text-white-50 text-decoration-none">Datenschutz</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="row mt-4 pt-4 border-top border-secondary">
                <div class="col-12 text-center">
                    <p class="text-white-50 mb-0">
                        &copy; <?php echo date('Y'); ?> Institut für Business Consulting e.V.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
