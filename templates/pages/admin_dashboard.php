<?php
/**
 * Admin Dashboard Template
 * Only accessible for roles: admin, vorstand, ressortleiter
 * 
 * Features:
 * - Three statistics cards (Active Members vs Alumni, Total Inventory Value, Open Project Positions)
 * - Inventory alert list (items with stock < 2)
 * - Member growth chart (last 12 months) using Chart.js
 * - Glassmorphism styling from theme.css
 */

// Check if user has permission to access this page
$userRole = $auth->getUserRole();
$allowedRoles = ['admin', 'vorstand', 'ressortleiter'];

if (!in_array($userRole, $allowedRoles, true)) {
    header('Location: index.php?page=home');
    exit;
}

// Initialize Inventory class for inventory calculations
require_once BASE_PATH . '/src/Inventory.php';
require_once BASE_PATH . '/src/Project.php';

// Get statistics data
try {
    $inventory = new Inventory($pdo);
    $project = new Project($pdo);
    
    // Active Members vs Alumni
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE role IN ('mitglied', 'alumni') GROUP BY role");
    $memberStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeMembersCount = 0;
    $alumniCount = 0;
    foreach ($memberStats as $stat) {
        if ($stat['role'] === 'mitglied') {
            $activeMembersCount = $stat['count'];
        } elseif ($stat['role'] === 'alumni') {
            $alumniCount = $stat['count'];
        }
    }
    
    // Total Inventory Value - Calculate sum of (purchase_price * quantity)
    $inventoryValue = $inventory->getTotalInventoryValue();
    
    // Open Project Positions - Count projects with status 'planning' or 'active'
    $openProjectPositions = $project->countOpenPositions();
    
    // Get inventory items with low stock (quantity < 2)
    $lowStockItems = [];
    $stmt = $pdo->prepare("SELECT id, name, quantity, location, category FROM inventory WHERE quantity < 2 AND status = 'active' ORDER BY quantity ASC, name ASC");
    $stmt->execute();
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get member growth data for last 12 months
    // Note: This could be optimized with a single query using date functions
    // For small datasets (<10k users), this approach is acceptable
    $memberGrowth = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthDate = date('Y-m', strtotime("-{$i} months"));
        $monthLabel = date('M Y', strtotime("-{$i} months"));
        
        // Count users created up to end of that month
        $endOfMonth = date('Y-m-t 23:59:59', strtotime("-{$i} months"));
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('mitglied', 'alumni', 'vorstand', 'ressortleiter', 'admin') AND created_at <= ?");
        $stmt->execute([$endOfMonth]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $memberGrowth[] = [
            'label' => $monthLabel,
            'count' => $count
        ];
    }
    
    // Get recent system logs
    require_once BASE_PATH . '/src/SystemLogger.php';
    $systemLogger = new SystemLogger($pdo);
    
    // Get last 10 activities for "Letzte Aktivitäten" section
    $recentActivities = $systemLogger->getLogs(['limit' => 10, 'offset' => 0]);
    
    // Enhance activities with target names
    foreach ($recentActivities as &$activity) {
        $activity['target_name'] = $systemLogger->getTargetName($activity['target_type'], (int)$activity['target_id']);
    }
    unset($activity); // Break reference
    
    // Get filters from query string
    $logFilters = [];
    if (!empty($_GET['log_target_type'])) {
        $logFilters['target_type'] = $_GET['log_target_type'];
    }
    if (!empty($_GET['log_action'])) {
        $logFilters['action'] = $_GET['log_action'];
    }
    
    // Set limit to show last 50 logs
    $logFilters['limit'] = 50;
    $logFilters['offset'] = 0;
    
    $systemLogs = $systemLogger->getLogs($logFilters);
    
    // Enhance logs with target names
    foreach ($systemLogs as &$log) {
        $log['target_name'] = $systemLogger->getTargetName($log['target_type'], (int)$log['target_id']);
    }
    unset($log); // Break reference
    
} catch (PDOException $e) {
    error_log("Error fetching admin dashboard data: " . $e->getMessage());
    $activeMembersCount = 0;
    $alumniCount = 0;
    $inventoryValue = 0.0;
    $openProjectPositions = 0;
    $lowStockItems = [];
    $memberGrowth = [];
    $recentActivities = [];
    $systemLogs = [];
}
?>

