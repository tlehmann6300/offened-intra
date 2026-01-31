<?php
declare(strict_types=1);

/**
 * Birthday Check Cron Script
 * 
 * This script should be run daily (e.g., via cron job at 00:05 AM) to:
 * 1. Find users whose birthday (day/month) matches today's date
 * 2. Check their notify_birthday privacy setting
 * 3. Send congratulations emails to members (if notify_birthday = TRUE)
 * 4. Send a summary email to the board (admin/vorstand)
 * 
 * Usage:
 *   php /path/to/cron/birthday_check.php
 * 
 * Cron setup example (runs daily at 00:05 AM):
 *   5 0 * * * cd /path/to/intra && php cron/birthday_check.php >> logs/birthday_check.log 2>&1
 * 
 * @author IBC Development Team
 * @version 1.0.0
 */

// Prevent direct browser access - only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

// Set up paths
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/MailService.php';

// Start script
$startTime = microtime(true);
echo "=====================================\n";
echo "Birthday Check Script Started\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "=====================================\n\n";

try {
    // Get database connection (User-DB for users table)
    $userPdo = DatabaseManager::getUserConnection();
    echo "✓ Connected to User-DB\n\n";
    
    // Get today's day and month
    $today = new DateTime();
    $todayMonth = $today->format('m'); // 01-12
    $todayDay = $today->format('d');   // 01-31
    
    echo "Checking for birthdays on: {$todayDay}.{$todayMonth}\n\n";
    
    // Query users with birthday today AND notify_birthday = TRUE
    $stmt = $userPdo->prepare("
        SELECT 
            id,
            email,
            firstname,
            lastname,
            birthdate,
            notify_birthday,
            role
        FROM users
        WHERE 
            birthdate IS NOT NULL
            AND MONTH(birthdate) = :month
            AND DAY(birthdate) = :day
            AND notify_birthday = 1
        ORDER BY firstname, lastname
    ");
    
    $stmt->execute([
        'month' => $todayMonth,
        'day' => $todayDay
    ]);
    
    $birthdayUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $birthdayCount = count($birthdayUsers);
    
    echo "Found {$birthdayCount} user(s) with birthday today (with notify_birthday = TRUE)\n\n";
    
    if ($birthdayCount === 0) {
        echo "No birthdays to process today.\n";
        exit(0);
    }
    
    // Initialize MailService
    $mailService = new MailService(BASE_PATH . '/logs/birthday_mail.log');
    echo "✓ MailService initialized\n\n";
    
    // Track results
    $emailsSent = 0;
    $emailsFailed = 0;
    $birthdayList = [];
    
    // Send birthday emails to each user
    echo "Sending birthday congratulations emails...\n";
    echo "----------------------------------------\n";
    
    foreach ($birthdayUsers as $user) {
        $fullName = trim($user['firstname'] . ' ' . $user['lastname']);
        
        // Calculate age if birth year is available
        $age = '';
        if ($user['birthdate']) {
            $birthDate = new DateTime($user['birthdate']);
            $ageYears = $today->diff($birthDate)->y;
            $age = " ({$ageYears} Jahre)";
        }
        
        echo "Processing: {$fullName}{$age} <{$user['email']}>\n";
        
        // Generate birthday email content
        $emailSubject = "🎉 Alles Gute zum Geburtstag, {$user['firstname']}!";
        $emailContent = generateBirthdayEmailContent($user['firstname'], $user['lastname']);
        
        // Send email
        $success = $mailService->sendEmail(
            $user['email'],
            $emailSubject,
            $emailContent,
            $fullName
        );
        
        if ($success) {
            echo "  ✓ Email sent successfully\n";
            $emailsSent++;
        } else {
            echo "  ✗ Failed to send email\n";
            $emailsFailed++;
        }
        
        // Add to summary list for admin
        $birthdayList[] = [
            'name' => $fullName,
            'email' => $user['email'],
            'age' => $age,
            'email_sent' => $success
        ];
    }
    
    echo "\n";
    
    // Send summary to board members (vorstand/admin)
    echo "Sending summary to board members...\n";
    echo "----------------------------------------\n";
    
    // Get board members (vorstand and admin roles)
    $boardStmt = $userPdo->prepare("
        SELECT email, firstname, lastname
        FROM users
        WHERE role IN ('vorstand', 'admin')
        AND email IS NOT NULL
        ORDER BY role, firstname
    ");
    $boardStmt->execute();
    $boardMembers = $boardStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($boardMembers) > 0) {
        $adminEmailContent = generateAdminSummaryEmail($birthdayList, $today->format('d.m.Y'));
        $adminEmailSubject = "📊 Geburtstage heute: {$birthdayCount} Mitglied(er)";
        
        foreach ($boardMembers as $board) {
            $boardName = trim($board['firstname'] . ' ' . $board['lastname']);
            echo "Sending to: {$boardName} <{$board['email']}>\n";
            
            $success = $mailService->sendEmail(
                $board['email'],
                $adminEmailSubject,
                $adminEmailContent,
                $boardName
            );
            
            if ($success) {
                echo "  ✓ Summary sent successfully\n";
            } else {
                echo "  ✗ Failed to send summary\n";
            }
        }
    } else {
        echo "⚠ No board members found (vorstand/admin role)\n";
    }
    
    echo "\n";
    
    // Final summary
    echo "=====================================\n";
    echo "Birthday Check Completed\n";
    echo "=====================================\n";
    echo "Users with birthdays: {$birthdayCount}\n";
    echo "Emails sent successfully: {$emailsSent}\n";
    echo "Emails failed: {$emailsFailed}\n";
    echo "Board members notified: " . count($boardMembers) . "\n";
    
    $executionTime = round(microtime(true) - $startTime, 2);
    echo "Execution time: {$executionTime} seconds\n";
    echo "=====================================\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Generate birthday congratulations email content
 * 
 * @param string $firstname User's first name
 * @param string $lastname User's last name
 * @return string HTML email content
 */
function generateBirthdayEmailContent(string $firstname, string $lastname): string {
    $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
    
    return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alles Gute zum Geburtstag!</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;">
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" style="max-width: 150px; height: auto; margin-bottom: 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 700;">🎉 Alles Gute zum Geburtstag! 🎉</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #333333; font-size: 18px; line-height: 1.6; margin: 0 0 20px 0;">
                                Liebe(r) ' . htmlspecialchars($firstname) . ',
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                das gesamte IBC-Team wünscht Dir alles Gute zu Deinem Geburtstag! 🎂
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Wir hoffen, dass Du einen wunderschönen Tag mit Deinen Liebsten verbringst und wünschen Dir für das kommende Lebensjahr alles erdenklich Gute, Gesundheit, Erfolg und viele glückliche Momente.
                            </p>
                            
                            <div style="background-color: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 30px 0; border-radius: 4px;">
                                <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0; font-style: italic;">
                                    "Das Alter ist keine Last, sondern eine Schatzkammer voller Erfahrungen und Weisheit."
                                </p>
                            </div>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Genieße Deinen besonderen Tag!
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0;">
                                Herzliche Grüße,<br>
                                <strong>Dein IBC-Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet gesendet.<br>
                                Du erhältst diese E-Mail, weil Du Deine Geburtstags-Benachrichtigungen aktiviert hast.<br>
                                <a href="' . htmlspecialchars(SITE_URL) . '/index.php?page=settings" style="color: #667eea; text-decoration: none;">Einstellungen ändern</a>
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
 * Generate admin summary email content
 * 
 * @param array $birthdayList List of users with birthdays
 * @param string $date Today's date formatted
 * @return string HTML email content
 */
function generateAdminSummaryEmail(array $birthdayList, string $date): string {
    $logoUrl = SITE_URL . '/assets/img/ibc_logo_original.webp';
    $count = count($birthdayList);
    
    // Build birthday list HTML
    $listHtml = '';
    foreach ($birthdayList as $birthday) {
        $statusIcon = $birthday['email_sent'] ? '✓' : '✗';
        $statusColor = $birthday['email_sent'] ? '#28a745' : '#dc3545';
        $statusText = $birthday['email_sent'] ? 'E-Mail versendet' : 'E-Mail fehlgeschlagen';
        
        $listHtml .= '
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                    ' . htmlspecialchars($birthday['name']) . htmlspecialchars($birthday['age']) . '
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #e0e0e0; color: #666666; font-size: 14px;">
                    ' . htmlspecialchars($birthday['email']) . '
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: center;">
                    <span style="color: ' . $statusColor . '; font-weight: bold; font-size: 16px;">' . $statusIcon . '</span>
                    <span style="color: ' . $statusColor . '; font-size: 12px; display: block; margin-top: 4px;">' . $statusText . '</span>
                </td>
            </tr>
        ';
    }
    
    return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geburtstags-Übersicht</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="700" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 40px; text-align: center;">
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="IBC Logo" style="max-width: 150px; height: auto; margin-bottom: 15px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">📊 Geburtstags-Übersicht</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">' . htmlspecialchars($date) . '</p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hallo,
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                heute haben <strong>' . $count . ' Mitglied' . ($count !== 1 ? 'er' : '') . '</strong> Geburtstag. Hier ist die Übersicht:
                            </p>
                            
                            <!-- Birthday table -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin: 0 0 30px 0; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden;">
                                <thead>
                                    <tr style="background-color: #f8f9fa;">
                                        <th style="padding: 12px; text-align: left; color: #555555; font-size: 14px; font-weight: 600; border-bottom: 2px solid #e0e0e0;">Name</th>
                                        <th style="padding: 12px; text-align: left; color: #555555; font-size: 14px; font-weight: 600; border-bottom: 2px solid #e0e0e0;">E-Mail</th>
                                        <th style="padding: 12px; text-align: center; color: #555555; font-size: 14px; font-weight: 600; border-bottom: 2px solid #e0e0e0;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $listHtml . '
                                </tbody>
                            </table>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                <strong>Hinweis:</strong> Diese Übersicht enthält nur Mitglieder, die ihre Geburtstags-Benachrichtigungen aktiviert haben (notify_birthday = TRUE).
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #999999; font-size: 12px; line-height: 1.5; margin: 0;">
                                Diese E-Mail wurde automatisch vom IBC-Intranet Geburtstags-Checker generiert.<br>
                                Cron-Script: birthday_check.php
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
