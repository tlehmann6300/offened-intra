<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Helper Service Class
 * Manages helper slots and registrations for events
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 */
class HelperService {
    private PDO $pdoContent;
    private PDO $pdoUser;
    private string $logFile;
    private ?MailService $mailService = null;
    
    public function __construct(PDO $pdoContent, PDO $pdoUser, ?MailService $mailService = null) {
        $this->pdoContent = $pdoContent;
        $this->pdoUser = $pdoUser;
        $this->mailService = $mailService;
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get all helper slots for a specific event with registration counts
     * 
     * @param int $eventId Event ID
     * @return array List of helper slots with registration counts
     */
    public function getHelperSlotsByEvent(int $eventId): array {
        try {
            $stmt = $this->pdoContent->prepare("
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
            $stmt = $this->pdoContent->prepare("
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
            $this->pdoContent->beginTransaction();
            
            // Lock the slot row with SELECT FOR UPDATE to prevent concurrent modifications
            $stmt = $this->pdoContent->prepare("
                SELECT id, slots_max
                FROM event_helper_slots
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$slotId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot) {
                $this->pdoContent->rollBack();
                return [
                    'success' => false,
                    'message' => 'Slot nicht gefunden'
                ];
            }
            
            // Check if user is already registered
            $stmt = $this->pdoContent->prepare("
                SELECT COUNT(*) as count
                FROM event_helper_registrations
                WHERE slot_id = ? AND user_id = ?
            ");
            $stmt->execute([$slotId, $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['count'] > 0) {
                $this->pdoContent->rollBack();
                return [
                    'success' => false,
                    'message' => 'Sie sind bereits für diesen Slot angemeldet'
                ];
            }
            
            // Count current registrations for this slot (with lock to ensure consistent read)
            $stmt = $this->pdoContent->prepare("
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
                $this->pdoContent->rollBack();
                return [
                    'success' => false,
                    'message' => 'Slot bereits voll'
                ];
            }
            
            // Register user for slot
            $stmt = $this->pdoContent->prepare("
                INSERT INTO event_helper_registrations (slot_id, user_id)
                VALUES (?, ?)
            ");
            
            $result = $stmt->execute([$slotId, $userId]);
            
            if ($result) {
                // Commit transaction
                $this->pdoContent->commit();
                
                // Get updated slot info
                $updatedSlot = $this->getSlotInfo($slotId);
                
                // Send confirmation email to the helper
                $this->sendHelperConfirmationEmail($slotId, $userId);
                
                return [
                    'success' => true,
                    'message' => 'Erfolgreich angemeldet!',
                    'slots_filled' => $updatedSlot['slots_filled'],
                    'slots_max' => $updatedSlot['slots_max'],
                    'slots_available' => $updatedSlot['slots_available']
                ];
            }
            
            $this->pdoContent->rollBack();
            return [
                'success' => false,
                'message' => 'Anmeldung fehlgeschlagen'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($this->pdoContent->inTransaction()) {
                $this->pdoContent->rollBack();
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
            $stmt = $this->pdoContent->prepare("
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
            $stmt = $this->pdoContent->prepare("
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
    
    /**
     * Send confirmation email to helper after successful registration
     * 
     * @param int $slotId Helper slot ID
     * @param int $userId User ID
     * @return bool Success status
     */
    private function sendHelperConfirmationEmail(int $slotId, int $userId): bool {
        try {
            // If no MailService is available, try to create one
            if ($this->mailService === null) {
                if (class_exists('MailService')) {
                    $this->mailService = new MailService();
                } else {
                    $this->log("Warning: MailService not available for helper confirmation email");
                    return false;
                }
            }
            
            // Get slot and event information
            $stmt = $this->pdoContent->prepare("
                SELECT 
                    s.task_name,
                    s.start_time,
                    s.end_time,
                    e.id as event_id,
                    e.title as event_title,
                    e.event_date,
                    e.location
                FROM event_helper_slots s
                INNER JOIN events e ON s.event_id = e.id
                WHERE s.id = ?
            ");
            $stmt->execute([$slotId]);
            $slotData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slotData) {
                $this->log("Error: Slot with ID {$slotId} not found for email confirmation");
                return false;
            }
            
            // Get user email and name
            $stmt = $this->pdoUser->prepare("
                SELECT email, firstname, lastname
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData || empty($userData['email'])) {
                $this->log("Error: User with ID {$userId} not found or has no email address");
                return false;
            }
            
            // Generate email content
            $emailSubject = "Bestätigung: Helfer-Anmeldung für " . $slotData['event_title'];
            $emailHtml = $this->generateHelperConfirmationEmailHtml($slotData, $userData);
            
            // Send email using MailService
            $result = $this->mailService->sendEmail(
                $userData['email'],
                $emailSubject,
                $emailHtml,
                $userData['firstname'] . ' ' . $userData['lastname']
            );
            
            if ($result) {
                $this->log("Helper confirmation email sent to user ID {$userId} for slot ID {$slotId}");
            } else {
                $this->log("Failed to send helper confirmation email to user ID {$userId} for slot ID {$slotId}");
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error sending helper confirmation email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate HTML email content for helper confirmation
     * 
     * @param array $slotData Slot and event data
     * @param array $userData User data
     * @return string HTML email content
     */
    private function generateHelperConfirmationEmailHtml(array $slotData, array $userData): string {
        // Format date and time
        $eventDate = date('d.m.Y', strtotime($slotData['event_date']));
        $startTime = !empty($slotData['start_time']) ? date('H:i', strtotime($slotData['start_time'])) : '';
        $endTime = !empty($slotData['end_time']) ? date('H:i', strtotime($slotData['end_time'])) : '';
        $timeRange = $startTime && $endTime ? "{$startTime} - {$endTime} Uhr" : '';
        
        $location = !empty($slotData['location']) ? $slotData['location'] : 'Noch nicht bekannt';
        
        // Logo URL
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        // Full name
        $fullName = trim(($userData['firstname'] ?? '') . ' ' . ($userData['lastname'] ?? ''));
        
        // Build HTML email
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helfer-Anmeldung Bestätigung</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 40px; text-align: center;">
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" style="max-width: 150px; height: auto;">
                            <h1 style="color: #ffffff; margin: 20px 0 0 0; font-size: 24px; font-weight: 600;">
                                Anmeldung bestätigt
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hallo ' . htmlspecialchars($fullName) . ',
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                vielen Dank für deine Anmeldung als Helfer! Wir freuen uns, dass du dabei bist.
                            </p>
                            
                            <!-- Event Details Card -->
                            <div style="background-color: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 0 0 30px 0; border-radius: 4px;">
                                <h2 style="color: #667eea; font-size: 20px; margin: 0 0 15px 0; font-weight: 600;">
                                    ' . htmlspecialchars($slotData['event_title']) . '
                                </h2>
                                
                                <table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px; width: 140px; vertical-align: top;">
                                            <strong>📅 Datum:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px;">
                                            ' . htmlspecialchars($eventDate) . '
                                        </td>
                                    </tr>
                                    ' . ($timeRange ? '<tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px; vertical-align: top;">
                                            <strong>🕐 Zeit:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px;">
                                            ' . htmlspecialchars($timeRange) . '
                                        </td>
                                    </tr>' : '') . '
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px; vertical-align: top;">
                                            <strong>📍 Ort:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px;">
                                            ' . htmlspecialchars($location) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px; vertical-align: top;">
                                            <strong>✅ Aufgabe:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px;">
                                            ' . htmlspecialchars($slotData['task_name']) . '
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                                Weitere Informationen zum Event findest du im Intranet.
                            </p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . htmlspecialchars(SITE_URL . '/index.php?page=events') . '" 
                                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                          color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 5px; 
                                          font-weight: 600; font-size: 16px;">
                                    Zum Event-Bereich
                                </a>
                            </div>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                Viele Grüße,<br>
                                <strong>Dein IBC-Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet gesendet.<br>
                                Bei Fragen wende dich bitte an den Event-Veranstalter.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Log message to application log file
     * 
     * @param string $message Message to log
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [HELPER-SERVICE] {$message}" . PHP_EOL;
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
}
