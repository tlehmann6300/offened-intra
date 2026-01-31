<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Mail Service Class
 * Centralized email service using PHPMailer with IONOS SMTP
 * Provides a simple interface for sending emails throughout the application
 * 
 * @requires PHP 8.0+ (uses typed properties and union types)
 * @requires PHPMailer 6.9+
 */
class MailService {
    private string $logFile;
    
    /**
     * Constructor
     * 
     * @param string|null $logFile Optional custom log file path
     */
    public function __construct(?string $logFile = null) {
        $this->logFile = $logFile ?? (BASE_PATH . '/logs/mail.log');
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Send an email using PHPMailer with IONOS SMTP configuration
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $recipientName Optional recipient name
     * @param string|null $plainTextBody Optional plain text alternative body
     * @return bool True on success, false on failure
     */
    public function sendEmail(
        string $to, 
        string $subject, 
        string $htmlBody, 
        string $recipientName = '',
        ?string $plainTextBody = null
    ): bool {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $this->log("Error: PHPMailer class not available");
                return false;
            }
            
            // Check if SMTP configuration is available
            if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS') || 
                empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
                $this->log("Error: SMTP configuration not properly defined");
                return false;
            }
            
            // Validate email address
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->log("Error: Invalid email address: {$to}");
                return false;
            }
            
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->CharSet    = 'UTF-8';
            
            // Set from address and name
            $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USER;
            $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'IBC Intranet';
            $mail->setFrom($fromEmail, $fromName);
            
            // Add recipient
            $mail->addAddress($to, $recipientName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            
            // Generate plain text alternative if not provided
            if ($plainTextBody === null) {
                $plainTextBody = $this->generatePlainTextFromHtml($htmlBody);
            }
            $mail->AltBody = $plainTextBody;
            
            // Send email
            $mail->send();
            $this->log("Email sent successfully to {$to}: {$subject}");
            return true;
            
        } catch (Exception $e) {
            $errorInfo = isset($mail) && isset($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
            $this->log("Failed to send email to {$to}: {$errorInfo}");
            return false;
        } catch (\Throwable $e) {
            $this->log("Unexpected error sending email to {$to}: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Send a simple text email (convenience method)
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Plain text message
     * @param string $recipientName Optional recipient name
     * @return bool True on success, false on failure
     */
    public function sendTextEmail(
        string $to, 
        string $subject, 
        string $message, 
        string $recipientName = ''
    ): bool {
        // Convert plain text to simple HTML
        $htmlBody = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $htmlBody = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        ' . $htmlBody . '
    </div>
</body>
</html>';
        
        return $this->sendEmail($to, $subject, $htmlBody, $recipientName, $message);
    }
    
    /**
     * Generate plain text version from HTML
     * 
     * @param string $html HTML content
     * @return string Plain text content
     */
    private function generatePlainTextFromHtml(string $html): string {
        // Replace common HTML line breaks with newlines
        $text = str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n\n", $html);
        
        // Remove all HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Clean up multiple newlines
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Send a notification email using pre-configured templates
     * This is a convenience method that wraps the email body in an IBC-branded template
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body content (can be plain text or HTML)
     * @param string $recipientName Optional recipient name
     * @return bool True on success, false on failure
     */
    public function sendNotification(
        string $to, 
        string $subject, 
        string $body, 
        string $recipientName = ''
    ): bool {
        // Wrap the body in the standard IBC template
        $htmlBody = $this->wrapInTemplate($body, $subject);
        return $this->sendEmail($to, $subject, $htmlBody, $recipientName);
    }
    
    /**
     * Send helper confirmation notification
     * 
     * @param string $to Recipient email address
     * @param string $recipientName Full name of recipient
     * @param array $eventData Event details (title, date, location)
     * @param array $slotData Slot details (task_name, start_time, end_time)
     * @return bool True on success, false on failure
     */
    public function sendHelperConfirmation(
        string $to,
        string $recipientName,
        array $eventData,
        array $slotData
    ): bool {
        $subject = "Bestätigung: Helfer-Anmeldung für " . $eventData['title'];
        $htmlBody = $this->generateHelperConfirmationTemplate($recipientName, $eventData, $slotData);
        return $this->sendEmail($to, $subject, $htmlBody, $recipientName);
    }
    
    /**
     * Send password reset notification
     * 
     * @param string $to Recipient email address
     * @param string $recipientName Full name of recipient
     * @param string $resetToken Password reset token
     * @param string $resetLink Full password reset link
     * @return bool True on success, false on failure
     */
    public function sendPasswordReset(
        string $to,
        string $recipientName,
        string $resetToken,
        string $resetLink
    ): bool {
        $subject = "Passwort zurücksetzen - IBC Intranet";
        $htmlBody = $this->generatePasswordResetTemplate($recipientName, $resetLink, $resetToken);
        return $this->sendEmail($to, $subject, $htmlBody, $recipientName);
    }
    
    /**
     * Send alumni account notification
     * 
     * @param string $to Recipient email address
     * @param string $recipientName Full name of recipient
     * @param string $username Account username/email
     * @param string $temporaryPassword Temporary password (optional)
     * @return bool True on success, false on failure
     */
    public function sendAlumniNotification(
        string $to,
        string $recipientName,
        string $username,
        string $temporaryPassword = ''
    ): bool {
        $subject = "Willkommen im IBC Alumni-Netzwerk";
        $htmlBody = $this->generateAlumniNotificationTemplate($recipientName, $username, $temporaryPassword);
        return $this->sendEmail($to, $subject, $htmlBody, $recipientName);
    }
    
    /**
     * Wrap content in the standard IBC-branded email template
     * 
     * @param string $content Email body content
     * @param string $title Optional title for the email
     * @return string Complete HTML email
     */
    private function wrapInTemplate(string $content, string $title = ''): string {
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
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
                            ' . ($title ? '<h1 style="color: #ffffff; margin: 20px 0 0 0; font-size: 24px; font-weight: 600;">' . htmlspecialchars($title) . '</h1>' : '') . '
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet gesendet.<br>
                                Bei Fragen wenden Sie sich bitte an das IBC-Team.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Generate helper confirmation email template
     * 
     * @param string $recipientName Full name of recipient
     * @param array $eventData Event details
     * @param array $slotData Slot details
     * @return string HTML email content
     */
    private function generateHelperConfirmationTemplate(
        string $recipientName,
        array $eventData,
        array $slotData
    ): string {
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        // Format date and time
        $eventDate = date('d.m.Y', strtotime($eventData['date']));
        $startTime = !empty($slotData['start_time']) ? date('H:i', strtotime($slotData['start_time'])) : '';
        $endTime = !empty($slotData['end_time']) ? date('H:i', strtotime($slotData['end_time'])) : '';
        $timeRange = $startTime && $endTime ? "{$startTime} - {$endTime} Uhr" : '';
        
        $location = !empty($eventData['location']) ? $eventData['location'] : 'Noch nicht bekannt';
        
        return '<!DOCTYPE html>
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
                                Hallo ' . htmlspecialchars($recipientName) . ',
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                vielen Dank für deine Anmeldung als Helfer! Wir freuen uns, dass du dabei bist.
                            </p>
                            
                            <!-- Event Details Card -->
                            <div style="background-color: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 0 0 30px 0; border-radius: 4px;">
                                <h2 style="color: #667eea; font-size: 20px; margin: 0 0 15px 0; font-weight: 600;">
                                    ' . htmlspecialchars($eventData['title']) . '
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
    }
    
    /**
     * Generate password reset email template
     * 
     * @param string $recipientName Full name of recipient
     * @param string $resetLink Password reset link
     * @param string $resetToken Reset token for display
     * @return string HTML email content
     */
    private function generatePasswordResetTemplate(
        string $recipientName,
        string $resetLink,
        string $resetToken
    ): string {
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen</title>
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
                                Passwort zurücksetzen
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hallo ' . htmlspecialchars($recipientName) . ',
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt. Klicken Sie auf den Button unten, um ein neues Passwort festzulegen.
                            </p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . htmlspecialchars($resetLink) . '" 
                                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                          color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 5px; 
                                          font-weight: 600; font-size: 16px;">
                                    Passwort zurücksetzen
                                </a>
                            </div>
                            
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 30px 0; border-radius: 4px;">
                                <p style="color: #856404; font-size: 14px; line-height: 1.6; margin: 0;">
                                    <strong>⚠️ Sicherheitshinweis:</strong><br>
                                    Dieser Link ist nur für 24 Stunden gültig. Wenn Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail bitte.
                                </p>
                            </div>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                Falls der Button nicht funktioniert, kopieren Sie bitte folgenden Link in Ihren Browser:
                            </p>
                            <p style="color: #667eea; font-size: 12px; word-break: break-all; margin: 10px 0 30px 0;">
                                ' . htmlspecialchars($resetLink) . '
                            </p>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                Mit freundlichen Grüßen,<br>
                                <strong>Ihr IBC-Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet gesendet.<br>
                                Bei Fragen wenden Sie sich bitte an das IBC-Team.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Generate alumni notification email template
     * 
     * @param string $recipientName Full name of recipient
     * @param string $username Account username/email
     * @param string $temporaryPassword Temporary password (if provided)
     * @return string HTML email content
     */
    private function generateAlumniNotificationTemplate(
        string $recipientName,
        string $username,
        string $temporaryPassword
    ): string {
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        $loginUrl = SITE_URL . '/index.php?page=login';
        
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen im IBC Alumni-Netzwerk</title>
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
                                Willkommen im Alumni-Netzwerk! 🎓
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hallo ' . htmlspecialchars($recipientName) . ',
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                herzlich willkommen im IBC Alumni-Netzwerk! Ihr Account wurde erfolgreich erstellt und Sie können sich ab sofort im Intranet anmelden.
                            </p>
                            
                            <!-- Login Details Card -->
                            <div style="background-color: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 30px 0; border-radius: 4px;">
                                <h2 style="color: #667eea; font-size: 18px; margin: 0 0 15px 0; font-weight: 600;">
                                    Ihre Zugangsdaten
                                </h2>
                                
                                <table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px; width: 140px;">
                                            <strong>Benutzername:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px; font-family: monospace;">
                                            ' . htmlspecialchars($username) . '
                                        </td>
                                    </tr>
                                    ' . ($temporaryPassword ? '<tr>
                                        <td style="padding: 8px 0; color: #666666; font-size: 14px;">
                                            <strong>Passwort:</strong>
                                        </td>
                                        <td style="padding: 8px 0; color: #333333; font-size: 14px; font-family: monospace;">
                                            ' . htmlspecialchars($temporaryPassword) . '
                                        </td>
                                    </tr>' : '') . '
                                </table>
                            </div>
                            
                            ' . ($temporaryPassword ? '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <p style="color: #856404; font-size: 14px; line-height: 1.6; margin: 0;">
                                    <strong>⚠️ Wichtig:</strong><br>
                                    Bitte ändern Sie Ihr Passwort nach der ersten Anmeldung aus Sicherheitsgründen.
                                </p>
                            </div>' : '') . '
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . htmlspecialchars($loginUrl) . '" 
                                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                          color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 5px; 
                                          font-weight: 600; font-size: 16px;">
                                    Zum Login
                                </a>
                            </div>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 20px 0;">
                                Als Alumni haben Sie Zugriff auf:
                            </p>
                            
                            <ul style="color: #666666; font-size: 14px; line-height: 1.8; margin: 0 0 20px 0; padding-left: 20px;">
                                <li>Exklusive Networking-Events</li>
                                <li>News und Updates aus dem IBC-Netzwerk</li>
                                <li>Zugang zu Veranstaltungen und Workshops</li>
                                <li>Kontakt zu aktuellen und ehemaligen Mitgliedern</li>
                            </ul>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                Wir freuen uns, Sie als Teil unseres Netzwerks begrüßen zu dürfen!<br><br>
                                Mit freundlichen Grüßen,<br>
                                <strong>Ihr IBC-Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet gesendet.<br>
                                Bei Fragen wenden Sie sich bitte an das IBC-Team.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Log message to mail log file
     * 
     * @param string $message Message to log
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [MAIL-SERVICE] {$message}" . PHP_EOL;
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to mail log file: {$this->logFile}. Original message: {$message}");
        }
    }
    
    /**
     * Send invitation email with registration link
     * 
     * @param string $to Recipient email address
     * @param string $token Invitation token
     * @param string $role Role that will be assigned (for display)
     * @param string $invitedBy Name of admin who sent the invitation
     * @param string $expiresAt Expiration date/time
     * @return bool True on success, false on failure
     */
    public function sendInvitationEmail(
        string $to,
        string $token,
        string $role = 'alumni',
        string $invitedBy = '',
        string $expiresAt = ''
    ): bool {
        try {
            // Build registration link
            $registrationUrl = (defined('SITE_URL') ? SITE_URL : 'https://ibc-intra.de') . '/index.php?page=register&token=' . urlencode($token);
            
            // Format expiration time
            $expiresFormatted = '';
            if (!empty($expiresAt)) {
                $expiresFormatted = date('d.m.Y H:i', strtotime($expiresAt));
            }
            
            // Translate role to German
            $roleNames = [
                'alumni' => 'Alumni',
                'mitglied' => 'Mitglied',
                'ressortleiter' => 'Ressortleiter',
                'vorstand' => 'Vorstand',
                'admin' => 'Administrator'
            ];
            $roleDisplay = $roleNames[$role] ?? $role;
            
            // Build subject
            $subject = 'Einladung zur Registrierung - IBC Intranet';
            
            // Build HTML email body
            $htmlBody = '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Willkommen beim IBC Intranet</h1>
        </div>
        <div class="content">
            <p>Hallo,</p>
            
            <p>Sie wurden eingeladen, sich beim IBC Intranet zu registrieren' . ($invitedBy ? ' von <strong>' . htmlspecialchars($invitedBy, ENT_QUOTES, 'UTF-8') . '</strong>' : '') . '.</p>
            
            <div class="info-box">
                <strong>Ihre Rolle:</strong> ' . htmlspecialchars($roleDisplay, ENT_QUOTES, 'UTF-8') . '<br>
                ' . ($expiresFormatted ? '<strong>Gültig bis:</strong> ' . htmlspecialchars($expiresFormatted, ENT_QUOTES, 'UTF-8') : '') . '
            </div>
            
            <p>Um Ihre Registrierung abzuschließen, klicken Sie bitte auf den folgenden Link:</p>
            
            <div style="text-align: center;">
                <a href="' . htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') . '" class="button">Jetzt registrieren</a>
            </div>
            
            <p style="margin-top: 20px; font-size: 14px; color: #666;">
                Falls der Button nicht funktioniert, kopieren Sie bitte diesen Link in Ihren Browser:<br>
                <a href="' . htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') . '" style="color: #667eea; word-break: break-all;">' . htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') . '</a>
            </p>
            
            <p style="margin-top: 30px;">
                <strong>Hinweis:</strong> Dieser Link ist nur einmalig verwendbar' . ($expiresFormatted ? ' und gültig bis zum ' . htmlspecialchars($expiresFormatted, ENT_QUOTES, 'UTF-8') : '') . '.
            </p>
        </div>
        <div class="footer">
            <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.</p>
            <p>&copy; ' . date('Y') . ' IBC Intranet. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>';
            
            // Build plain text alternative
            $plainText = "Willkommen beim IBC Intranet\n\n";
            $plainText .= "Sie wurden eingeladen, sich beim IBC Intranet zu registrieren" . ($invitedBy ? " von {$invitedBy}" : "") . ".\n\n";
            $plainText .= "Ihre Rolle: {$roleDisplay}\n";
            if ($expiresFormatted) {
                $plainText .= "Gültig bis: {$expiresFormatted}\n";
            }
            $plainText .= "\nUm Ihre Registrierung abzuschließen, öffnen Sie bitte folgenden Link in Ihrem Browser:\n\n";
            $plainText .= "{$registrationUrl}\n\n";
            $plainText .= "Hinweis: Dieser Link ist nur einmalig verwendbar" . ($expiresFormatted ? " und gültig bis zum {$expiresFormatted}" : "") . ".\n\n";
            $plainText .= "Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.\n\n";
            $plainText .= "© " . date('Y') . " IBC Intranet. Alle Rechte vorbehalten.";
            
            // Send email
            $result = $this->sendEmail($to, $subject, $htmlBody, '', $plainText);
            
            if ($result) {
                $this->log("Invitation email sent successfully to: {$to}");
            } else {
                $this->log("Failed to send invitation email to: {$to}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Error sending invitation email to {$to}: " . $e->getMessage());
            return false;
        }
    }
}
