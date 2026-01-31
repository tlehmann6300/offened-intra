<?php
declare(strict_types=1);

/**
 * Central API Router
 * 
 * This file serves as a centralized entry point for all AJAX/API requests.
 * It enforces security measures including:
 * - Mandatory 'action' parameter validation
 * - CSRF token verification for POST/DELETE/PUT requests
 * - Role-based permission checking before executing actions
 * - Comprehensive error handling and logging
 * 
 * Security Note: Frontend button visibility should NEVER be the only security barrier.
 * All API actions MUST validate permissions server-side using Auth::checkPermission()
 */

// Set JSON response header early
header('Content-Type: application/json');

// Wrap entire execution in try-catch to prevent PHP fatals from being exposed
try {
    // Load configuration and dependencies
    require_once __DIR__ . '/../config/config.php';
    require_once BASE_PATH . '/config/db.php';
    require_once BASE_PATH . '/src/Auth.php';
    require_once BASE_PATH . '/src/SystemLogger.php';

    // Initialize core services
    $pdo = Database::getConnection();
    $systemLogger = new SystemLogger($pdo);
    $auth = new Auth($pdo, $systemLogger);
} catch (Throwable $e) {
    // Critical error during initialization - log and return JSON error
    error_log("API Router initialization error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Systemfehler beim Initialisieren der API. Bitte kontaktieren Sie den Administrator.'
    ]);
    exit;
}

/**
 * Send JSON response and exit
 * 
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Additional data (optional)
 * @param int $httpCode HTTP status code (default: 200)
 */
