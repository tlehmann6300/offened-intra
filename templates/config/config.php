<?php
declare(strict_types=1);

/**
 * General Configuration Constants
 * JE Alumni Connect / IBC-Intra
 * Uses environment variables from .env file
 */

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// =============================================================================
// ENVIRONMENT CONFIGURATION
// =============================================================================
// Define application environment: 'production' or 'development'
// In production mode, error display is disabled for security reasons
// Read from environment variable, fallback to 'production' for safety
$envValue = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
// Validate environment value - only allow 'production' or 'development'
$envValue = in_array($envValue, ['production', 'development'], true) ? $envValue : 'production';
define('APP_ENV', $envValue);

// Configure error display based on environment
if (APP_ENV === 'production') {
    // In production, disable error display to prevent exposing system paths to potential attackers
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL); // Still log errors, but don't display them
    
    // Enable error logging to file
    ini_set('log_errors', '1');
    // Use absolute path to prevent directory traversal vulnerabilities
    $logDir = realpath(__DIR__ . '/..') . '/logs';
    
    // Try to create log directory if it doesn't exist
    // Use 0750 permissions to restrict access (owner: rwx, group: r-x, other: none)
    if (!is_dir($logDir)) {
        $mkdirResult = @mkdir($logDir, 0750, true);
        // Verify directory was actually created
        if (!$mkdirResult || !is_dir($logDir)) {
            // If directory creation fails, log warning and fall back to system error log
            // Don't expose full path in error message for security
            error_log('WARNING: Failed to create custom log directory - Using system default error log');
            // Don't override error_log, let it use system default
        }
    }
    
    // Only set custom log file if directory exists and is writable
    if (is_dir($logDir) && is_writable($logDir)) {
        $logFile = $logDir . '/php_errors.log';
        ini_set('error_log', $logFile);
    } else {
        if (is_dir($logDir)) {
            error_log('WARNING: Custom log directory not writable - Using system default error log');
        }
        // Don't override error_log, let it use system default
    }
} else {
    // In development, enable error display for debugging
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// =============================================================================
// SITE CONFIGURATION
// =============================================================================
// Site configuration from environment variables with fallbacks
// IMPORTANT: In production, set SITE_URL to your actual domain (e.g., https://intranet.ibc.de)
// Avoid using localhost in production environments
define('SITE_NAME', $_ENV['SITE_NAME'] ?? getenv('SITE_NAME') ?: 'IBC-Intra');
define('SITE_URL', 'https://intra.business-consulting.de');

// Determine base path for the application (supports both root and subdirectory installations)
// This automatically detects if the app is in a subdirectory (e.g., /intra/) or root (/)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = $scriptDir ?: '';
define('BASE_URL', $basePath);

// API URL - full path to API router
define('API_URL', SITE_URL . '/public/api/router.php');

// Path configuration
define('BASE_PATH', __DIR__ . '/..');
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

// Upload settings from environment variables with fallbacks
define('MAX_FILE_SIZE', (int)($_ENV['MAX_FILE_SIZE'] ?? getenv('MAX_FILE_SIZE') ?: 5242880)); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']); 

// Password generation for SSO users
define('RANDOM_PASSWORD_LENGTH', 32); // Length in bytes for random password generation

// Session configuration from environment variables with fallbacks
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME') ?: 3600 * 24)); // 24 hours

// Pagination
define('ITEMS_PER_PAGE', 12);

// Translation configuration
define('TRANSLATIONS_PATH', BASE_PATH . '/assets/data/translations.json');

// =============================================================================
// EMAIL ADDRESSES
// =============================================================================
define('EMPFAENGER_EMAIL', $_ENV['EMPFAENGER_EMAIL'] ?? getenv('EMPFAENGER_EMAIL') ?: 'tom.lehmann@business-consulting.de');
define('IT_EMAIL', $_ENV['IT_EMAIL'] ?? getenv('IT_EMAIL') ?: 'it-support@test.business-consulting.de');

// =============================================================================
// GOOGLE RECAPTCHA v3 Configuration
// =============================================================================
// Load from environment variables for better security
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? getenv('RECAPTCHA_SITE_KEY') ?: '6LfzQyUsAAAAALhJXnwV810IjeSKTW4WxPyA7xly');
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? getenv('RECAPTCHA_SECRET_KEY') ?: '6LfzQyUsAAAAALeUfLoMQwoSNJcuvwNHCfmawo43');

