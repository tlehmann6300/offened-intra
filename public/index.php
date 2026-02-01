<?php
declare(strict_types=1);

/**
 * Main Router for IBC-Intra
 * File location: /public/index.php
 * Enhanced with comprehensive logging system
 */

// -------------------------------------------------------------------
// 1. DEBUG MODE & ERROR CONFIGURATION
// -------------------------------------------------------------------
// DEBUGGING: Enable error display to see actual error messages instead of generic 500 errors
// PRODUCTION: Set display_errors to '0' after debugging is complete
error_reporting(E_ALL);
ini_set('display_errors', '1'); // Enabled for debugging - disable in production
ini_set('log_errors', '1');

// Define base directory first
$baseDir = dirname(__DIR__);

// Create logs directory if it doesn't exist
$logsDir = $baseDir . '/logs';
if (!is_dir($logsDir)) {
    if (!mkdir($logsDir, 0755, true)) {
        error_log("WARNING: Failed to create logs directory at: {$logsDir}");
    }
}

// Set error log file
$errorLogFile = $logsDir . '/app.log';
ini_set('error_log', $errorLogFile);

/**
 * Custom logging function with error handling
 */
function logMessage(string $message, string $level = 'INFO'): void {
    global $baseDir;
    $logFile = $baseDir . '/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $logEntry = "[{$timestamp}] [{$level}] [IP: {$ip}] [URI: {$uri}] {$message}" . PHP_EOL;
    
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Fallback to error_log if file writing fails
    if ($result === false) {
        error_log("Failed to write to log file. Original message: [{$level}] {$message}");
    }
}

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
    ];
    
    $type = $errorTypes[$errno] ?? 'UNKNOWN';
    $message = "PHP {$type}: {$errstr} in {$errfile}:{$errline}";
    logMessage($message, 'ERROR');
    
    // Don't execute PHP internal error handler
    return true;
});

// Log application start
logMessage("Application started");

// -------------------------------------------------------------------
// 2. SETUP & PFADE
// -------------------------------------------------------------------

$autoloadPath = $baseDir . '/vendor/autoload.php';

// Prüfung: Existiert der vendor Ordner?
if (!file_exists($autoloadPath)) {
    logMessage("CRITICAL: Vendor folder not found at: {$autoloadPath}", 'CRITICAL');
    http_response_code(503);
    die('<div style="font-family:sans-serif;padding:20px;text-align:center;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;margin:20px;border-radius:5px;">
            <h1>Setup Fehler</h1>
            <p>Der <code>vendor</code> Ordner wurde nicht gefunden.</p>
            <p><strong>System-Pfad:</strong> ' . htmlspecialchars($baseDir) . '</p>
            <p>Bitte führen Sie <code>composer install</code> im Hauptverzeichnis aus.</p>
            <p><strong>Hinweis:</strong> Der vendor Ordner muss im Hauptverzeichnis liegen (neben <code>public</code>, nicht darin!).</p>
         </div>');
}
require_once $autoloadPath;

// -------------------------------------------------------------------
// 3. LOAD .ENV FILE (FAULT-TOLERANT)
// -------------------------------------------------------------------

// Check and load .env file safely (optional but recommended)
// If .env is missing, the application will continue with defaults from config files
$envFilePath = $baseDir . '/.env';
if (file_exists($envFilePath)) {
    try {
        // Load .env file safely with Dotenv
        $dotenv = Dotenv\Dotenv::createImmutable($baseDir);
        $dotenv->load();
        logMessage(".env file loaded successfully");
    } catch (Exception $e) {
        logMessage("WARNING: Failed to load .env file: " . $e->getMessage() . " - Using default configuration values", 'WARNING');
        // Application continues with defaults - do not crash
    }
} else {
    logMessage("WARNING: .env file not found at {$envFilePath} - Using default configuration values", 'WARNING');
    // Note: Application can still work with defaults from config.php
}

// -------------------------------------------------------------------
// 4. CORE LADEN
// -------------------------------------------------------------------

// Wichtige Systemdateien laden
$requiredFiles = [
    '/config/config.php',
    '/config/db.php',
    '/src/Auth.php'
];

foreach ($requiredFiles as $file) {
    $filePath = $baseDir . $file;
    if (!file_exists($filePath)) {
        logMessage("CRITICAL: Required file not found: {$file}", 'CRITICAL');
        die("Kritischer Fehler: Die Datei <code>$file</code> fehlt im Hauptverzeichnis.");
    }
    require_once $filePath;
}

// Auth initialisieren
try {
    // Two-database architecture:
    // - Auth uses $userPdo (User Database for authentication)
    $userPdo = DatabaseManager::getUserConnection();
    $auth = new Auth($userPdo);
    logMessage("Auth system initialized successfully");
} catch (Exception $e) {
    logMessage("CRITICAL: Failed to initialize Auth: " . $e->getMessage(), 'CRITICAL');
    die("Kritischer Fehler: Auth-System konnte nicht initialisiert werden.");
}

// -------------------------------------------------------------------
// 5. ROUTING & SICHERHEIT
// -------------------------------------------------------------------

$page = $_GET['page'] ?? 'home';
logMessage("Page request: {$page}");

// Handle language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    logMessage("Language changed to: {$_GET['lang']}");
}

