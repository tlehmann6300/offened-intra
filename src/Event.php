<?php
declare(strict_types=1);

/**
 * Event Management Class
 * Handles all CRUD operations for the event system
 * Manages events with dates, locations, and participant tracking
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class Event {
    // Roles allowed to create/edit/delete events
    private const PRIVILEGED_ROLES = ['vorstand', 'ressort'];
    
    private PDO $pdo;
    private string $uploadDir;
    private string $logFile;
    private array $allowedImageTypes;
    private int $maxFileSize;
    private ?NewsService $newsService;
    
    public function __construct(PDO $pdo, ?NewsService $newsService = null) {
        $this->pdo = $pdo;
        $this->newsService = $newsService;
        // Store full path to upload directory
        $this->uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH . 'events/' : BASE_PATH . '/assets/uploads/events/';
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Use config constants with fallback (same pattern as News.php)
        $this->allowedImageTypes = defined('ALLOWED_IMAGE_TYPES') 
            ? array_merge(ALLOWED_IMAGE_TYPES, ['image/gif']) 
            : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $this->maxFileSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5242880; // 5MB default
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get all upcoming (future) events sorted by event date
     * Returns only events where event_date is in the future
     * 
     * @return array List of upcoming events
     */
    public function getUpcoming(): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id, 
                    e.title, 
                    e.description, 
                    e.event_date, 
                    e.location, 
                    e.image_path, 
                    e.max_participants, 
                    e.created_by, 
                    e.created_at,
                    e.updated_at,
                    u.firstname AS creator_firstname,
                    u.lastname AS creator_lastname
                FROM events e
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.event_date > NOW()
                ORDER BY e.event_date ASC
            ");
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching upcoming events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get the next upcoming event for countdown display
     * Returns only the single nearest future event
     * 
     * @return array|null Next upcoming event or null if none exists
     */
    public function getNextEvent(): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id, 
                    e.title, 
                    e.description, 
                    e.event_date, 
                    e.location, 
                    e.image_path, 
                    e.max_participants, 
                    e.created_by, 
                    e.created_at,
                    e.updated_at,
                    u.firstname AS creator_firstname,
                    u.lastname AS creator_lastname
                FROM events e
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.event_date > NOW()
                ORDER BY e.event_date ASC
                LIMIT 1
            ");
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching next event: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get single event by ID
     * 
     * @param int $id Event ID
     * @return array|null Event data or null if not found
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id, 
                    e.title, 
                    e.description, 
                    e.event_date, 
                    e.location, 
                    e.image_path, 
                    e.max_participants, 
                    e.created_by, 
                    e.created_at,
                    e.updated_at,
                    u.firstname AS creator_firstname,
                    u.lastname AS creator_lastname
                FROM events e
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.id = ?
            ");
            
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching event: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save (create or update) an event
     * Only users with 'vorstand' or 'ressort' role can save events
     * 
     * @param array $data Event data (including 'id' for update, 'created_by', 'user_role')
     * @param bool $sendNotification Whether to send email notifications to opted-in users (default: false)
     *                                Note: Actual sending depends on NewsService availability and users with email_opt_in = 1
     * @return int|false Event ID on success, false on failure
     */
    public function save(array $data, bool $sendNotification = false): int|false {
        // Validate required fields
        if (empty($data['title']) || empty($data['description']) || empty($data['event_date'])) {
            $this->log("Error saving event: Title, description and event_date are required");
            return false;
        }
        
        // Check permissions - only 'vorstand' or 'ressort' can save events
        $userRole = $data['user_role'] ?? null;
        if (!in_array($userRole, self::PRIVILEGED_ROLES, true)) {
            $this->log("Permission denied: User with role '{$userRole}' attempted to save event");
            return false;
        }
        
        try {
            // Determine if this is an update or create
            $isUpdate = !empty($data['id']);
            
            if ($isUpdate) {
                // Update existing event
                // Note: updated_at is automatically updated by database ON UPDATE CURRENT_TIMESTAMP
                $stmt = $this->pdo->prepare("
                    UPDATE events 
                    SET title = ?, 
                        description = ?, 
                        event_date = ?, 
                        location = ?, 
                        image_path = ?, 
                        max_participants = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $data['title'],
                    $data['description'],
                    $data['event_date'],
                    $data['location'] ?? null,
                    $data['image_path'] ?? null,
                    $data['max_participants'] ?? null,
                    $data['id']
                ]);
                
                if ($result) {
                    $this->log("Event updated: ID {$data['id']}, Title: {$data['title']}", $data['created_by'] ?? null);
                    
                    // Send email notification to users with email_opt_in = 1 if explicitly requested
                    if ($sendNotification) {
                        $this->sendEventNotificationToOptedInUsers((int)$data['id'], $data['created_by'] ?? null);
                    }
                    
                    return (int)$data['id'];
                }
                
                return false;
            } else {
                // Create new event
                if (empty($data['created_by'])) {
                    $this->log("Error creating event: created_by is required");
                    return false;
                }
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO events (
                        title, description, event_date, location, image_path, 
                        max_participants, created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $data['title'],
                    $data['description'],
                    $data['event_date'],
                    $data['location'] ?? null,
                    $data['image_path'] ?? null,
                    $data['max_participants'] ?? null,
                    $data['created_by']
                ]);
                
                if (!$result) {
                    return false;
                }
                
                $eventId = (int)$this->pdo->lastInsertId();
                $this->log("Event created: ID {$eventId}, Title: {$data['title']}", $data['created_by']);
                
                // Send email notification to users with email_opt_in = 1 if explicitly requested
                if ($sendNotification) {
                    $this->sendEventNotificationToOptedInUsers($eventId, $data['created_by']);
                }
                
                return $eventId;
            }
        } catch (PDOException $e) {
            $this->log("Error saving event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete an event and its associated image file
     * Only users with 'vorstand' or 'ressort' role can delete events
     * 
     * @param int $id Event ID
     * @param string|null $userRole User role performing the deletion
     * @param int|null $userId User ID performing the deletion
     * @return bool Success status
     */
    public function delete(int $id, ?string $userRole = null, ?int $userId = null): bool {
        // Check permissions - only 'vorstand' or 'ressort' can delete events
        if (!in_array($userRole, self::PRIVILEGED_ROLES, true)) {
            $this->log("Permission denied: User with role '{$userRole}' attempted to delete event ID {$id}", $userId);
            return false;
        }
        
        try {
            // Get event to retrieve image path
            $event = $this->getById($id);
            
            if (!$event) {
                $this->log("Error deleting event: Event ID {$id} not found", $userId);
                return false;
            }
            
            // Delete the event from database
            $stmt = $this->pdo->prepare("DELETE FROM events WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Delete associated image file if it exists
                if (!empty($event['image_path'])) {
                    $this->deleteImageFile($event['image_path']);
                }
                
                $this->log("Event deleted: ID {$id}, Title: {$event['title']}", $userId);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error deleting event: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Handle image upload, convert to WebP, and optimize to 1200px width
     * Similar to News module but optimized for event images (landscape format)
     * 
     * @param array $file Uploaded file from $_FILES
     * @param int $eventId Event ID
     * @return string|false Path to uploaded image or false on failure
     */
    public function handleImageUpload(array $file, int $eventId): string|false {
        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("Invalid file upload");
            return false;
        }
        
        // Check file size using configured maximum
        if ($file['size'] > $this->maxFileSize) {
            error_log("File too large: " . $file['size'] . " (max: " . $this->maxFileSize . ")");
            return false;
        }
        
        // Validate image type
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            error_log("Invalid image file");
            return false;
        }
        
        $mimeType = $imageInfo['mime'];
        // Use configured allowed types
        if (!in_array($mimeType, $this->allowedImageTypes, true)) {
            error_log("Unsupported image type: {$mimeType}");
            return false;
        }
        
        // Create image resource from uploaded file
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                break;
        }
        
        if ($sourceImage === false) {
            error_log("Failed to create image resource");
            return false;
        }
        
        // Get source image dimensions
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Validate dimensions
        if ($sourceWidth === false || $sourceHeight === false || $sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($sourceImage);
            error_log("Invalid image dimensions");
            return false;
        }
        
        // Calculate resize dimensions for 16:9 format (1200px x 675px)
        // Target dimensions in 16:9 ratio
        $targetWidth = 1200;
        $targetHeight = 675; // 16:9 ratio: 1200 * 9 / 16 = 675
        
        // Calculate the aspect ratios
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;
        
        // Calculate crop dimensions
        if ($sourceRatio > $targetRatio) {
            // Source is wider - crop width
            $tempHeight = $sourceHeight;
            $tempWidth = (int)($sourceHeight * $targetRatio);
            $cropX = (int)(($sourceWidth - $tempWidth) / 2);
            $cropY = 0;
        } else {
            // Source is taller - crop height
            $tempWidth = $sourceWidth;
            $tempHeight = (int)($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int)(($sourceHeight - $tempHeight) / 2);
        }
        
        // Create destination image
        $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
        
        if ($destImage === false) {
            imagedestroy($sourceImage);
            error_log("Failed to create destination image");
            return false;
        }
        
        // Enable alpha blending for transparent images (PNG, WebP, GIF)
        if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
        }
        
        // Resize and crop image to 16:9 format
        $result = imagecopyresampled(
            $destImage,
            $sourceImage,
            0, 0, // Destination x, y
            $cropX, $cropY, // Source x, y (crop offset)
            $targetWidth, $targetHeight, // Destination width, height
            $tempWidth, $tempHeight // Source width, height (cropped area)
        );
        
        if (!$result) {
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            error_log("Failed to resize image");
            return false;
        }
        
        // Generate unique filename (always .webp)
        $filename = 'event_' . $eventId . '_' . time() . '.webp';
        $filepath = $this->uploadDir . $filename;
        
        // Ensure upload directory exists with secure permissions
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0750, true)) {
                imagedestroy($sourceImage);
                imagedestroy($destImage);
                error_log("Failed to create upload directory");
                return false;
            }
        }
        
        // Save as WebP with 85% quality
        $success = imagewebp($destImage, $filepath, 85);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        if (!$success) {
            error_log("Failed to save WebP image");
            return false;
        }
        
        // Return relative path for database storage
        // Calculate relative path from BASE_PATH
        if (strpos($this->uploadDir, BASE_PATH) === 0) {
            $relativePath = substr($this->uploadDir, strlen(BASE_PATH) + 1) . $filename;
        } else {
            // Fallback: uploadDir is already relative (when UPLOAD_PATH is used)
            $relativePath = str_replace(BASE_PATH . '/', '', $this->uploadDir) . $filename;
        }
        return $relativePath;
    }
    
    /**
     * Delete image file from server
     * 
     * @param string $imagePath Path to the image file
     * @return bool Success status
     */
    private function deleteImageFile(string $imagePath): bool {
        if (empty($imagePath)) {
            return true;
        }
        
        // Construct full path
        $fullPath = BASE_PATH . '/' . ltrim($imagePath, '/');
        
        // Validate path to prevent path traversal attacks
        $basePath = realpath(BASE_PATH);
        $realFullPath = @realpath($fullPath);
        
        // If file doesn't exist, check parent directory
        if ($realFullPath === false && file_exists(dirname($fullPath))) {
            $realDir = realpath(dirname($fullPath));
            if ($realDir !== false) {
                $realFullPath = $realDir . '/' . basename($fullPath);
            }
        }
        
        // Ensure the file is within BASE_PATH
        if ($realFullPath === false || strpos($realFullPath, $basePath) !== 0) {
            error_log("Path traversal attempt blocked: {$imagePath}");
            return false;
        }
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            $result = @unlink($fullPath);
            if ($result) {
                $this->log("Image file deleted: {$imagePath}");
            } else {
                $this->log("Failed to delete image file: {$imagePath}");
            }
            return $result;
        }
        
        return true;
    }
    
    /**
     * Send email notification to users with email_opt_in = 1
     * Helper method to trigger NewsService for event notifications
     * Protected visibility allows for testing and potential subclass extensions
     * 
     * @deprecated Event notifications are no longer sent. This method is kept for compatibility.
     * @param int $eventId Event ID
     * @param int|null $userId User ID performing the action (for logging)
     * @return void
     */
    protected function sendEventNotificationToOptedInUsers(int $eventId, ?int $userId = null): void {
        // Event notifications have been deprecated - only project notifications are sent
        $this->log("Event notifications disabled - event ID {$eventId} created but no emails sent", $userId);
    }
    
    
    /**
     * Log message to application log file
     * 
     * @param string $message Message to log
     * @param int|null $userId User ID performing the action
     */
    private function log(string $message, ?int $userId = null): void {
        $timestamp = date('Y-m-d H:i:s');
        
        // Get client IP
        $ip = 'unknown';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize IP for logging
        $ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ? $ip : 'invalid';
        
        $userInfo = $userId ? "User ID: {$userId}" : 'User ID: unknown';
        $logMessage = "[{$timestamp}] [IP: {$ip}] [{$userInfo}] [EVENT] {$message}" . PHP_EOL;
        
        // Validate log file path to prevent path traversal
        $logDir = dirname($this->logFile);
        $basePath = realpath(BASE_PATH);
        $realLogDir = realpath($logDir);
        
        // Ensure log directory is within BASE_PATH
        if ($realLogDir === false || strpos($realLogDir, $basePath) !== 0) {
            error_log("Invalid log file path: {$this->logFile}. Path traversal attempt blocked.");
            return;
        }
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
}
