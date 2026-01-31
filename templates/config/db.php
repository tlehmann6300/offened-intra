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
// DatabaseManager Class - Singleton Pattern
// =============================================================================
class DatabaseManager {
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
    <title>Server-Konfigurationsfehler - IBC-Intra</title>
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
        .details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #888;
            text-align: left;
        }
        code {
            background: #eee;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">🔌</div>
        <h1>Server-Konfigurationsfehler</h1>
        <p>Die erforderlichen PHP-Datenbankerweiterungen (PDO) sind nicht verfügbar.</p>
        <div class="details">
            <strong>Lösung:</strong> Aktivieren Sie die folgenden PHP-Erweiterungen auf dem Server:<br>
            <code>pdo</code> und <code>pdo_mysql</code><br><br>
            Dies geschieht üblicherweise in der <code>php.ini</code> oder über das Hosting-Control-Panel.
            <br><br>
            Kontaktieren Sie gegebenenfalls Ihren Hosting-Anbieter für Unterstützung.
            <br><br>
            <strong>Fehlercode:</strong> PDO_EXTENSION_NOT_LOADED
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
    <title>Wartungsarbeiten - IBC-Intra</title>
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
        .details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #888;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">🔧</div>
        <h1>Wartungsarbeiten</h1>
        <p>Wir führen gerade Wartungsarbeiten an unserer Datenbank durch.</p>
        <p>Die Seite ist vorübergehend nicht verfügbar. Bitte versuchen Sie es in wenigen Minuten erneut.</p>
        <a href="/" class="button">Seite neu laden</a>
        <div class="details">
            Sollte das Problem weiterhin bestehen, kontaktieren Sie bitte den Administrator.<br>
            Fehlercode: DB_CONNECTION_FAILED
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
$pdo = DatabaseManager::getConnection();