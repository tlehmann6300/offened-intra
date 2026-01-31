<?php
/**
 * Clear Notifications API Endpoint
 * Handles clearing of notification badges (specifically helper update notifications)
 */

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';
require_once BASE_PATH . '/src/NotificationService.php';

header('Content-Type: application/json');

// Initialize database and auth
// Two-database architecture:
// - Auth uses $userPdo (User Database for authentication)
// - SystemLogger uses $contentPdo (Content Database for operational logs)
$userPdo = DatabaseManager::getUserConnection();
$contentPdo = DatabaseManager::getContentConnection();
$systemLogger = new SystemLogger($contentPdo);
$auth = new Auth($userPdo, $systemLogger);

// Check if user is logged in
if (!$auth->isLoggedIn() || !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Nicht angemeldet'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$notificationService = new NotificationService($contentPdo);

// Handle POST requests to clear notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark helper updates as read (clear the notification badge)
    $result = $notificationService->markHelperUpdatesAsRead($userId);
    echo json_encode($result);
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);