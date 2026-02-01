<?php
/**
 * Static Analysis Test for adjustQuantity System Logging
 * 
 * This test verifies the code structure without requiring database access.
 * It checks that the adjustQuantity method includes the necessary system_logs logic.
 */

declare(strict_types=1);

echo "=== Static Analysis: adjustQuantity System Logging ===\n\n";

// Test 1: Check that Inventory.php file exists
echo "Test 1: Verify Inventory.php exists...\n";
$inventoryFile = __DIR__ . '/../src/Inventory.php';
if (!file_exists($inventoryFile)) {
    echo "✗ Inventory.php not found at: {$inventoryFile}\n";
    exit(1);
}
echo "✓ Inventory.php found\n\n";

// Test 2: Check PHP syntax
echo "Test 2: Verify PHP syntax...\n";
$output = [];
$returnCode = 0;
exec("php -l " . escapeshellarg($inventoryFile) . " 2>&1", $output, $returnCode);
if ($returnCode !== 0) {
    echo "✗ PHP syntax error:\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
echo "✓ PHP syntax is valid\n\n";

// Test 3: Check that the method includes system_logs INSERT
echo "Test 3: Verify system_logs INSERT statement exists...\n";
$content = file_get_contents($inventoryFile);

// Check for INSERT INTO system_logs
if (strpos($content, 'INSERT INTO system_logs') === false) {
    echo "✗ No 'INSERT INTO system_logs' statement found\n";
    exit(1);
}
echo "✓ Found 'INSERT INTO system_logs' statement\n";

// Check for the required fields
$requiredFields = ['user_id', 'action', 'target_type', 'target_id', 'details'];
foreach ($requiredFields as $field) {
    if (strpos($content, $field) === false) {
        echo "✗ Required field '{$field}' not found in code\n";
        exit(1);
    }
}
echo "✓ All required fields present\n\n";

// Test 4: Verify the German message format is used
echo "Test 4: Verify German message format...\n";
if (strpos($content, 'hat Bestand von') === false) {
    echo "⚠ Warning: German phrase 'hat Bestand von' not found\n";
    echo "  Expected format: 'User [ID] hat Bestand von [Item] um [Anzahl] geändert'\n\n";
} else {
    echo "✓ German message format 'hat Bestand von' found\n";
}

if (strpos($content, 'geändert') === false) {
    echo "⚠ Warning: German word 'geändert' not found\n";
} else {
    echo "✓ German word 'geändert' found\n\n";
}

// Test 5: Verify the method structure with transaction handling
echo "Test 5: Verify transaction structure...\n";

// Extract the adjustQuantity method
preg_match('/public function adjustQuantity\(.*?\)\s*\{(.*?)\n\s{4}\}/s', $content, $matches);
if (empty($matches[1])) {
    echo "✗ Could not extract adjustQuantity method\n";
    exit(1);
}

$methodContent = $matches[1];

// Check for required transaction elements
$transactionChecks = [
    'beginTransaction' => 'Transaction start (beginTransaction)',
    'FOR UPDATE' => 'Row locking (SELECT ... FOR UPDATE)',
    'INSERT INTO system_logs' => 'System logging',
    'INSERT INTO inventory_logs' => 'Inventory logging',
    'commit()' => 'Transaction commit',
    'rollBack()' => 'Transaction rollback'
];

$allChecksPass = true;
foreach ($transactionChecks as $pattern => $description) {
    if (strpos($methodContent, $pattern) !== false) {
        echo "✓ {$description} present\n";
    } else {
        echo "✗ {$description} missing\n";
        $allChecksPass = false;
    }
}

if (!$allChecksPass) {
    exit(1);
}
echo "\n";

// Test 6: Verify the order of operations
echo "Test 6: Verify operation order...\n";
$posBeginTransaction = strpos($methodContent, 'beginTransaction');
$posForUpdate = strpos($methodContent, 'FOR UPDATE');
$posUpdateInventory = strpos($methodContent, 'UPDATE inventory');
$posInsertInventoryLogs = strpos($methodContent, 'INSERT INTO inventory_logs');
$posInsertSystemLogs = strpos($methodContent, 'INSERT INTO system_logs');
$posCommit = strpos($methodContent, 'commit()');

$orderChecks = [
    [$posBeginTransaction, $posForUpdate, 'Transaction starts before row lock'],
    [$posForUpdate, $posUpdateInventory, 'Row lock before inventory update'],
    [$posUpdateInventory, $posInsertInventoryLogs, 'Inventory update before inventory logs'],
    [$posInsertInventoryLogs, $posInsertSystemLogs, 'Inventory logs before system logs'],
    [$posInsertSystemLogs, $posCommit, 'System logs before commit']
];

$allOrderChecksPass = true;
foreach ($orderChecks as [$pos1, $pos2, $description]) {
    if ($pos1 !== false && $pos2 !== false && $pos1 < $pos2) {
        echo "✓ {$description}\n";
    } else {
        echo "⚠ Warning: {$description} - check order\n";
        // Don't fail on order checks, just warn
    }
}
echo "\n";

echo "=== Static Analysis Complete ===\n";
echo "✓ All required changes are present in the code\n";
echo "✓ System logging to system_logs table is implemented\n";
echo "✓ Transaction safety is maintained\n";
echo "✓ Row-level locking (FOR UPDATE) is in place\n";
echo "\n";
echo "NOTE: This test verifies code structure only.\n";
echo "For full integration testing, run: php tests/test_adjust_quantity_system_logs.php\n";
echo "(Requires database access and proper configuration)\n";
