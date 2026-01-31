<?php
declare(strict_types=1);

/**
 * News Management Class
 * Handles all CRUD operations for the news system
 * Manages news articles, subscriptions, and permissions
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class News {
    // Roles allowed to create/edit/delete news
    private const PRIVILEGED_ROLES = ['vorstand', 'ressort'];
    
    private PDO $pdo;
    private string $uploadDir;
    private string $logFile;
    private array $allowedImageTypes;
    private int $maxFileSize;
    private ?NewsService $newsService;
    private ?SystemLogger $systemLogger;
    
    public function __construct(PDO $pdo, ?NewsService $newsService = null, ?SystemLogger $systemLogger = null) {
        $this->pdo = $pdo;
        $this->newsService = $newsService;
        $this->systemLogger = $systemLogger;
        // Store full path to upload directory
        $this->uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH . 'news/' : BASE_PATH . '/assets/uploads/news/';
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Use config constants with fallback (same pattern as Inventory.php)
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
     * Get latest news articles with pagination support for AJAX archive
     * 
     * @param int $limit Maximum number of articles to return
     * @param int $offset Starting position for pagination
     * @return array List of news articles
     */
    public function getLatest(int $limit = 10, int $offset = 0): array {
        try {
            // Validate parameters to prevent abuse
            $limit = max(1, min($limit, 100)); // Between 1 and 100
            $offset = max(0, $offset); // Non-negative
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.id, 
                    n.title, 
                    n.content, 
                    n.image_path, 
                    n.cta_link, 
                    n.cta_label, 
                    n.author_id, 
                    n.category, 
                    n.created_at,
                    n.updated_at,
                    u.firstname AS author_firstname,
                    u.lastname AS author_lastname
                FROM news n
                LEFT JOIN users u ON n.author_id = u.id
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$limit, $offset]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching latest news: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get the featured (most recent) news article for highlight layout
     * 
     * @return array|null Featured news article or null if none exists
     */
    public function getFeatured(): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.id, 
                    n.title, 
                    n.content, 
                    n.image_path, 
                    n.cta_link, 
                    n.cta_label, 
                    n.author_id, 
                    n.category, 
                    n.created_at,
                    n.updated_at,
                    u.firstname AS author_firstname,
                    u.lastname AS author_lastname
                FROM news n
                LEFT JOIN users u ON n.author_id = u.id
                ORDER BY n.created_at DESC
                LIMIT 1
            ");
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching featured news: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get single news article by ID
     * 
     * @param int $id News article ID
     * @return array|null News article data or null if not found
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.id, 
                    n.title, 
                    n.content, 
                    n.image_path, 
                    n.cta_link, 
                    n.cta_label, 
                    n.author_id, 
                    n.category, 
                    n.created_at,
                    n.updated_at,
                    u.firstname AS author_firstname,
                    u.lastname AS author_lastname
                FROM news n
                LEFT JOIN users u ON n.author_id = u.id
                WHERE n.id = ?
            ");
            
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching news article: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save (create or update) a news article
     * Only users with 'vorstand' or 'ressort' role can save news
     * 
     * @param array $data News article data (including 'id' for update, 'author_id', 'user_role')
     * @param bool $sendNotification Whether to send email notifications to subscribers (default: false)
     *                                Note: Actual sending depends on NewsService availability and active subscribers
     * @return int|false News ID on success, false on failure
     */
    public function save(array $data, bool $sendNotification = false): int|false {
        // Validate required fields
        if (empty($data['title']) || empty($data['content'])) {
            $this->log("Error saving news: Title and content are required");
            return false;
        }
        
        // Check permissions - only 'vorstand' or 'ressort' can save news
        $userRole = $data['user_role'] ?? null;
        if (!in_array($userRole, self::PRIVILEGED_ROLES, true)) {
            $this->log("Permission denied: User with role '{$userRole}' attempted to save news");
            return false;
        }
        
        try {
            // Determine if this is an update or create
            $isUpdate = !empty($data['id']);
            
            if ($isUpdate) {
                // Update existing news article
                // Note: updated_at is automatically updated by database ON UPDATE CURRENT_TIMESTAMP
                $stmt = $this->pdo->prepare("
                    UPDATE news 
                    SET title = ?, 
                        content = ?, 
                        image_path = ?, 
                        cta_link = ?, 
                        cta_label = ?, 
                        category = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $data['title'],
                    $data['content'],
                    $data['image_path'] ?? null,
                    $data['cta_link'] ?? null,
                    $data['cta_label'] ?? null,
                    $data['category'] ?? null,
                    $data['id']
                ]);
                
                if ($result) {
                    $newsId = (int)$data['id'];
                    
                    // Log to system_logs
                    if ($this->systemLogger !== null && isset($data['author_id'])) {
                        $this->systemLogger->log((int)$data['author_id'], 'update', 'news', $newsId);
                    }
                    
                    $this->log("News article updated: ID {$newsId}, Title: {$data['title']}", $data['author_id'] ?? null);
                    
                    // Send email notification to subscribers only if explicitly requested
                    if ($sendNotification) {
                        $this->sendNewsNotificationToSubscribers($newsId, $data['author_id'] ?? null);
                    }
                    
                    return $newsId;
                }
                
                return false;
            } else {
                // Create new news article
                if (empty($data['author_id'])) {
                    $this->log("Error creating news: Author ID is required");
                    return false;
                }
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO news (
                        title, content, image_path, cta_link, cta_label, 
                        author_id, category
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $data['title'],
                    $data['content'],
                    $data['image_path'] ?? null,
                    $data['cta_link'] ?? null,
                    $data['cta_label'] ?? null,
                    $data['author_id'],
                    $data['category'] ?? null
                ]);
                
                if (!$result) {
                    return false;
                }
                
                $newsId = (int)$this->pdo->lastInsertId();
                
                // Log to system_logs
                if ($this->systemLogger !== null) {
                    $this->systemLogger->log((int)$data['author_id'], 'create', 'news', $newsId);
                }
                
                $this->log("News article created: ID {$newsId}, Title: {$data['title']}", $data['author_id']);
                
                // Send email notification to subscribers only if explicitly requested
                if ($sendNotification) {
                    $this->sendNewsNotificationToSubscribers($newsId, $data['author_id']);
                }
                
                return $newsId;
            }
        } catch (PDOException $e) {
            $this->log("Error saving news article: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a news article and its associated image file
     * Only users with 'vorstand' or 'ressort' role can delete news
     * 
     * @param int $id News article ID
     * @param string|null $userRole User role performing the deletion
     * @param int|null $userId User ID performing the deletion
     * @return bool Success status
     */
    public function delete(int $id, ?string $userRole = null, ?int $userId = null): bool {
        // Check permissions - only 'vorstand' or 'ressort' can delete news
        if (!in_array($userRole, self::PRIVILEGED_ROLES, true)) {
            $this->log("Permission denied: User with role '{$userRole}' attempted to delete news ID {$id}", $userId);
            return false;
        }
        
        try {
            // Get news article to retrieve image path
            $news = $this->getById($id);
            
            if (!$news) {
                $this->log("Error deleting news: Article ID {$id} not found", $userId);
                return false;
            }
            
            // Delete the news article from database
            $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Delete associated image file if it exists
                if (!empty($news['image_path'])) {
                    $this->deleteImageFile($news['image_path']);
                }
                
                // Log to system_logs
                if ($this->systemLogger !== null && $userId !== null) {
                    $this->systemLogger->log($userId, 'delete', 'news', $id);
                }
                
                $this->log("News article deleted: ID {$id}, Title: {$news['title']}", $userId);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error deleting news article: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Handle image upload, convert to WebP, and optimize to 1200px width
     * Similar to Alumni module but optimized for news images (landscape format)
     * 
     * @param array $file Uploaded file from $_FILES
     * @param int $newsId News article ID
     * @return string|false Path to uploaded image or false on failure
     */
    public function handleImageUpload(array $file, int $newsId): string|false {
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
        
        // Calculate resize dimensions maintaining aspect ratio
        // Target width is 1200px, height is calculated proportionally
        $targetWidth = 1200;
        
        // Only resize if image is wider than target
        if ($sourceWidth > $targetWidth) {
            $targetHeight = (int)(($targetWidth / $sourceWidth) * $sourceHeight);
        } else {
            // Image is already smaller, keep original dimensions
            $targetWidth = $sourceWidth;
            $targetHeight = $sourceHeight;
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
        
        // Resize image maintaining aspect ratio
        $result = imagecopyresampled(
            $destImage,
            $sourceImage,
            0, 0, // Destination x, y
            0, 0, // Source x, y
            $targetWidth, $targetHeight, // Destination width, height
            $sourceWidth, $sourceHeight // Source width, height
        );
        
        if (!$result) {
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            error_log("Failed to resize image");
            return false;
        }
        
        // Generate unique filename (always .webp)
        $filename = 'news_' . $newsId . '_' . time() . '.webp';
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
        
        // Validate path to prevent path traversal attacks (consistent with log method)
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
     * Subscribe user to news notifications
     * 
     * @param int $userId User ID to subscribe
     * @return bool Success status
     */
    public function subscribe(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO news_subscribers (user_id)
                VALUES (?)
                ON DUPLICATE KEY UPDATE subscribed_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                $this->log("User subscribed to news: User ID {$userId}", $userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error subscribing to news: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Unsubscribe user from news notifications
     * 
     * @param int $userId User ID to unsubscribe
     * @return bool Success status
     */
    public function unsubscribe(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM news_subscribers WHERE user_id = ?");
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                $this->log("User unsubscribed from news: User ID {$userId}", $userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error unsubscribing from news: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Check if user is subscribed to news
     * 
     * @param int $userId User ID to check
     * @return bool True if subscribed, false otherwise
     */
    public function isSubscribed(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM news_subscribers WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking subscription status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all subscribers for news notifications
     * 
     * @return array List of subscriber user IDs
     */
    public function getSubscribers(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT ns.user_id, u.email, u.firstname, u.lastname
                FROM news_subscribers ns
                JOIN users u ON ns.user_id = u.id
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching subscribers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send email notification to subscribers
     * Helper method to avoid code duplication in save()
     * Note: This method should only be called when notifications are actually needed
     * Protected visibility allows for testing and potential subclass extensions
     * 
     * @deprecated News notifications are no longer sent. This method is kept for compatibility.
     * @param int $newsId News article ID
     * @param int|null $userId User ID performing the action (for logging)
     * @return void
     */
    protected function sendNewsNotificationToSubscribers(int $newsId, ?int $userId = null): void {
        // News notifications have been deprecated - only project notifications are sent
        $this->log("News notifications disabled - news ID {$newsId} created but no emails sent", $userId);
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
        $logMessage = "[{$timestamp}] [IP: {$ip}] [{$userInfo}] [NEWS] {$message}" . PHP_EOL;
        
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
