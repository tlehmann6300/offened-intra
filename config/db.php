<?php
declare(strict_types=1);

/**
 * Database Connection Configuration
 * Secure PDO connection for JE Alumni Connect
 * Uses environment variables from .env file
 */

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Database credentials from environment variables
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'db5010762628.hosting-data.io');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'dbs9105747');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'dbu2806248');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: ')*U.lzR428>qcz1wa*gXgkA<?sN[2tK');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

// =============================================================================
// Database Connection Class - Singleton Pattern
// =============================================================================
class Database {
    private static ?PDO $instance = null;
    
    private function __construct() {}
    private function __clone() {}
    
    /**
     * Get PDO database connection instance
     * @return PDO Database connection
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Check if PDO extension is available
            if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
                error_log("CRITICAL: PDO or PDO_MySQL extension not installed/enabled on server");
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
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log error securely
                error_log("Database connection failed: " . $e->getMessage());
                
                // Show user-friendly error page
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
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
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">IBC</div>
        <div class="icon">🗄️</div>
        <h1>Datenbankfehler</h1>
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
        }
        
        return self::$instance;
    }
}

// Create global $pdo variable for backward compatibility
$pdo = Database::getConnection();