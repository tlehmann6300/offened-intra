<?php
declare(strict_types=1);

/**
 * Global Search API
 * 
 * Centralized search endpoint that searches across multiple database tables using UNION queries.
 * Searches: inventory, users/alumni_profiles, news, events, and projects.
 * Returns results grouped by type (inventory, user, news, event, project).
 */

// Set JSON response header
header('Content-Type: application/json');

// Load configuration and dependencies
require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';

try {
    // Initialize core services
    $pdo = Database::getConnection();
    $systemLogger = new SystemLogger($pdo);
    $auth = new Auth($pdo, $systemLogger);
    
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
    
    // Get and validate pagination parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Validate pagination parameters
    if ($limit < 1 || $limit > 100) {
        $limit = 50; // Default to 50
    }
    
    if ($offset < 0) {
        $offset = 0; // Default to 0
    }
    
    // Validate query length
    if (empty($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Leere Suchanfrage',
            'results' => [],
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        exit;
    }
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'message' => 'Suchanfrage zu kurz (mindestens 2 Zeichen)',
            'results' => [],
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        exit;
    }
    
    if (strlen($query) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Suchanfrage zu lang (maximal 100 Zeichen)',
            'results' => [],
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        exit;
    }
    
    // Prepare search term for LIKE queries
    $searchTerm = '%' . $query . '%';
    
    // Build UNION query to search across all tables
    $sql = "
        -- Search Inventory
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
        
        UNION ALL
        
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
            u.firstname LIKE :search6
            OR u.lastname LIKE :search7
            OR ap.company LIKE :search8
            OR ap.position LIKE :search9
            OR ap.bio LIKE :search10
        )
        
        UNION ALL
        
        -- Search News
        SELECT 
            'news' as type,
            id,
            title,
            COALESCE(category, 'News') as subtitle,
            NULL as quantity,
            content as extra_info,
            created_at as date
        FROM news
        WHERE title LIKE :search11
        OR content LIKE :search12
        
        UNION ALL
        
        -- Search Events
        SELECT 
            'event' as type,
            id,
            title,
            COALESCE(location, 'Event') as subtitle,
            NULL as quantity,
            description as extra_info,
            event_date as date
        FROM events
        WHERE title LIKE :search13
        OR description LIKE :search14
        OR location LIKE :search15
        
        UNION ALL
        
        -- Search Projects
        SELECT 
            'project' as type,
            id,
            title,
            COALESCE(client, 'Projekt') as subtitle,
            NULL as quantity,
            description as extra_info,
            created_at as date
        FROM projects
        WHERE title LIKE :search16
        OR description LIKE :search17
        OR client LIKE :search18
        
        ORDER BY date DESC
        LIMIT :limit OFFSET :offset
    ";
    
    // Prepare statement
    $stmt = $pdo->prepare($sql);
    
    // Bind all search parameters (18 total)
    for ($i = 1; $i <= 18; $i++) {
        $stmt->bindValue(":search{$i}", $searchTerm, PDO::PARAM_STR);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Execute query
    $stmt->execute();
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        
        // Add to grouped results
        $groupedResults[$type][] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'quantity' => $row['quantity'],
            'date' => $row['date'],
            'url' => $url,
            'type' => $type
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
        'results' => $groupedResults,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'returned' => $totalCount
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    // Log database error
    error_log("Global Search DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankfehler bei der Suche',
        'results' => [],
        'pagination' => [
            'limit' => $limit ?? 50,
            'offset' => $offset ?? 0
        ]
    ]);
} catch (Exception $e) {
    // Log general error
    error_log("Global Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler bei der Suche',
        'results' => [],
        'pagination' => [
            'limit' => $limit ?? 50,
            'offset' => $offset ?? 0
        ]
    ]);
}
