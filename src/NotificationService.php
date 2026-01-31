<?php
declare(strict_types=1);

/**
 * Notification Service Class
 * Manages user notifications and helper update status
 * 
 * @requires PHP 8.0+ (uses typed properties)
 */
class NotificationService {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if user has helper update notifications
     * 
     * @param int $userId User ID
     * @return bool True if has_helper_update is 1, false otherwise
     */
    public function hasHelperUpdate(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT has_helper_update
                FROM user_notifications
                WHERE user_id = ?
            ");
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['has_helper_update'] == 1;
        } catch (PDOException $e) {
            error_log("Error checking helper update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark helper updates as read (set has_helper_update to 0)
     * 
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function markHelperUpdatesAsRead(int $userId): array {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_notifications
                SET has_helper_update = 0, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Benachrichtigungen als gelesen markiert'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Fehler beim Markieren der Benachrichtigungen'
            ];
            
        } catch (PDOException $e) {
            error_log("Error marking helper updates as read: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Datenbankfehler'
            ];
        }
    }
    
    /**
     * Get new helper requests (slots with available positions)
     * Returns upcoming events with open helper slots
     * 
     * @param int $limit Maximum number of results
     * @return array List of events with open helper slots
     */
    public function getNewHelperRequests(int $limit = 10): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id as event_id,
                    e.title as event_title,
                    e.date as event_date,
                    e.location as event_location,
                    s.id as slot_id,
                    s.task_name,
                    s.start_time,
                    s.end_time,
                    s.slots_max,
                    COUNT(r.id) as slots_filled,
                    (s.slots_max - COUNT(r.id)) as slots_available
                FROM event_helper_slots s
                INNER JOIN events e ON s.event_id = e.id
                LEFT JOIN event_helper_registrations r ON s.id = r.slot_id
                WHERE e.date >= CURDATE()
                  AND s.start_time >= NOW()
                GROUP BY s.id
                HAVING slots_available > 0
                ORDER BY s.start_time ASC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching new helper requests: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set helper update notification for user
     * 
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function setHelperUpdate(int $userId): array {
        try {
            // Insert or update notification record
            $stmt = $this->pdo->prepare("
                INSERT INTO user_notifications (user_id, has_helper_update, created_at, updated_at)
                VALUES (?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                    has_helper_update = 1, 
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Benachrichtigung gesetzt'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Fehler beim Setzen der Benachrichtigung'
            ];
            
        } catch (PDOException $e) {
            error_log("Error setting helper update: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Datenbankfehler'
            ];
        }
    }
}
