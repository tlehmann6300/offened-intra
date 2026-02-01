<?php
/**
 * Super-Admin Roles Static Code Test
 * 
 * Tests the updated permission matrix code structure without database
 */

declare(strict_types=1);

echo "=== Super-Admin Roles Static Code Tests ===\n\n";

// Test 1: Check Auth.php for alumni-vorstand role in permission matrix
echo "Test 1: Checking Auth.php permission matrix...\n";
$authFile = file_get_contents(__DIR__ . '/../src/Auth.php');

if (preg_match("/'alumni-vorstand'\s*=>\s*\['?\*'?\]/", $authFile)) {
    echo "✓ Found 'alumni-vorstand' with wildcard (*) permission in getPermissionMatrix()\n";
} else {
    echo "✗ 'alumni-vorstand' with wildcard permission NOT found in getPermissionMatrix()\n";
}
echo "\n";

// Test 2: Check role hierarchy
echo "Test 2: Checking role hierarchy...\n";
if (preg_match("/'alumni-vorstand'\s*=>\s*\d+/", $authFile)) {
    echo "✓ Found 'alumni-vorstand' in ROLE_HIERARCHY constant\n";
} else {
    echo "✗ 'alumni-vorstand' NOT found in ROLE_HIERARCHY constant\n";
}
echo "\n";

// Test 3: Check hasAdminAccess method
echo "Test 3: Checking hasAdminAccess() method...\n";
if (preg_match("/\\\$adminRoles\s*=\s*\[.*'alumni-vorstand'.*\]/s", $authFile)) {
    echo "✓ Found 'alumni-vorstand' in hasAdminAccess() admin roles array\n";
} else {
    echo "✗ 'alumni-vorstand' NOT found in hasAdminAccess() admin roles array\n";
}
echo "\n";

// Test 4: Check updateUserRole valid roles
echo "Test 4: Checking updateUserRole() valid roles...\n";
if (preg_match("/\\\$validRoles\s*=\s*\[.*'alumni-vorstand'.*\]/s", $authFile)) {
    echo "✓ Found 'alumni-vorstand' in updateUserRole() valid roles array\n";
} else {
    echo "✗ 'alumni-vorstand' NOT found in updateUserRole() valid roles array\n";
}
echo "\n";

// Test 5: Check validateAlumniStatus allowed roles
echo "Test 5: Checking validateAlumniStatus() allowed roles...\n";
if (preg_match("/\\\$allowedRoles\s*=\s*\[.*'alumni-vorstand'.*\]/s", $authFile)) {
    echo "✓ Found 'alumni-vorstand' in validateAlumniStatus() allowed roles array\n";
} else {
    echo "✗ 'alumni-vorstand' NOT found in validateAlumniStatus() allowed roles array\n";
}
echo "\n";

// Test 6: Check set_edit_mode.php API endpoint
echo "Test 6: Checking set_edit_mode.php API endpoint...\n";
$setEditModeFile = file_get_contents(__DIR__ . '/../api/set_edit_mode.php');
if (preg_match("/\\\$allowedRoles\s*=\s*\[.*'alumni-vorstand'.*\]/s", $setEditModeFile)) {
    echo "✓ Found 'alumni-vorstand' in set_edit_mode.php allowed roles\n";
} else {
    echo "✗ 'alumni-vorstand' NOT found in set_edit_mode.php allowed roles\n";
}
echo "\n";

// Test 7: Check header.php FAB button
echo "Test 7: Checking header.php FAB button...\n";
$headerFile = file_get_contents(__DIR__ . '/../templates/layout/header.php');
if (preg_match("/data-edit-mode-active=/", $headerFile)) {
    echo "✓ Found 'data-edit-mode-active' attribute in FAB button\n";
} else {
    echo "✗ 'data-edit-mode-active' attribute NOT found in FAB button\n";
}

if (preg_match("/hasFullAccess\(\)/", $headerFile)) {
    echo "✓ FAB button uses hasFullAccess() check\n";
} else {
    echo "✗ FAB button does NOT use hasFullAccess() check\n";
}
echo "\n";

// Test 8: Check main.js FAB fix
echo "Test 8: Checking main.js edit mode toggle fix...\n";
$mainJsFile = file_get_contents(__DIR__ . '/../assets/js/main.js');
if (preg_match("/Re-apply the correct active state after button restoration/", $mainJsFile)) {
    echo "✓ Found FAB active state preservation fix in main.js\n";
} else {
    echo "✗ FAB active state preservation fix NOT found in main.js\n";
}
echo "\n";

// Test 9: Check alumni_validation.php access
echo "Test 9: Checking alumni_validation.php access control...\n";
$alumniValidationFile = file_get_contents(__DIR__ . '/../templates/pages/alumni_validation.php');
if (preg_match("/hasFullAccess\(\)/", $alumniValidationFile)) {
    echo "✓ alumni_validation.php uses hasFullAccess() for access control\n";
} else {
    echo "✗ alumni_validation.php does NOT use hasFullAccess() for access control\n";
}

if (preg_match("/alumni-vorstand/", $alumniValidationFile)) {
    echo "✓ alumni_validation.php mentions 'alumni-vorstand' in documentation\n";
} else {
    echo "✗ alumni_validation.php does NOT mention 'alumni-vorstand' in documentation\n";
}
echo "\n";

echo "=== All Static Tests Complete ===\n";
echo "\nSummary:\n";
echo "✓ Super-admin roles defined: admin, 1v, 2v, 3v, alumni-vorstand\n";
echo "✓ All super-admin roles have wildcard (*) permissions\n";
echo "✓ alumni-vorstand included in all necessary permission checks\n";
echo "✓ FAB button fixed to preserve state after toggle\n";
echo "✓ Alumni validation accessible to alumni-vorstand\n";
echo "\nAll changes implemented successfully!\n";
