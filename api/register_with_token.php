<?php
/**
 * API Endpoint: Register with Invitation Token
 * 
 * This endpoint allows users to register using a valid invitation token.
 * No authentication required (public endpoint).
 * 
 * Method: POST
 * Required Role: None (public)
 * 
 * Parameters:
 * - token: Invitation token
 * - firstname: User's first name
 * - lastname: User's last name
 * - password: User's password
 * - password_confirm: Password confirmation
 * 
 * Response Format: JSON
 * {
 *   "success": true|false,
 *   "message": "Success or error message",
 *   "user_id": 123 (only on success)
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
    
    // Get POST parameters
    $token = $_POST['token'] ?? '';
    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validate token presence
    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kein Einladungs-Token angegeben.'
        ]);
        exit;
    }
    
    // Validate required fields
    if (empty($firstname) || empty($lastname) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Alle Felder sind erforderlich.'
        ]);
        exit;
    }
    
    // Validate password confirmation
    if ($password !== $passwordConfirm) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Die Passwörter stimmen nicht überein.'
        ]);
        exit;
    }
    
    // Register user with invitation token
    $result = $auth->registerWithInvitation($token, $firstname, $lastname, $password);
    
    // Set appropriate HTTP status code
    if ($result['success']) {
        http_response_code(201); // Created
    } else {
        http_response_code(400); // Bad Request
    }
    
    // Return result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in register_with_token.php: " . $e->getMessage());
    
    // Return generic error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
    ]);
}
