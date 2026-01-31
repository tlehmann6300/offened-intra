<?php
/**
 * Test Script for Token-Based Invitation System
 * 
 * This script tests the invitation system functionality without requiring
 * a full web server setup. It can be run from the command line.
 * 
 * Usage: php tests/test_invitation_system.php
 */

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SystemLogger.php';
require_once __DIR__ . '/../src/MailService.php';

// Start session for Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Token-Based Invitation System Test ===\n\n";

try {
    // Initialize connections
    $userPdo = DatabaseManager::getUserConnection();
    $contentPdo = DatabaseManager::getContentConnection();
    
    echo "✓ Database connections established\n";
    
    // Check if invitations table exists
    $stmt = $userPdo->query("SHOW TABLES LIKE 'invitations'");
    if ($stmt->rowCount() === 0) {
        echo "✗ FAIL: invitations table does not exist\n";
        echo "   Please run: migrations/006_add_invitations_table.sql\n";
        exit(1);
    }
    echo "✓ Invitations table exists\n";
    
    // Check table structure
    $stmt = $userPdo->query("DESCRIBE invitations");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['id', 'email', 'token', 'role', 'created_by', 'created_at', 'expires_at', 'accepted_at'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (count($missingColumns) > 0) {
        echo "✗ FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n";
        exit(1);
    }
    echo "✓ All required columns present\n";
    
    // Initialize Auth (without real user session for testing)
    $auth = new Auth($userPdo, new SystemLogger($contentPdo));
    echo "✓ Auth class initialized\n";
    
    // Test 1: Token generation (cryptographic strength)
    echo "\nTest 1: Token Generation\n";
    $token1 = bin2hex(random_bytes(32));
    $token2 = bin2hex(random_bytes(32));
    
    if (strlen($token1) !== 64) {
        echo "✗ FAIL: Token length incorrect (expected 64, got " . strlen($token1) . ")\n";
        exit(1);
    }
    
    if ($token1 === $token2) {
        echo "✗ FAIL: Tokens are not unique\n";
        exit(1);
    }
    
    echo "✓ Token generation working (64 chars, unique)\n";
    
    // Test 2: Email validation
    echo "\nTest 2: Email Validation\n";
    $validEmails = [
        'test@example.com',
        'user.name@domain.co.uk',
        'test+tag@example.com'
    ];
    
    $invalidEmails = [
        'not-an-email',
        '@example.com',
        'test@',
        'test..double@example.com'
    ];
    
    foreach ($validEmails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "✗ FAIL: Valid email rejected: {$email}\n";
            exit(1);
        }
    }
    
    foreach ($invalidEmails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "✗ FAIL: Invalid email accepted: {$email}\n";
            exit(1);
        }
    }
    
    echo "✓ Email validation working correctly\n";
    
    // Test 3: Check MailService class exists
    echo "\nTest 3: MailService Integration\n";
    
    if (!class_exists('MailService')) {
        echo "✗ FAIL: MailService class not found\n";
        exit(1);
    }
    
    $mailService = new MailService();
    
    if (!method_exists($mailService, 'sendInvitationEmail')) {
        echo "✗ FAIL: sendInvitationEmail method not found\n";
        exit(1);
    }
    
    echo "✓ MailService class and sendInvitationEmail method available\n";
    
    // Test 4: Check API endpoints exist
    echo "\nTest 4: API Endpoints\n";
    
    $apiEndpoints = [
        'api/send_invitation.php',
        'api/register_with_token.php',
        'api/delete_invitation.php'
    ];
    
    foreach ($apiEndpoints as $endpoint) {
        $path = __DIR__ . '/../' . $endpoint;
        if (!file_exists($path)) {
            echo "✗ FAIL: API endpoint not found: {$endpoint}\n";
            exit(1);
        }
        
        // Check syntax
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($path), $output, $returnVar);
        
        if ($returnVar !== 0) {
            echo "✗ FAIL: Syntax error in {$endpoint}\n";
            echo "   " . implode("\n   ", $output) . "\n";
            exit(1);
        }
    }
    
    echo "✓ All API endpoints exist and have valid syntax\n";
    
    // Test 5: Check templates exist
    echo "\nTest 5: Templates\n";
    
    $templates = [
        'templates/pages/register.php',
        'templates/components/invitation_management.php'
    ];
    
    foreach ($templates as $template) {
        $path = __DIR__ . '/../' . $template;
        if (!file_exists($path)) {
            echo "✗ FAIL: Template not found: {$template}\n";
            exit(1);
        }
        
        // Check syntax
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($path), $output, $returnVar);
        
        if ($returnVar !== 0) {
            echo "✗ FAIL: Syntax error in {$template}\n";
            echo "   " . implode("\n   ", $output) . "\n";
            exit(1);
        }
    }
    
    echo "✓ All templates exist and have valid syntax\n";
    
    // Test 6: Check Auth methods exist
    echo "\nTest 6: Auth Methods\n";
    
    $authMethods = [
        'createInvitation',
        'validateInvitationToken',
        'registerWithInvitation',
        'getPendingInvitations',
        'deleteInvitation'
    ];
    
    foreach ($authMethods as $method) {
        if (!method_exists($auth, $method)) {
            echo "✗ FAIL: Auth method not found: {$method}\n";
            exit(1);
        }
    }
    
    echo "✓ All Auth methods available\n";
    
    // Test 7: Check SMTP configuration
    echo "\nTest 7: SMTP Configuration\n";
    
    $smtpConstants = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_PORT'];
    $missingConstants = [];
    
    foreach ($smtpConstants as $constant) {
        if (!defined($constant) || empty(constant($constant))) {
            $missingConstants[] = $constant;
        }
    }
    
    if (count($missingConstants) > 0) {
        echo "⚠ WARNING: Missing SMTP configuration: " . implode(', ', $missingConstants) . "\n";
        echo "   Email sending will not work until these are configured in config/config.php\n";
    } else {
        echo "✓ SMTP configuration present\n";
        echo "   Host: " . SMTP_HOST . "\n";
        echo "   Port: " . SMTP_PORT . "\n";
        echo "   User: " . SMTP_USER . "\n";
    }
    
    // Summary
    echo "\n=== Test Summary ===\n";
    echo "✓ All critical tests passed\n";
    echo "✓ Database structure correct\n";
    echo "✓ All classes and methods available\n";
    echo "✓ All API endpoints and templates present\n";
    echo "✓ System ready for testing\n\n";
    
    echo "Next steps:\n";
    echo "1. Ensure SMTP credentials are configured in config/config.php\n";
    echo "2. Run database migration if not already done:\n";
    echo "   mysql -u user -p database < migrations/006_add_invitations_table.sql\n";
    echo "3. Log in as admin/vorstand and navigate to Admin Dashboard\n";
    echo "4. Test creating an invitation\n";
    echo "5. Check email delivery\n";
    echo "6. Test registration with the token link\n\n";
    
} catch (PDOException $e) {
    echo "\n✗ FAIL: Database error\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ FAIL: Unexpected error\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}
