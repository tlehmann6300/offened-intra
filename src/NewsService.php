<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * News Service Class
 * Handles email notifications for project announcements
 * Uses PHPMailer for SMTP email delivery
 * 
 * Note: News and event notifications have been deprecated.
 * This service now only handles project announcement notifications.
 */
class NewsService {
    private PDO $pdo;
    private string $logFile;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logFile = BASE_PATH . '/logs/app.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Send news notification email to all subscribers
     * 
     * @deprecated This method is deprecated. Use sendProjectNotification() instead.
     * @param int $newsId ID of the news article to send
     * @return array Result with success status and details
     */
    public function sendNewsNotification(int $newsId): array {
        $this->log("Warning: sendNewsNotification is deprecated and no longer sends emails.");
        
        return [
            'success' => true,
            'message' => 'News notifications have been disabled',
            'sent' => 0,
            'failed' => 0
        ];
    }
    
    /**
     * Send event notification email to all opted-in users
     * 
     * @deprecated This method is deprecated. Use sendProjectNotification() instead.
     * @param int $eventId ID of the event to send notification for
     * @return array Result with success status and details
     */
    public function sendEventNotification(int $eventId): array {
        $this->log("Warning: sendEventNotification is deprecated and no longer sends emails.");
        
        return [
            'success' => true,
            'message' => 'Event notifications have been disabled',
            'sent' => 0,
            'failed' => 0
        ];
    }
    
