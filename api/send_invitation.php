<?php
/**
 * API Endpoint: Send Invitation
 * 
 * This endpoint allows admins and vorstand to create invitation tokens
 * and send invitation emails to new users.
 * 
 * Method: POST
 * Required Role: admin or vorstand
 * 
 * Parameters:
 * - csrf_token: CSRF protection token
 * - email: Email address to invite
 * - role: Role to assign (default: 'alumni')
 * - expiration_hours: Hours until token expires (default: 48, max: 168)
 * 
 * Response Format: JSON
 * {
 *   "success": true|false,
 *   "message": "Success or error message",
 *   "invitation_id": 123 (only on success)
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
require_once __DIR__ . '/../src/MailService.php';

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
            'message' => 'Keine Berechtigung. Nur Admins und Vorstand können Einladungen versenden.'
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
    
    // Get POST parameters
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'alumni';
    $expirationHours = isset($_POST['expiration_hours']) ? (int)$_POST['expiration_hours'] : 48;
    
    // Validate expiration hours (max 1 week)
    if ($expirationHours < 1 || $expirationHours > 168) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültige Ablaufzeit. Muss zwischen 1 und 168 Stunden liegen.'
        ]);
        exit;
    }
    
    // Validate role
    $validRoles = ['alumni', 'mitglied', 'ressortleiter'];
    if (!in_array($role, $validRoles, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültige Rolle.'
        ]);
        exit;
    }
    
    // Create invitation
    $result = $auth->createInvitation($email, $role, $expirationHours);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }
    
    // Send invitation email
    $mailService = new MailService();
    $invitedBy = $auth->getFullName() ?? 'IBC Admin';
    $mailSent = $mailService->sendInvitationEmail(
        $email,
        $result['token'],
        $role,
        $invitedBy,
        $result['expires_at']
    );
    
    if (!$mailSent) {
        // Log warning but don't fail the request - invitation was created
        error_log("Warning: Invitation created but email failed to send to: {$email}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Einladung erstellt, aber E-Mail konnte nicht gesendet werden. Bitte kontaktieren Sie die IT.',
            'invitation_id' => $result['invitation_id'],
            'email_sent' => false
        ]);
        exit;
    }
    
    // Success
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Einladung erfolgreich erstellt und E-Mail versendet.',
        'invitation_id' => $result['invitation_id'],
        'email_sent' => true
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in send_invitation.php: " . $e->getMessage());
    
    // Return generic error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
    ]);
}
