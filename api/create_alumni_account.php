<?php
/**
 * API Endpoint: Create Alumni Account
 * 
 * This endpoint allows board members (vorstand) and admins to create alumni accounts.
 * 
 * Method: POST
 * Required Role: vorstand or admin
 * 
 * Parameters:
 * - csrf_token: CSRF protection token
 * - email: Alumni email address
 * - firstname: Alumni first name
 * - lastname: Alumni last name
 * - password: Initial password (min 8 characters)
 * 
 * Response Format: JSON
 * {
 *   "success": true|false,
 *   "message": "Success or error message",
 *   "user_id": 123 (only on success)
 * }
 * 
 * Example Usage:
 * 
 * fetch('api/create_alumni_account.php', {
 *   method: 'POST',
 *   headers: {
 *     'Content-Type': 'application/x-www-form-urlencoded',
 *   },
 *   body: new URLSearchParams({
 *     csrf_token: document.querySelector('[name="csrf_token"]').value,
 *     email: 'alumni@example.com',
 *     firstname: 'Max',
 *     lastname: 'Mustermann',
 *     password: 'SecurePassword123!'
 *   })
 * })
 * .then(response => response.json())
 * .then(data => {
 *   if (data.success) {
 *     alert('Alumni account created successfully!');
 *   } else {
 *     alert('Error: ' + data.message);
 *   }
 * });
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
    // Two-database architecture:
    // - Auth uses $userPdo (User Database: dbs15253086)
    // - SystemLogger uses $contentPdo (Content Database: dbs15161271)
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
    
    // Check if user has required role (vorstand or admin)
    $userRole = $auth->getUserRole();
    if (!in_array($userRole, ['vorstand', 'admin'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Keine Berechtigung. Nur Vorstand und Admins können Alumni-Konten erstellen.'
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
    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Create alumni account
    $result = $auth->createAlumniAccount($email, $firstname, $lastname, $password);
    
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
    error_log("Error in create_alumni_account.php: " . $e->getMessage());
    
    // Return generic error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
    ]);
}
