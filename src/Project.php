<?php
declare(strict_types=1);

/**
 * Project Management Class
 * Handles all CRUD operations for the project system
 * Manages projects with status, dates, and team information
 * 
 * Alumni Access Control:
 * - Alumni users (especially unvalidated ones) are restricted from accessing active project data
 * - Use getAllForUser() and getLatestForUser() methods with user role to enforce restrictions
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
     * Check if user role has access to active projects
     * Alumni users do not have access to active project data
     * 
     * @param string $userRole User's role
     * @return bool True if user can access active projects, false otherwise
     */
    private function canAccessActiveProjects(string $userRole): bool {
        // Alumni users cannot access active project data
        return $userRole !== 'alumni';
    }
    
    /**
     * Get all projects with optional filter and role-based access control
     * 
     * @param string|null $status Filter by status (planning, active, on_hold, completed, cancelled)
     * @param int $limit Maximum number of projects to return
     * @param int $offset Starting position for pagination
     * @param string|null $userRole User role for access control (null = no restrictions)
     * @return array List of projects
     */
    public function getAll(?string $status = null, int $limit = 100, int $offset = 0, ?string $userRole = null): array {
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
            $whereConditions = [];
            
            if ($status) {
                $whereConditions[] = "p.status = ?";
                $params[] = $status;
            }
            
            // Alumni access restriction: exclude active project data
            if ($userRole && !$this->canAccessActiveProjects($userRole)) {
                $whereConditions[] = "p.status NOT IN ('planning', 'active')";
            }
            
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(' AND ', $whereConditions);
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
     * Alumni users are excluded from accessing this data
     * 
     * @param int $limit Maximum number of projects to return (default: 3)
     * @param string|null $userRole User role for access control (null = no restrictions)
     * @return array List of latest projects (empty array for alumni users)
     */
    public function getLatest(int $limit = 3, ?string $userRole = null): array {
        // Alumni users cannot access active project data
        if ($userRole && !$this->canAccessActiveProjects($userRole)) {
            return [];
        }
        
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
     * Get a single project by ID with role-based access control
     * Alumni users cannot access active projects
     * 
     * @param int $id Project ID
     * @param string|null $userRole User role for access control (null = no restrictions)
     * @return array|null Project data or null if not found or access denied
     */
    public function getById(int $id, ?string $userRole = null): ?array {
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
            
            if (!$result) {
                return null;
            }
            
            // Alumni access restriction: cannot view active projects
            if ($userRole && !$this->canAccessActiveProjects($userRole)) {
                if (in_array($result['status'], ['planning', 'active'], true)) {
                    error_log("Access denied: Alumni user attempted to access active project ID: {$id}");
                    return null;
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error fetching project by ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search projects by query with role-based access control
     * Searches in title, description, and client fields
     * Alumni users can only search completed/cancelled projects
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @param string|null $userRole User role for access control (null = no restrictions)
     * @return array List of matching projects
     */
    public function search(string $query, int $limit = 10, ?string $userRole = null): array {
        try {
            // Limit search query length
            $query = substr(trim($query), 0, 255);
            
            if (empty($query)) {
                return [];
            }
            
            $sql = "
                SELECT 
                    p.id, 
                    p.title, 
                    p.description, 
                    p.client,
                    p.status,
                    p.created_at
                FROM projects p
                WHERE (p.title LIKE ? OR p.description LIKE ? OR p.client LIKE ?)";
            
            $params = [];
            $searchTerm = '%' . $query . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            
            // Alumni access restriction
            if ($userRole && !$this->canAccessActiveProjects($userRole)) {
                $sql .= " AND p.status NOT IN ('planning', 'active')";
            } else {
                $sql .= " AND p.status IN ('planning', 'active')";
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count open project positions with role-based access control
     * Counts all projects with status 'planning' or 'active' that represent
     * available project opportunities for members to join.
     * Note: This counts projects (not individual team member positions).
     * Alumni users receive 0 count as they cannot access active projects.
     * 
     * @param string|null $userRole User role for access control (null = no restrictions)
     * @return int Number of open projects
     */
    public function countOpenPositions(?string $userRole = null): int {
        // Alumni users cannot access active project data
        if ($userRole && !$this->canAccessActiveProjects($userRole)) {
            return 0;
        }
        
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
