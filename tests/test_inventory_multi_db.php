<?php
/**
 * Inventory Multi-Database Test
 * 
 * Tests the new getItems() method in Inventory.php that:
 * - Queries inventory from Content-DB
 * - Fetches responsible user data from User-DB
 * - Merges data correctly in PHP
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../vendor/autoload.php';

// Try to load config and db, but handle gracefully if unavailable
$dbAvailable = false;
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../src/Inventory.php';
    require_once __DIR__ . '/../src/SystemLogger.php';
    
    // Check if database connections are available
    if (isset($contentPdo) && $contentPdo instanceof PDO) {
        $dbAvailable = true;
        $systemLogger = new SystemLogger($contentPdo);
        $inventory = new Inventory($contentPdo, $systemLogger);
    }
} catch (Exception $e) {
    echo "⚠ Database not available: " . $e->getMessage() . "\n";
    echo "Will run static tests only.\n\n";
}

echo "=== Inventory Multi-Database Tests ===\n\n";

// Test 1: Check if getItems() method exists
echo "Test 1: Check if getItems() method exists\n";
if ($dbAvailable && isset($inventory)) {
    if (method_exists($inventory, 'getItems')) {
        echo "✓ getItems() method exists\n";
    } else {
        echo "✗ getItems() method does not exist\n";
        exit(1);
    }
} else {
    // Use reflection without instantiation
    require_once __DIR__ . '/../src/Inventory.php';
    $reflection = new ReflectionClass('Inventory');
    if ($reflection->hasMethod('getItems')) {
        echo "✓ getItems() method exists (checked via reflection)\n";
    } else {
        echo "✗ getItems() method does not exist\n";
        exit(1);
    }
}
echo "\n";

// Test 2: Call getItems() and check structure
echo "Test 2: Call getItems() and verify returned structure\n";
if ($dbAvailable && isset($inventory)) {
    try {
        $items = $inventory->getItems();
        echo "✓ getItems() executed successfully\n";
        echo "  Found " . count($items) . " inventory items\n";
        
        // Check if any items have responsible user data
        $itemsWithResponsible = 0;
        foreach ($items as $item) {
            if (isset($item['responsible_user_id']) && !empty($item['responsible_user_id'])) {
                $itemsWithResponsible++;
                
                // Verify the merged user data fields exist
                if (isset($item['responsible_firstname']) && isset($item['responsible_lastname']) && isset($item['responsible_fullname'])) {
                    echo "✓ Item '{$item['name']}' has merged responsible user data:\n";
                    echo "  - ID: {$item['responsible_user_id']}\n";
                    echo "  - Name: {$item['responsible_fullname']}\n";
                    break; // Just show one example
                }
            }
        }
        
        if ($itemsWithResponsible > 0) {
            echo "✓ Found {$itemsWithResponsible} items with responsible users\n";
        } else {
            echo "ℹ No items with responsible users found (this is OK if database is empty)\n";
        }
    } catch (Exception $e) {
        echo "✗ Error calling getItems(): " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "⊘ Skipped (database not available)\n";
}
echo "\n";

// Test 3: Test with search filters
echo "Test 3: Test getItems() with search and filters\n";
if ($dbAvailable && isset($inventory)) {
    try {
        $searchResults = $inventory->getItems('test', ['status' => 'active']);
        echo "✓ getItems() with filters executed successfully\n";
        echo "  Found " . count($searchResults) . " items matching filters\n";
    } catch (Exception $e) {
        echo "✗ Error calling getItems() with filters: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "⊘ Skipped (database not available)\n";
}
echo "\n";

// Test 4: Verify helper methods are private
echo "Test 4: Verify helper methods exist and have correct visibility\n";
$reflection = new ReflectionClass('Inventory');

$helperMethods = ['fetchResponsibleUserData', 'mergeResponsibleUserData'];
foreach ($helperMethods as $methodName) {
    if ($reflection->hasMethod($methodName)) {
        $method = $reflection->getMethod($methodName);
        if ($method->isPrivate()) {
            echo "✓ {$methodName}() exists and is private\n";
        } else {
            echo "⚠ {$methodName}() exists but is not private\n";
        }
    } else {
        echo "✗ {$methodName}() does not exist\n";
    }
}
echo "\n";

// Test 5: Compare with old getAll() method to ensure compatibility
echo "Test 5: Compare getItems() with getAll() method\n";
if ($dbAvailable && isset($inventory)) {
    try {
        $itemsNew = $inventory->getItems();
        $itemsOld = $inventory->getAll();
        
        if (count($itemsNew) === count($itemsOld)) {
            echo "✓ getItems() returns same number of items as getAll()\n";
            echo "  Count: " . count($itemsNew) . " items\n";
        } else {
            echo "⚠ Different item counts: getItems()=" . count($itemsNew) . ", getAll()=" . count($itemsOld) . "\n";
        }
        
        // Check that all fields from getAll() are present in getItems()
        if (!empty($itemsOld)) {
            $oldFields = array_keys($itemsOld[0]);
            $newFields = array_keys($itemsNew[0]);
            
            $missingFields = array_diff($oldFields, $newFields);
            if (empty($missingFields)) {
                echo "✓ getItems() includes all fields from getAll()\n";
            } else {
                echo "✗ getItems() is missing fields: " . implode(', ', $missingFields) . "\n";
            }
            
            // Check for new fields added by getItems()
            $newlyAddedFields = array_diff($newFields, $oldFields);
            if (!empty($newlyAddedFields)) {
                echo "✓ getItems() adds new fields: " . implode(', ', $newlyAddedFields) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ Error comparing methods: " . $e->getMessage() . "\n";
    }
} else {
    echo "⊘ Skipped (database not available)\n";
}
echo "\n";

echo "=== All tests completed ===\n";
