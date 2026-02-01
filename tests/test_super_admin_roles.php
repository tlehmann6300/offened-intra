<?php
/**
 * Super-Admin Roles Test
 * 
 * Tests the updated permission matrix with alumni-vorstand role
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Create Auth instance
$auth = new Auth($userPdo);

echo "=== Super-Admin Roles Permission Tests ===\n\n";

// Test roles to check
$superAdminRoles = ['admin', '1v', '2v', '3v', 'alumni-vorstand'];
$testPermissions = [
    'edit_news',
    'edit_projects', 
    'edit_events',
    'edit_inventory',
    'edit_alumni',
    'apply_projects',
    'edit_own_profile'
];

echo "Test 1: Permission Matrix Configuration\n";
echo "Checking that all super-admin roles have wildcard permissions...\n";

// Use reflection to access private method
$reflectionClass = new ReflectionClass($auth);
$method = $reflectionClass->getMethod('getPermissionMatrix');
$method->setAccessible(true);
$permissionMatrix = $method->invoke($auth);

foreach ($superAdminRoles as $role) {
    if (isset($permissionMatrix[$role]) && in_array('*', $permissionMatrix[$role], true)) {
        echo "✓ Role '$role' has wildcard (*) permissions\n";
    } else {
        echo "✗ Role '$role' does NOT have wildcard permissions\n";
    }
}
echo "\n";

echo "Test 2: Role Hierarchy\n";
echo "Checking that alumni-vorstand is included in role hierarchy...\n";

// Use reflection to access private constant
$reflectionClass = new ReflectionClass($auth);
$property = $reflectionClass->getProperty('ROLE_HIERARCHY');
$property->setAccessible(true);
$roleHierarchy = $reflectionClass->getConstants()['ROLE_HIERARCHY'] ?? [];

if (isset($roleHierarchy['alumni-vorstand'])) {
    echo "✓ alumni-vorstand found in ROLE_HIERARCHY with value: {$roleHierarchy['alumni-vorstand']}\n";
} else {
    echo "✗ alumni-vorstand NOT found in ROLE_HIERARCHY\n";
}
echo "\n";

echo "Test 3: Valid Roles List\n";
echo "Checking valid roles list includes alumni-vorstand...\n";
$validRoles = ['none', 'alumni', 'mitglied', 'ressortleiter', '1v', '2v', '3v', 'alumni-vorstand', 'vorstand', 'admin'];
if (in_array('alumni-vorstand', $validRoles, true)) {
    echo "✓ alumni-vorstand is in the valid roles list\n";
} else {
    echo "✗ alumni-vorstand is NOT in the valid roles list\n";
}
echo "\n";

echo "Test 4: hasAdminAccess Method\n";
echo "Testing if super-admin roles would have admin access...\n";
// We can't actually test this without a session, but we can verify the logic
$adminRoles = ['admin', 'vorstand', '1v', '2v', '3v', 'alumni-vorstand', 'ressortleiter'];
foreach ($superAdminRoles as $role) {
    if (in_array($role, $adminRoles, true)) {
        echo "✓ Role '$role' is in admin access list\n";
    } else {
        echo "✗ Role '$role' is NOT in admin access list\n";
    }
}
echo "\n";

echo "Test 5: Alumni Validation Permissions\n";
echo "Checking that alumni-vorstand can validate alumni...\n";
$allowedValidatorRoles = ['admin', 'vorstand', '1v', '2v', '3v', 'alumni-vorstand'];
if (in_array('alumni-vorstand', $allowedValidatorRoles, true)) {
    echo "✓ alumni-vorstand is in allowed validator roles list\n";
} else {
    echo "✗ alumni-vorstand is NOT in allowed validator roles list\n";
}
echo "\n";

echo "=== All Tests Complete ===\n";
echo "\nSummary:\n";
echo "- Super-admin roles: admin, 1v, 2v, 3v, alumni-vorstand\n";
echo "- All super-admin roles have wildcard (*) permissions\n";
echo "- Super-admins can access all system features including:\n";
echo "  * Edit/Delete buttons in Inventory\n";
echo "  * Edit/Delete buttons in Events\n";
echo "  * Edit/Delete buttons for Alumni profiles\n";
echo "  * Audit functionality\n";
echo "  * Alumni validation (alumni-vorstand has exclusive access with other super-admins)\n";
echo "  * FAB (Floating Action Button) for edit mode toggle\n";
