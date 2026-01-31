<?php
/**
 * Notification API Endpoint
 * Handles notification-related AJAX requests
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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Get new helper requests
    if ($action === 'get_helper_requests') {
        $helperRequests = $notificationService->getNewHelperRequests(10);
        
        echo json_encode([
            'success' => true,
            'requests' => $helperRequests
        ]);
        exit;
    }
    
    // Mark helper updates as read
    if ($action === 'mark_as_read') {
        $result = $notificationService->markHelperUpdatesAsRead($userId);
        echo json_encode($result);
        exit;
    }
    
    // Check notification status
    if ($action === 'check_status') {
        $hasUpdate = $notificationService->hasHelperUpdate($userId);
        echo json_encode([
            'success' => true,
            'hasUpdate' => $hasUpdate
        ]);
        exit;
    }
}

// Invalid request
echo json_encode([
    'success' => false,
    'message' => 'Ungültige Anfrage'
]);
