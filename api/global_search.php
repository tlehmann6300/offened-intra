<?php
declare(strict_types=1);

/**
 * Global Search API
 * 
 * Centralized search endpoint that searches across multiple database tables.
 * Searches: inventory, users/alumni_profiles, news, events, and projects.
 * Returns results grouped by type (inventory, user, news, event, project).
 * 
 * Architecture:
 * - Uses two separate database connections (User-DB and Content-DB)
 * - User-DB: users, alumni_profiles
 * - Content-DB: inventory, events, projects, news
 * - Results are merged in PHP and sorted by relevance score
 * 
 * Performance optimizations:
 * - Separate queries to each database to handle multi-database architecture
 * - Top 5 results per category for optimal performance
 * - Each result includes explicit Type label ([News], [Inventar], etc.)
 * - Database indexes recommended: inventory(name), users(firstname, lastname), news(title)
 */

// Set JSON response header
header('Content-Type: application/json');

// Load configuration and dependencies
require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';

try {
    // Use global database connections from config/db.php
    // $userPdo - User Database (Benutzer, Alumni-Profile, Authentication)
    // $contentPdo - Content Database (Projekte, Inventar, Events, News, System Logs)
    global $userPdo, $contentPdo;
    
    // Initialize core services
    // Note: SystemLogger uses Content DB as it primarily logs content-related actions
    // Auth uses User DB as it handles user authentication
    $systemLogger = new SystemLogger($contentPdo);
    $auth = new Auth($userPdo, $systemLogger);
    
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Nicht angemeldet. Bitte melden Sie sich an.',
            'results' => []
        ]);
        exit;
    }
    
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Nur GET-Anfragen erlaubt',
            'results' => []
        ]);
        exit;
    }
    
    // Get and validate search query
    $query = trim($_GET['q'] ?? '');
    
    // Validate query length
    if (empty($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Leere Suchanfrage',
            'results' => []
        ]);
        exit;
    }
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'message' => 'Suchanfrage zu kurz (mindestens 2 Zeichen)',
            'results' => []
        ]);
        exit;
    }
    
    if (strlen($query) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Suchanfrage zu lang (maximal 100 Zeichen)',
            'results' => []
        ]);
        exit;
    }
    
    // Prepare search term for LIKE queries
    $searchTerm = '%' . $query . '%';
    
    // Type label mapping for result categorization
    // Note: Keys use English database type names, values are German display labels
    $typeLabels = [
        'inventory' => '[Inventar]',
        'user' => '[Person]',
        'news' => '[News]',
        'event' => '[Event]',
        'project' => '[Projekt]'
    ];
    
    // ===========================================================================
    // STEP 1: Query User Database (users, alumni_profiles)
    // Limit to Top 5 results per category for performance
    // ===========================================================================
    $sqlUserDb = "
        -- Search Users (with alumni profiles)
        -- Only published alumni profiles are shown for privacy/GDPR compliance
        SELECT 
            'user' as type,
            u.id,
            CONCAT(u.firstname, ' ', u.lastname) as title,
            CONCAT(
                COALESCE(ap.position, ''),
                IF(ap.company IS NOT NULL AND ap.position IS NOT NULL, ' @ ', ''),
                COALESCE(ap.company, '')
            ) as subtitle,
            NULL as quantity,
            ap.bio as extra_info,
            u.created_at as date
        FROM users u
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND ap.is_published = 1
        WHERE (
            u.firstname LIKE :search1
            OR u.lastname LIKE :search2
            OR ap.company LIKE :search3
            OR ap.position LIKE :search4
            OR ap.bio LIKE :search5
        )
        ORDER BY u.created_at DESC
        LIMIT 5
    ";
    
    $stmtUser = $userPdo->prepare($sqlUserDb);
    for ($i = 1; $i <= 5; $i++) {
        $stmtUser->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    $stmtUser->execute();
    $userResults = $stmtUser->fetchAll(PDO::FETCH_ASSOC);
    
    // ===========================================================================
    // STEP 2: Query Content Database (inventory, events, projects, news)
    // Separate queries with Top 5 limit per category for performance
    // ===========================================================================
    
    // Query 2.1: Search Inventory (Top 5)
    $sqlInventory = "
        SELECT 
            'inventory' as type,
            i.id,
            i.name as title,
            CONCAT(
                COALESCE(il.name, i.location, 'Kein Standort'),
                ' · ',
                COALESCE(ic.display_name, i.category, 'Keine Kategorie')
            ) as subtitle,
            i.quantity,
            NULL as extra_info,
            i.created_at as date
        FROM inventory i
        LEFT JOIN inventory_locations il ON i.location = il.name
        LEFT JOIN inventory_categories ic ON i.category = ic.key_name
        WHERE i.status = 'active'
        AND (
            i.name LIKE :search1
            OR i.description LIKE :search2
            OR i.location LIKE :search3
            OR il.name LIKE :search4
            OR i.tags LIKE :search5
        )
        ORDER BY i.created_at DESC
        LIMIT 5
    ";
    
    $stmtInventory = $contentPdo->prepare($sqlInventory);
    for ($i = 1; $i <= 5; $i++) {
        $stmtInventory->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    $stmtInventory->execute();
    $inventoryResults = $stmtInventory->fetchAll(PDO::FETCH_ASSOC);
    
    // Query 2.2: Search News (Top 5)
    $sqlNews = "
        SELECT 
            'news' as type,
            id,
            title,
            COALESCE(category, 'News') as subtitle,
            NULL as quantity,
            content as extra_info,
            created_at as date
        FROM news
        WHERE title LIKE :search1
        OR content LIKE :search2
        ORDER BY created_at DESC
        LIMIT 5
    ";
    
    $stmtNews = $contentPdo->prepare($sqlNews);
    for ($i = 1; $i <= 2; $i++) {
        $stmtNews->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    $stmtNews->execute();
    $newsResults = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
    
    // Query 2.3: Search Events (Top 5)
    $sqlEvents = "
        SELECT 
            'event' as type,
            id,
            title,
            COALESCE(location, 'Event') as subtitle,
            NULL as quantity,
            description as extra_info,
            event_date as date
        FROM events
        WHERE title LIKE :search1
        OR description LIKE :search2
        OR location LIKE :search3
        ORDER BY event_date DESC
        LIMIT 5
    ";
    
    $stmtEvents = $contentPdo->prepare($sqlEvents);
    for ($i = 1; $i <= 3; $i++) {
        $stmtEvents->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    $stmtEvents->execute();
    $eventsResults = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);
    
    // Query 2.4: Search Projects (Top 5)
    $sqlProjects = "
        SELECT 
            'project' as type,
            id,
            title,
            COALESCE(client, 'Projekt') as subtitle,
            NULL as quantity,
            description as extra_info,
            created_at as date
        FROM projects
        WHERE title LIKE :search1
        OR description LIKE :search2
        OR client LIKE :search3
        ORDER BY created_at DESC
        LIMIT 5
    ";
    
    $stmtProjects = $contentPdo->prepare($sqlProjects);
    for ($i = 1; $i <= 3; $i++) {
        $stmtProjects->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    $stmtProjects->execute();
    $projectsResults = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge all content results
    $contentResults = array_merge($inventoryResults, $newsResults, $eventsResults, $projectsResults);
    
    // ===========================================================================
    // STEP 3: Merge results from both databases
    // ===========================================================================
    $allResults = array_merge($userResults, $contentResults);
    
    // ===========================================================================
    // STEP 4: Calculate relevance score for each result
    // ===========================================================================
    // Relevance scoring algorithm:
    // - Exact match in title: +10 points
    // - Partial match in title: +5 points
    // - Match in subtitle: +3 points
    // - Match in extra_info: +1 point
    // - Case-insensitive matching
    // - Recent items (within 30 days): +2 points bonus
    // ===========================================================================
    $searchLower = mb_strtolower($query);
    
    foreach ($allResults as $key => $result) {
        $score = 0;
        
        // Check title relevance
        $titleLower = mb_strtolower($result['title'] ?? '');
        if ($titleLower === $searchLower) {
            $score += 10; // Exact match in title
        } elseif (mb_strpos($titleLower, $searchLower) !== false) {
            $score += 5; // Partial match in title
        }
        
        // Check subtitle relevance
        $subtitleLower = mb_strtolower($result['subtitle'] ?? '');
        if (mb_strpos($subtitleLower, $searchLower) !== false) {
            $score += 3;
        }
        
        // Check extra_info (description/bio) relevance
        $extraInfoLower = mb_strtolower($result['extra_info'] ?? '');
        if (mb_strpos($extraInfoLower, $searchLower) !== false) {
            $score += 1;
        }
        
        // Bonus for recent items (within 30 days)
        if (!empty($result['date'])) {
            $itemDate = strtotime($result['date']);
            if ($itemDate !== false) {
                $daysSinceCreation = (time() - $itemDate) / (60 * 60 * 24);
                if ($daysSinceCreation <= 30) {
                    $score += 2;
                }
            }
        }
        
        // Store score with the result
        $allResults[$key]['relevance_score'] = $score;
    }
    
    // Sort all results by relevance score (DESC), then by date (DESC) as tiebreaker
    usort($allResults, function($a, $b) {
        // Primary sort: by relevance score (higher is better) - use comparison operators
        if ($a['relevance_score'] !== $b['relevance_score']) {
            return ($a['relevance_score'] < $b['relevance_score']) ? 1 : -1;
        }
        // Secondary sort: by date (newer is better) - use comparison instead of subtraction
        $dateA = strtotime($a['date']);
        $dateB = strtotime($b['date']);
        if ($dateA === $dateB) {
            return 0;
        }
        return ($dateA < $dateB) ? 1 : -1;
    });
    
    // Group results by type
    $groupedResults = [
        'inventory' => [],
        'user' => [],
        'news' => [],
        'event' => [],
        'project' => []
    ];
    
    foreach ($allResults as $row) {
        $type = $row['type'];
        
        // Build URL based on type
        $url = '';
        switch ($type) {
            case 'inventory':
                $url = 'index.php?page=inventory#item-' . $row['id'];
                break;
            case 'user':
                $url = 'index.php?page=alumni_database#alumni-' . $row['id'];
                break;
            case 'news':
                $url = 'index.php?page=newsroom#news-' . $row['id'];
                break;
            case 'event':
                $url = 'index.php?page=events#event-' . $row['id'];
                break;
            case 'project':
                $url = 'index.php?page=projects#project-' . $row['id'];
                break;
        }
        
        // Get type label for display using mapping
        if (!isset($typeLabels[$type])) {
            // Log unexpected type for debugging
            error_log("Global Search: Unexpected result type encountered: " . $type);
            $typeLabel = '[Unknown]';
        } else {
            $typeLabel = $typeLabels[$type];
        }
        
        // Add to grouped results with explicit type label
        $groupedResults[$type][] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'quantity' => $row['quantity'],
            'date' => $row['date'],
            'url' => $url,
            'type' => $type,
            'typeLabel' => $typeLabel
        ];
    }
    
    // Calculate total count
    $totalCount = count($allResults);
    
    // Calculate counts per category
    $counts = [
        'inventory' => count($groupedResults['inventory']),
        'user' => count($groupedResults['user']),
        'news' => count($groupedResults['news']),
        'event' => count($groupedResults['event']),
        'project' => count($groupedResults['project'])
    ];
    
    // Return results
    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => $totalCount,
        'counts' => $counts,
        'results' => $groupedResults
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    // Log database error
    error_log("Global Search DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankfehler bei der Suche',
        'results' => []
    ]);
} catch (Exception $e) {
    // Log general error
    error_log("Global Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler bei der Suche',
        'results' => []
    ]);
}
