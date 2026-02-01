<?php
declare(strict_types=1);

/**
 * Database Connection Configuration
 * Secure PDO connection for JE Alumni Connect
 * Uses environment variables from .env file
 */

// Load environment variables from .env file safely
// Application continues with defaults if .env is missing or fails to load
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } catch (Exception $e) {
        // Log the error but don't crash - use defaults
        error_log("WARNING: Failed to load .env file in db.php: " . $e->getMessage());
    }
}

// Content Database credentials (Projekte, Inventar, Events, News)
define('DB_CONTENT_HOST', $_ENV['DB_CONTENT_HOST'] ?? getenv('DB_CONTENT_HOST') ?: 'db5019375140.hosting-data.io');
define('DB_CONTENT_NAME', $_ENV['DB_CONTENT_NAME'] ?? getenv('DB_CONTENT_NAME') ?: 'dbs15161271');
define('DB_CONTENT_USER', $_ENV['DB_CONTENT_USER'] ?? getenv('DB_CONTENT_USER') ?: 'dbu2067984');
define('DB_CONTENT_PASS', $_ENV['DB_CONTENT_PASS'] ?? getenv('DB_CONTENT_PASS') ?: 'Wort!Zahl?Wort#41254g');

// User Database credentials (Benutzerkonten, Alumni-Profile)
define('DB_USER_HOST', $_ENV['DB_USER_HOST'] ?? getenv('DB_USER_HOST') ?: 'db5019508945.hosting-data.io');
define('DB_USER_NAME', $_ENV['DB_USER_NAME'] ?? getenv('DB_USER_NAME') ?: 'dbs15253086');
define('DB_USER_USER', $_ENV['DB_USER_USER'] ?? getenv('DB_USER_USER') ?: 'dbu4494103');
define('DB_USER_PASS', $_ENV['DB_USER_PASS'] ?? getenv('DB_USER_PASS') ?: 'Q9!mZ7$A2v#Lr@8x');

// Common database charset
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

// Legacy database credentials for backward compatibility (uses Content DB)
define('DB_HOST', DB_CONTENT_HOST);
define('DB_NAME', DB_CONTENT_NAME);
define('DB_USER', DB_CONTENT_USER);
define('DB_PASS', DB_CONTENT_PASS);

// =============================================================================
// DatabaseManager Class - Singleton Pattern with Multi-Database Support
// =============================================================================
/**
 * DatabaseManager - Multi-Database Connection Manager
 * 
 * This class manages two separate PDO database connections for the IBC-Intra system:
 * 
 * 1. Content Database ($contentDbInstance):
 *    - Hosts: Projekte (Projects), Inventar (Inventory), Events, News
 *    - Server: db5019375140.hosting-data.io
 *    - Database: dbs15161271
 * 
 * 2. User Database ($userDbInstance):
 *    - Hosts: Benutzerkonten (User Accounts), Alumni-Profile
 *    - Server: db5019508945.hosting-data.io
 *    - Database: dbs15253086
 * 
 * Singleton Pattern Implementation:
 * - Each database connection is lazily initialized on first access
 * - Connections are reused across the application (single instance per database)
 * - Private constructor and __clone() prevent direct instantiation
 * 
 * Error Handling:
 * - Each database connection has independent error handling
 * - Connection failures display user-friendly error pages instead of crashing
 * - Errors are logged securely without exposing sensitive information
 * - HTTP 503 status codes are returned for service unavailability
 * 
 * Usage Examples:
 * 
 *   // Get Content Database for inventory, events, projects, news
 *   $pdoContent = DatabaseManager::getContentConnection();
 *   $event = new Event($pdoContent);
 * 
 *   // Get User Database for authentication, alumni profiles
 *   $pdoUser = DatabaseManager::getUserConnection();
 *   $auth = new Auth($pdoUser);
 * 
 *   // Legacy compatibility - returns Content DB
 *   $pdo = DatabaseManager::getConnection();
 * 
 * @author IBC Development Team
 * @version 2.0.0
 * @since 2.0.0 Multi-database support added
 */
class DatabaseManager {
    private static ?PDO $contentDbInstance = null;
    private static ?PDO $userDbInstance = null;
    
