<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'de'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php 
    // Generate and store CSRF token for logged-in users
    if (isset($auth) && $auth->isLoggedIn()) {
        $csrfToken = $auth->getCsrfToken();
        if (!$csrfToken) {
            $csrfToken = $auth->generateCsrfToken();
        }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <title><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> - Intranet</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/img/cropped_maskottchen_32x32.webp">
    
    <!-- Bootstrap CSS 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Fonts -->
    <link href="<?= SITE_URL ?>/assets/css/fonts.css" rel="stylesheet">
    
    <!-- Custom CSS - Minimalistisches IBC-Branding -->
    <link href="<?= SITE_URL ?>/assets/css/theme.css" rel="stylesheet">
    
    <!-- Canvas Confetti Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    
    <!-- Global JavaScript Configuration -->
    <script>
        window.appConfig = {
            baseUrl: '<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>'
        };
        
        window.ibcConfig = {
            baseUrl: '<?= SITE_URL ?>',
            apiUrl: '<?= API_URL ?>',
            <?php if ($auth->isLoggedIn()): ?>
            csrfToken: '<?php echo htmlspecialchars($auth->getCsrfToken() ?? $auth->generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>'
            <?php else: ?>
            csrfToken: null
            <?php endif; ?>
        };
    </script>
</head>
<?php
// Load header helper functions
require_once BASE_PATH . '/src/HeaderHelpers.php';

// Initialize edit mode state
$editModeActive = $_SESSION['edit_mode_active'] ?? false;
$editModeClass = $editModeActive ? ' edit-mode-enabled' : '';

// Check for helper update notifications
$hasHelperUpdate = hasHelperUpdate($pdo, $auth);

// Get user display name
$displayName = getUserDisplayName();
?>
<body class="intranet-body<?php echo $editModeClass; ?>">
    
    <!-- Edit Mode Banner -->
    <div id="edit-mode-banner" class="edit-mode-banner <?php echo $editModeActive ? 'show' : ''; ?>">
        <i class="fas fa-pen-to-square me-2"></i>
        <span>Bearbeitungsmodus aktiv - Änderungen werden sofort gespeichert</span>
    </div>
    
    <!-- Intelligente Top-Navbar - Solid IBC Blue (Breakpoint: lg) -->
    <nav class="navbar navbar-expand-lg sticky-top bg-ibc-blue">
        <div class="container-fluid px-3 px-lg-4 navbar-container-constrained">
            <!-- Logo/Brand -->
            <a class="navbar-brand" href="index.php?page=home">
                <img src="<?= SITE_URL ?>/assets/img/ibc_logo_original_navbar.webp" 
                     alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> Logo" 
                     class="navbar-logo img-fluid" 
                     id="navbar-logo">
                <span id="navbar-fallback-text" style="display:none; color: #20234A; font-weight: 700;">JE Alumni</span>
            </a>
            
            <!-- Navbar Toggler für Mobile (lg Breakpoint) -->
            <button class="navbar-toggler" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" 
                    aria-expanded="false" 
                    aria-label="Navigation umschalten">
                <div class="toggler-icon">
                    <span class="toggler-bar"></span>
                    <span class="toggler-bar"></span>
                    <span class="toggler-bar"></span>
                </div>
            </button>
            
            <!-- Navbar Content - Intelligente, ausfahrbare Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php
                $currentLang = $_SESSION['lang'] ?? 'de';
                $getParamsWithoutLang = $_GET;
                unset($getParamsWithoutLang['lang']);
                $queryStringWithoutLang = !empty($getParamsWithoutLang) ? '&' . http_build_query($getParamsWithoutLang) : '';
                ?>
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Home Link -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=home">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </li>
                    
                    <?php if ($auth->isLoggedIn()): ?>
                        <!-- Elegante, ausfahrbare Suchleiste innerhalb der Navbar -->
                        <li class="nav-item me-3" id="global-search-container">
                            <div class="navbar-search-wrapper">
                                <?php $searchInstance = 'navbar'; include BASE_PATH . '/templates/components/search_component.php'; ?>
                            </div>
                        </li>
                        
                        <!-- Admin/Vorstand Links -->
                        <?php if ($auth->can('edit_news') || $auth->can('edit_projects')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog me-2"></i>Verwaltung
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="index.php?page=admin_dashboard"><i class="fas fa-chart-line me-2"></i>Admin Dashboard</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if ($auth->can('edit_news')): ?>
                                        <li><a class="dropdown-item" href="index.php?page=news_editor"><i class="fas fa-newspaper me-2"></i>News Editor</a></li>
                                    <?php endif; ?>
                                    <?php if ($auth->can('edit_projects')): ?>
                                        <li><a class="dropdown-item" href="index.php?page=project_management"><i class="fas fa-project-diagram me-2"></i>Projekt-Verwaltung</a></li>
                                    <?php endif; ?>
                                    <?php if ($auth->can('edit_events')): ?>
                                        <li><a class="dropdown-item" href="index.php?page=event_management"><i class="fas fa-calendar-alt me-2"></i>Event-Verwaltung</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Navigation Links für alle Benutzer -->
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="index.php?page=events">
                                <i class="fas fa-calendar-alt me-2"></i>Events
                                <?php if ($hasHelperUpdate): ?>
                                    <span class="badge rounded-pill bg-danger ms-1" id="eventsBadge">1</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=projects">
                                <i class="fas fa-briefcase me-2"></i>Projekte
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=inventory">
                                <i class="fas fa-boxes me-2"></i>Inventar
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=alumni_database">
                                <i class="fas fa-user-graduate me-2"></i>Alumni Datenbank
                            </a>
                        </li>
                        
                        <!-- Notification Bell -->
                        <li class="nav-item dropdown ms-lg-3 position-relative" id="notificationBellContainer">
                            <button class="btn btn-outline-secondary position-relative" 
                                    type="button" 
                                    id="notificationBell" 
                                    aria-label="Benachrichtigungen">
                                <i class="fas fa-bell"></i>
                                <?php if ($hasHelperUpdate): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge">
                                        1
                                        <span class="visually-hidden">neue Benachrichtigungen</span>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <!-- Notification Panel -->
                            <div class="dropdown-menu dropdown-menu-end p-0" id="notificationPanel" style="width: 400px; max-height: 500px; display: none;">
                                <div class="card border-0">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Neue Helfer-Gesuche</h6>
                                    </div>
                                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;" id="notificationContent">
                                        <div class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Wird geladen...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <!-- User Menu Dropdown -->
                        <li class="nav-item dropdown ms-lg-3">
                            <button class="btn btn-outline-primary dropdown-toggle" 
                                    type="button" 
                                    id="userActionsDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false" 
                                    aria-label="Benutzer-Menü">
                                <i class="fas fa-user me-2"></i>
                                <span class="d-none d-lg-inline">
                                    <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="d-inline d-lg-none">Menü</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userActionsDropdown">
                                <li class="dropdown-header">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <li>
                                    <a class="dropdown-item" href="index.php?page=settings">
                                        <i class="fas fa-cog me-2"></i>Einstellungen
                                    </a>
                                </li>
                                
                                <li><hr class="dropdown-divider"></li>
                                
                                <li class="dropdown-header">
                                    <i class="fas fa-language me-2"></i>Sprache
                                </li>
                                <li>
                                    <a class="dropdown-item lang-item" href="?lang=de<?php echo $queryStringWithoutLang; ?>" <?php echo $currentLang === 'de' ? 'aria-current="true"' : ''; ?>>
                                        <img src="<?= SITE_URL ?>/assets/img/flags/de.svg" alt="Deutsch" class="flag-img-sm me-2">
                                        <span>Deutsch</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item lang-item" href="?lang=en<?php echo $queryStringWithoutLang; ?>" <?php echo $currentLang === 'en' ? 'aria-current="true"' : ''; ?>>
                                        <img src="<?= SITE_URL ?>/assets/img/flags/gb.svg" alt="English" class="flag-img-sm me-2">
                                        <span>English</span>
                                    </a>
                                </li>
                                
                                <li><hr class="dropdown-divider"></li>
                                
                                <li>
                                    <a class="dropdown-item text-danger" href="index.php?page=logout">
                                        <i class="fas fa-sign-out-alt me-2"></i>Abmelden
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Language Switcher für nicht eingeloggte Benutzer -->
                        <li class="nav-item dropdown ms-lg-3">
                            <button class="btn lang-toggle dropdown-toggle" 
                                    type="button" 
                                    id="langDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false" 
                                    aria-label="Sprache wählen">
                                <?php
                                $flagUrl = $currentLang === 'de' 
                                    ? SITE_URL . '/assets/img/flags/de.svg' 
                                    : SITE_URL . '/assets/img/flags/gb.svg';
                                $flagAlt = $currentLang === 'de' ? 'Deutsch' : 'English';
                                ?>
                                <img src="<?php echo htmlspecialchars($flagUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $flagAlt; ?>" class="flag-img">
                                <span class="ms-2"><?php echo strtoupper($currentLang); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end lang-dropdown" aria-labelledby="langDropdown">
                                <li>
                                    <a class="dropdown-item lang-item" href="?lang=de<?php echo $queryStringWithoutLang; ?>" <?php echo $currentLang === 'de' ? 'aria-current="true"' : ''; ?>>
                                        <img src="<?= SITE_URL ?>/assets/img/flags/de.svg" alt="Deutsch" class="flag-img">
                                        <span>Deutsch</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item lang-item" href="?lang=en<?php echo $queryStringWithoutLang; ?>" <?php echo $currentLang === 'en' ? 'aria-current="true"' : ''; ?>>
                                        <img src="<?= SITE_URL ?>/assets/img/flags/gb.svg" alt="English" class="flag-img">
                                        <span>English</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <main class="main-content mt-4 mt-lg-5">
