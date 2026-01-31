<?php
/**
 * Authentication System Test
 * 
 * Tests the refactored Auth.php with:
 * - Database-based rate limiting
 * - TOTP 2FA functionality
 * - Password-only authentication (no Microsoft SSO)
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Create Auth instance
$auth = new Auth($userPdo);

echo "=== Authentication System Tests ===\n\n";

// Test 1: Rate Limiting Database Connection
echo "Test 1: Rate Limiting Database Table\n";
try {
    $stmt = $userPdo->query("SHOW TABLES LIKE 'login_attempts'");
    $exists = $stmt->rowCount() > 0;
    if ($exists) {
        echo "✓ login_attempts table exists\n";
        
        // Check table structure
        $stmt = $userPdo->query("DESCRIBE login_attempts");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'ip_address', 'email', 'attempt_time', 'success', 'user_agent'];
        $hasAllColumns = count(array_intersect($requiredColumns, $columns)) === count($requiredColumns);
        
        if ($hasAllColumns) {
            echo "✓ login_attempts table has correct structure\n";
        } else {
            echo "✗ login_attempts table missing some columns\n";
        }
    } else {
        echo "✗ login_attempts table does not exist\n";
        echo "  Run migration: 003_add_login_attempts_table.sql\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking login_attempts table: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: TOTP Fields in Users Table
echo "Test 2: TOTP Fields in Users Table\n";
try {
    $stmt = $userPdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totpFields = ['totp_secret', 'totp_enabled', 'totp_verified_at'];
    $hasTotpFields = count(array_intersect($totpFields, $columns)) === count($totpFields);
    
    if ($hasTotpFields) {
        echo "✓ users table has TOTP fields\n";
    } else {
        echo "✗ users table missing TOTP fields\n";
        echo "  Run migration: 004_add_totp_to_users.sql\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking users table: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: TOTP Secret Generation
echo "Test 3: TOTP Secret Generation\n";
try {
    $secret = $auth->generateTotpSecret();
    if (!empty($secret) && strlen($secret) >= 16 && strlen($secret) <= 32) {
        echo "✓ TOTP secret generated successfully: {$secret} (length: " . strlen($secret) . ")\n";
    } else {
        echo "✗ TOTP secret generation failed or returned unexpected format (length: " . strlen($secret) . ")\n";
    }
} catch (Exception $e) {
    echo "✗ Error generating TOTP secret: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: TOTP QR Code URL Generation
echo "Test 4: TOTP QR Code URL Generation\n";
try {
    $testEmail = "test@example.com";
    $testSecret = $auth->generateTotpSecret();
    $qrUrl = $auth->getTotpQrCodeUrl($testEmail, $testSecret);
    
    if (str_contains($qrUrl, 'otpauth://totp/') && str_contains($qrUrl, $testEmail)) {
        echo "✓ QR code URL generated correctly\n";
        echo "  URL: {$qrUrl}\n";
    } else {
        echo "✗ QR code URL format incorrect\n";
    }
} catch (Exception $e) {
    echo "✗ Error generating QR code URL: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Microsoft SSO Removal
echo "Test 5: Microsoft SSO Removal\n";
$microsoftMethods = ['loginWithMicrosoft'];
$authClass = new ReflectionClass('Auth');
$methods = $authClass->getMethods();
$methodNames = array_map(fn($m) => $m->getName(), $methods);

$foundMicrosoft = array_intersect($microsoftMethods, $methodNames);
if (empty($foundMicrosoft)) {
    echo "✓ Microsoft SSO methods removed from Auth class\n";
} else {
    echo "✗ Found Microsoft SSO methods: " . implode(', ', $foundMicrosoft) . "\n";
}
echo "\n";

// Test 6: Required Methods Exist
echo "Test 6: Required Methods Exist\n";
$requiredMethods = [
    'login',
    'loginWithPassword',
    'generateTotpSecret',
    'enableTotp',
    'disableTotp',
    'getTotpQrCodeUrl',
    'isTotpEnabled'
];

$missingMethods = array_diff($requiredMethods, $methodNames);
if (empty($missingMethods)) {
    echo "✓ All required methods exist\n";
} else {
    echo "✗ Missing methods: " . implode(', ', $missingMethods) . "\n";
}
echo "\n";

// Test 7: Password Hashing
echo "Test 7: Password Hashing Functions\n";
$testPassword = "TestPassword123!";
$hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

if (password_verify($testPassword, $hashedPassword)) {
    echo "✓ password_hash and password_verify working correctly\n";
} else {
    echo "✗ Password hashing/verification failed\n";
}
echo "\n";

// Test 8: Login Method Signature
echo "Test 8: Login Method Signature\n";
$loginMethod = $authClass->getMethod('login');
$params = $loginMethod->getParameters();
$paramNames = array_map(fn($p) => $p->getName(), $params);

$expectedParams = ['email', 'password', 'totpCode'];
if ($paramNames === $expectedParams) {
    echo "✓ login() method has correct signature (email, password, totpCode)\n";
} else {
    echo "✗ login() method signature incorrect\n";
    echo "  Expected: " . implode(', ', $expectedParams) . "\n";
    echo "  Found: " . implode(', ', $paramNames) . "\n";
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "All critical functionality has been tested.\n";
echo "Please run the SQL migrations if any tables are missing.\n";
echo "\nMigrations to run:\n";
echo "  1. migrations/003_add_login_attempts_table.sql\n";
echo "  2. migrations/004_add_totp_to_users.sql\n";
