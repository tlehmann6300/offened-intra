<?php
declare(strict_types=1);

/**
 * Alumni Management Class
 * Handles all CRUD operations for the alumni system
 * Manages alumni profiles, networking, and engagement features
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class Alumni {
    // Roles allowed to edit any profile (not just their own)
    private const PRIVILEGED_ROLES = ['admin', 'vorstand', 'ressortleiter'];
    
    private PDO $pdo;
    private string $logFile;
    private ?SystemLogger $systemLogger;
    
    public function __construct(PDO $pdo, ?SystemLogger $systemLogger = null) {
        $this->pdo = $pdo;
        $this->systemLogger = $systemLogger;
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get all active/published alumni profiles
     * Only returns profiles where is_published = 1
     * 
     * @return array List of published alumni profiles
     */
    public function getAllActive(): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM alumni 
                WHERE is_published = 1
                ORDER BY lastname ASC, firstname ASC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching active alumni: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all alumni profiles with optional search and filter
     * 
     * @param string|null $search Search term for name, company, or position
     * @param array $filters Additional filters (graduation_year, industry, location)
     * @return array List of alumni profiles
     */
    public function getAll(?string $search = null, array $filters = []): array {
        try {
            $sql = "SELECT * FROM alumni WHERE 1=1";
            $params = [];
            
            // Add search filter with length limit
            if ($search) {
                // Limit search term length to prevent performance issues
                $search = substr(trim($search), 0, 255);
                $sql .= " AND (firstname LIKE ? OR lastname LIKE ? OR company LIKE ? OR position LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Add graduation year filter
            if (!empty($filters['graduation_year'])) {
                $sql .= " AND graduation_year = ?";
                $params[] = $filters['graduation_year'];
            }
            
            // Add industry filter
            if (!empty($filters['industry']) && $filters['industry'] !== 'all') {
                $sql .= " AND industry = ?";
                $params[] = $filters['industry'];
            }
            
            // Add location filter
            if (!empty($filters['location']) && $filters['location'] !== 'all') {
                $sql .= " AND location = ?";
                $params[] = $filters['location'];
            }
            
            $sql .= " ORDER BY lastname ASC, firstname ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching alumni: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single alumni profile by ID
     * 
     * @param int $id Alumni ID
     * @return array|null Alumni data or null if not found
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM alumni WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching alumni profile: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new alumni profile
     * 
     * @param array $data Alumni data
     * @param int $userId User ID creating the profile
     * @return int|false New profile ID or false on failure
     */
    public function create(array $data, int $userId): int|false {
        // Validate required fields
        if (empty($data['firstname']) || empty($data['lastname'])) {
            $this->log("Error creating alumni profile: First name and last name are required", $userId);
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO alumni (
                    firstname, lastname, email, phone, 
                    company, position, industry, location,
                    graduation_year, bio, linkedin_url, profile_picture,
                    is_published, created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['firstname'] ?? '',
                $data['lastname'] ?? '',
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['company'] ?? null,
                $data['position'] ?? null,
                $data['industry'] ?? null,
                $data['location'] ?? null,
                $data['graduation_year'] ?? null,
                $data['bio'] ?? null,
                $data['linkedin_url'] ?? null,
                $data['profile_picture'] ?? null,
                $data['is_published'] ?? 0,
                $userId
            ]);
            
            if (!$result) {
                return false;
            }
            
            $alumniId = (int)$this->pdo->lastInsertId();
            
            // Log to system_logs
            if ($this->systemLogger !== null) {
                $this->systemLogger->log($userId, 'create', 'alumni', $alumniId);
            }
            
            $this->log("Alumni profile created: ID {$alumniId}, Name: {$data['firstname']} {$data['lastname']}", $userId);
            
            return $alumniId;
            
        } catch (PDOException $e) {
            $this->log("Error creating alumni profile: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Update existing alumni profile
     * 
     * @param int $id Alumni ID
     * @param array $data Updated profile data
     * @param int|null $userId User ID performing the update
     * @return bool Success status
     */
    public function update(int $id, array $data, ?int $userId = null): bool {
        // Build dynamic update query based on provided data
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'firstname', 'lastname', 'email', 'phone',
            'company', 'position', 'industry', 'location',
            'graduation_year', 'bio', 'linkedin_url', 'profile_picture', 'is_published'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        try {
            $values[] = $id;
            $sql = "UPDATE alumni SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                // Log to system_logs
                if ($this->systemLogger !== null && $userId !== null) {
                    $this->systemLogger->log($userId, 'update', 'alumni', $id);
                }
                
                $alumniName = ($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? '');
                $this->log("Alumni profile updated: {$alumniName}", $userId);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->log("Error updating alumni profile: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Update alumni profile with strict permission checking
     * Users can only update their own profile unless they have admin/vorstand/ressort role
     * 
     * @param int $userId User ID attempting to make the update
     * @param array $data Updated profile data (including 'id' field for the profile to update)
     * @return bool Success status
     */
    public function updateProfile(int $userId, array $data): bool {
        // Validate that profile ID is provided
        if (!isset($data['id'])) {
            $this->log("Error updating profile: No profile ID provided", $userId);
            return false;
        }
        
        $profileId = (int)$data['id'];
        
        // Get the alumni profile to check ownership
        $profile = $this->getById($profileId);
        if (!$profile) {
            $this->log("Error updating profile: Profile ID {$profileId} not found", $userId);
            return false;
        }
        
        // Get user role from database
        try {
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->log("Error updating profile: User ID {$userId} not found", $userId);
                return false;
            }
            
            $userRole = $user['role'];
            
            // Check permissions: user must own the profile OR have admin/vorstand/ressort role
            $hasAdminAccess = in_array($userRole, self::PRIVILEGED_ROLES, true);
            $isOwnProfile = (int)$profile['created_by'] === $userId;
            
            if (!$hasAdminAccess && !$isOwnProfile) {
                $this->log("Permission denied: User ID {$userId} (role: {$userRole}) attempted to update profile ID {$profileId}", $userId);
                return false;
            }
            
            // Remove 'id' from data array before passing to update method
            unset($data['id']);
            
            // Call the update method
            $result = $this->update($profileId, $data, $userId);
            
            if ($result) {
                $this->log("Profile updated via updateProfile: Profile ID {$profileId} by User ID {$userId} (role: {$userRole})", $userId);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->log("Error in updateProfile: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Delete alumni profile
     * 
     * @param int $id Alumni ID
     * @param int|null $userId User ID performing the deletion
     * @return bool Success status
     */
    public function delete(int $id, ?int $userId = null): bool {
        try {
            // Get profile info for logging
            $alumni = $this->getById($id);
            $alumniName = $alumni ? "{$alumni['firstname']} {$alumni['lastname']}" : "ID {$id}";
            
            $stmt = $this->pdo->prepare("DELETE FROM alumni WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Log to system_logs
                if ($this->systemLogger !== null && $userId !== null) {
                    $this->systemLogger->log($userId, 'delete', 'alumni', $id);
                }
                
                $this->log("Alumni profile deleted: {$alumniName}", $userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error deleting alumni profile: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Get alumni statistics
     * 
     * @return array Statistics data
     */
    public function getStatistics(): array {
        try {
            $stats = [];
            
            // Total alumni
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM alumni");
            $stats['total_alumni'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Alumni by industry
            $stmt = $this->pdo->query("
                SELECT industry, COUNT(*) as count 
                FROM alumni 
                WHERE industry IS NOT NULL AND industry != ''
                GROUP BY industry
                ORDER BY count DESC
                LIMIT 5
            ");
            $stats['by_industry'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Alumni by graduation year
            $stmt = $this->pdo->query("
                SELECT graduation_year, COUNT(*) as count 
                FROM alumni 
                WHERE graduation_year IS NOT NULL
                GROUP BY graduation_year
                ORDER BY graduation_year DESC
                LIMIT 10
            ");
            $stats['by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error fetching alumni statistics: " . $e->getMessage());
            return [
                'total_alumni' => 0,
                'by_industry' => [],
                'by_year' => []
            ];
        }
    }
    
    /**
     * Handle image upload, convert to WebP, and crop to square
     * 
     * @param array $file Uploaded file from $_FILES
     * @param int $alumniId Alumni profile ID
     * @return string|false Path to uploaded image or false on failure
     */
    public function handleImageUpload(array $file, int $alumniId): string|false {
        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("Invalid file upload");
            return false;
        }
        
        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            error_log("File too large: " . $file['size']);
            return false;
        }
        
        // Validate image type
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            error_log("Invalid image file");
            return false;
        }
        
        $mimeType = $imageInfo['mime'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
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
        
        // Calculate crop dimensions for square (500x500)
        // This implements CSS "object-fit: cover" behavior:
        // - Takes the smaller dimension to ensure the entire square is filled
        // - Centers the crop to avoid cutting off important parts
        // - Maintains aspect ratio without distortion
        $targetSize = 500;
        $cropSize = min($sourceWidth, $sourceHeight);
        
        // Calculate crop position (center crop for object-fit: cover behavior)
        $cropX = ($sourceWidth - $cropSize) / 2;
        $cropY = ($sourceHeight - $cropSize) / 2;
        
        // Create destination image
        $destImage = imagecreatetruecolor($targetSize, $targetSize);
        
        if ($destImage === false) {
            imagedestroy($sourceImage);
            error_log("Failed to create destination image");
            return false;
        }
        
        // Enable alpha blending for transparent images (PNG, WebP, GIF)
        // Only apply to image types that support transparency
        if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
        }
        
        // Crop and resize to square (object-fit: cover equivalent)
        // This resamples the image to maintain quality during resize
        $result = imagecopyresampled(
            $destImage,
            $sourceImage,
            0, 0, // Destination x, y
            (int)$cropX, (int)$cropY, // Source x, y (centered crop)
            $targetSize, $targetSize, // Destination width, height
            $cropSize, $cropSize // Source width, height (maintains aspect ratio)
        );
        
        if (!$result) {
            imagedestroy($sourceImage);
            imagedestroy($destImage);
            error_log("Failed to crop/resize image");
            return false;
        }
        
        // Generate unique filename
        $filename = 'alumni_' . $alumniId . '_' . time() . '.webp';
        $uploadDir = BASE_PATH . '/assets/uploads/alumni/';
        $filepath = $uploadDir . $filename;
        
        // Ensure upload directory exists with secure permissions
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0750, true)) {
                imagedestroy($sourceImage);
                imagedestroy($destImage);
                error_log("Failed to create upload directory");
                return false;
            }
        }
        
        // Save as WebP
        $success = imagewebp($destImage, $filepath, 85); // 85% quality
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        if (!$success) {
            error_log("Failed to save WebP image");
            return false;
        }
        
        // Return relative path for database storage
        return 'assets/uploads/alumni/' . $filename;
    }
    
    /**
     * Delete old profile picture file
     * 
     * @param string $picturePath Path to the picture file
     * @return bool Success status
     */
    public function deleteOldProfilePicture(string $picturePath): bool {
        if (empty($picturePath)) {
            return true;
        }
        
        $fullPath = BASE_PATH . '/' . $picturePath;
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return @unlink($fullPath);
        }
        
        return true;
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
        $logMessage = "[{$timestamp}] [IP: {$ip}] [{$userInfo}] [ALUMNI] {$message}" . PHP_EOL;
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
}
