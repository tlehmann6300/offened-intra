<?php
/**
 * Helper functions for header template
 * Extracts common logic to keep template clean
 */

/**
 * Get user display name from email
 * Extracts username from email (everything before @), with fallback for edge cases
 * 
 * @param string|null $userEmail User's email address
 * @return string Display name for user
 */
function getUserDisplayName($userEmail = null) {
    // Get email from session if not provided
    if ($userEmail === null) {
        $userEmail = $_SESSION['email'] ?? 'Benutzer';
    }
    
    // Extract username from email
    if (strpos($userEmail, '@') !== false) {
        $parts = explode('@', $userEmail, 2);
        // Use the part before @ if non-empty, otherwise use fallback
        return !empty($parts[0]) ? $parts[0] : 'Benutzer';
    }
    
    // If no @ sign found, return the email as-is or fallback
    return !empty($userEmail) ? $userEmail : 'Benutzer';
}

/**
 * Check if user has helper update notifications
 * 
 * @param PDO $pdo Database connection
 * @param object $auth Authentication object
 * @return bool True if user has helper updates
 */
function hasHelperUpdate($pdo, $auth) {
    if (!$auth->isLoggedIn() || !isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Note: NotificationService is loaded globally in most pages
    // If not already loaded, we load it here
    if (!class_exists('NotificationService')) {
        require_once BASE_PATH . '/src/NotificationService.php';
    }
    
    $notificationService = new NotificationService($pdo);
    return $notificationService->hasHelperUpdate((int)$_SESSION['user_id']);
}
