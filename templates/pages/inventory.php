<?php
/**
 * Inventory Management Page
 * Displays inventory items with glassmorphism design
 * Provides CRUD functionality for authorized users
 */

// Initialize Inventory class
require_once BASE_PATH . '/src/Inventory.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);
$inventory = new Inventory($pdo, $systemLogger);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    // Validate CSRF token for all state-changing actions
    $statefulActions = ['create', 'update', 'delete', 'adjust_quantity', 'add_location', 'delete_location', 'add_category', 'delete_category'];
    if (in_array($action, $statefulActions, true)) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$auth->verifyCsrfToken($csrfToken)) {
            $response = ['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte laden Sie die Seite neu.'];
            echo json_encode($response);
            exit;
        }
    }
    
    switch ($action) {
        case 'search':
            // AJAX search with multi-filter support
            // Check if user is logged in (search is read-only, so we don't need edit permission)
            if (!$auth->isLoggedIn()) {
                $response = ['success' => false, 'message' => 'Nicht angemeldet'];
                break;
            }
            
            $search = $_POST['search'] ?? null;
            $filters = [
                'category' => $_POST['category'] ?? 'all',
                'location' => $_POST['location'] ?? 'all',
                'status' => $_POST['status'] ?? 'all'
            ];
            
            $items = $inventory->getAll($search, $filters);
            $response = [
                'success' => true,
                'items' => $items,
                'count' => count($items)
            ];
            break;
            
        case 'create':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            // Validate required fields
            if (empty($_POST['name'])) {
                $response = ['success' => false, 'message' => 'Name ist erforderlich'];
                break;
            }
            
            // Handle image upload with transaction support
            $imagePath = null;  // Will contain only the filename, not full path
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Upload image first (not yet committed to database)
                $uploadResult = $inventory->handleImageUpload($_FILES['image']);
                if (!$uploadResult['success']) {
                    // Image upload failed, return error immediately
                    $response = $uploadResult;
                    break;
                }
                $imagePath = $uploadResult['path'];  // Returns filename only
            }
            
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? '',
                'category' => $_POST['category'] ?? '',
                'quantity' => (int)($_POST['quantity'] ?? 0),
                'image_path' => $imagePath,
                'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                'tags' => !empty($_POST['tags']) ? $_POST['tags'] : null
            ];
            
            // Create item with transaction support
            $itemId = $inventory->create($data, $auth->getUserId());
            if ($itemId) {
                $response = ['success' => true, 'message' => 'Gegenstand erfolgreich erstellt', 'id' => $itemId];
            } else {
                // Database operation failed, clean up uploaded image if exists
                if ($imagePath) {
                    $inventory->deleteImage($imagePath);
                }
                $response = ['success' => false, 'message' => 'Fehler beim Erstellen'];
            }
            break;
            
        case 'update':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $itemId = (int)($_POST['id'] ?? 0);
            
            // Validate required fields
            if (empty($_POST['name'])) {
                $response = ['success' => false, 'message' => 'Name ist erforderlich'];
                break;
            }
            
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? '',
                'category' => $_POST['category'] ?? '',
                'quantity' => (int)($_POST['quantity'] ?? 0),
                'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                'tags' => !empty($_POST['tags']) ? $_POST['tags'] : null
            ];
            
            // Handle image upload with transaction support
            $newImagePath = null;  // Will contain only the filename, not full path
            $oldItem = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Upload new image
                $uploadResult = $inventory->handleImageUpload($_FILES['image']);
                if (!$uploadResult['success']) {
                    // Image upload failed, return error immediately
                    $response = $uploadResult;
                    break;
                }
                $newImagePath = $uploadResult['path'];  // Returns filename only
                $data['image_path'] = $newImagePath;
                
                // Get old item data for cleanup after successful update
                $oldItem = $inventory->getById($itemId);
            }
            
            // Update item with transaction support
            if ($inventory->update($itemId, $data, $auth->getUserId())) {
                // Update successful, delete old image if a new one was uploaded
                if ($newImagePath && isset($oldItem) && !empty($oldItem['image_path'])) {
                    $inventory->deleteImage($oldItem['image_path']);
                }
                $response = ['success' => true, 'message' => 'Gegenstand erfolgreich aktualisiert'];
            } else {
                // Database update failed, clean up newly uploaded image if exists
                if ($newImagePath) {
                    $inventory->deleteImage($newImagePath);
                }
                $response = ['success' => false, 'message' => 'Fehler beim Aktualisieren'];
            }
            break;
            
        case 'delete':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $itemId = (int)($_POST['id'] ?? 0);
            if ($inventory->delete($itemId, $auth->getUserId())) {
                $response = ['success' => true, 'message' => 'Gegenstand erfolgreich gelöscht'];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Löschen'];
            }
            break;
            
        case 'get':
            $itemId = (int)($_POST['id'] ?? 0);
            $item = $inventory->getById($itemId);
            if ($item) {
                $response = ['success' => true, 'item' => $item];
            } else {
                $response = ['success' => false, 'message' => 'Gegenstand nicht gefunden'];
            }
            break;
            
        case 'adjust_quantity':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $itemId = (int)($_POST['id'] ?? 0);
            $adjustment = (int)($_POST['adjustment'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            
            if ($adjustment === 0) {
                $response = ['success' => false, 'message' => 'Ungültige Mengenänderung'];
                break;
            }
            
            // Use adjustQuantity method for proper logging and transaction safety
            if ($inventory->adjustQuantity($itemId, $adjustment, $comment, $auth->getUserId())) {
                // Get updated item to return the actual new quantity
                $updatedItem = $inventory->getById($itemId);
                if ($updatedItem) {
                    $response = ['success' => true, 'message' => 'Menge aktualisiert', 'newQuantity' => (int)$updatedItem['quantity']];
                } else {
                    $response = ['success' => false, 'message' => 'Fehler beim Abrufen der aktualisierten Menge'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Aktualisieren der Menge'];
            }
            break;
            
        case 'add_location':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $locationName = trim($_POST['location_name'] ?? '');
            
            if (empty($locationName)) {
                $response = ['success' => false, 'message' => 'Standort-Name ist erforderlich'];
                break;
            }
            
            if ($inventory->addLocation($locationName, $auth->getUserId())) {
                // Get the newly created location ID
                $stmt = $pdo->prepare("SELECT id FROM inventory_locations WHERE name = ?");
                $stmt->execute([$locationName]);
                $locationData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get updated locations list
                $locations = $inventory->getConfiguredLocations();
                $response = [
                    'success' => true, 
                    'message' => 'Standort erfolgreich hinzugefügt',
                    'location' => $locationName,
                    'location_id' => $locationData ? (int)$locationData['id'] : null,
                    'locations' => $locations
                ];
            } else {
                $response = ['success' => false, 'message' => 'Standort existiert bereits oder konnte nicht hinzugefügt werden'];
            }
            break;
            
        case 'delete_location':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $locationId = (int)($_POST['location_id'] ?? 0);
            
            if ($locationId <= 0) {
                $response = ['success' => false, 'message' => 'Ungültige Standort-ID'];
                break;
            }
            
            $result = $inventory->deleteLocation($locationId, $auth->getUserId());
            $response = $result;
            
            if ($result['success']) {
                // Get updated locations list
                $locations = $inventory->getConfiguredLocations();
                $response['locations'] = $locations;
            }
            break;
            
        case 'add_category':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $keyName = trim($_POST['key_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            
            if (empty($keyName) || empty($displayName)) {
                $response = ['success' => false, 'message' => 'Schlüsselname und Anzeigename sind erforderlich'];
                break;
            }
            
            if ($inventory->addCategory($keyName, $displayName, $auth->getUserId())) {
                // Get the newly created category ID
                $stmt = $pdo->prepare("SELECT id FROM inventory_categories WHERE key_name = ?");
                $stmt->execute([$keyName]);
                $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get updated categories list
                $categories = $inventory->getConfiguredCategories();
                $response = [
                    'success' => true, 
                    'message' => 'Kategorie erfolgreich hinzugefügt',
                    'category' => [
                        'id' => $categoryData ? (int)$categoryData['id'] : null,
                        'key_name' => $keyName, 
                        'display_name' => $displayName
                    ],
                    'categories' => $categories
                ];
            } else {
                $response = ['success' => false, 'message' => 'Kategorie existiert bereits oder konnte nicht hinzugefügt werden'];
            }
            break;
            
        case 'delete_category':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $categoryId = (int)($_POST['category_id'] ?? 0);
            
            if ($categoryId <= 0) {
                $response = ['success' => false, 'message' => 'Ungültige Kategorie-ID'];
                break;
            }
            
            $result = $inventory->deleteCategory($categoryId, $auth->getUserId());
            $response = $result;
            
            if ($result['success']) {
                // Get updated categories list
                $categories = $inventory->getConfiguredCategories();
                $response['categories'] = $categories;
            }
            break;
            
        case 'get_all_locations':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $locations = $inventory->getAllLocations();
            $response = ['success' => true, 'locations' => $locations];
            break;
            
        case 'get_all_categories':
            if (!$auth->can('edit_inventory')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $categories = $inventory->getAllCategories();
            $response = ['success' => true, 'categories' => $categories];
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get search query
$search = $_GET['search'] ?? null;

// Get all inventory items
$items = $inventory->getAll($search);
$stats = $inventory->getStatistics();

// Check if user can edit inventory
$canEdit = $auth->can('edit_inventory');

// Get CSRF token for AJAX requests
$csrfToken = $auth->getCsrfToken();

// Get locations from database (now database-driven)
$allLocations = $inventory->getConfiguredLocations();
sort($allLocations); // Sort alphabetically for better UX

// Get categories from database (now database-driven)
// Returns associative array: key_name => display_name
$allCategories = $inventory->getConfiguredCategories();
if (!is_array($allCategories)) {
    $allCategories = []; // Safety fallback
}
?>

<div class="container container-xl my-5" data-csrf-token="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Header -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Inventar</span></span>
                <span class="word-wrapper"><span class="word text-gradient">System</span></span>
            </h1>
            <p class="ibc-lead">
                Verwalten Sie das Inventar der Junior Enterprise
            </p>
        </div>
    </div>

    <!-- Search Bar and Add Button - Sticky Filter Bar -->
    <div class="sticky-filter-bar">
        <div class="row mb-4">
            <div class="col-md-8 mb-3 mb-md-0">
                <div class="input-group search-form">
                    <input type="text" class="form-control" id="liveSearchInput" placeholder="Nach Name, Ort oder Tags suchen..." value="<?php echo htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Suche nach Inventar">
                    <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                    </span>
                </div>
                <small class="text-muted">Die Ergebnisse werden während der Eingabe automatisch aktualisiert</small>
            </div>
            <?php if ($canEdit): ?>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-success btn-edit" data-bs-toggle="modal" data-bs-target="#inventoryModal" data-action="create-inventory">
                        <i class="fas fa-plus me-2"></i>Neuer Gegenstand
                    </button>
                    <?php if ($auth->hasFullAccess()): ?>
                        <a href="index.php?page=inventory_config" class="btn btn-outline-secondary btn-edit ms-2" title="Standorte und Kategorien verwalten">
                            <i class="fas fa-cog"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter Pills for Categories, Locations, and Status -->
        <div class="row mb-4">
        <div class="col-12 mb-3">
            <h6 class="text-muted mb-2">Kategorie filtern:</h6>
            <div class="filter-pills" role="group" aria-label="Kategorie-Filter">
                <span class="filter-pill active" data-filter-type="category" data-value="all" tabindex="0" role="button">
                    <i class="fas fa-list me-1"></i> Alle
                </span>
                <span class="filter-pill" data-filter-type="category" data-value="technik" tabindex="0" role="button">
                    <i class="fas fa-laptop me-1"></i> Technik
                </span>
                <span class="filter-pill" data-filter-type="category" data-value="marketing" tabindex="0" role="button">
                    <i class="fas fa-bullhorn me-1"></i> Marketing
                </span>
                <span class="filter-pill" data-filter-type="category" data-value="buero" tabindex="0" role="button">
                    <i class="fas fa-building me-1"></i> Büro
                </span>
                <span class="filter-pill" data-filter-type="category" data-value="veranstaltung" tabindex="0" role="button">
                    <i class="fas fa-calendar-alt me-1"></i> Veranstaltung
                </span>
                <span class="filter-pill" data-filter-type="category" data-value="sonstiges" tabindex="0" role="button">
                    <i class="fas fa-box me-1"></i> Sonstiges
                </span>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <h6 class="text-muted mb-2">Standort filtern:</h6>
            <select class="form-select form-select-lg" id="locationFilter" aria-label="Standort filtern" style="min-height: 44px;">
                <option value="all" selected>Alle Standorte</option>
                <option value="Keller vorne H-1.87">Keller vorne H-1.87</option>
                <option value="Keller hinten H-1.88">Keller hinten H-1.88</option>
                <?php foreach ($allLocations as $location): ?>
                    <?php if ($location !== 'Keller vorne H-1.87' && $location !== 'Keller hinten H-1.88'): ?>
                        <option value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-6 mb-3">
            <h6 class="text-muted mb-2">Status filtern:</h6>
            <div class="filter-pills" role="group" aria-label="Status-Filter">
                <span class="filter-pill active" data-filter-type="status" data-value="all" tabindex="0" role="button">
                    <i class="fas fa-circle me-1"></i> Alle Status
                </span>
                <span class="filter-pill" data-filter-type="status" data-value="active" tabindex="0" role="button">
                    <i class="fas fa-check-circle text-success me-1"></i> Aktiv
                </span>
                <span class="filter-pill" data-filter-type="status" data-value="broken" tabindex="0" role="button">
                    <i class="fas fa-times-circle text-danger me-1"></i> Defekt
                </span>
                <span class="filter-pill" data-filter-type="status" data-value="archived" tabindex="0" role="button">
                    <i class="fas fa-archive text-secondary me-1"></i> Archiviert
                </span>
            </div>
        </div>
    </div>
    </div> <!-- End sticky-filter-bar -->

    <!-- Statistics Cards -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-4">
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-boxes text-primary"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo $stats['total_items']; ?></h3>
                    <p class="text-muted mb-0">Gesamt Artikel</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-layer-group text-success"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo $stats['total_quantity']; ?></h3>
                    <p class="text-muted mb-0">Gesamt Menge</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-tags text-info"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo $stats['categories']; ?></h3>
                    <p class="text-muted mb-0">Kategorien</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo $stats['zero_quantity']; ?></h3>
                    <p class="text-muted mb-0">Nicht verfügbar</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Card Grid (CSS Grid) -->
    <div class="inventory-grid" id="inventoryContainer" role="region" aria-live="polite" aria-label="Inventar-Liste">
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="card glass-card text-center py-5 px-4 h-100">
                    <div class="card-body">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h4>Keine Gegenstände gefunden</h4>
                        <p class="text-muted">
                            <?php if ($search): ?>
                                Ihre Suche ergab keine Treffer. Versuchen Sie andere Suchbegriffe.
                            <?php else: ?>
                                Das Inventar ist leer. Fügen Sie den ersten Gegenstand hinzu!
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($items as $index => $item): ?>
                <?php 
                // Normalize category for data-attribute (remove umlauts)
                $category = strtolower($item['category'] ?? '');
                $category = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $category);
                
                // Determine status indicator color
                $quantity = (int)$item['quantity'];
                $status = $item['status'] ?? 'active';
                if ($quantity == 0 || $status === 'broken') {
                    $statusColor = 'danger'; // Red
                    $statusIcon = 'circle';
                } elseif ($quantity >= 1 && $quantity <= 5) {
                    $statusColor = 'warning'; // Yellow
                    $statusIcon = 'circle';
                } else { // quantity > 5
                    $statusColor = 'success'; // Green
                    $statusIcon = 'circle';
                }
                ?>
                <div class="card glass-card inventory-card <?php echo ($item['quantity'] == 0) ? 'out-of-stock' : ''; ?>" 
                     data-category="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" 
                     data-item-id="<?php echo $item['id']; ?>" 
                     data-aos="fade-up" 
                     data-aos-delay="<?php echo ($index % 3) * 100; ?>">
                        <!-- Status Indicator -->
                        <div class="status-indicator">
                            <i class="fas fa-<?php echo $statusIcon; ?> text-<?php echo $statusColor; ?>" 
                               title="<?php 
                                   if ($quantity == 0 || $status === 'broken') echo 'Nicht verfügbar';
                                   elseif ($quantity >= 1 && $quantity <= 5) echo 'Niedriger Bestand';
                                   else echo 'Auf Lager';
                               ?>"></i>
                        </div>
                        
                        <!-- Image -->
                        <div class="inventory-image-container">
                            <?php if (!empty($item['image_path']) && preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', basename($item['image_path']))): ?>
                                <img src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/assets/uploads/inventory/<?php echo htmlspecialchars(basename($item['image_path']), ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="card-img-top inventory-image img-fluid" 
                                     alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="inventory-image-placeholder">
                                    <i class="fas fa-image fa-3x text-white-50"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Edit/Delete Buttons (overlay) -->
                            <?php if ($canEdit): ?>
                                <div class="inventory-actions">
                                    <button type="button" class="btn btn-light btn-edit me-2" 
                                            data-action="edit-inventory"
                                            data-item-id="<?php echo $item['id']; ?>"
                                            title="Bearbeiten"
                                            style="min-width: 44px; min-height: 44px;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-delete" 
                                            data-action="delete-inventory"
                                            data-item-id="<?php echo $item['id']; ?>"
                                            title="Löschen"
                                            style="min-width: 44px; min-height: 44px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Quantity Badge -->
                            <?php if ($item['quantity'] == 0): ?>
                                <span class="badge bg-danger inventory-badge">Nicht verfügbar</span>
                            <?php else: ?>
                                <span class="badge bg-success inventory-badge"><?php echo $item['quantity']; ?>x</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="card-body">
                            <h5 class="card-title fw-bold mb-2"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            
                            <!-- Category Badge (subtle) -->
                            <?php if (!empty($item['category'])): ?>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark category-badge">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Storage Location -->
                            <?php if (!empty($item['location'])): ?>
                                <p class="location-text text-muted small mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['description'])): ?>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            
                            <!-- Mobile Quantity Controls -->
                            <?php if ($canEdit): ?>
                                <div class="mobile-quantity-controls d-lg-none mt-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-center gap-3">
                                        <button type="button" 
                                                class="btn btn-lg btn-outline-danger quantity-btn" 
                                                data-action="adjust-quantity"
                                                data-item-id="<?php echo $item['id']; ?>"
                                                data-adjustment="-1"
                                                title="Menge verringern"
                                                <?php echo ($item['quantity'] <= 0) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <span class="quantity-display fs-4 fw-bold" data-item-id="<?php echo $item['id']; ?>">
                                            <?php echo $item['quantity']; ?>
                                        </span>
                                        <button type="button" 
                                                class="btn btn-lg btn-outline-success quantity-btn" 
                                                data-action="adjust-quantity"
                                                data-item-id="<?php echo $item['id']; ?>"
                                                data-adjustment="1"
                                                title="Menge erhöhen">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-info" 
                                                data-action="quantity-comment"
                                                data-item-id="<?php echo $item['id']; ?>"
                                                title="Änderung mit Kommentar"
                                                style="min-width: 44px; min-height: 44px;">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Inventory Modal (Create/Edit) -->
<?php if ($canEdit): ?>
<div class="modal fade" id="inventoryModal" tabindex="-1" aria-labelledby="inventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="inventoryModalLabel">Neuer Gegenstand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="inventoryForm" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="itemId" name="id">
                    <input type="hidden" id="formAction" name="action" value="create">
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="itemName" name="name" placeholder="Name" required>
                        <label for="itemName">Name *</label>
                        <div class="invalid-feedback">
                            Bitte geben Sie einen Namen ein.
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="itemDescription" name="description" placeholder="Beschreibung" style="height: 100px"></textarea>
                        <label for="itemDescription">Beschreibung</label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <select class="form-select" id="itemLocation" name="location">
                                    <option value="">-- Ort auswählen --</option>
                                    <?php foreach ($allLocations as $location): ?>
                                        <option value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="itemLocation">Ort</label>
                            </div>
                            <!-- Quick-Add Location -->
                            <div class="mt-2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="quickAddLocationInput" placeholder="Neuer Standort">
                                    <label for="quickAddLocationInput">Neuer Standort</label>
                                </div>
                                <button class="btn btn-outline-success btn-sm mt-2 w-100" type="button" id="quickAddLocationBtn" title="Standort hinzufügen" aria-label="Standort hinzufügen">
                                    <i class="fas fa-plus me-1" aria-hidden="true"></i> Standort hinzufügen
                                </button>
                                <small class="text-muted d-block mt-1">Schnell einen neuen Standort hinzufügen</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <select class="form-select" id="itemCategory" name="category">
                                    <option value="">-- Kategorie auswählen --</option>
                                    <?php foreach ($allCategories as $key => $displayName): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="itemCategory">Kategorie</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="itemQuantity" name="quantity" min="0" value="0" placeholder="Menge" required>
                                <label for="itemQuantity">Menge *</label>
                                <div class="invalid-feedback">
                                    Bitte geben Sie eine gültige Menge ein.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="itemPurchaseDate" name="purchase_date">
                                <label for="itemPurchaseDate">Anschaffungsdatum</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="itemTags" name="tags" placeholder="z.B. elektronik, wichtig, neu">
                        <label for="itemTags">Tags</label>
                        <small class="form-text">Trennen Sie mehrere Tags mit Kommas</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bild hochladen</label>
                        <!-- Drag and Drop Zone -->
                        <div class="drag-drop-zone" id="dragDropZone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p class="mb-2"><strong>Bild hier ablegen</strong> oder klicken zum Auswählen</p>
                            <small class="text-muted">Max 5MB. Formate: JPEG, PNG, WebP, GIF</small>
                        </div>
                        <input type="file" class="form-control d-none" id="itemImage" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                        
                        <!-- Upload Progress Bar -->
                        <div id="uploadProgress" class="mt-3" style="display: none;">
                            <div class="progress progress-custom">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span id="uploadProgressText">0%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <img id="previewImg" src="" alt="Vorschau" class="img-fluid rounded shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="submitBtnText">Erstellen</span>
                        <span id="submitSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quantity Adjustment Comment Modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="quantityCommentModal" tabindex="-1" aria-labelledby="quantityCommentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="quantityCommentModalLabel">Kommentar zur Mengenänderung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="commentItemId">
                <input type="hidden" id="commentAdjustment">
                <input type="hidden" id="commentButtonElement">
                <div class="form-floating mb-3">
                    <textarea class="form-control" id="quantityComment" placeholder="z.B. Verschrottung, Verlust, Neuzugang..." style="height: 100px"></textarea>
                    <label for="quantityComment">Grund der Änderung (optional)</label>
                    <small class="form-text">Geben Sie optional einen Kommentar ein, um die Änderung zu dokumentieren.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" data-action="submit-quantity-comment">Bestätigen</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Back to Top Button -->
<button id="inventoryBackToTop" class="btn btn-primary back-to-top-btn" 
        onclick="scrollToTop()" 
        title="Nach oben scrollen"
        aria-label="Nach oben scrollen">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- JavaScript for Location Dropdown Filter -->
<!-- Custom Styles for Inventory Cards -->
<style>
/* Sticky Filter Bar */
.sticky-filter-bar {
    position: sticky;
    top: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    z-index: 100;
    padding: 1rem 0;
    margin-left: -1rem;
    margin-right: -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* CSS Grid Layout for Inventory Cards */
.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Empty State Full Width */
.inventory-grid .empty-state {
    grid-column: 1 / -1;
}

/* Image Container with 4:3 Aspect Ratio */
.inventory-image-container {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    margin-bottom: 1rem;
}

/* Image Styling with object-fit: cover */
.inventory-image-container .inventory-image,
.inventory-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Placeholder for missing images */
.inventory-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.2), rgba(108, 92, 231, 0.1));
}

/* Quantity Badge - Floating on top right */
.inventory-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    z-index: 10;
}

/* Action Buttons Overlay */
.inventory-actions {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 9;
}

.inventory-card:hover .inventory-actions {
    opacity: 1;
}

/* Status Indicator */
.status-indicator {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 10;
}

/* Card Padding Adjustments */
.inventory-card {
    padding: 1rem !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
}

.inventory-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

/* Category Badge - Subtle styling */
.category-badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

/* Location Text Styling */
.location-text {
    font-size: 0.875rem;
    margin: 0;
}

/* Skeleton Loader Adjustments for Card Layout */
.skeleton-loader-item .inventory-image-container {
    aspect-ratio: 4 / 3;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .inventory-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1rem;
    }
    
    .sticky-filter-bar {
        /* Keep sticky on mobile for better UX */
        padding: 0.75rem 0;
        margin-left: -0.5rem;
        margin-right: -0.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const locationFilter = document.getElementById('locationFilter');
    if (locationFilter) {
        locationFilter.addEventListener('change', function() {
            const value = this.value;
            // Use the existing applyFilter function from main.js
            if (typeof applyFilter === 'function') {
                // Create a temporary element to pass to applyFilter
                const tempElement = document.createElement('div');
                tempElement.setAttribute('data-filter-type', 'location');
                tempElement.setAttribute('data-value', value);
                applyFilter('location', value, tempElement);
            }
        });
    }
});
</script>

<!-- JavaScript functions are now in /assets/js/main.js -->
