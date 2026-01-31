<?php
declare(strict_types=1);

/**
 * Helper Service Class
 * Manages helper slots and registrations for events
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class HelperService {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all helper slots for a specific event with registration counts
     * 
     * @param int $eventId Event ID
     * @return array List of helper slots with registration counts
     */
    public function getHelperSlotsByEvent(int $eventId): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.id,
                    s.event_id,
                    s.task_name,
                    s.start_time,
                    s.end_time,
                    s.slots_max,
                    COUNT(r.id) as slots_filled,
                    (s.slots_max - COUNT(r.id)) as slots_available
                FROM event_helper_slots s
                LEFT JOIN event_helper_registrations r ON s.id = r.slot_id
                WHERE s.event_id = ?
                GROUP BY s.id
                ORDER BY s.start_time ASC
            ");
            
            $stmt->execute([$eventId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching helper slots: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if a user is already registered for a specific slot
     * 
     * @param int $slotId Helper slot ID
     * @param int|null $userId User ID
     * @return bool True if user is registered, false otherwise
     */
    public function isUserRegistered(int $slotId, ?int $userId): bool {
        // Return false if userId is null or invalid
        if ($userId === null || $userId <= 0) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM event_helper_registrations
                WHERE slot_id = ? AND user_id = ?
            ");
            
            $stmt->execute([$slotId, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking user registration: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Register a user for a helper slot (one-click sign-up)
     * Checks availability and prevents duplicate registrations
     * Uses transaction to prevent race conditions
     * 
     * @param int $slotId Helper slot ID
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function registerForSlot(int $slotId, int $userId): array {
        try {
            // Start transaction to prevent race conditions
            $this->pdo->beginTransaction();
            
            // Lock the slot row with SELECT FOR UPDATE to prevent concurrent modifications
            $stmt = $this->pdo->prepare("
                SELECT id, slots_max
                FROM event_helper_slots
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$slotId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Slot nicht gefunden'
                ];
            }
            
            // Check if user is already registered
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM event_helper_registrations
                WHERE slot_id = ? AND user_id = ?
            ");
            $stmt->execute([$slotId, $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['count'] > 0) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Sie sind bereits für diesen Slot angemeldet'
                ];
            }
            
            // Count current registrations for this slot (with lock to ensure consistent read)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as slots_registered
                FROM event_helper_registrations
                WHERE slot_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$slotId]);
            $registrations = $stmt->fetch(PDO::FETCH_ASSOC);
            $slots_registered = $registrations['slots_registered'] ?? 0;
            
            // Check if slot is full: only insert if slots_registered < slots_max
            if ($slots_registered >= $slot['slots_max']) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Slot bereits voll'
                ];
            }
            
            // Register user for slot
            $stmt = $this->pdo->prepare("
                INSERT INTO event_helper_registrations (slot_id, user_id)
                VALUES (?, ?)
            ");
            
            $result = $stmt->execute([$slotId, $userId]);
            
            if ($result) {
                // Commit transaction
                $this->pdo->commit();
                
                // Get updated slot info
                $updatedSlot = $this->getSlotInfo($slotId);
                
                return [
                    'success' => true,
                    'message' => 'Erfolgreich angemeldet!',
                    'slots_filled' => $updatedSlot['slots_filled'],
                    'slots_max' => $updatedSlot['slots_max'],
                    'slots_available' => $updatedSlot['slots_available']
                ];
            }
            
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Anmeldung fehlgeschlagen'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            error_log("Error registering for slot: " . $e->getMessage());
            
            // Check if it's a duplicate entry error (SQLSTATE 23000)
            if (strpos($e->getSqlState() ?? '', '23000') !== false) {
                return [
                    'success' => false,
                    'message' => 'Sie sind bereits für diesen Slot angemeldet'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Datenbankfehler beim Anmelden'
            ];
        }
    }
    
    /**
     * Unregister a user from a helper slot
     * 
     * @param int $slotId Helper slot ID
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function unregisterFromSlot(int $slotId, int $userId): array {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM event_helper_registrations
                WHERE slot_id = ? AND user_id = ?
            ");
            
            $result = $stmt->execute([$slotId, $userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Get updated slot info
                $updatedSlot = $this->getSlotInfo($slotId);
                
                return [
                    'success' => true,
                    'message' => 'Erfolgreich abgemeldet',
                    'slots_filled' => $updatedSlot['slots_filled'],
                    'slots_max' => $updatedSlot['slots_max'],
                    'slots_available' => $updatedSlot['slots_available']
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Sie waren nicht für diesen Slot angemeldet'
            ];
            
        } catch (PDOException $e) {
            error_log("Error unregistering from slot: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Datenbankfehler beim Abmelden'
            ];
        }
    }
    
    /**
     * Get detailed information about a specific slot
     * 
     * @param int $slotId Helper slot ID
     * @return array Slot information with registration counts
     */
    private function getSlotInfo(int $slotId): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.id,
                    s.event_id,
                    s.task_name,
                    s.start_time,
                    s.end_time,
                    s.slots_max,
                    COUNT(r.id) as slots_filled,
                    (s.slots_max - COUNT(r.id)) as slots_available
                FROM event_helper_slots s
                LEFT JOIN event_helper_registrations r ON s.id = r.slot_id
                WHERE s.id = ?
                GROUP BY s.id
            ");
            
            $stmt->execute([$slotId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result !== false ? $result : [];
        } catch (PDOException $e) {
            error_log("Error fetching slot info: " . $e->getMessage());
            return [];
        }
    }
}
