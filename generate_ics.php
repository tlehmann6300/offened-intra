<?php
declare(strict_types=1);

/**
 * Calendar Export Endpoint
 * Generates and downloads .ics files for helper slots
 * Only accessible to registered users
 * 
 * Usage: /generate_ics.php?slot_id=Y
 */

// Define base path for includes
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Load required files
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';
require_once BASE_PATH . '/src/CalendarService.php';

// Initialize database and auth
// Two-database architecture:
// - Auth uses $userPdo (User Database: dbs15253086)
// - SystemLogger uses $contentPdo (Content Database: dbs15161271)
$userPdo = DatabaseManager::getUserConnection();
$contentPdo = DatabaseManager::getContentConnection();
$systemLogger = new SystemLogger($contentPdo);
$auth = new Auth($userPdo, $systemLogger);

// Check if user is logged in
if (!$auth->isLoggedIn() || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fehler: Authentifizierung erforderlich';
    exit;
}

// Validate required parameters
if (!isset($_GET['slot_id'])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fehler: slot_id Parameter ist erforderlich';
    exit;
}

// Validate and sanitize parameters
$slotId = filter_var($_GET['slot_id'], FILTER_VALIDATE_INT);

if ($slotId === false || $slotId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fehler: Ungültiger Parameter';
    exit;
}

try {
    // Initialize CalendarService
    $calendarService = new CalendarService($contentPdo);
    
    // Generate ICS content for the slot
    $icsContent = $calendarService->generateIcsForSlot($slotId);
    
    if ($icsContent === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fehler: Slot nicht gefunden';
        exit;
    }
    
    // Set headers for .ics file download
    // Expire header set to 1 hour in the past to prevent caching
    $cacheExpireSeconds = 3600; // 1 hour
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="einsatz.ics"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() - $cacheExpireSeconds) . ' GMT');
    header('Content-Length: ' . strlen($icsContent));
    
    // Output the ICS content
    echo $icsContent;
    
} catch (Exception $e) {
    error_log('Error generating ICS file: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fehler: Kalender konnte nicht generiert werden';
    exit;
}
