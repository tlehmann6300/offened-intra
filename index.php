<?php
declare(strict_types=1);

/**
 * Central Router for IBC-Intra
 * File location: /index.php (root directory)
 * 
 * This is the main entry point for the application.
 * It handles routing, session management, authentication checks, and template loading.
 */

// -------------------------------------------------------------------
// 1. BASIC SETUP
// -------------------------------------------------------------------
// Note: Error display should be configured via environment variables in production
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Disabled for production security
ini_set('log_errors', '1');

// Define base directory
define('BASE_DIR', __DIR__);

// -------------------------------------------------------------------
// 2. AUTOLOADER & DEPENDENCIES
// -------------------------------------------------------------------
$autoloadPath = BASE_DIR . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    error_log("CRITICAL: vendor/autoload.php not found at " . $autoloadPath . " - Please run composer install");
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartungsmodus - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🔧</div>
        <h1>Wartungsmodus</h1>
        <p>Die Website wird gerade gewartet.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Bitte versuchen Sie es später erneut.</strong><br>
            Bei Fragen kontaktieren Sie bitte den Administrator.
        </div>
    </div>
</body>
</html>');
}
require_once $autoloadPath;

// -------------------------------------------------------------------
// 3. LOAD CONFIGURATION AND AUTH
// -------------------------------------------------------------------

// Check for .env file existence (optional but recommended)
$envPath = BASE_DIR . '/.env';
if (!file_exists($envPath)) {
    error_log("WARNING: .env file not found at " . $envPath . " - Using default configuration values");
    // Note: Application can still work with defaults from config.php, but log the warning
}

// Load configuration with robust path checking
$configPath = BASE_DIR . '/config/config.php';
if (!file_exists($configPath)) {
    error_log("CRITICAL: config/config.php not found at " . $configPath);
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurationsfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">⚙️</div>
        <h1>Konfigurationsfehler</h1>
        <p>Die Systemkonfiguration konnte nicht geladen werden.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Support kontaktieren:</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>');
}
require_once $configPath;

// Load database connection
$dbPath = BASE_DIR . '/config/db.php';
if (!file_exists($dbPath)) {
    error_log("CRITICAL: config/db.php not found at " . $dbPath);
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbankfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🗄️</div>
        <h1>Datenbankfehler</h1>
        <p>Die Datenbankverbindung konnte nicht hergestellt werden.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Support kontaktieren:</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>');
}
require_once $dbPath;

// Load Auth class
$authPath = BASE_DIR . '/src/Auth.php';
if (!file_exists($authPath)) {
    error_log("CRITICAL: src/Auth.php not found at " . $authPath);
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Systemfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🔐</div>
        <h1>Systemfehler</h1>
        <p>Das Authentifizierungssystem ist nicht verfügbar.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Support kontaktieren:</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>');
}
require_once $authPath;

// Load SystemLogger
$systemLoggerPath = BASE_DIR . '/src/SystemLogger.php';
if (!file_exists($systemLoggerPath)) {
    error_log("CRITICAL: src/SystemLogger.php not found at " . $systemLoggerPath);
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Systemfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">📝</div>
        <h1>Systemfehler</h1>
        <p>Das System-Logger-Modul ist nicht verfügbar.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Support kontaktieren:</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>');
}
require_once $systemLoggerPath;

// Initialize Auth system with SystemLogger
// Note: Two-database architecture:
// - Auth uses $userPdo (User Database for authentication)
// - SystemLogger uses $contentPdo (Content Database for operational logs)
try {
    $systemLogger = new SystemLogger($contentPdo);
    $auth = new Auth($userPdo, $systemLogger);
} catch (Exception $e) {
    error_log("CRITICAL: Failed to initialize Auth system - " . $e->getMessage());
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialisierungsfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">⚠️</div>
        <h1>Initialisierungsfehler</h1>
        <p>Das Authentifizierungssystem konnte nicht initialisiert werden.</p>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <div class="contact">
            <strong>Support kontaktieren:</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>');
}

// -------------------------------------------------------------------
// 4. ROUTING LOGIC
// -------------------------------------------------------------------

// Get requested page from query parameter
$page = $_GET['page'] ?? 'home';

// Sanitize page parameter to prevent log injection and path traversal
// Only allow alphanumeric characters, underscores, and hyphens
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

// Handle language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Set default language if not set
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'de';
}

// Handle logout
if ($page === 'logout') {
    $auth->logout();
    header('Location: index.php?page=login');
    exit;
}

// Handle admin login form submission
if ($page === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $totpCode = $_POST['totp_code'] ?? null;
    
    $result = $auth->login($email, $password, $totpCode);
    
    if ($result['success']) {
        header('Location: index.php?page=home');
        exit;
    } elseif (isset($result['requires_2fa']) && $result['requires_2fa']) {
        // Password correct but needs 2FA code
        header('Location: index.php?page=login&requires_2fa=1&message=' . urlencode($result['message']));
        exit;
    } else {
        header('Location: index.php?page=login&error=' . urlencode($result['message']));
        exit;
    }
}

// Define public pages that don't require authentication
$publicPages = [
    'login',
    'landing',
    'forgot_password', 
    'reset_password',
    'impressum', 
    'datenschutz'
];

$isPublic = in_array($page, $publicPages, true);

// Check authentication for non-public pages
if (!$isPublic) {
    if (!$auth->isLoggedIn()) {
        // Show landing page for home requests when not logged in
        if ($page === 'home') {
            $page = 'landing';
        } else {
            // Redirect to login for other protected pages
            header('Location: index.php?page=login');
            exit;
        }
    } else {
        // Check session timeout for logged-in users
        if (!$auth->checkSessionTimeout()) {
            $auth->logout();
            header('Location: index.php?page=login&timeout=1');
            exit;
        }
        
        // Check if user has role 'none' and redirect to role selection
        $userRole = $auth->getUserRole();
        if ($userRole === 'none' && $page !== 'select_role') {
            header('Location: index.php?page=select_role');
            exit;
        }
    }
}

