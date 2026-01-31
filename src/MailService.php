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
}