<div class="container-xl my-5">
    <!-- Page Header -->
    <div class="bg-white shadow-lg border-0 rounded mb-5 p-5 h-100">
        <h1 class="display-4 text-center mb-3">
            <i class="fas fa-chart-line me-3"></i>
            <span class="text-gradient-premium">Admin Dashboard</span>
        </h1>
        <p class="lead text-center text-muted mb-0">
            Übersicht und Statistiken für die Verwaltung
        </p>
    </div>

    <!-- Bootstrap Grid Layout for Widgets -->
    <div class="row g-4 mb-5">
        <!-- Active Members vs Alumni Card -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-users fa-3x" style="color: var(--ibc-blue);"></i>
                    </div>
                    <h3 class="h5 mb-3" style="color: var(--ibc-blue);">Aktive Mitglieder vs. Alumni</h3>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-3" style="background: rgba(109, 151, 68, 0.1); border-radius: var(--border-radius-soft);">
                                <div class="h2 mb-1" style="color: var(--ibc-green);"><?php echo htmlspecialchars((string)$activeMembersCount, ENT_QUOTES, 'UTF-8'); ?></div>
                                <small class="text-muted">Aktive</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3" style="background: rgba(32, 35, 74, 0.1); border-radius: var(--border-radius-soft);">
                                <div class="h2 mb-1" style="color: var(--ibc-blue);"><?php echo htmlspecialchars((string)$alumniCount, ENT_QUOTES, 'UTF-8'); ?></div>
                                <small class="text-muted">Alumni</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Gesamt: <?php echo htmlspecialchars((string)($activeMembersCount + $alumniCount), ENT_QUOTES, 'UTF-8'); ?> Mitglieder</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Inventory Value Card -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-box-open fa-3x" style="color: var(--ibc-green);"></i>
                    </div>
                    <h3 class="h5 mb-3" style="color: var(--ibc-blue);">Gesamtwert Inventar</h3>
                    <div class="p-3" style="background: rgba(109, 151, 68, 0.1); border-radius: var(--border-radius-soft);">
                        <div class="h2 mb-1" style="color: var(--ibc-green);"><?php echo number_format($inventoryValue, 2, ',', '.'); ?> €</div>
                        <small class="text-muted">Gesamtwert (aktive Gegenstände)</small>
                    </div>
                    <div class="mt-3">
                        <a href="index.php?page=inventory" class="btn btn-sm btn-outline-ibc me-2">
                            <i class="fas fa-arrow-right me-2"></i>Zum Inventar
                        </a>
                        <a href="index.php?page=inventory_config" class="btn btn-sm btn-outline-secondary" title="Standorte und Kategorien verwalten">
                            <i class="fas fa-cog"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Project Positions Card -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-briefcase fa-3x" style="color: var(--ibc-accent-blue);"></i>
                    </div>
                    <h3 class="h5 mb-3" style="color: var(--ibc-blue);">Offene Projektplätze</h3>
                    <div class="p-3" style="background: rgba(52, 129, 185, 0.1); border-radius: var(--border-radius-soft);">
                        <div class="h2 mb-1" style="color: var(--ibc-accent-blue);"><?php echo htmlspecialchars((string)$openProjectPositions, ENT_QUOTES, 'UTF-8'); ?></div>
                        <small class="text-muted">Verfügbare Plätze</small>
                    </div>
                    <div class="mt-3">
                        <a href="index.php?page=project_management" class="btn btn-sm btn-outline-ibc">
                            <i class="fas fa-arrow-right me-2"></i>Zu den Projekten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Member Growth Chart -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4" style="color: var(--ibc-blue);">
                        <i class="fas fa-chart-line me-2"></i>Mitgliederentwicklung (letzte 12 Monate)
                    </h2>
                    <div style="position: relative; height: 350px;">
                        <canvas id="memberGrowthChart" data-member-growth='<?php echo htmlspecialchars(json_encode($memberGrowth), ENT_QUOTES, 'UTF-8'); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Alert List -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3" style="color: var(--ibc-blue);">
                        <i class="fas fa-exclamation-triangle me-2"></i>Inventar-Warnungen
                        <?php if (count($lowStockItems) > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo htmlspecialchars((string)count($lowStockItems), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (count($lowStockItems) > 0): ?>
                        <!-- Compact Vertical List -->
                        <div class="overflow-auto" style="max-height: 450px;">
                            <?php foreach ($lowStockItems as $item): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong class="text-truncate me-2"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($item['quantity'] == 0): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation me-1"></i><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if (!empty($item['location'])): ?>
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item['category'])): ?>
                                            <span class="ms-2">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="index.php?page=inventory#item-<?php echo htmlspecialchars((string)$item['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       class="btn btn-sm btn-outline-ibc mt-2 w-100">
                                        <i class="fas fa-eye me-1"></i>Anzeigen
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <strong>Alles in Ordnung!</strong><br>
                                <span class="small">Alle Inventargegenstände haben einen ausreichenden Bestand.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Letzte Aktivitäten Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <h2 class="h4 mb-4" style="color: var(--ibc-blue);">
                        <i class="fas fa-list me-2"></i>Letzte Aktivitäten
                    </h2>
                    
                    <?php if (count($recentActivities) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="color: var(--ibc-blue);">Zeitstempel</th>
                                        <th style="color: var(--ibc-blue);">Nutzername</th>
                                        <th style="color: var(--ibc-blue);">Aktion</th>
                                        <th style="color: var(--ibc-blue);">Betroffenes Modul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $timestamp = new DateTime($activity['timestamp']);
                                                echo htmlspecialchars($timestamp->format('d.m.Y H:i:s'), ENT_QUOTES, 'UTF-8'); 
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($activity['firstname']) && !empty($activity['lastname'])): ?>
                                                    <?php echo htmlspecialchars($activity['firstname'] . ' ' . $activity['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Benutzer ID: <?php echo htmlspecialchars((string)$activity['user_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Color-code actions
                                                $actionStyle = '';
                                                $actionText = $activity['action'];
                                                
                                                switch ($activity['action']) {
                                                    case 'create':
                                                        $actionStyle = 'color: #28a745; font-weight: bold;'; // Green
                                                        $actionText = 'Erstellt';
                                                        break;
                                                    case 'delete':
                                                        $actionStyle = 'color: #dc3545; font-weight: bold;'; // Red
                                                        $actionText = 'Gelöscht';
                                                        break;
                                                    case 'update':
                                                        $actionStyle = 'color: #17a2b8; font-weight: bold;'; // Blue/Info
                                                        $actionText = 'Aktualisiert';
                                                        break;
                                                    default:
                                                        $actionStyle = 'color: #6c757d;'; // Gray
                                                }
                                                ?>
                                                <span style="<?php echo $actionStyle; ?>">
                                                    <?php echo htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                // Display module name in German
                                                $moduleName = $activity['target_type'];
                                                switch ($activity['target_type']) {
                                                    case 'inventory':
                                                        $moduleName = 'Inventar';
                                                        break;
                                                    case 'news':
                                                        $moduleName = 'News';
                                                        break;
                                                    case 'alumni':
                                                        $moduleName = 'Alumni';
                                                        break;
                                                }
                                                echo htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8');
                                                
                                                // Optionally show the target name
                                                if (!empty($activity['target_name'])) {
                                                    echo ' <span class="text-muted">(' . htmlspecialchars($activity['target_name'], ENT_QUOTES, 'UTF-8') . ')</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle fa-2x me-3"></i>
                            <div>
                                <strong>Keine Aktivitäten</strong><br>
                                Es wurden noch keine Aktivitäten protokolliert.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Logs Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h4 mb-4" style="color: var(--ibc-blue);">
                        <i class="fas fa-history me-2"></i>System-Protokoll
                        <?php if (count($systemLogs) > 0): ?>
                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars((string)count($systemLogs), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </h2>
                
                <!-- Filter Controls -->
                <form method="GET" action="index.php" class="mb-3">
                    <input type="hidden" name="page" value="admin_dashboard">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Zieltyp</label>
                            <select name="log_target_type" class="form-select" onchange="this.form.submit()">
                                <option value="">Alle</option>
                                <option value="inventory" <?php echo (isset($_GET['log_target_type']) && $_GET['log_target_type'] === 'inventory') ? 'selected' : ''; ?>>Inventar</option>
                                <option value="news" <?php echo (isset($_GET['log_target_type']) && $_GET['log_target_type'] === 'news') ? 'selected' : ''; ?>>News</option>
                                <option value="alumni" <?php echo (isset($_GET['log_target_type']) && $_GET['log_target_type'] === 'alumni') ? 'selected' : ''; ?>>Alumni</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Aktion</label>
                            <select name="log_action" class="form-select" onchange="this.form.submit()">
                                <option value="">Alle</option>
                                <option value="create" <?php echo (isset($_GET['log_action']) && $_GET['log_action'] === 'create') ? 'selected' : ''; ?>>Erstellt</option>
                                <option value="update" <?php echo (isset($_GET['log_action']) && $_GET['log_action'] === 'update') ? 'selected' : ''; ?>>Aktualisiert</option>
                                <option value="delete" <?php echo (isset($_GET['log_action']) && $_GET['log_action'] === 'delete') ? 'selected' : ''; ?>>Gelöscht</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <?php if (!empty($_GET['log_target_type']) || !empty($_GET['log_action'])): ?>
                                <a href="index.php?page=admin_dashboard" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-undo me-2"></i>Filter zurücksetzen
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <?php if (count($systemLogs) > 0): ?>
                    <p class="text-muted mb-3">
                        Die letzten <?php echo htmlspecialchars((string)count($systemLogs), ENT_QUOTES, 'UTF-8'); ?> administrativen Aktionen:
                    </p>
                    
                    <!-- Card Stack View (Mobile) -->
                    <div class="card-stack">
                        <?php foreach ($systemLogs as $log): ?>
                            <?php
                            // Prepare action badge
                            $actionBadgeClass = 'bg-secondary';
                            $actionIcon = 'fa-circle';
                            $actionLabel = $log['action'];
                            
                            switch ($log['action']) {
                                case 'create':
                                    $actionBadgeClass = 'bg-success';
                                    $actionIcon = 'fa-plus';
                                    $actionLabel = 'Erstellt';
                                    break;
                                case 'update':
                                    $actionBadgeClass = 'bg-info';
                                    $actionIcon = 'fa-edit';
                                    $actionLabel = 'Aktualisiert';
                                    break;
                                case 'delete':
                                    $actionBadgeClass = 'bg-danger';
                                    $actionIcon = 'fa-trash';
                                    $actionLabel = 'Gelöscht';
                                    break;
                            }
                            
                            // Prepare type badge
                            $typeBadgeColor = 'var(--ibc-blue)';
                            $typeIcon = 'fa-file';
                            $typeLabel = $log['target_type'];
                            
                            switch ($log['target_type']) {
                                case 'inventory':
                                    $typeIcon = 'fa-box';
                                    $typeLabel = 'Inventar';
                                    break;
                                case 'news':
                                    $typeIcon = 'fa-newspaper';
                                    $typeLabel = 'News';
                                    break;
                                case 'alumni':
                                    $typeIcon = 'fa-user-graduate';
                                    $typeLabel = 'Alumni';
                                    break;
                            }
                            ?>
                            <div class="card-stack-item">
                                <div class="card-stack-row">
                                    <span class="card-stack-label"><i class="fas fa-clock me-2"></i>Zeitpunkt</span>
                                    <span class="card-stack-value">
                                        <small class="text-muted">
                                            <?php 
                                            $timestamp = new DateTime($log['timestamp']);
                                            echo $timestamp->format('d.m.Y H:i:s'); 
                                            ?>
                                        </small>
                                    </span>
                                </div>
                                <div class="card-stack-row">
                                    <span class="card-stack-label"><i class="fas fa-user me-2"></i>Benutzer</span>
                                    <span class="card-stack-value">
                                        <?php if (!empty($log['firstname']) && !empty($log['lastname'])): ?>
                                            <strong><?php echo htmlspecialchars($log['firstname'] . ' ' . $log['lastname'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Benutzer ID: <?php echo htmlspecialchars((string)$log['user_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="card-stack-row">
                                    <span class="card-stack-label"><i class="fas fa-bolt me-2"></i>Aktion</span>
                                    <span class="card-stack-value">
                                        <span class="badge <?php echo $actionBadgeClass; ?>">
                                            <i class="fas <?php echo $actionIcon; ?> me-1"></i><?php echo $actionLabel; ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="card-stack-row">
                                    <span class="card-stack-label"><i class="fas fa-folder me-2"></i>Typ</span>
                                    <span class="card-stack-value">
                                        <span class="badge" style="background-color: <?php echo $typeBadgeColor; ?>; color: white;">
                                            <i class="fas <?php echo $typeIcon; ?> me-1"></i><?php echo $typeLabel; ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="card-stack-row">
                                    <span class="card-stack-label"><i class="fas fa-file me-2"></i>Ziel</span>
                                    <span class="card-stack-value">
                                        <?php if (!empty($log['target_name'])): ?>
                                            <strong><?php echo htmlspecialchars($log['target_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">ID: <?php echo htmlspecialchars((string)$log['target_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Table View (Desktop) -->
                    <div class="table-responsive">
                        <table class="table table-hover table-card-stacking">
                            <thead>
                                <tr>
                                    <th style="color: var(--ibc-blue);">
                                        <i class="fas fa-clock me-2"></i>Zeitpunkt
                                    </th>
                                    <th style="color: var(--ibc-blue);">
                                        <i class="fas fa-user me-2"></i>Benutzer
                                    </th>
                                    <th style="color: var(--ibc-blue);">
                                        <i class="fas fa-bolt me-2"></i>Aktion
                                    </th>
                                    <th style="color: var(--ibc-blue);">
                                        <i class="fas fa-folder me-2"></i>Typ
                                    </th>
                                    <th style="color: var(--ibc-blue);">
                                        <i class="fas fa-file me-2"></i>Ziel
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($systemLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                $timestamp = new DateTime($log['timestamp']);
                                                echo $timestamp->format('d.m.Y H:i:s'); 
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['firstname']) && !empty($log['lastname'])): ?>
                                                <strong><?php echo htmlspecialchars($log['firstname'] . ' ' . $log['lastname'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Benutzer ID: <?php echo htmlspecialchars((string)$log['user_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $actionBadgeClass = 'bg-secondary';
                                            $actionIcon = 'fa-circle';
                                            $actionLabel = $log['action'];
                                            
                                            switch ($log['action']) {
                                                case 'create':
                                                    $actionBadgeClass = 'bg-success';
                                                    $actionIcon = 'fa-plus';
                                                    $actionLabel = 'Erstellt';
                                                    break;
                                                case 'update':
                                                    $actionBadgeClass = 'bg-info';
                                                    $actionIcon = 'fa-edit';
                                                    $actionLabel = 'Aktualisiert';
                                                    break;
                                                case 'delete':
                                                    $actionBadgeClass = 'bg-danger';
                                                    $actionIcon = 'fa-trash';
                                                    $actionLabel = 'Gelöscht';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $actionBadgeClass; ?>">
                                                <i class="fas <?php echo $actionIcon; ?> me-1"></i><?php echo $actionLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $typeBadgeColor = 'var(--ibc-blue)';
                                            $typeIcon = 'fa-file';
                                            $typeLabel = $log['target_type'];
                                            
                                            switch ($log['target_type']) {
                                                case 'inventory':
                                                    $typeIcon = 'fa-box';
                                                    $typeLabel = 'Inventar';
                                                    break;
                                                case 'news':
                                                    $typeIcon = 'fa-newspaper';
                                                    $typeLabel = 'News';
                                                    break;
                                                case 'alumni':
                                                    $typeIcon = 'fa-user-graduate';
                                                    $typeLabel = 'Alumni';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge" style="background-color: <?php echo $typeBadgeColor; ?>; color: white;">
                                                <i class="fas <?php echo $typeIcon; ?> me-1"></i><?php echo $typeLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['target_name'])): ?>
                                                <strong><?php echo htmlspecialchars($log['target_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">ID: <?php echo htmlspecialchars((string)$log['target_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <strong>Keine Einträge gefunden</strong><br>
                            Es wurden noch keine administrativen Aktionen protokolliert.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js from CDN with SRI for security -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" 
        integrity="sha384-UaIN1Ne/L8X/TgKxtH59IgL6FYvXYRwGkqVu/hUc6TsKLs+9j/0wVp8w4tqEUuM4" 
        crossorigin="anonymous"></script>

<!-- Initialize Member Growth Chart -->

