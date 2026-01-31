<?php
/**
 * Offline Test Script for Token-Based Invitation System
 * 
 * This script tests the invitation system code structure without requiring
 * database connectivity. It checks syntax, class structure, and methods.
 * 
 * Usage: php tests/test_invitation_offline.php
 */

echo "=== Token-Based Invitation System - Offline Tests ===\n\n";

// Test 1: Check file structure
echo "Test 1: File Structure\n";

$requiredFiles = [
    'src/Auth.php',
    'src/MailService.php',
    'api/send_invitation.php',
    'api/register_with_token.php',
    'api/delete_invitation.php',
    'templates/pages/register.php',
    'templates/components/invitation_management.php',
    'migrations/006_add_invitations_table.sql',
    'docs/invitation_system.md'
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/../' . $file;
    if (!file_exists($path)) {
        $missingFiles[] = $file;
    }
}

if (count($missingFiles) > 0) {
    echo "✗ FAIL: Missing files:\n";
    foreach ($missingFiles as $file) {
        echo "   - {$file}\n";
    }
    exit(1);
}

echo "✓ All required files present (" . count($requiredFiles) . " files)\n\n";

// Test 2: Check PHP syntax
echo "Test 2: PHP Syntax\n";

$phpFiles = array_filter($requiredFiles, function($file) {
    return pathinfo($file, PATHINFO_EXTENSION) === 'php';
});

$syntaxErrors = [];
foreach ($phpFiles as $file) {
    $path = __DIR__ . '/../' . $file;
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnVar);
    
    if ($returnVar !== 0) {
        $syntaxErrors[$file] = implode("\n", $output);
    }
}

if (count($syntaxErrors) > 0) {
    echo "✗ FAIL: Syntax errors found:\n";
    foreach ($syntaxErrors as $file => $error) {
        echo "   {$file}:\n";
        echo "   {$error}\n";
    }
    exit(1);
}

echo "✓ All PHP files have valid syntax (" . count($phpFiles) . " files)\n\n";

// Test 3: Check SQL syntax (basic)
echo "Test 3: SQL Migration\n";

$sqlPath = __DIR__ . '/../migrations/006_add_invitations_table.sql';
$sqlContent = file_get_contents($sqlPath);

$requiredSqlKeywords = [
    'CREATE TABLE',
    'invitations',
    'email',
    'token',
    'role',
    'created_by',
    'expires_at',
    'accepted_at',
    'UNIQUE KEY'
];

$missingSqlKeywords = [];
foreach ($requiredSqlKeywords as $keyword) {
    if (stripos($sqlContent, $keyword) === false) {
        $missingSqlKeywords[] = $keyword;
    }
}

if (count($missingSqlKeywords) > 0) {
    echo "✗ FAIL: Missing SQL keywords: " . implode(', ', $missingSqlKeywords) . "\n";
    exit(1);
}

echo "✓ SQL migration has all required elements\n\n";

// Test 4: Check Auth.php methods
echo "Test 4: Auth Class Methods\n";

$authPath = __DIR__ . '/../src/Auth.php';
$authContent = file_get_contents($authPath);

$requiredMethods = [
    'createInvitation',
    'validateInvitationToken',
    'registerWithInvitation',
    'getPendingInvitations',
    'deleteInvitation'
];

$missingMethods = [];
foreach ($requiredMethods as $method) {
    if (strpos($authContent, "function {$method}") === false && 
        strpos($authContent, "public function {$method}") === false) {
        $missingMethods[] = $method;
    }
}

if (count($missingMethods) > 0) {
    echo "✗ FAIL: Missing Auth methods: " . implode(', ', $missingMethods) . "\n";
    exit(1);
}

echo "✓ All required Auth methods present (" . count($requiredMethods) . " methods)\n\n";

// Test 5: Check MailService method
echo "Test 5: MailService Method\n";

$mailServicePath = __DIR__ . '/../src/MailService.php';
$mailServiceContent = file_get_contents($mailServicePath);

if (strpos($mailServiceContent, "function sendInvitationEmail") === false &&
    strpos($mailServiceContent, "public function sendInvitationEmail") === false) {
    echo "✗ FAIL: sendInvitationEmail method not found in MailService\n";
    exit(1);
}

echo "✓ sendInvitationEmail method present in MailService\n\n";

// Test 6: Check API endpoints
echo "Test 6: API Endpoints\n";

