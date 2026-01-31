<?php
declare(strict_types=1);

/**
 * Project Management Class
 * Handles all CRUD operations for the project system
 * Manages projects with status, dates, and team information
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class Project {
    private PDO $pdo;
    private string $logFile;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // Use BASE_DIR which is the standard constant defined in index.php
        $this->logFile = BASE_DIR . '/logs/app.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get all projects with optional filter
     * 
     * @param string|null $status Filter by status (planning, active, on_hold, completed, cancelled)
     * @param int $limit Maximum number of projects to return
     * @param int $offset Starting position for pagination
     * @return array List of projects
     */
    public function getAll(?string $status = null, int $limit = 100, int $offset = 0): array {
        try {
            $sql = "SELECT 
                        p.id, 
                        p.title, 
                        p.description, 
                        p.client,
                        p.project_type,
                        p.status,
                        p.start_date,
                        p.end_date,
                        p.budget,
                        p.team_size,
                        p.project_lead_id,
                        p.image_path,
                        p.created_by,
                        p.created_at,
                        p.updated_at,
                        u.firstname AS lead_firstname,
                        u.lastname AS lead_lastname
                    FROM projects p
                    LEFT JOIN users u ON p.project_lead_id = u.id";
            
            $params = [];
            
            if ($status) {
                $sql .= " WHERE p.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get latest projects for dashboard
     * Returns only projects in 'planning' or 'active' status
     * 
     * @param int $limit Maximum number of projects to return (default: 3)
     * @return array List of latest projects
     */
    public function getLatest(int $limit = 3): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, 
                    p.title, 
                    p.description, 
                    p.client,
                    p.project_type,
                    p.status,
                    p.start_date,
                    p.end_date,
                    p.budget,
                    p.team_size,
                    p.project_lead_id,
                    p.image_path,
                    p.created_by,
                    p.created_at,
                    p.updated_at,
                    u.firstname AS lead_firstname,
                    u.lastname AS lead_lastname
                FROM projects p
                LEFT JOIN users u ON p.project_lead_id = u.id
                WHERE p.status IN ('planning', 'active')
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching latest projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a single project by ID
     * 
     * @param int $id Project ID
     * @return array|null Project data or null if not found
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, 
                    p.title, 
                    p.description, 
                    p.client,
                    p.project_type,
                    p.status,
                    p.start_date,
                    p.end_date,
                    p.budget,
                    p.team_size,
                    p.project_lead_id,
                    p.image_path,
                    p.created_by,
                    p.created_at,
                    p.updated_at,
                    u.firstname AS lead_firstname,
                    u.lastname AS lead_lastname
                FROM projects p
                LEFT JOIN users u ON p.project_lead_id = u.id
                WHERE p.id = ?
            ");
            
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching project by ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search projects by query
     * Searches in title, description, and client fields
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array List of matching projects
     */
    public function search(string $query, int $limit = 10): array {
        try {
            // Limit search query length
            $query = substr(trim($query), 0, 255);
            
            if (empty($query)) {
                return [];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, 
                    p.title, 
                    p.description, 
                    p.client,
                    p.status,
                    p.created_at
                FROM projects p
                WHERE (p.title LIKE ? OR p.description LIKE ? OR p.client LIKE ?)
                AND p.status IN ('planning', 'active')
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            
            $searchTerm = '%' . $query . '%';
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count open project positions
     * Counts all projects with status 'planning' or 'active' that represent
     * available project opportunities for members to join.
     * Note: This counts projects (not individual team member positions).
     * 
     * @return int Number of open projects
     */
    public function countOpenPositions(): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM projects
                WHERE status IN ('planning', 'active')
            ");
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error counting open project positions: " . $e->getMessage());
            return 0;
        }
    }
}
