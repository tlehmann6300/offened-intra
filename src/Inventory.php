<?php
declare(strict_types=1);

/**
 * Inventory Management Class
 * Handles all CRUD operations for the inventory system
 * Provides secure file upload functionality for inventory images
 */
class Inventory {
    // Constants
    private const ALLOWED_STATUSES = ['active', 'archived', 'broken'];
    private const MAX_LOG_LIMIT = 1000;
    
    private PDO $pdo;
    private string $uploadDir;
    private string $logFile;
    private array $allowedImageTypes;
    private int $maxFileSize;
    private ?SystemLogger $systemLogger;
    
    public function __construct(PDO $pdo, ?SystemLogger $systemLogger = null) {
        $this->pdo = $pdo;
        $this->systemLogger = $systemLogger;
        $this->uploadDir = BASE_PATH . '/assets/uploads/inventory/';
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Use config constants with fallback
        $this->allowedImageTypes = defined('ALLOWED_IMAGE_TYPES') 
            ? array_merge(ALLOWED_IMAGE_TYPES, ['image/gif']) 
            : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $this->maxFileSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5242880;
        
        // Ensure directories exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get configured locations from database
     * 
     * @return array List of configured locations
     */
    public function getConfiguredLocations(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT name 
                FROM inventory_locations 
                WHERE is_active = 1 
                ORDER BY name ASC
            ");
            
            $locations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $locations[] = $row['name'];
            }
            
            return $locations;
        } catch (PDOException $e) {
            error_log("Error fetching locations from database: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get configured categories from database
     * 
     * @return array Associative array of category keys and display names
     */
    public function getConfiguredCategories(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT key_name, display_name 
                FROM inventory_categories 
                WHERE is_active = 1 
                ORDER BY display_name ASC
            ");
            
            $categories = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $categories[$row['key_name']] = $row['display_name'];
            }
            
            return $categories;
        } catch (PDOException $e) {
            error_log("Error fetching categories from database: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get locations from database (alias for getConfiguredLocations)
     * 
     * @return array List of configured locations
     */
    public function getLocations(): array {
        return $this->getConfiguredLocations();
    }
    
    /**
     * Get categories from database (alias for getConfiguredCategories)
     * 
     * @return array Associative array of category keys and display names
     */
    public function getCategories(): array {
        return $this->getConfiguredCategories();
    }
    
    /**
     * Add a new location to the database
     * 
     * @param string $locationName Location name to add
     * @param int|null $userId User ID creating the location
     * @return bool Success status
     */
    public function addLocation(string $locationName, ?int $userId = null): bool {
        // Clean and validate location name
        $locationName = trim($locationName);
        
        if (empty($locationName)) {
            $this->log("Error adding location: Location name is empty", $userId);
            return false;
        }
        
        // Check if location already exists (case-insensitive)
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM inventory_locations 
                WHERE LOWER(name) = LOWER(?)
            ");
            $stmt->execute([$locationName]);
            
            if ($stmt->fetch()) {
                $this->log("Location already exists: {$locationName}", $userId);
                return false; // Location already exists
            }
            
            // Insert new location
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_locations (name, created_by) 
                VALUES (?, ?)
            ");
            
            $result = $stmt->execute([$locationName, $userId]);
            
            if ($result) {
                $this->log("New location added: {$locationName}", $userId);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->log("Error adding location: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Delete a location from the database
     * Checks if location is in use before deletion
     * 
     * @param int $locationId Location ID to delete
     * @param int|null $userId User ID performing the deletion
     * @return array Result with success status and message
     */
    public function deleteLocation(int $locationId, ?int $userId = null): array {
        try {
            // Get location name first
            $stmt = $this->pdo->prepare("SELECT name FROM inventory_locations WHERE id = ?");
            $stmt->execute([$locationId]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$location) {
                return ['success' => false, 'message' => 'Standort nicht gefunden'];
            }
            
            $locationName = $location['name'];
            
            // Check if location is in use (Foreign Key Protection)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM inventory 
                WHERE location = ?
            ");
            $stmt->execute([$locationName]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                return [
                    'success' => false, 
                    'message' => "Standort kann nicht gelöscht werden, da noch {$count} Gegenstand/Gegenstände dort gelagert sind"
                ];
            }
            
            // Delete location
            $stmt = $this->pdo->prepare("DELETE FROM inventory_locations WHERE id = ?");
            $result = $stmt->execute([$locationId]);
            
            if ($result) {
                $this->log("Location deleted: {$locationName}", $userId);
                return ['success' => true, 'message' => 'Standort erfolgreich gelöscht'];
            }
            
            return ['success' => false, 'message' => 'Fehler beim Löschen des Standorts'];
            
        } catch (PDOException $e) {
            $this->log("Error deleting location: " . $e->getMessage(), $userId);
            return ['success' => false, 'message' => 'Datenbankfehler beim Löschen des Standorts'];
        }
    }
    
    /**
     * Add a new category to the database
     * 
     * @param string $keyName Category key name (e.g., 'technik')
     * @param string $displayName Category display name (e.g., 'Technik')
     * @param int|null $userId User ID creating the category
     * @return bool Success status
     */
    public function addCategory(string $keyName, string $displayName, ?int $userId = null): bool {
        // Clean and validate
        $keyName = trim($keyName);
        $displayName = trim($displayName);
        
        if (empty($keyName) || empty($displayName)) {
            $this->log("Error adding category: Key name or display name is empty", $userId);
            return false;
        }
        
        // Check if category already exists (case-insensitive)
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM inventory_categories 
                WHERE LOWER(key_name) = LOWER(?)
            ");
            $stmt->execute([$keyName]);
            
            if ($stmt->fetch()) {
                $this->log("Category already exists: {$keyName}", $userId);
                return false; // Category already exists
            }
            
            // Insert new category
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_categories (key_name, display_name) 
                VALUES (?, ?)
            ");
            
            $result = $stmt->execute([$keyName, $displayName]);
            
            if ($result) {
                $this->log("New category added: {$keyName} ({$displayName})", $userId);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            $this->log("Error adding category: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Delete a category from the database
     * Checks if category is in use before deletion
     * 
     * @param int $categoryId Category ID to delete
     * @param int|null $userId User ID performing the deletion
     * @return array Result with success status and message
     */
    public function deleteCategory(int $categoryId, ?int $userId = null): array {
        try {
            // Get category key_name first
            $stmt = $this->pdo->prepare("SELECT key_name, display_name FROM inventory_categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                return ['success' => false, 'message' => 'Kategorie nicht gefunden'];
            }
            
            $keyName = $category['key_name'];
            $displayName = $category['display_name'];
            
            // Check if category is in use (Foreign Key Protection)
            // Note: We check both key_name and display_name because the inventory table
            // stores category as a VARCHAR and items may have been created with either value
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM inventory 
                WHERE category = ? OR category = ?
            ");
            $stmt->execute([$keyName, $displayName]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                return [
                    'success' => false, 
                    'message' => "Kategorie kann nicht gelöscht werden, da noch {$count} Gegenstand/Gegenstände dieser Kategorie zugeordnet sind"
                ];
            }
            
            // Delete category
            $stmt = $this->pdo->prepare("DELETE FROM inventory_categories WHERE id = ?");
            $result = $stmt->execute([$categoryId]);
            
            if ($result) {
                $this->log("Category deleted: {$keyName} ({$displayName})", $userId);
                return ['success' => true, 'message' => 'Kategorie erfolgreich gelöscht'];
            }
            
            return ['success' => false, 'message' => 'Fehler beim Löschen der Kategorie'];
            
        } catch (PDOException $e) {
            $this->log("Error deleting category: " . $e->getMessage(), $userId);
            return ['success' => false, 'message' => 'Datenbankfehler beim Löschen der Kategorie'];
        }
    }
    
    /**
     * Get all locations including inactive ones
     * 
     * @return array List of all locations with their details
     */
    public function getAllLocations(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, is_active, created_at 
                FROM inventory_locations 
                ORDER BY name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all locations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all categories including inactive ones
     * 
     * @return array List of all categories with their details
     */
    public function getAllCategories(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT id, key_name, display_name, is_active, created_at 
                FROM inventory_categories 
                ORDER BY display_name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate location against configuration
     * Case-insensitive validation
     * 
     * @param string|null $location Location to validate
     * @return bool True if valid or empty, false otherwise
     */
    private function isValidLocation(?string $location): bool {
        if (empty($location)) {
            return true; // Empty location is allowed
        }
        
        $configuredLocations = $this->getConfiguredLocations();
        
        // Case-insensitive comparison
        $locationLower = strtolower($location);
        foreach ($configuredLocations as $configLocation) {
            if (strtolower($configLocation) === $locationLower) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate category against configuration
     * Case-insensitive validation that checks both keys and values
     * 
     * @param string|null $category Category to validate
     * @return bool True if valid or empty, false otherwise
     */
    private function isValidCategory(?string $category): bool {
        if (empty($category)) {
            return true; // Empty category is allowed
        }
        
        $configuredCategories = $this->getConfiguredCategories();
        
        // Normalize for case-insensitive comparison
        $categoryLower = strtolower($category);
        
        // Check both keys and values (case-insensitive)
        foreach ($configuredCategories as $key => $value) {
            if (strtolower($key) === $categoryLower || strtolower($value) === $categoryLower) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all inventory items with optional search filter and multi-filter support
     * 
     * @param string|null $search Search term for name, location, or tags
     * @param array $filters Additional filters (category, location, status)
     * @return array List of inventory items
     */
    public function getAll(?string $search = null, array $filters = []): array {
        try {
            $sql = "SELECT * FROM inventory WHERE 1=1";
            $params = [];
            
            // Add search filter
            if ($search) {
                $sql .= " AND (name LIKE ? OR location LIKE ? OR tags LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Add category filter
            if (!empty($filters['category']) && $filters['category'] !== 'all') {
                $sql .= " AND category = ?";
                $params[] = $filters['category'];
            }
            
            // Add location filter
            if (!empty($filters['location']) && $filters['location'] !== 'all') {
                $sql .= " AND location = ?";
                $params[] = $filters['location'];
            }
            
            // Add status filter
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            $sql .= " ORDER BY name ASC";
            
            // Always use prepare() for consistency and security
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching inventory items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get inventory items filtered by status
     * 
     * @param string $status Status value ('active', 'archived', 'broken')
     * @return array List of inventory items
     */
    public function getByStatus(string $status): array {
        // Validate status parameter
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            error_log("Invalid status value attempted: {$status}");
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM inventory 
                WHERE status = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$status]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching inventory items by status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single inventory item by ID
     * 
     * @param int $id Item ID
     * @return array|null Item data or null if not found
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM inventory WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching inventory item: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new inventory item
     * Wrapped in transaction to ensure atomicity with image upload
     * 
     * @param array $data Item data (name, description, location, category, quantity)
     * @param int $userId User ID creating the item
     * @return int|false New item ID or false on failure
     */
    public function create(array $data, int $userId): int|false {
        // Clean and sanitize name field first
        $name = isset($data['name']) ? strip_tags(trim($data['name'])) : '';
        
        // Validate required fields after sanitization
        if (empty($name)) {
            $this->log("Error creating inventory item: Name is required", $userId);
            return false;
        }
        
        // Validate location against configuration if provided
        $location = $data['location'] ?? '';
        if (!$this->isValidLocation($location)) {
            $this->log("Error creating inventory item: Invalid location '{$location}'. Only configured locations are allowed.", $userId);
            return false;
        }
        
        // Validate category against configuration if provided
        $category = $data['category'] ?? '';
        if (!$this->isValidCategory($category)) {
            $this->log("Error creating inventory item: Invalid category '{$category}'. Only configured categories are allowed.", $userId);
            return false;
        }
        
        // Validate status value if provided
        $status = $data['status'] ?? 'active';
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $this->log("Error creating inventory item: Invalid status value '{$status}'", $userId);
            return false;
        }
        
        // Clean and sanitize tags field
        $tags = isset($data['tags']) ? strip_tags(trim($data['tags'])) : null;
        
        // Start transaction to ensure atomicity
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory (name, description, location, category, quantity, image_path, purchase_date, status, tags, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $name,
                $data['description'] ?? '',
                $location,
                $category,
                max(0, (int)($data['quantity'] ?? 0)),
                $data['image_path'] ?? null,
                $data['purchase_date'] ?? null,
                $status,
                $tags,
                $userId
            ]);
            
            if (!$result) {
                $this->pdo->rollBack();
                return false;
            }
            
            $itemId = (int)$this->pdo->lastInsertId();
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log to system_logs
            if ($this->systemLogger !== null) {
                $this->systemLogger->log($userId, 'create', 'inventory', $itemId);
            }
            
            $this->log("Inventory item created: ID {$itemId}, Name: {$name}", $userId);
            return $itemId;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Log error and return failure
            $this->log("Error creating inventory item: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Update existing inventory item
     * Wrapped in transaction to ensure atomicity with image upload
     * 
     * @param int $id Item ID
     * @param array $data Updated item data
     * @param int|null $userId User ID performing the update
     * @return bool Success status
     */
    public function update(int $id, array $data, ?int $userId = null): bool {
        // Build dynamic update query based on provided data
        $fields = [];
        $values = [];
        
        if (isset($data['name'])) {
            // Clean and sanitize name field
            $fields[] = 'name = ?';
            $values[] = strip_tags(trim($data['name']));
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $values[] = $data['description'];
        }
        if (isset($data['location'])) {
            // Validate location against configuration
            if (!$this->isValidLocation($data['location'])) {
                $this->log("Error updating inventory item: Invalid location '{$data['location']}'. Only configured locations are allowed.", $userId);
                return false;
            }
            $fields[] = 'location = ?';
            $values[] = $data['location'];
        }
        if (isset($data['category'])) {
            // Validate category against configuration
            if (!$this->isValidCategory($data['category'])) {
                $this->log("Error updating inventory item: Invalid category '{$data['category']}'. Only configured categories are allowed.", $userId);
                return false;
            }
            $fields[] = 'category = ?';
            $values[] = $data['category'];
        }
        if (isset($data['quantity'])) {
            $fields[] = 'quantity = ?';
            $values[] = $data['quantity'];
        }
        if (isset($data['image_path'])) {
            $fields[] = 'image_path = ?';
            $values[] = $data['image_path'];
        }
        if (isset($data['purchase_date'])) {
            $fields[] = 'purchase_date = ?';
            $values[] = $data['purchase_date'];
        }
        if (isset($data['status'])) {
            // Validate status value
            if (!in_array($data['status'], self::ALLOWED_STATUSES, true)) {
                $this->log("Error updating inventory item: Invalid status value '{$data['status']}'", $userId);
                return false;
            }
            $fields[] = 'status = ?';
            $values[] = $data['status'];
        }
        if (isset($data['tags'])) {
            // Clean and sanitize tags field
            $fields[] = 'tags = ?';
            $values[] = strip_tags(trim($data['tags']));
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // Start transaction to ensure atomicity
        $this->pdo->beginTransaction();
        
        try {
            $values[] = $id;
            $sql = "UPDATE inventory SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                $this->pdo->rollBack();
                return false;
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log to system_logs
            if ($this->systemLogger !== null && $userId !== null) {
                $this->systemLogger->log($userId, 'update', 'inventory', $id);
            }
            
            $itemName = $data['name'] ?? 'ID ' . $id;
            $this->log("Inventory item updated: {$itemName}", $userId);
            
            return true;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Log error and return failure
            $this->log("Error updating inventory item: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Delete inventory item
     * 
     * @param int $id Item ID
     * @param int|null $userId User ID performing the deletion
     * @return bool Success status
     */
    public function delete(int $id, ?int $userId = null): bool {
        try {
            // Get item to delete associated image file
            $item = $this->getById($id);
            $itemName = $item['name'] ?? 'ID ' . $id;
            
            // Delete from database
            $stmt = $this->pdo->prepare("DELETE FROM inventory WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Delete image file if exists
            if ($result && $item && !empty($item['image_path'])) {
                // Validate filename to prevent path traversal
                $filename = basename($item['image_path']);
                if ($this->isValidFilename($filename)) {
                    $imagePath = $this->uploadDir . $filename;
                    if (file_exists($imagePath) && is_file($imagePath)) {
                        if (!unlink($imagePath)) {
                            $this->log("Warning: Failed to delete image file: {$imagePath}", $userId);
                        }
                    }
                }
            }
            
            if ($result) {
                // Log to system_logs
                if ($this->systemLogger !== null && $userId !== null) {
                    $this->systemLogger->log($userId, 'delete', 'inventory', $id);
                }
                
                $this->log("Inventory item deleted: {$itemName}", $userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error deleting inventory item: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Handle image upload with enhanced security checks and transaction support
     * Performs file signature validation (MIME type check) to prevent malicious files
     * 
     * @param array $file Uploaded file from $_FILES
     * @return array Result with 'success' (bool), 'message' (string), 'path' (string|null)
     */
    public function handleImageUpload(array $file): array {
        // Validate file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'Keine Datei hochgeladen.',
                'path' => null
            ];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Fehler beim Hochladen der Datei.',
                'path' => null
            ];
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'Datei ist zu groß. Maximum: 5MB.',
                'path' => null
            ];
        }
        
        // SECURITY: Validate MIME type using file signature (magic bytes), not just extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedImageTypes, true)) {
            return [
                'success' => false,
                'message' => 'Ungültiges Dateiformat. Erlaubt: JPEG, PNG, WebP, GIF.',
                'path' => null
            ];
        }
        
        // Additional security: Verify file signature matches image format
        if (!$this->verifyImageSignature($file['tmp_name'], $mimeType)) {
            return [
                'success' => false,
                'message' => 'Die Datei hat keine gültige Bildsignatur. Möglicherweise handelt es sich um eine getarnte Datei.',
                'path' => null
            ];
        }
        
        // Generate secure filename with WebP extension
        $filename = uniqid('inv_', true) . '.webp';
        $targetPath = $this->uploadDir . $filename;
        
        // Convert and optimize image to WebP
        $conversionResult = $this->convertToWebP($file['tmp_name'], $targetPath, $mimeType);
        
        if (!$conversionResult['success']) {
            // Clean up any partially created files
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }
            return [
                'success' => false,
                'message' => $conversionResult['message'],
                'path' => null
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Bild erfolgreich hochgeladen und optimiert.',
            'path' => $filename
        ];
    }
    
    /**
     * Verify image file signature (magic bytes) to prevent malicious file uploads
     * 
     * @param string $filePath Path to the uploaded file
     * @param string $expectedMimeType Expected MIME type
     * @return bool True if signature is valid, false otherwise
     */
    private function verifyImageSignature(string $filePath, string $expectedMimeType): bool {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }
        
        // Read first 12 bytes for signature verification
        $header = fread($handle, 12);
        fclose($handle);
        
        if ($header === false) {
            return false;
        }
        
        // Verify magic bytes based on MIME type
        switch ($expectedMimeType) {
            case 'image/jpeg':
                // JPEG starts with FF D8 FF (requires at least 3 bytes)
                if (strlen($header) < 3) {
                    return false;
                }
                return (ord($header[0]) === 0xFF && ord($header[1]) === 0xD8 && ord($header[2]) === 0xFF);
                
            case 'image/png':
                // PNG starts with 89 50 4E 47 0D 0A 1A 0A (requires full 8 bytes)
                if (strlen($header) < 8) {
                    return false;
                }
                return (ord($header[0]) === 0x89 && 
                        ord($header[1]) === 0x50 && 
                        ord($header[2]) === 0x4E && 
                        ord($header[3]) === 0x47 &&
                        ord($header[4]) === 0x0D &&
                        ord($header[5]) === 0x0A &&
                        ord($header[6]) === 0x1A &&
                        ord($header[7]) === 0x0A);
                
            case 'image/gif':
                // GIF starts with GIF87a or GIF89a (requires at least 6 bytes for complete header with version)
                if (strlen($header) < 6) {
                    return false;
                }
                return (substr($header, 0, 3) === 'GIF' && 
                        (substr($header, 3, 3) === '87a' || substr($header, 3, 3) === '89a'));
                
            case 'image/webp':
                // WebP: RIFF at offset 0, WEBP at offset 8 (requires at least 12 bytes)
                if (strlen($header) < 12) {
                    return false;
                }
                return (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP');
                
            default:
                return false;
        }
    }
    
    /**
     * Upload and validate inventory image (legacy method for backward compatibility)
     * 
     * @deprecated Use handleImageUpload() instead for enhanced security
     * @param array $file Uploaded file from $_FILES
     * @return array Result with 'success' (bool), 'message' (string), 'path' (string|null)
     */
    public function uploadImage(array $file): array {
        // Delegate to the new handleImageUpload method with enhanced security
        return $this->handleImageUpload($file);
    }
    
    /**
     * Convert image to WebP format with size optimization
     * 
     * @param string $sourcePath Source image path
     * @param string $targetPath Target WebP image path
     * @param string $mimeType Source image MIME type
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    private function convertToWebP(string $sourcePath, string $targetPath, string $mimeType): array {
        // Check if WebP support is available
        if (!function_exists('imagewebp')) {
            return [
                'success' => false,
                'message' => 'WebP-Unterstützung ist nicht verfügbar. Bitte aktivieren Sie WebP in der GD-Erweiterung.'
            ];
        }
        
        // Maximum dimensions for mobile optimization
        $maxWidth = 1200;
        $maxHeight = 1200;
        $webpQuality = 85; // Balance between quality and file size
        
        try {
            // Create image resource from source file based on type
            $sourceImage = match($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($sourcePath),
                'image/png' => @imagecreatefrompng($sourcePath),
                'image/webp' => @imagecreatefromwebp($sourcePath),
                'image/gif' => @imagecreatefromgif($sourcePath),
                default => false
            };
            
            if ($sourceImage === false) {
                return [
                    'success' => false,
                    'message' => 'Fehler beim Laden des Bildes.'
                ];
            }
            
            // Get original dimensions
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            
            // Calculate new dimensions maintaining aspect ratio
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
            
            if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
                $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
                $newWidth = (int)round($originalWidth * $ratio);
                $newHeight = (int)round($originalHeight * $ratio);
            }
            
            // Create optimized image
            $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            if ($optimizedImage === false) {
                imagedestroy($sourceImage);
                return [
                    'success' => false,
                    'message' => 'Fehler beim Erstellen des optimierten Bildes.'
                ];
            }
            
            // Preserve transparency for PNG/GIF (not needed for JPEG)
            if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)) {
                imagealphablending($optimizedImage, false);
                imagesavealpha($optimizedImage, true);
            }
            
            // Resize image
            $resizeResult = imagecopyresampled(
                $optimizedImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );
            
            if (!$resizeResult) {
                imagedestroy($sourceImage);
                imagedestroy($optimizedImage);
                return [
                    'success' => false,
                    'message' => 'Fehler beim Skalieren des Bildes.'
                ];
            }
            
            // Save as WebP with optimization
            $saveResult = imagewebp($optimizedImage, $targetPath, $webpQuality);
            
            // Clean up resources
            imagedestroy($sourceImage);
            imagedestroy($optimizedImage);
            
            if (!$saveResult) {
                return [
                    'success' => false,
                    'message' => 'Fehler beim Speichern des WebP-Bildes.'
                ];
            }
            
            // Set appropriate permissions
            chmod($targetPath, 0644);
            
            return [
                'success' => true,
                'message' => 'Bild erfolgreich konvertiert und optimiert.'
            ];
            
        } catch (Exception $e) {
            error_log("Error converting image to WebP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Fehler bei der Bildkonvertierung: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete image file
     * 
     * @param string $filename Image filename
     * @return bool Success status
     */
    public function deleteImage(string $filename): bool {
        // Validate filename to prevent path traversal
        $filename = basename($filename);
        if (!$this->isValidFilename($filename)) {
            error_log("Warning: Invalid filename attempted for deletion: {$filename}");
            return false;
        }
        
        $filePath = $this->uploadDir . $filename;
        
        if (file_exists($filePath) && is_file($filePath)) {
            if (!unlink($filePath)) {
                error_log("Warning: Failed to delete image file: {$filePath}");
                return false;
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate filename to prevent path traversal attacks
     * 
     * @param string $filename Filename to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidFilename(string $filename): bool {
        // Check for directory traversal patterns
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }
        
        // Check for valid format (alphanumeric, underscore, dot, dash)
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get unique locations from inventory items
     * Returns a list of all unique locations currently in use
     * 
     * @return array List of unique location strings
     */
    public function getUniqueLocations(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT location 
                FROM inventory 
                WHERE location IS NOT NULL AND location != ''
                ORDER BY location ASC
            ");
            
            $locations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $locations[] = $row['location'];
            }
            
            return $locations;
        } catch (PDOException $e) {
            error_log("Error fetching unique locations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get inventory statistics
     * 
     * @return array Statistics data
     */
    public function getStatistics(): array {
        try {
            $stats = [];
            
            // Total items
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM inventory");
            $stats['total_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Items with zero quantity
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM inventory WHERE quantity = 0");
            $stats['zero_quantity'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total quantity
            $stmt = $this->pdo->query("SELECT SUM(quantity) as total FROM inventory");
            $stats['total_quantity'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Categories count
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT category) as total FROM inventory WHERE category IS NOT NULL AND category != ''");
            $stats['categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error fetching inventory statistics: " . $e->getMessage());
            return [
                'total_items' => 0,
                'zero_quantity' => 0,
                'total_quantity' => 0,
                'categories' => 0
            ];
        }
    }
    
    /**
     * Calculate the total value of the inventory
     * Sums up all (purchase_price * quantity) for active items
     * 
     * @return float Total inventory value in euros
     */
    public function getTotalInventoryValue(): float {
        try {
            $stmt = $this->pdo->query("
                SELECT COALESCE(SUM(purchase_price * quantity), 0) as total_value 
                FROM inventory 
                WHERE status = 'active' 
                AND purchase_price IS NOT NULL
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalValue = $result['total_value'] ?? 0.0;
            
            return (float)$totalValue;
        } catch (PDOException $e) {
            error_log("Error calculating total inventory value: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Adjust inventory quantity and log the change
     * 
     * This method is thread-safe and prevents race conditions using:
     * 1. PDO Transactions (beginTransaction, commit, rollBack) for atomicity
     * 2. Row-level locking (SELECT ... FOR UPDATE) to prevent concurrent modifications
     * 
     * Security Features:
     * - Prevents negative inventory levels
     * - Handles nested transactions correctly
     * - Ensures all operations (read, update, log) are atomic
     * - Locks the inventory row during adjustment to prevent lost updates
     * 
     * @param int $id Item ID
     * @param int $change Quantity change (positive to add, negative to subtract)
     * @param string $comment Comment describing the change
     * @param int $userId User ID performing the adjustment
     * @return bool Success status
     */
    public function adjustQuantity(int $id, int $change, string $comment, int $userId): bool {
        try {
            // Start transaction to ensure data consistency
            // Check if we're already in a transaction to avoid nested transactions
            $alreadyInTransaction = $this->pdo->inTransaction();
            if (!$alreadyInTransaction) {
                $this->pdo->beginTransaction();
            }
            
            // Get current item with row lock (SELECT ... FOR UPDATE)
            // This locks the row until the transaction is committed, preventing
            // other transactions from reading or modifying it concurrently
            $stmt = $this->pdo->prepare("SELECT quantity, name FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                if (!$alreadyInTransaction) {
                    $this->pdo->rollBack();
                }
                $this->log("Error adjusting quantity: Item ID {$id} not found", $userId);
                return false;
            }
            
            $currentQuantity = (int)$item['quantity'];
            $newQuantity = $currentQuantity + $change;
            
            // Prevent negative inventory
            if ($newQuantity < 0) {
                if (!$alreadyInTransaction) {
                    $this->pdo->rollBack();
                }
                $this->log("Error adjusting quantity: Would result in negative inventory (current: {$currentQuantity}, change: {$change}) for item '{$item['name']}'", $userId);
                return false;
            }
            
            // Update inventory quantity
            $updateStmt = $this->pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
            $updateStmt->execute([$newQuantity, $id]);
            
            // Log the change
            $logStmt = $this->pdo->prepare("
                INSERT INTO inventory_logs (item_id, user_id, change_amount, comment)
                VALUES (?, ?, ?, ?)
            ");
            $logStmt->execute([$id, $userId, $change, $comment]);
            
            // Commit transaction only if we started it
            if (!$alreadyInTransaction) {
                $this->pdo->commit();
            }
            
            $this->log("Quantity adjusted for '{$item['name']}': {$currentQuantity} -> {$newQuantity} (change: {$change})", $userId);
            return true;
            
        } catch (PDOException $e) {
            // Rollback on error only if we started the transaction
            if ($this->pdo->inTransaction() && !$alreadyInTransaction) {
                $this->pdo->rollBack();
            }
            $this->log("Error adjusting quantity: " . $e->getMessage(), $userId);
            return false;
        }
    }
    
    /**
     * Add inventory log entry for tracking changes
     * 
     * @param int $itemId Item ID
     * @param int|null $userId User ID performing the action
     * @param int $changeAmount Quantity change amount
     * @param string|null $comment Optional comment about the change
     * @return bool Success status
     */
    public function addLog(int $itemId, ?int $userId, int $changeAmount = 0, ?string $comment = null): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_logs (item_id, user_id, change_amount, comment)
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $itemId,
                $userId,
                $changeAmount,
                $comment
            ]);
        } catch (PDOException $e) {
            error_log("Error adding inventory log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get logs for a specific inventory item
     * Returns logs with user information loaded separately from User-DB
     * 
     * @param int $itemId Item ID
     * @param int $limit Maximum number of logs to retrieve (max 1000)
     * @return array List of log entries
     */
    public function getLogs(int $itemId, int $limit = 50): array {
        try {
            // Validate and cap limit parameter to prevent excessive memory usage
            $limit = max(1, min($limit, self::MAX_LOG_LIMIT));
            
            // Query inventory_logs without JOIN
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM inventory_logs
                WHERE item_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$itemId, $limit]);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unique user IDs
            $userIds = array_unique(array_column($logs, 'user_id'));
            
            // Fetch user data from User-DB and merge into logs
            $userData = $this->fetchUserData($userIds);
            $this->mergeUserDataIntoLogs($logs, $userData);
            
            return $logs;
        } catch (PDOException $e) {
            error_log("Error fetching inventory logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get history of administrative actions for a specific inventory item from system_logs
     * Returns log entries with user information loaded separately from User-DB
     * 
     * @param int $itemId Item ID
     * @param int $limit Maximum number of entries to retrieve (max 1000)
     * @return array List of system log entries
     */
    public function getHistory(int $itemId, int $limit = 50): array {
        try {
            // Validate and cap limit parameter to prevent excessive memory usage
            $limit = max(1, min($limit, self::MAX_LOG_LIMIT));
            
            // Query system_logs from Content-DB without JOIN
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM system_logs
                WHERE target_type = 'inventory' AND target_id = ?
                ORDER BY timestamp DESC
                LIMIT ?
            ");
            $stmt->execute([$itemId, $limit]);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unique user IDs
            $userIds = array_unique(array_column($logs, 'user_id'));
            
            // Fetch user data from User-DB and merge into logs
            $userData = $this->fetchUserData($userIds);
            $this->mergeUserDataIntoLogs($logs, $userData);
            
            return $logs;
        } catch (PDOException $e) {
            error_log("Error fetching inventory history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch user data from User-DB for given user IDs
     * 
     * @param array $userIds Array of user IDs
     * @return array Associative array with user_id as key and user data as value
     */
    private function fetchUserData(array $userIds): array {
        $userData = [];
        
        if (empty($userIds)) {
            return $userData;
        }
        
        try {
            // Fetch user data from User-DB using DatabaseManager
            $userPdo = DatabaseManager::getUserConnection();
            
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $userStmt = $userPdo->prepare("
                SELECT id, firstname, lastname, email
                FROM users
                WHERE id IN ($placeholders)
            ");
            $userStmt->execute($userIds);
            
            while ($user = $userStmt->fetch(PDO::FETCH_ASSOC)) {
                $userData[$user['id']] = $user;
            }
        } catch (PDOException $e) {
            error_log("Error fetching user data: " . $e->getMessage());
        }
        
        return $userData;
    }
    
    /**
     * Merge user data into log entries
     * 
     * @param array $logs Log entries (passed by reference)
     * @param array $userData User data indexed by user ID
     * @return void
     */
    private function mergeUserDataIntoLogs(array &$logs, array $userData): void {
        foreach ($logs as &$log) {
            $userId = $log['user_id'] ?? null;
            if ($userId !== null && isset($userData[$userId])) {
                $log['firstname'] = $userData[$userId]['firstname'];
                $log['lastname'] = $userData[$userId]['lastname'];
                $log['email'] = $userData[$userId]['email'];
            } else {
                $log['firstname'] = null;
                $log['lastname'] = null;
                $log['email'] = null;
            }
        }
    }
    
    /**
     * Log message to application log file
     * 
     * @param string $message Message to log
     * @param int|null $userId User ID performing the action
     */
    private function log(string $message, ?int $userId = null): void {
        $timestamp = date('Y-m-d H:i:s');
        
        // Get client IP, considering proxy headers
        $ip = 'unknown';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the first IP from the X-Forwarded-For list
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize IP for logging (support both IPv4 and IPv6)
        $ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ? $ip : 'invalid';
        
        $userInfo = $userId ? "User ID: {$userId}" : 'User ID: unknown';
        $logMessage = "[{$timestamp}] [IP: {$ip}] [{$userInfo}] [INVENTORY] {$message}" . PHP_EOL;
        
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
            // Fallback to error_log if file writing fails
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
}
