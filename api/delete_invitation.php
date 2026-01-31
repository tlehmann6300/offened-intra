<?php
/**
 * API Endpoint: Delete Invitation
 * 
 * This endpoint allows admins and vorstand to delete/cancel pending invitations.
 * 
 * Method: POST
 * Required Role: admin or vorstand
 * 
 * Parameters:
 * - csrf_token: CSRF protection token
 * - invitation_id: ID of the invitation to delete
 * 
 * Response Format: JSON
 * {
 *   "success": true|false,
 *   "message": "Success or error message"
 * }
 */

// Set header for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SystemLogger.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Initialize Auth with SystemLogger
    $userPdo = DatabaseManager::getUserConnection();
    $contentPdo = DatabaseManager::getContentConnection();
    $auth = new Auth($userPdo, new SystemLogger($contentPdo));
    
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Sie müssen angemeldet sein.'
        ]);
        exit;
    }
    
    // Check if user has required role (admin or vorstand)
    $userRole = $auth->getUserRole();
    if (!in_array($userRole, ['admin', 'vorstand'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Keine Berechtigung. Nur Admins und Vorstand können Einladungen löschen.'
        ]);
        exit;
    }
    
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$auth->verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger CSRF-Token. Bitte laden Sie die Seite neu.'
        ]);
        exit;
    }
    
    // Get POST parameter
    $invitationId = isset($_POST['invitation_id']) ? (int)$_POST['invitation_id'] : 0;
    
    if ($invitationId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültige Einladungs-ID.'
        ]);
        exit;
    }
    
    // Delete invitation
    $result = $auth->deleteInvitation($invitationId);
    
    // Set appropriate HTTP status code
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    // Return result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in delete_invitation.php: " . $e->getMessage());
    
    // Return generic error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
    ]);
}
