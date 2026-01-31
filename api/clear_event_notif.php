<?php
declare(strict_types=1);

/**
 * Clear Event Notification API
 * Clears the has_helper_update flag when user visits the Events page
 */

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';
require_once BASE_PATH . '/src/NotificationService.php';

header('Content-Type: application/json');

// Initialize database and auth
$pdo = Database::getConnection();
$systemLogger = new SystemLogger($pdo);
$auth = new Auth($pdo, $systemLogger);

// Check if user is logged in
if (!$auth->isLoggedIn() || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Nicht angemeldet'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $notificationService = new NotificationService($pdo);
    
    // Clear the helper update notification
    $result = $notificationService->markHelperUpdatesAsRead($userId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error in clear_event_notif.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein Fehler ist aufgetreten'
    ]);
}
