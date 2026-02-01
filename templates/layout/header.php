<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'de'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    
    <!-- Bootstrap 5.3.0 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    
    <!-- Font Awesome 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- Custom CSS (with cache busting) -->
    <?php
    // Cache busting: use file modification time as version
    // This automatically updates when files are rebuilt, forcing browser refresh
    // For very high-traffic sites, consider using a static version number instead
    $cssVersion = file_exists(BASE_PATH . '/assets/css/theme.min.css') 
        ? '?v=' . filemtime(BASE_PATH . '/assets/css/theme.min.css')
        : '?v=' . time();
    $fontsVersion = file_exists(BASE_PATH . '/assets/css/fonts.min.css')
        ? '?v=' . filemtime(BASE_PATH . '/assets/css/fonts.min.css')
        : '?v=' . time();
    ?>
    <link href="<?= SITE_URL ?>/assets/css/fonts.min.css<?= $fontsVersion ?>" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/theme.min.css<?= $cssVersion ?>" rel="stylesheet">
    
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
$editModeClass = $editModeActive ? ' edit-mode-active' : '';

// Check for helper update notifications
$hasHelperUpdate = hasHelperUpdate($pdo, $auth);

// Get user display name
$displayName = getUserDisplayName();
?>
<body class="d-flex flex-column min-vh-100<?php echo $editModeClass; ?>">
    
    <!-- Edit Mode Banner -->
    <?php if ($editModeActive): ?>
    <div class="alert alert-warning mb-0 rounded-0 text-center edit-mode-alert" role="alert">
        <i class="fas fa-pen-to-square me-2"></i>
        <span>Bearbeitungsmodus aktiv - Änderungen werden sofort gespeichert</span>
    </div>
    <?php endif; ?>
    
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <!-- Brand/Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php?page=home">
                <img src="<?= SITE_URL ?>/assets/img/ibc_logo_original_navbar.webp" 
                     alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> Logo" 
                     height="40" 
                     class="d-inline-block align-text-top me-2">
                <span class="fw-bold d-none d-md-inline"><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            
            <!-- Hamburger Toggle Button (Mobile) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($auth->isLoggedIn()): ?>
                    <?php 
                    // Get current page for active link highlighting (from sanitized $page variable)
                    $currentPage = $page ?? 'home';
                    ?>
                    <!-- Left Navigation (Logged In Users) -->
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link<?php echo ($currentPage === 'home') ? ' active' : ''; ?>" href="index.php?page=home">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo (in_array($currentPage, ['events', 'events_modern'], true)) ? ' active' : ''; ?> position-relative" href="index.php?page=events">
                                <i class="fas fa-calendar-alt me-1"></i>Events
                                <?php if ($hasHelperUpdate): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        1
                                        <span class="visually-hidden">neue Benachrichtigungen</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo ($currentPage === 'projects') ? ' active' : ''; ?>" href="index.php?page=projects">
                                <i class="fas fa-briefcase me-1"></i>Projekte
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo ($currentPage === 'inventory') ? ' active' : ''; ?>" href="index.php?page=inventory">
                                <i class="fas fa-boxes me-1"></i>Inventar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo (in_array($currentPage, ['alumni_database', 'alumni', 'alumni_modern'], true)) ? ' active' : ''; ?>" href="index.php?page=alumni_database">
                                <i class="fas fa-user-graduate me-1"></i>Alumni
                            </a>
                        </li>
                        
                        <!-- Admin/Vorstand Links -->
                        <?php if ($auth->can('edit_news') || $auth->can('edit_projects') || $auth->can('edit_events')): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?php echo (in_array($currentPage, ['admin_dashboard', 'news_editor', 'project_management', 'event_management', 'user_management', 'inventory_audit'], true)) ? ' active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog me-1"></i>Verwaltung
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item<?php echo ($currentPage === 'admin_dashboard') ? ' active' : ''; ?>" href="index.php?page=admin_dashboard"><i class="fas fa-chart-line me-2"></i>Admin Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($auth->can('edit_news')): ?>
                                    <li><a class="dropdown-item<?php echo ($currentPage === 'news_editor') ? ' active' : ''; ?>" href="index.php?page=news_editor"><i class="fas fa-newspaper me-2"></i>News Editor</a></li>
                                <?php endif; ?>
                                <?php if ($auth->can('edit_projects')): ?>
                                    <li><a class="dropdown-item<?php echo ($currentPage === 'project_management') ? ' active' : ''; ?>" href="index.php?page=project_management"><i class="fas fa-project-diagram me-2"></i>Projekt-Verwaltung</a></li>
                                <?php endif; ?>
                                <?php if ($auth->can('edit_events')): ?>
                                    <li><a class="dropdown-item<?php echo ($currentPage === 'event_management') ? ' active' : ''; ?>" href="index.php?page=event_management"><i class="fas fa-calendar-alt me-2"></i>Event-Verwaltung</a></li>
                                <?php endif; ?>
                                <?php if ($auth->hasFullAccess()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item<?php echo ($currentPage === 'user_management') ? ' active' : ''; ?>" href="index.php?page=user_management"><i class="fas fa-users-cog me-2"></i>Benutzerverwaltung</a></li>
                                    <li><a class="dropdown-item<?php echo ($currentPage === 'inventory_audit') ? ' active' : ''; ?>" href="index.php?page=inventory_audit"><i class="fas fa-history me-2"></i>Inventar-Audit</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Right Navigation (User Menu) -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Search (Desktop only) -->
                        <li class="nav-item d-none d-lg-block me-2">
                            <div class="navbar-search-wrapper">
                                <?php $searchInstance = 'navbar'; include BASE_PATH . '/templates/components/search_component.php'; ?>
                            </div>
                        </li>
                        
                        <!-- Notification Bell -->
                        <li class="nav-item dropdown me-2">
                            <button class="btn btn-outline-light position-relative" 
                                    type="button" 
                                    id="notificationBell" 
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    aria-label="Benachrichtigungen">
                                <i class="fas fa-bell"></i>
                                <?php if ($hasHelperUpdate): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        1
                                        <span class="visually-hidden">neue Benachrichtigungen</span>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <!-- Notification Dropdown -->
                            <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px; max-height: 400px;">
                                <div class="card border-0">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Benachrichtigungen</h6>
                                    </div>
                                    <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;" id="notificationContent">
                                        <div class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Wird geladen...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" 
                                    type="button" 
                                    id="userDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false">
                                <i class="fas fa-user me-1"></i>
                                <span class="d-none d-md-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="d-inline d-md-none">Menü</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
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
                                <?php
                                $currentLang = $_SESSION['lang'] ?? 'de';
                                $getParamsWithoutLang = $_GET;
                                unset($getParamsWithoutLang['lang']);
                                $queryStringWithoutLang = !empty($getParamsWithoutLang) ? '&' . http_build_query($getParamsWithoutLang) : '';
                                ?>
                                <li>
                                    <a class="dropdown-item" href="?lang=de<?php echo $queryStringWithoutLang; ?>">
                                        <img src="<?= SITE_URL ?>/assets/img/flags/de.svg" alt="Deutsch" width="20" height="15" class="me-2">
                                        Deutsch
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?lang=en<?php echo $queryStringWithoutLang; ?>">
                                        <img src="<?= SITE_URL ?>/assets/img/flags/gb.svg" alt="English" width="20" height="15" class="me-2">
                                        English
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
                    </ul>
                <?php else: ?>
                    <!-- Right Navigation (Not Logged In) -->
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <?php
                            $currentLang = $_SESSION['lang'] ?? 'de';
                            $getParamsWithoutLang = $_GET;
                            unset($getParamsWithoutLang['lang']);
                            $queryStringWithoutLang = !empty($getParamsWithoutLang) ? '&' . http_build_query($getParamsWithoutLang) : '';
                            $flagUrl = $currentLang === 'de' 
                                ? SITE_URL . '/assets/img/flags/de.svg' 
                                : SITE_URL . '/assets/img/flags/gb.svg';
                            $flagAlt = $currentLang === 'de' ? 'Deutsch' : 'English';
                            ?>
                            <button class="btn btn-outline-light dropdown-toggle" 
                                    type="button" 
                                    id="langDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false">
                                <img src="<?php echo htmlspecialchars($flagUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo $flagAlt; ?>" 
                                     width="20" 
                                     height="15" 
                                     class="me-2">
                                <?php echo strtoupper($currentLang); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                                <li>
                                    <a class="dropdown-item" href="?lang=de<?php echo $queryStringWithoutLang; ?>">
                                        <img src="<?= SITE_URL ?>/assets/img/flags/de.svg" alt="Deutsch" width="20" height="15" class="me-2">
                                        Deutsch
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?lang=en<?php echo $queryStringWithoutLang; ?>">
                                        <img src="<?= SITE_URL ?>/assets/img/flags/gb.svg" alt="English" width="20" height="15" class="me-2">
                                        English
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb Navigation -->
    <?php if ($auth->isLoggedIn()): ?>
    <nav aria-label="breadcrumb" class="bg-light border-bottom">
        <div class="container-fluid">
            <ol class="breadcrumb py-2 mb-0">
                <li class="breadcrumb-item"><a href="index.php?page=home"><i class="fas fa-home"></i></a></li>
                <?php
                // Define breadcrumb mappings
                $breadcrumbMap = [
                    'home' => ['Dashboard', 'fas fa-home'],
                    'events' => ['Events', 'fas fa-calendar-alt'],
                    'events_modern' => ['Events', 'fas fa-calendar-alt'],
                    'event_management' => ['Events', 'fas fa-calendar-alt', 'Verwaltung'],
                    'projects' => ['Projekte', 'fas fa-briefcase'],
                    'project_management' => ['Projekte', 'fas fa-briefcase', 'Verwaltung'],
                    'inventory' => ['Inventar', 'fas fa-boxes'],
                    'inventory_config' => ['Inventar', 'fas fa-boxes', 'Konfiguration'],
                    'inventory_audit' => ['Inventar', 'fas fa-boxes', 'Audit'],
                    'alumni_database' => ['Alumni', 'fas fa-user-graduate'],
                    'alumni' => ['Alumni', 'fas fa-user-graduate'],
                    'alumni_modern' => ['Alumni', 'fas fa-user-graduate'],
                    'settings' => ['Einstellungen', 'fas fa-cog'],
                    'admin_dashboard' => ['Verwaltung', 'fas fa-cog', 'Dashboard'],
                    'news_editor' => ['Verwaltung', 'fas fa-cog', 'News Editor'],
                    'user_management' => ['Verwaltung', 'fas fa-cog', 'Benutzerverwaltung'],
                    'newsroom' => ['News', 'fas fa-newspaper'],
                ];
                
                // Use the already defined $currentPage variable (set at line 108)
                if (isset($breadcrumbMap[$currentPage])) {
                    $breadcrumb = $breadcrumbMap[$currentPage];
                    $mainTitle = $breadcrumb[0];
                    $icon = $breadcrumb[1];
                    
                    // If there's a sub-page (3rd element)
                    if (isset($breadcrumb[2])) {
                        echo '<li class="breadcrumb-item"><i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1"></i>' . htmlspecialchars($mainTitle, ENT_QUOTES, 'UTF-8') . '</li>';
                        echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($breadcrumb[2], ENT_QUOTES, 'UTF-8') . '</li>';
                    } else {
                        echo '<li class="breadcrumb-item active" aria-current="page"><i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1"></i>' . htmlspecialchars($mainTitle, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                } else {
                    // Default breadcrumb for unknown pages
                    echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars(ucfirst($currentPage), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                ?>
            </ol>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Mobile Search Overlay (for logged in users) -->
    <?php if ($auth->isLoggedIn()): ?>
    <div class="mobile-search-overlay d-lg-none" id="mobileSearchOverlay" style="display: none;">
        <div class="p-3 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Suche</h5>
                <button class="btn btn-sm btn-outline-secondary" id="mobileSearchClose" aria-label="Suche schließen">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div>
                <?php $searchInstance = 'mobile'; include BASE_PATH . '/templates/components/search_component.php'; ?>
            </div>
        </div>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav d-lg-none bg-primary position-fixed bottom-0 start-0 end-0 py-2">
        <div class="container-fluid">
            <div class="row text-center g-0">
                <div class="col">
                    <a href="index.php?page=home" class="text-white text-decoration-none d-flex flex-column align-items-center<?php echo ($currentPage === 'home') ? ' active' : ''; ?>">
                        <i class="fas fa-home fs-5"></i>
                        <small>Home</small>
                    </a>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-link text-white text-decoration-none d-flex flex-column align-items-center w-100" id="mobileSearchBtn">
                        <i class="fas fa-search fs-5"></i>
                        <small>Suche</small>
                    </button>
                </div>
                <div class="col">
                    <a href="index.php?page=inventory" class="text-white text-decoration-none d-flex flex-column align-items-center<?php echo ($currentPage === 'inventory') ? ' active' : ''; ?>">
                        <i class="fas fa-boxes fs-5"></i>
                        <small>Inventar</small>
                    </a>
                </div>
                <div class="col">
                    <a href="index.php?page=settings" class="text-white text-decoration-none d-flex flex-column align-items-center<?php echo ($currentPage === 'settings') ? ' active' : ''; ?>">
                        <i class="fas fa-user fs-5"></i>
                        <small>Profil</small>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Edit Mode FAB (Floating Action Button) - Only for board members (admin, 1V, 2V, 3V) -->
    <?php if (isset($auth) && $auth->isLoggedIn()): ?>
        <?php if ($auth->isBoardMember()): ?>
            <?php 
            $fabActiveClass = $editModeActive ? ' active' : '';
            $fabDataActive = $editModeActive ? 'true' : 'false';
            ?>
            <button class="btn btn-warning position-fixed bottom-0 end-0 m-3 rounded-circle<?php echo $fabActiveClass; ?>" 
                    style="width: 56px; height: 56px; z-index: 1030;"
                    type="button" 
                    id="edit-mode-fab"
                    data-edit-mode-active="<?php echo $fabDataActive; ?>"
                    title="Edit-Modus umschalten"
                    aria-label="Edit-Modus umschalten">
                <i class="fas fa-pen"></i>
            </button>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Main Content Container -->
    <main class="flex-grow-1 py-4">