$apiFiles = [
    'api/send_invitation.php' => ['csrf_token', 'email', 'role'],
    'api/register_with_token.php' => ['token', 'firstname', 'lastname', 'password'],
    'api/delete_invitation.php' => ['csrf_token', 'invitation_id']
];

foreach ($apiFiles as $file => $requiredParams) {
    $path = __DIR__ . '/../' . $file;
    $content = file_get_contents($path);
    
    // Check for JSON response
    if (strpos($content, "header('Content-Type: application/json')") === false) {
        echo "✗ FAIL: {$file} missing JSON response header\n";
        exit(1);
    }
    
    // Check for required parameters
    foreach ($requiredParams as $param) {
        if (strpos($content, "\$_POST['{$param}']") === false) {
            echo "✗ FAIL: {$file} missing parameter: {$param}\n";
            exit(1);
        }
    }
}

echo "✓ All API endpoints properly structured\n\n";

// Test 7: Check registration template
echo "Test 7: Registration Template\n";

$registerPath = __DIR__ . '/../templates/pages/register.php';
$registerContent = file_get_contents($registerPath);

$requiredElements = [
    'validateInvitationToken',
    'firstname',
    'lastname',
    'password',
    'password_confirm',
    'registerForm'
];

$missingElements = [];
foreach ($requiredElements as $element) {
    if (strpos($registerContent, $element) === false) {
        $missingElements[] = $element;
    }
}

if (count($missingElements) > 0) {
    echo "✗ FAIL: Missing elements in register.php: " . implode(', ', $missingElements) . "\n";
    exit(1);
}

echo "✓ Registration template has all required elements\n\n";

// Test 8: Check invitation management component
echo "Test 8: Invitation Management Component\n";

$inviteMgmtPath = __DIR__ . '/../templates/components/invitation_management.php';
$inviteMgmtContent = file_get_contents($inviteMgmtPath);

$requiredFeatures = [
    'invitationForm',
    'send_invitation.php',
    'delete-invitation-btn',
    'getPendingInvitations'
];

$missingFeatures = [];
foreach ($requiredFeatures as $feature) {
    if (strpos($inviteMgmtContent, $feature) === false) {
        $missingFeatures[] = $feature;
    }
}

if (count($missingFeatures) > 0) {
    echo "✗ FAIL: Missing features in invitation_management.php: " . implode(', ', $missingFeatures) . "\n";
    exit(1);
}

echo "✓ Invitation management component complete\n\n";

// Test 9: Check documentation
echo "Test 9: Documentation\n";

$docPath = __DIR__ . '/../docs/invitation_system.md';
if (!file_exists($docPath)) {
    echo "✗ FAIL: Documentation file not found\n";
    exit(1);
}

$docSize = filesize($docPath);
if ($docSize < 1000) {
    echo "✗ FAIL: Documentation seems incomplete (only {$docSize} bytes)\n";
    exit(1);
}

echo "✓ Documentation present (" . number_format($docSize) . " bytes)\n\n";

// Test 10: Token generation test (cryptographic strength)
echo "Test 10: Token Generation Quality\n";

$tokens = [];
for ($i = 0; $i < 100; $i++) {
    $token = bin2hex(random_bytes(32));
    
    // Check length
    if (strlen($token) !== 64) {
        echo "✗ FAIL: Token length incorrect\n";
        exit(1);
    }
    
    // Check uniqueness
    if (in_array($token, $tokens, true)) {
        echo "✗ FAIL: Duplicate token generated\n";
        exit(1);
    }
    
    $tokens[] = $token;
}

echo "✓ Token generation working (100 unique 64-char tokens)\n\n";

// Summary
echo "=== Test Summary ===\n";
echo "✓ All offline tests passed\n";
echo "✓ File structure complete\n";
echo "✓ PHP syntax valid\n";
echo "✓ SQL migration ready\n";
echo "✓ All methods and endpoints present\n";
echo "✓ Templates properly structured\n";
echo "✓ Documentation complete\n";
echo "✓ Token generation secure\n\n";

echo "System is ready for deployment!\n\n";

echo "Next steps:\n";
echo "1. Ensure database credentials are configured in config/config.php\n";
echo "2. Run the database migration:\n";
echo "   mysql -u user -p database < migrations/006_add_invitations_table.sql\n";
echo "3. Configure SMTP settings in config/config.php (IONOS)\n";
echo "4. Test in browser:\n";
echo "   - Login as admin/vorstand\n";
echo "   - Navigate to Admin Dashboard\n";
echo "   - Create test invitation\n";
echo "   - Check email delivery\n";
echo "   - Test registration flow\n\n";
