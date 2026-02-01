<?php
declare(strict_types=1);

/**
 * System Logger Class
 * Handles logging of administrative actions on inventory, news, and alumni
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class SystemLogger {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an administrative action
     * 
     * @param int $userId User ID performing the action
     * @param string $action Action type (create, update, delete)
     * @param string $targetType Target type (inventory, news, alumni)
     * @param int $targetId ID of the target record
     * @return bool Success status
     */
    public function log(int $userId, string $action, string $targetType, int $targetId): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_logs (user_id, action, target_type, target_id)
                VALUES (?, ?, ?, ?)
            ");
            
            return $stmt->execute([$userId, $action, $targetType, $targetId]);
        } catch (PDOException $e) {
            error_log("Error logging system action: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get system logs with optional filters
     * 
     * @param array $filters Optional filters (target_type, action, user_id, limit, offset)
     * @return array List of log entries with user information
     */
    public function getLogs(array $filters = []): array {
        try {
            $sql = "
                SELECT 
                    sl.id,
                    sl.user_id,
                    sl.action,
                    sl.target_type,
                    sl.target_id,
                    sl.timestamp,
                    u.firstname,
                    u.lastname,
                    u.email
                FROM system_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            // Add target_type filter
            if (!empty($filters['target_type'])) {
                $sql .= " AND sl.target_type = ?";
                $params[] = $filters['target_type'];
            }
            
            // Add action filter
            if (!empty($filters['action'])) {
                $sql .= " AND sl.action = ?";
                $params[] = $filters['action'];
            }
            
            // Add user_id filter
            if (!empty($filters['user_id'])) {
                $sql .= " AND sl.user_id = ?";
                $params[] = (int)$filters['user_id'];
            }
            
            // Add target_id filter
            if (!empty($filters['target_id'])) {
                $sql .= " AND sl.target_id = ?";
                $params[] = (int)$filters['target_id'];
            }
            
            // Add date range filter
            if (!empty($filters['date_from'])) {
                $sql .= " AND sl.timestamp >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND sl.timestamp <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Order by most recent first
            $sql .= " ORDER BY sl.timestamp DESC";
            
            // Add limit and offset
            $limit = isset($filters['limit']) ? max(1, min((int)$filters['limit'], 1000)) : 100;
            $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching system logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get the total count of logs matching the filters
     * 
     * @param array $filters Optional filters (target_type, action, user_id)
     * @return int Total count of matching logs
     */
    public function getLogCount(array $filters = []): int {
        try {
            $sql = "SELECT COUNT(*) as count FROM system_logs sl WHERE 1=1";
            $params = [];
            
            // Add target_type filter
            if (!empty($filters['target_type'])) {
                $sql .= " AND sl.target_type = ?";
                $params[] = $filters['target_type'];
            }
            
            // Add action filter
            if (!empty($filters['action'])) {
                $sql .= " AND sl.action = ?";
                $params[] = $filters['action'];
            }
            
            // Add user_id filter
            if (!empty($filters['user_id'])) {
                $sql .= " AND sl.user_id = ?";
                $params[] = (int)$filters['user_id'];
            }
            
            // Add target_id filter
            if (!empty($filters['target_id'])) {
                $sql .= " AND sl.target_id = ?";
                $params[] = (int)$filters['target_id'];
            }
            
            // Add date range filter
            if (!empty($filters['date_from'])) {
                $sql .= " AND sl.timestamp >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND sl.timestamp <= ?";
                $params[] = $filters['date_to'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error counting system logs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get the target name for a log entry
     * This retrieves the name/title of the logged item for display purposes
     * 
     * @param string $targetType Target type (inventory, news, alumni)
     * @param int $targetId Target ID
     * @return string Target name or empty string if not found
     */
    public function getTargetName(string $targetType, int $targetId): string {
        try {
            switch ($targetType) {
                case 'inventory':
                    $stmt = $this->pdo->prepare("SELECT name FROM inventory WHERE id = ?");
                    $stmt->execute([$targetId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['name'] ?? '';
                    
                case 'news':
                    $stmt = $this->pdo->prepare("SELECT title FROM news WHERE id = ?");
                    $stmt->execute([$targetId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['title'] ?? '';
                    
                case 'alumni':
                    $stmt = $this->pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) as name FROM alumni WHERE id = ?");
                    $stmt->execute([$targetId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['name'] ?? '';
                    
                default:
                    return '';
            }
        } catch (PDOException $e) {
            error_log("Error getting target name: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Log a login attempt with GDPR-compliant IP anonymization
     * 
     * @param string $username Username/email attempted
     * @param bool $success Whether login was successful
     * @param int|null $userId User ID if login was successful
     * @param string|null $failureReason Reason for failure (if applicable)
     * @return bool Success status
     */
    public function logLoginAttempt(string $username, bool $success, ?int $userId = null, ?string $failureReason = null): bool {
        try {
            // Get IP address with proxy support
            $ip = $this->getClientIp();
            
            // Anonymize IP for GDPR compliance
            $anonymizedIp = $this->anonymizeIp($ip);
            
            // Get user agent (truncate if too long and sanitize)
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent) {
                // Sanitize user agent to remove potentially malicious characters
                $userAgent = filter_var($userAgent, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                if (strlen($userAgent) > 500) {
                    $userAgent = substr($userAgent, 0, 500);
                }
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (username, user_id, ip_address_anonymized, success, failure_reason, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $username,
                $userId,
                $anonymizedIp,
                $success ? 1 : 0,
                $failureReason,
                $userAgent
            ]);
        } catch (PDOException $e) {
            error_log("Error logging login attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address with proxy header support
     * Uses the last valid IP from X-Forwarded-For chain for better security
     * 
     * @return string IP address or 'unknown'
     */
    private function getClientIp(): string {
        $ip = 'unknown';
        
        // Check proxy headers (in order of trust)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
            // Use the last valid IP from the chain (most trusted proxy)
            // This prevents IP spoofing where attacker can inject fake IPs at the start
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            
            // Iterate from the end to find the last valid IP
            for ($i = count($ips) - 1; $i >= 0; $i--) {
                if (filter_var($ips[$i], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                    $ip = $ips[$i];
                    break;
                }
            }
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP format
        $validIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        return $validIp ? $ip : 'unknown';
    }
    
    /**
     * Anonymize IP address for GDPR compliance
     * IPv4: Removes last octet (e.g., 192.168.1.100 -> 192.168.1.0)
     * IPv6: Removes last 80 bits (e.g., 2001:0db8:85a3::8a2e:0370:7334 -> 2001:0db8:85a3::)
     * 
     * @param string $ip IP address to anonymize
     * @return string Anonymized IP address
     */
    private function anonymizeIp(string $ip): string {
        // Check if it's a valid IPv4 address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Remove last octet
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        }
        
        // Check if it's a valid IPv6 address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Remove last 80 bits (keep first 48 bits)
            // Convert to binary representation
            $binary = inet_pton($ip);
            if ($binary !== false) {
                // Zero out the last 10 bytes (80 bits) for anonymization
                $anonymized = substr($binary, 0, 6) . str_repeat("\0", 10);
                return inet_ntop($anonymized);
            }
        }
        
        // If IP is invalid or unknown, return as-is
        return $ip;
    }
}
