<?php
declare(strict_types=1);

/**
 * Edit Mode Toggle API Endpoint
 * Handles AJAX requests to toggle edit mode state in session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if user has permission (admin, vorstand, 1v, 2v, 3v, alumni-vorstand, or ressortleiter)
$allowedRoles = ['admin', 'vorstand', '1v', '2v', '3v', 'alumni-vorstand', 'ressortleiter'];
$userRole = $_SESSION['role'] ?? 'none';

if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

if ($action !== 'toggle_edit_mode') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Toggle edit mode in session
$currentState = $_SESSION['edit_mode_active'] ?? false;
$newState = !$currentState;
$_SESSION['edit_mode_active'] = $newState;

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'edit_mode_active' => $newState
]);