// =============================================================================
// GOOGLE ANALYTICS
// =============================================================================
define('GOOGLE_ANALYTICS_ID', $_ENV['GOOGLE_ANALYTICS_ID'] ?? getenv('GOOGLE_ANALYTICS_ID') ?: 'G-GLT586XQ3P');

// =============================================================================
// OPTIONAL HUBSPOT CRM
// =============================================================================
define('HUBSPOT_API_KEY', $_ENV['HUBSPOT_API_KEY'] ?? getenv('HUBSPOT_API_KEY') ?: '');

// =============================================================================
// SECURITY & PROXY
// =============================================================================
define('TRUST_PROXY_HEADERS', filter_var($_ENV['TRUST_PROXY_HEADERS'] ?? getenv('TRUST_PROXY_HEADERS') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// =============================================================================
// SMTP Configuration
// =============================================================================
// Load from environment variables for better security
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.ionos.de');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587));
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: 'tls');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: 'mail@test.business-consulting.de');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: 'Test12345678.');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: 'mail@test.business-consulting.de');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'JE Alumni Connect');

// =============================================================================
// UPTIME MONITORING
// =============================================================================
define('UPTIME_CHECK_TOKEN', $_ENV['UPTIME_CHECK_TOKEN'] ?? getenv('UPTIME_CHECK_TOKEN') ?: 'CHANGE_THIS_TO_SECURE_RANDOM_TOKEN_BEFORE_PRODUCTION');
define('UPTIME_URL_TO_CHECK', $_ENV['UPTIME_URL_TO_CHECK'] ?? getenv('UPTIME_URL_TO_CHECK') ?: 'https://www.business-consulting.de');
define('UPTIME_ALERT_EMAIL', $_ENV['UPTIME_ALERT_EMAIL'] ?? getenv('UPTIME_ALERT_EMAIL') ?: 'it-support@test.business-consulting.de');

// =============================================================================
// MICROSOFT ENTRA ID (Azure AD) Configuration for SSO
// =============================================================================
// Load from environment variables for better security
// IMPORTANT: Set these values in your .env file!
define('MS_CLIENT_ID', $_ENV['MS_CLIENT_ID'] ?? getenv('MS_CLIENT_ID') ?: 'YOUR_CLIENT_ID_HERE');
define('MS_CLIENT_SECRET', $_ENV['MS_CLIENT_SECRET'] ?? getenv('MS_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET_HERE');
define('MS_TENANT_ID', $_ENV['MS_TENANT_ID'] ?? getenv('MS_TENANT_ID') ?: 'YOUR_TENANT_ID_HERE');
define('MS_REDIRECT_URI', SITE_URL . '/index.php?page=microsoft_callback');

// Start session with secure cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    // Check if session save path is writable
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    
    if (!is_writable($sessionPath)) {
        error_log("WARNING: Session save path is not writable: {$sessionPath}");
        // Try to continue anyway - PHP might handle it gracefully
    }
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '', 
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
        'httponly' => true, 
        'samesite' => 'Strict' 
    ]);
    
    // Try to start session with error handling
    // Note: session_start() returns false on failure but doesn't throw exceptions
    $sessionStarted = @session_start();
    
    if (!$sessionStarted) {
        $lastError = error_get_last();
        $errorMessage = $lastError ? $lastError['message'] : 'Unknown error';
        error_log("CRITICAL: Failed to start session: " . $errorMessage);
        http_response_code(500);
        die('<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session-Fehler - IBC-Intra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
               display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .error-container { background: white; border-radius: 10px; padding: 40px; max-width: 600px; 
                          box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        h1 { color: #333; margin-bottom: 20px; font-size: 28px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        .details { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 20px; 
                   font-size: 14px; color: #888; text-align: left; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">🔐</div>
        <h1>Session-Fehler</h1>
        <p>Die Sitzungsverwaltung konnte nicht gestartet werden.</p>
        <div class="details">
            <strong>Mögliche Ursachen:</strong><br>
            • Keine Schreibrechte im Session-Verzeichnis<br>
            • Unzureichender Speicherplatz<br>
            • Fehlerhafte PHP-Konfiguration<br><br>
            <strong>Session-Pfad:</strong> <code>' . htmlspecialchars($sessionPath) . '</code><br><br>
            Prüfen Sie die Berechtigungen und kontaktieren Sie gegebenenfalls Ihren Administrator.
            <br><br>
            <strong>Fehlercode:</strong> SESSION_START_FAILED
        </div>
    </div>
</body>
</html>');
    }
}