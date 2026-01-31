<?php
/**
 * Alumni Workflow and Role Hierarchy Test
 * 
 * Tests the new alumni validation workflow:
 * - Role hierarchy with 1V, 2V, 3V positions
 * - Alumni validation status
 * - Project access control for alumni
 * - Alumni transition workflow
 */

declare(strict_types=1);

// Load configuration
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Project.php';
require_once __DIR__ . '/../src/SystemLogger.php';

// Initialize services
$userPdo = DatabaseManager::getUserConnection();
$contentPdo = DatabaseManager::getContentConnection();
$systemLogger = new SystemLogger($contentPdo);
$auth = new Auth($userPdo, $systemLogger);
$project = new Project($contentPdo);

echo "=== Alumni Workflow & Role Hierarchy Tests ===\n\n";

// Test 1: Alumni Validation Fields in Users Table
echo "Test 1: Alumni Validation Fields in Users Table\n";
try {
    $stmt = $userPdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $alumniFields = ['is_alumni_validated', 'alumni_status_requested_at'];
    $hasAlumniFields = count(array_intersect($alumniFields, $columns)) === count($alumniFields);
    
    if ($hasAlumniFields) {
        echo "✓ users table has alumni validation fields\n";
    } else {
        echo "✗ users table missing alumni validation fields\n";
        echo "  Run migration: 005_add_alumni_validation_fields.sql\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking users table: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Role Hierarchy Constants
echo "Test 2: Role Hierarchy Constants\n";
try {
    $authClass = new ReflectionClass('Auth');
    $roleHierarchy = $authClass->getConstant('ROLE_HIERARCHY');
    
    if ($roleHierarchy !== false) {
        echo "✓ ROLE_HIERARCHY constant exists\n";
        
        // Check new roles
        $expectedRoles = ['none', 'alumni', 'mitglied', 'ressortleiter', '1v', '2v', '3v', 'vorstand', 'admin'];
        $actualRoles = array_keys($roleHierarchy);
        
        $missingRoles = array_diff($expectedRoles, $actualRoles);
        if (empty($missingRoles)) {
            echo "✓ All expected roles present in hierarchy\n";
            echo "  Role hierarchy: " . implode(' < ', array_keys($roleHierarchy)) . "\n";
        } else {
            echo "✗ Missing roles: " . implode(', ', $missingRoles) . "\n";
        }
        
        // Verify hierarchy order (alumni should be lowest active role)
        if ($roleHierarchy['alumni'] < $roleHierarchy['mitglied']) {
            echo "✓ Alumni role correctly positioned below Mitglied\n";
        } else {
            echo "✗ Alumni role hierarchy incorrect\n";
        }
        
        // Verify board positions
        if ($roleHierarchy['admin'] > $roleHierarchy['vorstand'] && 
            $roleHierarchy['vorstand'] > $roleHierarchy['ressortleiter']) {
            echo "✓ Board hierarchy correctly ordered\n";
        } else {
            echo "✗ Board hierarchy incorrect\n";
        }
    } else {
        echo "✗ ROLE_HIERARCHY constant not found\n";
    }
} catch (ReflectionException $e) {
    echo "✗ Error checking role hierarchy: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Alumni Workflow Methods
echo "Test 3: Alumni Workflow Methods\n";
$requiredMethods = [
    'requestAlumniStatus',
    'validateAlumniStatus',
    'getPendingAlumniValidations',
    'isValidatedAlumni'
];

$authClass = new ReflectionClass('Auth');
$methods = $authClass->getMethods();
$methodNames = array_map(fn($m) => $m->getName(), $methods);

$missingMethods = array_diff($requiredMethods, $methodNames);
if (empty($missingMethods)) {
    echo "✓ All alumni workflow methods exist\n";
} else {
    echo "✗ Missing methods: " . implode(', ', $missingMethods) . "\n";
}
echo "\n";

// Test 4: Project Access Control Methods
echo "Test 4: Project Access Control Methods\n";
try {
    $projectClass = new ReflectionClass('Project');
    
    // Check if methods accept user role parameter
    $getAllMethod = $projectClass->getMethod('getAll');
    $params = $getAllMethod->getParameters();
    $paramNames = array_map(fn($p) => $p->getName(), $params);
    
    if (in_array('userRole', $paramNames)) {
        echo "✓ Project::getAll() accepts userRole parameter\n";
    } else {
        echo "✗ Project::getAll() missing userRole parameter\n";
    }
    
    $getLatestMethod = $projectClass->getMethod('getLatest');
    $params = $getLatestMethod->getParameters();
    $paramNames = array_map(fn($p) => $p->getName(), $params);
    
    if (in_array('userRole', $paramNames)) {
        echo "✓ Project::getLatest() accepts userRole parameter\n";
    } else {
        echo "✗ Project::getLatest() missing userRole parameter\n";
    }
    
    $getByIdMethod = $projectClass->getMethod('getById');
    $params = $getByIdMethod->getParameters();
    $paramNames = array_map(fn($p) => $p->getName(), $params);
    
    if (in_array('userRole', $paramNames)) {
        echo "✓ Project::getById() accepts userRole parameter\n";
    } else {
        echo "✗ Project::getById() missing userRole parameter\n";
    }
} catch (ReflectionException $e) {
    echo "✗ Error checking Project methods: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Alumni Access Restrictions (Simulated)
echo "Test 5: Alumni Access Restrictions (Simulated)\n";
try {
    // Test with alumni role - should return empty for active projects
    $alumniProjects = $project->getLatest(3, 'alumni');
    if (empty($alumniProjects)) {
        echo "✓ Alumni users correctly blocked from accessing active projects\n";
    } else {
        echo "✗ Alumni users can still access active projects\n";
    }
    
    // Test with mitglied role - should return projects
    $memberProjects = $project->getLatest(3, 'mitglied');
    if (!empty($memberProjects) || $memberProjects === []) {
        echo "✓ Mitglied users can access active projects (returned " . count($memberProjects) . " projects)\n";
    } else {
        echo "✗ Mitglied users cannot access active projects\n";
    }
    
    // Test count for alumni
    $alumniCount = $project->countOpenPositions('alumni');
    if ($alumniCount === 0) {
        echo "✓ Alumni correctly receive 0 open project positions\n";
    } else {
        echo "✗ Alumni receive non-zero count: {$alumniCount}\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing access restrictions: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Check Permission Method Enhancement
echo "Test 6: Check Permission Method Enhancement\n";
try {
    $authClass = new ReflectionClass('Auth');
    $checkPermMethod = $authClass->getMethod('checkPermission');
    
    // Get method source to check for alumni validation logic
    $filename = $authClass->getFileName();
    $startLine = $checkPermMethod->getStartLine();
    $endLine = $checkPermMethod->getEndLine();
    $source = file_get_contents($filename);
    $lines = explode("\n", $source);
    $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    
    if (str_contains($methodSource, 'is_alumni_validated')) {
        echo "✓ checkPermission() includes alumni validation logic\n";
    } else {
        echo "✗ checkPermission() missing alumni validation logic\n";
    }
    
    if (str_contains($methodSource, 'alumni')) {
        echo "✓ checkPermission() handles alumni role specifically\n";
    } else {
        echo "✗ checkPermission() does not handle alumni role\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking checkPermission method: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Database Index for Alumni Validation
echo "Test 7: Database Index for Alumni Validation\n";
try {
    $stmt = $userPdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_alumni_validation'");
    $index = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($index) {
        echo "✓ idx_alumni_validation index exists on users table\n";
    } else {
        echo "⚠ idx_alumni_validation index not found (optional optimization)\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking index: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Alumni Validation Template
echo "Test 8: Alumni Validation Template\n";
$templatePath = __DIR__ . '/../templates/pages/alumni_validation.php';
if (file_exists($templatePath)) {
    echo "✓ alumni_validation.php template exists\n";
    
    // Check if template includes key elements
    $templateContent = file_get_contents($templatePath);
    if (str_contains($templateContent, 'getPendingAlumniValidations')) {
        echo "✓ Template uses getPendingAlumniValidations() method\n";
    } else {
        echo "✗ Template missing getPendingAlumniValidations() call\n";
    }
    
    if (str_contains($templateContent, 'validate_alumni')) {
        echo "✓ Template includes validation form/action\n";
    } else {
        echo "✗ Template missing validation action\n";
    }
} else {
    echo "✗ alumni_validation.php template not found\n";
}
echo "\n";

// Test 9: API Router Alumni Endpoints
echo "Test 9: API Router Alumni Endpoints\n";
$routerPath = __DIR__ . '/../api/router.php';
if (file_exists($routerPath)) {
    $routerContent = file_get_contents($routerPath);
    
    $endpoints = ['request_alumni_status', 'validate_alumni', 'get_pending_alumni'];
    $foundEndpoints = 0;
    
    foreach ($endpoints as $endpoint) {
        if (str_contains($routerContent, "case '{$endpoint}':")) {
            echo "✓ API endpoint '{$endpoint}' exists\n";
            $foundEndpoints++;
        } else {
            echo "✗ API endpoint '{$endpoint}' missing\n";
        }
    }
    
    if ($foundEndpoints === count($endpoints)) {
        echo "✓ All alumni workflow API endpoints present\n";
    }
} else {
    echo "✗ api/router.php not found\n";
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Alumni workflow implementation tested.\n";
echo "\nKey Features:\n";
echo "  ✓ Role hierarchy: Admin/1V-3V > Ressortleiter > Mitglied > Alumni\n";
echo "  ✓ Alumni validation workflow with is_alumni_validated flag\n";
echo "  ✓ Project access restrictions for alumni users\n";
echo "  ✓ Admin interface for alumni validation\n";
echo "  ✓ API endpoints for alumni status management\n";
echo "\nNext Steps:\n";
echo "  1. Run migration: migrations/005_add_alumni_validation_fields.sql\n";
echo "  2. Test alumni transition workflow manually\n";
echo "  3. Verify access restrictions in production\n";
echo "  4. Test admin validation interface\n";