// Set default language if not set
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'de';
}

// Logout
if ($page === 'logout') {
    $auth->logout();
    logMessage("User logged out");
    header('Location: index.php?page=login');
    exit;
}

// Öffentliche Seiten (Whitelist)
$publicPages = [
    'login', 'forgot_password', 'reset_password', 
    'microsoft_callback', 'impressum', 'datenschutz'
];

$isPublic = in_array($page, $publicPages, true);

// Zugriffsschutz prüfen
if (!$isPublic) {
    if (!$auth->isLoggedIn()) {
        logMessage("Access denied: User not logged in, redirecting to login");
        header('Location: index.php?page=login');
        exit;
    }
    if (!$auth->checkSessionTimeout()) {
        logMessage("Session timeout for user ID: " . $auth->getUserId());
        $auth->logout();
        header('Location: index.php?page=login&timeout=1');
        exit;
    }
    
    // Check if user has role 'none' and redirect to role selection
    $userRole = $auth->getUserRole();
    if ($userRole === 'none' && $page !== 'select_role') {
        logMessage("User has no role set, redirecting to role selection. User ID: " . $auth->getUserId());
        header('Location: index.php?page=select_role');
        exit;
    }
}

// -------------------------------------------------------------------
// 6. VIEW LADEN (Templates)
// -------------------------------------------------------------------

$templateDir = $baseDir . '/templates';
$targetFile = '';

// Pfad zur Datei bestimmen
if ($page === 'login') {
    $targetFile = $templateDir . '/login.php';
} else {
    $targetFile = $templateDir . '/pages/' . basename($page) . '.php';
}

// Existenz prüfen
if (!file_exists($targetFile)) {
    logMessage("Template not found: {$targetFile}", 'WARNING');
    
    // Fallback: Home laden oder 404
    if ($page === 'home') {
        // Wenn Home fehlt, erstelle eine temporäre Startseite im Speicher
        logMessage("Home template missing, displaying fallback", 'WARNING');
        echo "<h1>Willkommen im Intranet</h1><p>Die Seite home.php wurde noch nicht erstellt.</p>";
        exit;
    }
    http_response_code(404);
    die("<div style='text-align:center;padding:50px;'><h1>404</h1><p>Seite '$page' nicht gefunden.</p><a href='index.php'>Zurück</a></div>");
}

// -------------------------------------------------------------------
// 7. AUSGABE RENDERN
// -------------------------------------------------------------------

try {
    ob_start();
    include $targetFile;
    $content = ob_get_clean();
    
    // Layout (Header/Footer) nur laden, wenn es kein Callback ist
    if ($page !== 'microsoft_callback') {
        // Header
        if (file_exists($templateDir . '/layout/header.php')) {
            include $templateDir . '/layout/header.php';
        }
        
        // Inhalt
        echo $content;
        
        // Footer
        if (file_exists($templateDir . '/layout/footer.php')) {
            include $templateDir . '/layout/footer.php';
        }
    } else {
        echo $content;
    }
    
    logMessage("Page rendered successfully: {$page}");
    
} catch (Exception $e) {
    logMessage("ERROR rendering page: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo "<div style='text-align:center;padding:50px;'><h1>500</h1><p>Ein Fehler ist aufgetreten.</p></div>";
}