    /**
     * Send project notification email to all users with project_alerts enabled
     * 
     * @param int $projectId ID of the project to send notification for
     * @return array Result with success status and details
     */
    public function sendProjectNotification(int $projectId): array {
        // Get project details
        $projectStmt = $this->pdo->prepare("
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
                p.image_path,
                u.firstname AS creator_firstname,
                u.lastname AS creator_lastname
            FROM projects p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ");
        
        $projectStmt->execute([$projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            $this->log("Error: Project with ID {$projectId} not found");
            return [
                'success' => false,
                'message' => 'Project not found',
                'sent' => 0,
                'failed' => 0
            ];
        }
        
        // Get all users with project_alerts = 1
        $recipientsStmt = $this->pdo->query("
            SELECT id, email, firstname, lastname
            FROM users
            WHERE project_alerts = 1
            AND email IS NOT NULL 
            AND email != ''
        ");
        
        $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($recipients)) {
            $this->log("No users with project alerts enabled for project notification (Project ID: {$projectId})");
            return [
                'success' => true,
                'message' => 'No users to notify',
                'sent' => 0,
                'failed' => 0
            ];
        }
        
        // Generate email HTML content
        $emailHtml = $this->generateProjectEmailHtml($project);
        $emailSubject = "Neue Projekt-Ausschreibung: " . $project['title'];
        
        $sentCount = 0;
        $failedCount = 0;
        
        // Send email to each recipient
        foreach ($recipients as $recipient) {
            try {
                $result = $this->sendEmail(
                    $recipient['email'],
                    $emailSubject,
                    $emailHtml,
                    $recipient['firstname'] . ' ' . $recipient['lastname']
                );
                
                if ($result) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            } catch (Exception $e) {
                $failedCount++;
                $this->log("Failed to send email to {$recipient['email']}: " . $e->getMessage());
            }
        }
        
        $this->log("Project notification sent: Project ID {$projectId}, Sent: {$sentCount}, Failed: {$failedCount}");
        
        return [
            'success' => $sentCount > 0,
            'message' => "Sent to {$sentCount} users, {$failedCount} failed",
            'sent' => $sentCount,
            'failed' => $failedCount
        ];
    }
    
    /**
     * Generate HTML email content in IBC design
     * 
     * @param array $news News article data
     * @return string HTML email content
     */
    private function generateEmailHtml(array $news): string {
        // Extract teaser from content
        $teaser = $this->generateTeaser($news['content']);
        
        // Prepare image URL
        $imageUrl = '';
        if (!empty($news['image_path'])) {
            $imageUrl = SITE_URL . '/' . ltrim($news['image_path'], '/');
        }
        
        // Prepare CTA link - default to news page
        $ctaLink = !empty($news['cta_link']) ? $news['cta_link'] : SITE_URL . '/index.php?page=newsroom';
        $ctaLabel = !empty($news['cta_label']) ? $news['cta_label'] : 'Mehr erfahren';
        
        // Logo URL
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        // Author name
        $authorName = trim(($news['author_firstname'] ?? '') . ' ' . ($news['author_lastname'] ?? ''));
        if (empty($authorName)) {
            $authorName = 'IBC Team';
        }
        
        // Build HTML email
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($news['title']) . '</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 200px;
            height: auto;
        }
        .content {
            padding: 40px 30px;
        }
        .news-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333333;
            font-size: 24px;
            margin: 0 0 20px 0;
            line-height: 1.3;
        }
        .teaser {
            color: #666666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px 0;
        }
        .author {
            color: #999999;
            font-size: 14px;
            font-style: italic;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .footer {
            background-color: #f8f8f8;
            padding: 30px;
            text-align: center;
            color: #999999;
            font-size: 14px;
            line-height: 1.6;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" class="logo">
        </div>
        <div class="content">';
        
        if ($imageUrl) {
            $html .= '
            <img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($news['title']) . '" class="news-image">';
        }
        
        $html .= '
            <h1>' . htmlspecialchars($news['title']) . '</h1>
            <div class="teaser">' . htmlspecialchars($teaser) . '</div>
            <a href="' . htmlspecialchars($ctaLink) . '" class="cta-button">' . htmlspecialchars($ctaLabel) . '</a>
            <div class="author">Von ' . htmlspecialchars($authorName) . '</div>
        </div>
        <div class="footer">
            <p><strong>🔔 System-Benachrichtigung</strong></p>
            <p>Sie erhalten diese E-Mail, weil Sie News-Benachrichtigungen abonniert haben.</p>
            <p>
                <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=newsroom">News anzeigen</a> | 
                <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a>
            </p>
            <p>
                Sie können News-Benachrichtigungen jederzeit in Ihren <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a> deaktivieren.
            </p>
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate HTML email content for event notifications in IBC design
     * 
     * @param array $event Event data
     * @return string HTML email content
     */
    private function generateEventEmailHtml(array $event): string {
        // Extract teaser from description
        $teaser = $this->generateTeaser($event['description']);
        
        // Prepare image URL
        $imageUrl = '';
        if (!empty($event['image_path'])) {
            $imageUrl = SITE_URL . '/' . ltrim($event['image_path'], '/');
        }
        
        // Format event date
        $eventDate = date('d.m.Y H:i', strtotime($event['event_date']));
        
        // Event location
        $location = !empty($event['location']) ? $event['location'] : 'Ort wird noch bekannt gegeben';
        
        // Direct link to event page with event ID (validated as integer)
        $eventLink = SITE_URL . '/index.php?page=events&event_id=' . (int)$event['id'];
        
        // Logo URL
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        // Creator name
        $creatorName = trim(($event['creator_firstname'] ?? '') . ' ' . ($event['creator_lastname'] ?? ''));
        if (empty($creatorName)) {
            $creatorName = 'IBC Team';
        }
        
        // Build HTML email
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($event['title']) . '</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 200px;
            height: auto;
        }
        .content {
            padding: 40px 30px;
        }
        .event-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333333;
            font-size: 24px;
            margin: 0 0 20px 0;
            line-height: 1.3;
        }
        .event-details {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .event-detail-item {
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        .event-detail-label {
            font-weight: 600;
            color: #667eea;
        }
        .description {
            color: #666666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px 0;
        }
        .creator {
            color: #999999;
            font-size: 14px;
            font-style: italic;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .footer {
            background-color: #f8f8f8;
            padding: 30px;
            text-align: center;
            color: #999999;
            font-size: 14px;
            line-height: 1.6;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" class="logo">
        </div>
        <div class="content">';
        
        if ($imageUrl) {
            $html .= '
            <img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($event['title']) . '" class="event-image">';
        }
        
        $html .= '
            <h1>' . htmlspecialchars($event['title']) . '</h1>
            <div class="event-details">
                <div class="event-detail-item">
                    <span class="event-detail-label">📅 Datum:</span> ' . htmlspecialchars($eventDate) . '
                </div>
                <div class="event-detail-item">
                    <span class="event-detail-label">📍 Ort:</span> ' . htmlspecialchars($location) . '
                </div>
            </div>
            <div class="description">' . htmlspecialchars($teaser) . '</div>
            <a href="' . htmlspecialchars($eventLink) . '" class="cta-button">Event Details anzeigen</a>
            <div class="creator">Organisiert von ' . htmlspecialchars($creatorName) . '</div>
        </div>
        <div class="footer">
            <p><strong>🔔 System-Benachrichtigung</strong></p>
            <p>Sie erhalten diese E-Mail, weil Sie Event-Benachrichtigungen abonniert haben.</p>
            <p>
                <a href="' . htmlspecialchars($eventLink) . '">Event anzeigen</a> | 
                <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a>
            </p>
            <p>
                Sie können Event-Benachrichtigungen jederzeit in Ihren <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a> deaktivieren.
            </p>
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate HTML email content for project notifications in IBC design
     * 
     * @param array $project Project data
     * @return string HTML email content
     */
    private function generateProjectEmailHtml(array $project): string {
        // Extract teaser from description
        $teaser = $this->generateTeaser($project['description']);
        
        // Prepare image URL
        $imageUrl = '';
        if (!empty($project['image_path'])) {
            $imageUrl = SITE_URL . '/' . ltrim($project['image_path'], '/');
        }
        
        // Format project details
        $startDate = !empty($project['start_date']) ? date('d.m.Y', strtotime($project['start_date'])) : 'Noch nicht festgelegt';
        $endDate = !empty($project['end_date']) ? date('d.m.Y', strtotime($project['end_date'])) : 'Noch nicht festgelegt';
        $client = !empty($project['client']) ? $project['client'] : 'Vertraulich';
        $teamSize = !empty($project['team_size']) ? $project['team_size'] . ' Personen' : 'Noch offen';
        
        // Map project type to German
        $projectTypes = [
            'consulting' => 'Consulting',
            'internal' => 'Intern',
            'research' => 'Forschung',
            'event' => 'Event',
            'marketing' => 'Marketing'
        ];
        $projectType = $projectTypes[$project['project_type']] ?? $project['project_type'];
        
        // Map status to German
        $statusLabels = [
            'planning' => 'In Planung',
            'active' => 'Aktiv',
            'on_hold' => 'Pausiert',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen'
        ];
        $status = $statusLabels[$project['status']] ?? $project['status'];
        
        // Direct link to projects page
        $projectLink = SITE_URL . '/index.php?page=projects';
        
        // Logo URL
        $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
        
        // Creator name
        $creatorName = trim(($project['creator_firstname'] ?? '') . ' ' . ($project['creator_lastname'] ?? ''));
        if (empty($creatorName)) {
            $creatorName = 'IBC Team';
        }
        
        // Build HTML email
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($project['title']) . '</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 200px;
            height: auto;
        }
        .content {
            padding: 40px 30px;
        }
        .project-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333333;
            font-size: 24px;
            margin: 0 0 20px 0;
            line-height: 1.3;
        }
        .project-details {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .project-detail-item {
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        .project-detail-label {
            font-weight: 600;
            color: #667eea;
        }
        .description {
            color: #666666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px 0;
        }
        .creator {
            color: #999999;
            font-size: 14px;
            font-style: italic;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .footer {
            background-color: #f8f8f8;
            padding: 30px;
            text-align: center;
            color: #999999;
            font-size: 14px;
            line-height: 1.6;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" class="logo">
        </div>
        <div class="content">';
        
        if ($imageUrl) {
            $html .= '
            <img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($project['title']) . '" class="project-image">';
        }
        
        $html .= '
            <h1>🚀 ' . htmlspecialchars($project['title']) . '</h1>
            <div class="project-details">
                <div class="project-detail-item">
                    <span class="project-detail-label">📋 Projekttyp:</span> ' . htmlspecialchars($projectType) . '
                </div>
                <div class="project-detail-item">
                    <span class="project-detail-label">🏢 Kunde:</span> ' . htmlspecialchars($client) . '
                </div>
                <div class="project-detail-item">
                    <span class="project-detail-label">📅 Zeitraum:</span> ' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . '
                </div>
                <div class="project-detail-item">
                    <span class="project-detail-label">👥 Teamgröße:</span> ' . htmlspecialchars($teamSize) . '
                </div>
                <div class="project-detail-item">
                    <span class="project-detail-label">📊 Status:</span> ' . htmlspecialchars($status) . '
                </div>
            </div>
            <div class="description">' . htmlspecialchars($teaser) . '</div>
            <a href="' . htmlspecialchars($projectLink) . '" class="cta-button">Projekt-Details anzeigen</a>
            <div class="creator">Erstellt von ' . htmlspecialchars($creatorName) . '</div>
        </div>
        <div class="footer">
            <p><strong>🔔 Projekt-Ausschreibung</strong></p>
            <p>Sie erhalten diese E-Mail, weil Sie Benachrichtigungen für Projekt-Ausschreibungen abonniert haben.</p>
            <p>
                <a href="' . htmlspecialchars($projectLink) . '">Projekte anzeigen</a> | 
                <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a>
            </p>
            <p>
                Sie können Projekt-Benachrichtigungen jederzeit in Ihren <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings">Benachrichtigungseinstellungen</a> deaktivieren.
            </p>
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate teaser text from content (first 200 characters)
     * 
     * @param string $content Content to generate teaser from
     * @return string Teaser text
     */
    private function generateTeaser(string $content): string {
        $teaser = strip_tags($content);
        if (strlen($teaser) > 200) {
            $teaser = substr($teaser, 0, 197) . '...';
        }
        return $teaser;
    }
    
    /**
     * Send email using PHPMailer with SMTP
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $recipientName Recipient name
     * @return bool Success status
     */
    private function sendEmail(string $to, string $subject, string $htmlBody, string $recipientName = ''): bool {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to, $recipientName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            
            // Generate plain text alternative
            $plainText = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n\n", $htmlBody));
            $mail->AltBody = $plainText;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->log("Email send failed to {$to}: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Log message to application log file
     * 
     * @param string $message Message to log
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [NEWS-SERVICE] {$message}" . PHP_EOL;
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
}
