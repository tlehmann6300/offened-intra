<?php
// Handle AJAX request for role selection BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_role') {
    header('Content-Type: application/json');
    
    $role = $_POST['role'] ?? '';
    
    // Validate role
    if (!in_array($role, ['alumni', 'mitglied'], true)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Rolle']);
        exit;
    }
    
    // Check if user is logged in (auth variable should be available from index.php)
    if (!isset($auth) || !$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
        exit;
    }
    
    // Check if user already has a role other than 'none'
    $currentRole = $auth->getUserRole();
    if ($currentRole && $currentRole !== 'none') {
        echo json_encode(['success' => false, 'message' => 'Rolle wurde bereits festgelegt']);
        exit;
    }
    
    // Update user role
    $userId = $auth->getUserId();
    if ($auth->updateUserRole($userId, $role)) {
        echo json_encode(['success' => true, 'message' => 'Rolle erfolgreich gesetzt']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fehler beim Setzen der Rolle']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'de'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Rolle wählen - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS - Centralized Design System -->
    <link href="<?= SITE_URL ?>/assets/css/theme.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--ibc-blue, #20234A) 0%, var(--ibc-green, #6D9744) 50%, var(--ibc-blue, #20234A) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
            font-family: var(--font, 'Inter', sans-serif);
        }
        
        @keyframes gradientShift {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        .role-selection-container {
            width: 100%;
            max-width: 1000px;
            padding: 40px 20px;
        }
        
        .page-title {
            text-align: center;
            color: white;
            margin-bottom: 20px;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInDown 0.8s ease-out;
        }
        
        .page-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 50px;
            font-size: clamp(1rem, 2vw, 1.25rem);
            animation: fadeInDown 0.8s ease-out 0.2s both;
        }
        
        .role-cards-wrapper {
            /* No longer using CSS Grid - will be replaced with Bootstrap classes in HTML */
            /* Use Bootstrap: <div class="row row-cols-1 row-cols-md-2 g-4"> */
            display: flex;
            flex-wrap: wrap;
            gap: 1.875rem; /* 30px */
            margin-bottom: 1.875rem; /* 30px */
        }
        
        /* Fallback for direct children when not using Bootstrap classes */
        .role-cards-wrapper > * {
            flex: 1 1 18.75rem; /* 300px - converted to rem for consistency */
            min-width: 18.75rem; /* 300px - converted to rem for consistency */
        }
        
        .role-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 3px solid rgba(255, 255, 255, 0.4);
            padding: 50px 40px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out both;
            box-shadow: 0 12px 40px rgba(31, 38, 135, 0.25);
        }
        
        .role-card:nth-child(1) {
            animation-delay: 0.3s;
        }
        
        .role-card:nth-child(2) {
            animation-delay: 0.5s;
        }
        
        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .role-card:hover {
            transform: translateY(-15px) scale(1.05);
            border-color: rgba(109, 151, 68, 0.7);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.5);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .role-card:hover::before {
            opacity: 1;
        }
        
        .role-card:active {
            transform: translateY(-5px) scale(1.01);
        }
        
        .role-icon {
            font-size: 6rem;
            margin-bottom: 30px;
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.2));
        }
        
        .role-card:hover .role-icon {
            transform: scale(1.3) rotate(10deg);
        }
        
        .role-card.alumni .role-icon {
            color: #6D9744;
            text-shadow: 0 4px 12px rgba(109, 151, 68, 0.4);
        }
        
        .role-card.mitglied .role-icon {
            color: #20234A;
            text-shadow: 0 4px 12px rgba(32, 35, 74, 0.4);
        }
        
        .role-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ibc-blue, #20234A);
            margin-bottom: 20px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            letter-spacing: -0.02em;
        }
        
        .role-description {
            color: var(--ibc-text-secondary, #4d5061);
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        .role-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            animation: fadeInUp 0.8s ease-out 0.7s both;
        }
        
        .info-box i {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #FFD700;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Respect user's motion preferences */
        @media (prefers-reduced-motion: reduce) {
            body {
                animation: none;
                background: var(--ibc-blue, #20234A);
            }
            
            .page-title,
            .page-subtitle,
            .role-card,
            .info-box {
                animation: none;
                opacity: 1;
                transform: none;
            }
            
            .role-card:hover {
                transform: none;
            }
        }
        
        @media (max-width: 768px) {
            .role-card {
                padding: 30px 20px;
            }
            
            .role-icon {
                font-size: 3rem;
            }
            
            .role-title {
                font-size: 1.5rem;
            }
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="role-selection-container">
        <h1 class="page-title">Willkommen bei JE Alumni Connect!</h1>
        <p class="page-subtitle">Bitte wählen Sie Ihre Rolle aus, um fortzufahren</p>
        
        <div class="role-cards-wrapper">
            <!-- Alumni Card -->
            <div class="role-card alumni" onclick="selectRole('alumni')">
                <span class="role-badge">Alumni</span>
                <div class="text-center">
                    <div class="role-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h2 class="role-title">Ich bin Alumni</h2>
                    <p class="role-description">
                        Sie haben Ihre aktive Zeit in der Junior Enterprise abgeschlossen und möchten als Alumni weiterhin vernetzt bleiben.
                    </p>
                </div>
            </div>
            
            <!-- Active Member Card -->
            <div class="role-card mitglied" onclick="selectRole('mitglied')">
                <span class="role-badge">Aktiv</span>
                <div class="text-center">
                    <div class="role-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h2 class="role-title">Ich bin aktives Mitglied</h2>
                    <p class="role-description">
                        Sie sind derzeit aktives Mitglied der Junior Enterprise und möchten an Projekten teilnehmen und sich engagieren.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p class="mb-0"><strong>Hinweis:</strong> Diese Wahl ist final und kann nur vom Vorstand geändert werden.</p>
        </div>
    </div>

    <!-- JavaScript functions are now in /assets/js/main.js -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>