    private function __construct() {}
    private function __clone() {}
    
    /**
     * Get PDO database connection instance (legacy method - returns Content DB)
     * @return PDO Database connection
     */
    public static function getConnection(): PDO {
        return self::getContentConnection();
    }
    
    /**
     * Get Content Database connection (Inventar, Events, Projekte, News)
     * @return PDO Content Database connection
     */
    public static function getContentConnection(): PDO {
        if (self::$contentDbInstance === null) {
            self::$contentDbInstance = self::createConnection(
                DB_CONTENT_HOST,
                DB_CONTENT_NAME,
                DB_CONTENT_USER,
                DB_CONTENT_PASS,
                'Content-DB'
            );
        }
        return self::$contentDbInstance;
    }
    
    /**
     * Get User Database connection (Benutzer, Alumni-Profile)
     * @return PDO User Database connection
     */
    public static function getUserConnection(): PDO {
        if (self::$userDbInstance === null) {
            self::$userDbInstance = self::createConnection(
                DB_USER_HOST,
                DB_USER_NAME,
                DB_USER_USER,
                DB_USER_PASS,
                'User-DB'
            );
        }
        return self::$userDbInstance;
    }
    
    /**
     * Create a PDO connection with given credentials
     * @param string $host Database host
     * @param string $dbName Database name
     * @param string $user Database user
     * @param string $pass Database password
     * @param string $label Connection label for error logging
     * @return PDO Database connection
     */
    private static function createConnection(string $host, string $dbName, string $user, string $pass, string $label): PDO {
        // Check if PDO extension is available
        if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
            error_log("CRITICAL: PDO or PDO_MySQL extension not installed/enabled on server ({$label})");
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System vorübergehend nicht verfügbar - IBC-Intra</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .logo {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .contact {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">⚙️</div>
        <h1>System vorübergehend nicht verfügbar</h1>
        <p>Das IBC-Intranet ist vorübergehend nicht verfügbar.</p>
        <p>Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.</p>
        <div class="contact">
            <strong>Support kontaktieren</strong><br>
            Bei anhaltenden Problemen wenden Sie sich bitte an die IT-Abteilung.
        </div>
    </div>
</body>
</html>';
            exit;
        }
        
        try {
            $dsn = "mysql:host=" . $host . ";dbname=" . $dbName . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            // Log error securely with detailed information for debugging
            $errorMsg = "DB Connection Error ({$label}): " . $e->getMessage();
            error_log($errorMsg);
            
            // Show user-friendly error page with debug info when display_errors is enabled
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            
            // Check if we should display detailed error (based on ini setting)
            $showDetails = ini_get('display_errors') === '1';
            
            echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbankfehler - IBC-Intra</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .logo {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .contact {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: left;
            font-size: 13px;
            font-family: monospace;
            color: #856404;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🗄️</div>
        <h1>Datenbankverbindungsfehler</h1>
        <p>Das IBC-Intranet konnte keine Verbindung zur Datenbank herstellen.</p>
        <p><strong>Betroffene Datenbank:</strong> ' . htmlspecialchars($label) . '</p>';
            
            if ($showDetails) {
                echo '<div class="debug-info">
                    <strong>Debug-Information:</strong><br>
                    Host: ' . htmlspecialchars($host) . '<br>
                    Datenbank: ' . htmlspecialchars($dbName) . '<br>
                    Benutzer: ' . htmlspecialchars($user) . '<br>
                    Fehler: ' . htmlspecialchars($e->getMessage()) . '
                </div>';
            }
            
            echo '<div class="contact">
            <strong>Support kontaktieren</strong><br>
            Bitte wenden Sie sich an die IT-Abteilung oder den Systemadministrator.
        </div>
    </div>
</body>
</html>';
            exit;
        }
    }
}

// Create global PDO instances for the two-database model
// $userPdo - User Database (Benutzer, Alumni-Profile, Authentication)
// $contentPdo - Content Database (Projekte, Inventar, Events, News, System Logs)
$userPdo = DatabaseManager::getUserConnection();
$contentPdo = DatabaseManager::getContentConnection();

// Create global $pdo variable for backward compatibility (points to Content DB)
$pdo = DatabaseManager::getConnection();