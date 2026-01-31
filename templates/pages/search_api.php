<?php
/**
 * Global Search API
 * Returns search results from News, Events, Alumni, and Projects
 * Used by the global search feature in the header
 * 
 * Note: This file is accessed through the router (index.php?page=search_api)
 * so $auth, $pdo, and constants are available
 */

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($auth) || !$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search query with proper sanitization
$query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$query = trim($query);

// Validate query
if (empty($query) || strlen($query) < 2 || strlen($query) > 100) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $results = [
        'news' => [],
        'events' => [],
        'alumni' => [],
        'projects' => [],
        'inventory' => []
    ];
    
    // Search News
    $stmt = $pdo->prepare("
        SELECT id, title, category, created_at
        FROM news
        WHERE title LIKE ? OR content LIKE ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $newsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newsResults as $item) {
        $results['news'][] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'subtitle' => $item['category'] ?? 'News',
            'date' => $item['created_at'],
            'url' => 'index.php?page=newsroom#news-' . $item['id'],
            'type' => 'news'
        ];
    }
    
    // Search Events
    $stmt = $pdo->prepare("
        SELECT id, title, event_date, location
        FROM events
        WHERE title LIKE ? OR description LIKE ?
        ORDER BY event_date ASC
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $eventResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($eventResults as $item) {
        $results['events'][] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'subtitle' => $item['location'] ?? 'Event',
            'date' => $item['event_date'],
            'url' => 'index.php?page=events#event-' . $item['id'],
            'type' => 'event'
        ];
    }
    
    // Search Alumni
    $stmt = $pdo->prepare("
        SELECT id, firstname, lastname, company, position
        FROM alumni_profiles
        WHERE is_published = 1 
        AND (firstname LIKE ? OR lastname LIKE ? OR company LIKE ? OR position LIKE ?)
        ORDER BY lastname ASC
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $alumniResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alumniResults as $item) {
        $results['alumni'][] = [
            'id' => $item['id'],
            'title' => trim(($item['firstname'] ?? '') . ' ' . ($item['lastname'] ?? '')),
            'subtitle' => ($item['position'] ?? '') . ($item['company'] ? ' @ ' . $item['company'] : ''),
            'date' => null,
            'url' => 'index.php?page=alumni_database#alumni-' . $item['id'],
            'type' => 'alumni'
        ];
    }
    
    // Search Projects
    require_once BASE_DIR . '/src/Project.php';
    $project = new Project($pdo);
    $projectResults = $project->search($query, 5);
    
    foreach ($projectResults as $item) {
        $results['projects'][] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'subtitle' => $item['client'] ?? 'Projekt',
            'date' => $item['created_at'],
            'url' => 'index.php?page=projects#project-' . $item['id'],
            'type' => 'project'
        ];
    }
    
    // Search Inventory Items
    $stmt = $pdo->prepare("
        SELECT id, name, location, category, quantity
        FROM inventory
        WHERE name LIKE ? OR location LIKE ? OR tags LIKE ? OR description LIKE ?
        ORDER BY name ASC
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $inventoryResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($inventoryResults as $item) {
        $results['inventory'][] = [
            'id' => $item['id'],
            'title' => $item['name'],
            'subtitle' => ($item['location'] ?? 'Kein Standort') . ' · ' . ($item['category'] ?? 'Keine Kategorie'),
            'quantity' => $item['quantity'],
            'url' => 'index.php?page=inventory#item-' . $item['id'],
            'type' => 'inventory'
        ];
    }
    
    // Return results
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