// -------------------------------------------------------------------
// 5. MODULE-SPECIFIC PERMISSION CHECKS
// -------------------------------------------------------------------

// Define module access requirements
// Each module specifies the minimum role required to access it
$modulePermissions = [
    'events' => 'mitglied',           // Events module - accessible to all members and above
    'events_modern' => 'mitglied',
    'event_management' => 'ressortleiter',  // Event management - requires department leader or above
    'alumni' => 'mitglied',           // Alumni module - accessible to all members and above
    'alumni_modern' => 'mitglied',
    'alumni_database' => 'mitglied',
    'inventory' => 'mitglied',        // Inventory module - accessible to all members and above
    'inventory_config' => 'ressortleiter', // Inventory configuration - requires department leader or above
    'admin_dashboard' => 'admin',     // Admin dashboard - requires admin role
    'settings' => 'mitglied',         // Settings - accessible to all members
    'newsroom' => 'mitglied',         // Newsroom - accessible to all members
    'news_editor' => 'ressortleiter', // News editor - requires department leader or above
    'projects' => 'mitglied',         // Projects - accessible to all members
    'project_management' => 'ressortleiter', // Project management - requires department leader or above
];

// Check module-specific permissions for logged-in users
if ($auth->isLoggedIn() && isset($modulePermissions[$page])) {
    $requiredRole = $modulePermissions[$page];
    try {
        // Use Auth's checkPermission method to verify role hierarchy
        if (!$auth->checkPermission($requiredRole)) {
            // User doesn't have sufficient permissions
            $userRole = $auth->getUserRole();
            error_log("Access denied: User with role '{$userRole}' attempted to access '{$page}' which requires '{$requiredRole}'");
            
            http_response_code(403);
            die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugriff verweigert - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; 
            color: white; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        a:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🔒</div>
        <h1>403 - Zugriff verweigert</h1>
        <p>Sie haben keine Berechtigung, auf diese Seite zuzugreifen.</p>
        <p>Diese Seite erfordert die Rolle: <strong>' . htmlspecialchars($requiredRole) . '</strong></p>
        <p>Ihre aktuelle Rolle: <strong>' . htmlspecialchars($userRole) . '</strong></p>
        <a href="index.php">Zurück zur Startseite</a>
    </div>
</body>
</html>');
        }
    } catch (RuntimeException $e) {
        // User not logged in (shouldn't happen as we check isLoggedIn above, but handle it anyway)
        error_log("Permission check error: " . $e->getMessage());
        header('Location: index.php?page=login');
        exit;
    }
}

// -------------------------------------------------------------------
// 6. TEMPLATE LOADING
// -------------------------------------------------------------------

$templateDir = BASE_DIR . '/templates';

// Load necessary classes for home page
if ($page === 'home' && $auth->isLoggedIn()) {
    require_once BASE_DIR . '/src/News.php';
    require_once BASE_DIR . '/src/Event.php';
    require_once BASE_DIR . '/src/Project.php';
    require_once BASE_DIR . '/src/Inventory.php';
    require_once BASE_DIR . '/src/Alumni.php';
}

// Determine template file path
if ($page === 'login') {
    $templateFile = $templateDir . '/login.php';
} else {
    $templateFile = $templateDir . '/pages/' . basename($page) . '.php';
}

// Check if template exists
if (!file_exists($templateFile)) {
    error_log("404: Template not found - " . $templateFile . " (page: " . htmlspecialchars($page) . ")");
    http_response_code(404);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seite nicht gefunden - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; 
            color: white; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        a:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🔍</div>
        <h1>404 - Seite nicht gefunden</h1>
        <p>Die angeforderte Seite <strong>' . htmlspecialchars($page) . '</strong> existiert nicht.</p>
        <p>Möglicherweise wurde die Seite verschoben oder gelöscht.</p>
        <a href="index.php">Zurück zur Startseite</a>
    </div>
</body>
</html>');
}

// -------------------------------------------------------------------
// 7. RENDER OUTPUT
// -------------------------------------------------------------------

try {
    // Start output buffering to capture template content
    ob_start();
    include $templateFile;
    $content = ob_get_clean();
    
    // Load header and footer for all pages except special cases
    $pagesWithoutLayout = ['login', 'landing', 'register'];
    
    if (!in_array($page, $pagesWithoutLayout, true)) {
        // Include header
        $headerFile = $templateDir . '/layout/header.php';
        if (file_exists($headerFile)) {
            include $headerFile;
        }
        
        // Output page content
        echo $content;
        
        // Include footer
        $footerFile = $templateDir . '/layout/footer.php';
        if (file_exists($footerFile)) {
            include $footerFile;
        }
    } else {
        // For special pages, output content directly without layout
        echo $content;
    }
    
} catch (Exception $e) {
    error_log("ERROR: Exception while rendering page - " . $e->getMessage() . " (Trace: " . $e->getTraceAsString() . ")");
    http_response_code(500);
    die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serverfehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .logo { font-size: 48px; font-weight: bold; color: #667eea; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .contact { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">⚠️</div>
        <h1>500 - Interner Serverfehler</h1>
        <p>Es ist ein unerwarteter Fehler beim Laden der Seite aufgetreten.</p>
        <p>Das IBC-Intranet arbeitet bereits an der Behebung des Problems.</p>
        <div class="contact">
            <strong>Bitte versuchen Sie es später erneut.</strong><br>
            Bei anhaltenden Problemen wenden Sie sich an die IT-Abteilung.
        </div>
    </div>
</body>
</html>');
}