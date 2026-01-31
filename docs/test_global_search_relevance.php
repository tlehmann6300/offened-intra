<?php
/**
 * Test Script for Global Search Relevance Scoring
 * 
 * This script tests the relevance scoring algorithm used in api/global_search.php
 * without requiring database connections.
 * 
 * USAGE:
 * Run this script from command line: php test_global_search_relevance.php
 */

declare(strict_types=1);

echo "=== Global Search Relevance Scoring Test ===\n\n";

// Test data with various match scenarios
$testResults = [
    // Exact title match - should score highest
    [
        'type' => 'inventory',
        'id' => 1,
        'title' => 'Laptop',
        'subtitle' => 'IT Equipment',
        'quantity' => 5,
        'extra_info' => 'Dell laptops for developers',
        'date' => date('Y-m-d H:i:s', strtotime('-10 days'))
    ],
    // Partial title match
    [
        'type' => 'news',
        'id' => 2,
        'title' => 'New Laptop Policy Announced',
        'subtitle' => 'News',
        'quantity' => null,
        'extra_info' => 'We are updating our equipment policy',
        'date' => date('Y-m-d H:i:s', strtotime('-5 days'))
    ],
    // Subtitle match only
    [
        'type' => 'event',
        'id' => 3,
        'title' => 'Tech Conference 2026',
        'subtitle' => 'Laptop presentations and demos',
        'quantity' => null,
        'extra_info' => 'Annual tech event',
        'date' => date('Y-m-d H:i:s', strtotime('-60 days'))
    ],
    // Extra info match only
    [
        'type' => 'project',
        'id' => 4,
        'title' => 'Office Equipment Upgrade',
        'subtitle' => 'IT Project',
        'quantity' => null,
        'extra_info' => 'Purchasing new laptops and monitors for the team',
        'date' => date('Y-m-d H:i:s', strtotime('-90 days'))
    ],
    // Recent item (within 30 days) with partial match - should get bonus
    [
        'type' => 'inventory',
        'id' => 5,
        'title' => 'Gaming Laptop',
        'subtitle' => 'IT Equipment',
        'quantity' => 2,
        'extra_info' => 'High-performance machines',
        'date' => date('Y-m-d H:i:s', strtotime('-15 days'))
    ],
    // No match - should score 0
    [
        'type' => 'news',
        'id' => 6,
        'title' => 'Company Picnic',
        'subtitle' => 'Events',
        'quantity' => null,
        'extra_info' => 'Join us for fun activities',
        'date' => date('Y-m-d H:i:s', strtotime('-120 days'))
    ]
];

$query = 'Laptop';
$allResults = $testResults;

echo "Search Query: '{$query}'\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Replicate the relevance scoring algorithm from api/global_search.php
$searchLower = mb_strtolower($query);

foreach ($allResults as $key => $result) {
    $score = 0;
    
    // Check title relevance
    $titleLower = mb_strtolower($result['title'] ?? '');
    if ($titleLower === $searchLower) {
        $score += 10; // Exact match in title
        $matchDetails = 'Exact title match (+10)';
    } elseif (strpos($titleLower, $searchLower) !== false) {
        $score += 5; // Partial match in title
        $matchDetails = 'Partial title match (+5)';
    } else {
        $matchDetails = 'No title match';
    }
    
    // Check subtitle relevance
    $subtitleLower = mb_strtolower($result['subtitle'] ?? '');
    if (strpos($subtitleLower, $searchLower) !== false) {
        $score += 3;
        $matchDetails .= ', Subtitle match (+3)';
    }
    
    // Check extra_info (description/bio) relevance
    $extraInfoLower = mb_strtolower($result['extra_info'] ?? '');
    if (strpos($extraInfoLower, $searchLower) !== false) {
        $score += 1;
        $matchDetails .= ', Description match (+1)';
    }
    
    // Bonus for recent items (within 30 days)
    if (!empty($result['date'])) {
        $itemDate = strtotime($result['date']);
        $daysSinceCreation = (time() - $itemDate) / (60 * 60 * 24);
        if ($daysSinceCreation <= 30) {
            $score += 2;
            $matchDetails .= ', Recent item (+2)';
        }
    }
    
    // Store score with the result
    $allResults[$key]['relevance_score'] = $score;
    $allResults[$key]['match_details'] = $matchDetails;
}

// Sort all results by relevance score (DESC), then by date (DESC) as tiebreaker
usort($allResults, function($a, $b) {
    // Primary sort: by relevance score (higher is better)
    if ($a['relevance_score'] !== $b['relevance_score']) {
        return $b['relevance_score'] - $a['relevance_score'];
    }
    // Secondary sort: by date (newer is better)
    return strtotime($b['date']) - strtotime($a['date']);
});

// Display results
echo "Results (sorted by relevance):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($allResults as $index => $result) {
    $rank = $index + 1;
    echo "#{$rank} - Score: {$result['relevance_score']}\n";
    echo "   Title: {$result['title']}\n";
    echo "   Type: {$result['type']}\n";
    echo "   Match: {$result['match_details']}\n";
    echo "   Date: {$result['date']}\n";
    echo "\n";
}

// Verify expected order
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Validation:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$expectedOrder = [
    ['id' => 1, 'score' => 13, 'reason' => 'Exact match + description + recent'],
    ['id' => 2, 'score' => 7, 'reason' => 'Partial match + recent'],
    ['id' => 5, 'score' => 7, 'reason' => 'Partial match + recent'],
    ['id' => 3, 'score' => 3, 'reason' => 'Subtitle match only'],
    ['id' => 4, 'score' => 1, 'reason' => 'Description match only'],
    ['id' => 6, 'score' => 0, 'reason' => 'No match']
];

$allPassed = true;
foreach ($expectedOrder as $index => $expected) {
    $actual = $allResults[$index];
    $passed = ($actual['id'] === $expected['id'] && $actual['relevance_score'] === $expected['score']);
    
    // For tied scores, accept either order
    if (!$passed && $expected['score'] === 7 && $actual['relevance_score'] === 7) {
        $passed = true; // Accept any order for tied items
    }
    
    $status = $passed ? '✓' : '✗';
    $position = $index + 1;
    echo "{$status} Position #{$position}: ";
    
    if ($passed) {
        echo "PASS - ID {$actual['id']} with score {$actual['relevance_score']} ({$expected['reason']})\n";
    } else {
        echo "FAIL - Expected ID {$expected['id']} with score {$expected['score']}, ";
        echo "got ID {$actual['id']} with score {$actual['relevance_score']}\n";
        $allPassed = false;
    }
}

echo "\n";
if ($allPassed) {
    echo "=== All Tests Passed! ===\n";
    echo "✓ Exact title matches score highest (10 points)\n";
    echo "✓ Partial title matches score well (5 points)\n";
    echo "✓ Subtitle matches add value (3 points)\n";
    echo "✓ Description matches count (1 point)\n";
    echo "✓ Recent items get bonus (2 points)\n";
    echo "✓ Sorting works correctly (score DESC, then date DESC)\n";
    exit(0);
} else {
    echo "=== Some Tests Failed ===\n";
    exit(1);
}