function sendResponse(bool $success, string $message, array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Log API request for audit trail
 * 
 * @param string $action The action being performed
 * @param string $status Status (SUCCESS, FAILED, DENIED)
 * @param string|null $details Additional details
 */
function logApiRequest(string $action, string $status, ?string $details = null): void {
    global $auth;
    $userId = $auth->getUserId() ?? 'anonymous';
    $role = $auth->getUserRole() ?? 'none';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $logMessage = "[{$timestamp}] API [{$status}] User: {$userId} (Role: {$role}) | IP: {$ip} | Action: {$action}";
    if ($details) {
        $logMessage .= " | Details: {$details}";
    }
    
    error_log($logMessage);
}

// ============================================================================
// REQUEST PROCESSING - All wrapped in try-catch for structured error handling
// ============================================================================

try {
    // ============================================================================
    // 1. REQUEST METHOD VALIDATION
    // ============================================================================
    
    // Only allow POST, DELETE, PUT, and GET requests
    $method = $_SERVER['REQUEST_METHOD'];
    $allowedMethods = ['POST', 'DELETE', 'PUT', 'GET'];
    
    if (!in_array($method, $allowedMethods, true)) {
        logApiRequest('INVALID_METHOD', 'FAILED', "Method: {$method}");
        sendResponse(false, 'HTTP-Methode nicht erlaubt', [], 405);
    }
    
    // ============================================================================
    // 2. USER AUTHENTICATION CHECK
    // ============================================================================
    
    if (!$auth->isLoggedIn()) {
        logApiRequest('AUTHENTICATION', 'FAILED', 'User not logged in');
        sendResponse(false, 'Nicht angemeldet. Bitte melden Sie sich an.', [], 401);
    }
    
    // ============================================================================
    // 3. ACTION PARAMETER VALIDATION
    // ============================================================================
    
    // Get action from appropriate superglobal based on method
    $action = null;
    switch ($method) {
        case 'POST':
            $action = $_POST['action'] ?? null;
            break;
        case 'GET':
            $action = $_GET['action'] ?? null;
            break;
        case 'DELETE':
        case 'PUT':
            // Parse php://input for DELETE/PUT requests with size limit to prevent DoS
            $maxInputSize = 10 * 1024 * 1024; // 10 MB limit
            $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            
            if ($contentLength > $maxInputSize) {
                logApiRequest('OVERSIZED_REQUEST', 'FAILED', "Content-Length: {$contentLength} bytes");
                sendResponse(false, 'Anfrage zu groß. Maximale Größe: 10 MB', [], 413);
            }
            
            // Use stream_get_contents with actual byte limit to prevent spoofed Content-Length
            $handle = fopen('php://input', 'r');
            $rawInput = stream_get_contents($handle, $maxInputSize);
            fclose($handle);
            
            if ($rawInput === false) {
                logApiRequest('READ_ERROR', 'FAILED', 'Failed to read request body');
                sendResponse(false, 'Fehler beim Lesen der Anfrage', [], 400);
            }
            
            parse_str($rawInput, $requestData);
            $action = $requestData['action'] ?? null;
            break;
    }
    
    // Action parameter is mandatory
    if (empty($action)) {
        logApiRequest('MISSING_ACTION', 'FAILED', "Method: {$method}");
        sendResponse(false, 'Fehlender Action-Parameter. Alle API-Anfragen müssen eine action enthalten.', [], 400);
    }
    
    // Sanitize action
    $action = trim($action);
    
    // ============================================================================
    // 4. CSRF TOKEN VALIDATION (for state-changing requests)
    // ============================================================================
    
    // CSRF validation required for POST, DELETE, PUT
    $statefulMethods = ['POST', 'DELETE', 'PUT'];
    if (in_array($method, $statefulMethods, true)) {
        $csrfToken = null;
        
        // Try to get CSRF token from header first (modern approach)
        $headers = getallheaders();
        if ($headers && is_array($headers)) {
            // Case-insensitive header lookup
            $headerKeys = array_change_key_case($headers, CASE_LOWER);
            $csrfToken = $headerKeys['x-csrf-token'] ?? null;
        }
        
        if (!$csrfToken) {
            // Fallback to POST data or request body for backward compatibility
            switch ($method) {
                case 'POST':
                    $csrfToken = $_POST['csrf_token'] ?? null;
                    break;
                case 'DELETE':
                case 'PUT':
                    $csrfToken = $requestData['csrf_token'] ?? null;
                    break;
            }
        }
        
        if (!$csrfToken || !$auth->verifyCsrfToken($csrfToken)) {
            logApiRequest($action, 'FAILED', 'Invalid CSRF token');
            sendResponse(false, 'Ungültiges CSRF-Token. Bitte laden Sie die Seite neu.', [], 403);
        }
    }

// ============================================================================
// 5. ACTION ROUTING & EXECUTION
// ============================================================================

try {
    // Route to appropriate handler based on action
    switch ($action) {
        
        // ====================================================================
        // NOTIFICATION ACTIONS
        // ====================================================================
        
        case 'get_helper_requests':
            // Any logged-in user can view helper requests
            require_once BASE_PATH . '/src/NotificationService.php';
            $notificationService = new NotificationService($pdo);
            $helperRequests = $notificationService->getNewHelperRequests(10);
            
            logApiRequest($action, 'SUCCESS');
            sendResponse(true, 'Helper requests abgerufen', ['requests' => $helperRequests]);
            break;
            
        case 'mark_notifications_read':
            // Any logged-in user can mark their own notifications as read
            require_once BASE_PATH . '/src/NotificationService.php';
            $notificationService = new NotificationService($pdo);
            $userId = $auth->getUserId();
            $result = $notificationService->markHelperUpdatesAsRead($userId);
            
            logApiRequest($action, 'SUCCESS');
            sendResponse($result['success'], $result['message']);
            break;
            
        case 'check_notification_status':
            // Any logged-in user can check their notification status
            require_once BASE_PATH . '/src/NotificationService.php';
            $notificationService = new NotificationService($pdo);
            $userId = $auth->getUserId();
            $hasUpdate = $notificationService->hasHelperUpdate($userId);
            
            logApiRequest($action, 'SUCCESS');
            sendResponse(true, 'Status abgerufen', ['hasUpdate' => $hasUpdate]);
            break;
        
        // ====================================================================
        // INVENTORY ACTIONS
        // ====================================================================
        
        case 'inventory_search':
            // Any logged-in user can search inventory (read-only)
            require_once BASE_PATH . '/src/Inventory.php';
            $inventory = new Inventory($pdo, $systemLogger);
            
            // Sanitize and validate search input
            $search = null;
            if (isset($_POST['search']) || isset($_GET['search'])) {
                $searchInput = $_POST['search'] ?? $_GET['search'] ?? '';
                $search = trim($searchInput);
                // Limit search string length
                if (strlen($search) > 100) {
                    $search = substr($search, 0, 100);
                }
            }
            
            // Validate filter values against allowed values
            $allowedCategories = ['all', 'electronics', 'furniture', 'supplies', 'equipment', 'other'];
            $allowedLocations = ['all', 'office', 'warehouse', 'archive', 'storage', 'other'];
            $allowedStatuses = ['all', 'available', 'in_use', 'maintenance', 'retired'];
            
            $category = $_POST['category'] ?? $_GET['category'] ?? 'all';
            $location = $_POST['location'] ?? $_GET['location'] ?? 'all';
            $status = $_POST['status'] ?? $_GET['status'] ?? 'all';
            
            // Sanitize filter values - only allow predefined values
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'all';
            }
            if (!in_array($location, $allowedLocations, true)) {
                $location = 'all';
            }
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'all';
            }
            
            $filters = [
                'category' => $category,
                'location' => $location,
                'status' => $status
            ];
            
            $items = $inventory->getAll($search, $filters);
            
            logApiRequest($action, 'SUCCESS');
            sendResponse(true, 'Inventar durchsucht', ['items' => $items, 'count' => count($items)]);
            break;
            
        case 'inventory_create':
            // Requires 'alumni' role or higher
            if (!$auth->checkPermission('alumni')) {
                logApiRequest($action, 'DENIED', 'Insufficient permissions');
                sendResponse(false, 'Keine Berechtigung zum Erstellen von Inventareinträgen', [], 403);
            }
            
            require_once BASE_PATH . '/src/Inventory.php';
            $inventory = new Inventory($pdo, $systemLogger);
            
            // Validate required fields
            if (empty($_POST['name'])) {
                sendResponse(false, 'Name ist erforderlich', [], 400);
            }
            
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $inventory->handleImageUpload($_FILES['image']);
                if (!$uploadResult['success']) {
                    logApiRequest($action, 'FAILED', 'Image upload failed');
                    sendResponse(false, $uploadResult['message'], [], 400);
                }
                $imagePath = $uploadResult['path'];
            }
            
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? '',
                'category' => $_POST['category'] ?? '',
                'quantity' => (int)($_POST['quantity'] ?? 0),
                'image_path' => $imagePath,
                'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                'tags' => !empty($_POST['tags']) ? $_POST['tags'] : null
            ];
            
            $itemId = $inventory->create($data, $auth->getUserId());
            
            if ($itemId) {
                logApiRequest($action, 'SUCCESS', "Item ID: {$itemId}");
                sendResponse(true, 'Gegenstand erfolgreich erstellt', ['id' => $itemId]);
            } else {
                if ($imagePath) {
                    $inventory->deleteImage($imagePath);
                }
                logApiRequest($action, 'FAILED', 'Database error');
                sendResponse(false, 'Fehler beim Erstellen', [], 500);
            }
            break;
            
        case 'inventory_update':
            // Requires 'alumni' role or higher
            if (!$auth->checkPermission('alumni')) {
                logApiRequest($action, 'DENIED', 'Insufficient permissions');
                sendResponse(false, 'Keine Berechtigung zum Aktualisieren von Inventareinträgen', [], 403);
            }
            
            require_once BASE_PATH . '/src/Inventory.php';
            $inventory = new Inventory($pdo, $systemLogger);
            
            $itemId = (int)($_POST['id'] ?? 0);
            if (empty($_POST['name']) || $itemId <= 0) {
                sendResponse(false, 'Name und ID sind erforderlich', [], 400);
            }
            
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? '',
                'category' => $_POST['category'] ?? '',
                'quantity' => (int)($_POST['quantity'] ?? 0),
                'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                'tags' => !empty($_POST['tags']) ? $_POST['tags'] : null
            ];
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $inventory->handleImageUpload($_FILES['image']);
                if (!$uploadResult['success']) {
                    logApiRequest($action, 'FAILED', 'Image upload failed');
                    sendResponse(false, $uploadResult['message'], [], 400);
                }
                $data['image_path'] = $uploadResult['path'];
                
                // Get old item for cleanup
                $oldItem = $inventory->getById($itemId);
            }
            
            $success = $inventory->update($itemId, $data);
            
            if ($success) {
                // Clean up old image if new one was uploaded
                if (isset($oldItem) && !empty($oldItem['image_path'])) {
                    $inventory->deleteImage($oldItem['image_path']);
                }
                logApiRequest($action, 'SUCCESS', "Item ID: {$itemId}");
                sendResponse(true, 'Gegenstand erfolgreich aktualisiert');
            } else {
                // Clean up new image on failure
                if (isset($data['image_path'])) {
                    $inventory->deleteImage($data['image_path']);
                }
                logApiRequest($action, 'FAILED', 'Database error');
                sendResponse(false, 'Fehler beim Aktualisieren', [], 500);
            }
            break;
            
        case 'inventory_delete':
            // Requires 'alumni' role or higher
            if (!$auth->checkPermission('alumni')) {
                logApiRequest($action, 'DENIED', 'Insufficient permissions');
                sendResponse(false, 'Keine Berechtigung zum Löschen von Inventareinträgen', [], 403);
            }
            
            require_once BASE_PATH . '/src/Inventory.php';
            $inventory = new Inventory($pdo, $systemLogger);
            
            $itemId = (int)($_POST['id'] ?? 0);
            if ($itemId <= 0) {
                sendResponse(false, 'Ungültige ID', [], 400);
            }
            
            $success = $inventory->delete($itemId);
            
            if ($success) {
                logApiRequest($action, 'SUCCESS', "Item ID: {$itemId}");
                sendResponse(true, 'Gegenstand erfolgreich gelöscht');
            } else {
                logApiRequest($action, 'FAILED', 'Database error');
                sendResponse(false, 'Fehler beim Löschen', [], 500);
            }
            break;
        
        // ====================================================================
        // DEFAULT: Unknown Action
        // ====================================================================
        
        default:
            logApiRequest($action, 'FAILED', 'Unknown action');
            sendResponse(false, "Unbekannte Aktion: {$action}", [], 400);
    }
    
} catch (RuntimeException $e) {
    // Handle authentication/permission errors
    $action = $action ?? 'UNKNOWN';
    logApiRequest($action, 'DENIED', $e->getMessage());
    // Use generic error message for client, detailed message logged server-side
    sendResponse(false, 'Zugriff verweigert. Bitte überprüfen Sie Ihre Anmeldung und Berechtigungen.', [], 403);
    
} catch (Exception $e) {
    // Handle unexpected errors
    // Log detailed error server-side
    $action = $action ?? 'UNKNOWN';
    logApiRequest($action, 'ERROR', $e->getMessage());
    error_log("API Router Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    // Return generic error message to client to avoid information leakage
    sendResponse(false, 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.', [], 500);
}

} catch (Throwable $e) {
    // Outermost catch-all for any throwable (including PHP errors in PHP 7+)
    // This ensures no PHP fatal errors are exposed to the client
    error_log("API Router Critical Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein kritischer Systemfehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.'
    ]);
    exit;
}
