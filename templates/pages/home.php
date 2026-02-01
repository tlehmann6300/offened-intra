<?php
// Note: Classes are loaded by the router (index.php) before this template
// so News, Event, Project, Inventory, and Alumni classes are available

// Configuration constants
define('LOW_STOCK_THRESHOLD', 5);

// Initialize services
$newsService = null;
if (class_exists('NewsService')) {
    require_once BASE_DIR . '/src/NewsService.php';
    $newsService = new NewsService($pdo);
}

require_once BASE_DIR . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);

$news = new News($pdo, $newsService, $systemLogger);
$event = new Event($pdo, $newsService);
$project = new Project($pdo);
$inventory = new Inventory($pdo, $systemLogger);
$alumni = new Alumni($pdo, $systemLogger);

// Fetch data for dashboard teasers - only summary data, not full lists
$latestNews = $news->getLatest(3);
$nextEvent = $event->getNextEvent();
$latestProjects = $project->getLatest(3);

// Get inventory statistics for teaser - lightweight queries only
$inventoryStats = [
    'total_items' => 0,
    'low_stock' => 0
];
try {
    // Use direct COUNT queries instead of loading all items
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $inventoryStats['total_items'] = $result ? (int)$result['total'] : 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inventory WHERE status = 'active' AND quantity < ?");
    $stmt->execute([LOW_STOCK_THRESHOLD]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $inventoryStats['low_stock'] = $result ? (int)$result['total'] : 0;
} catch (Exception $e) {
    error_log("Error fetching inventory stats: " . $e->getMessage());
}

// Get alumni statistics for teaser
$alumniStats = $alumni->getStatistics();

// Get user's first name
$firstname = $auth->getFirstname() ?? 'Benutzer';
?>

<div class="container container-xl my-5">
    <!-- Personalized Welcome Header - Compact on Mobile -->
    <div class="hero-section-dashboard glass-card mb-4 p-3 p-md-5 text-center h-100">
        <h1 class="display-6 mb-2 mb-md-3">
            Willkommen, <span class="text-gradient-premium"><?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?></span>!
        </h1>
        <p class="lead text-muted mb-0">
            Ihr persönliches Dashboard im IBC-Intra
        </p>
    </div>

    <!-- Bento-Box Dashboard Layout -->
    <!-- Top Row: News Cards in 3-column grid on desktop -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 g-md-4 mb-4">
        <?php if (!empty($latestNews)): ?>
            <?php foreach ($latestNews as $newsItem): ?>
                <div class="col">
                    <div class="card glass-card bento-card h-100">
                        <?php if (!empty($newsItem['image_path'])): ?>
                            <div class="ratio ratio-16x9">
                                <img src="<?php echo htmlspecialchars($newsItem['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($newsItem['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                     style="object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <?php if (!empty($newsItem['category'])): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($newsItem['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($newsItem['created_at'])); ?>
                                </small>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($newsItem['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?php 
                                $content = strip_tags($newsItem['content']);
                                echo htmlspecialchars(mb_substr($content, 0, 100), ENT_QUOTES, 'UTF-8') . '...'; 
                                ?>
                            </p>
                            <a href="index.php?page=newsroom#news-<?php echo $newsItem['id']; ?>" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-arrow-right me-1"></i>Weiterlesen
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Keine aktuellen News verfügbar.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Second Row: Next Event and Latest Projects in 3-column grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 g-md-4 mb-4">
        <!-- Next Event Card -->
        <div class="col">
            <div class="card glass-card bento-card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Nächstes Event</h5>
                </div>
                <?php if ($nextEvent): ?>
                    <?php if (!empty($nextEvent['image_path'])): ?>
                        <div class="ratio ratio-16x9">
                            <img src="<?php echo htmlspecialchars($nextEvent['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                 style="object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title h6"><?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <p class="card-text small text-muted flex-grow-1">
                            <?php 
                            $desc = strip_tags($nextEvent['description']);
                            echo htmlspecialchars(mb_substr($desc, 0, 80), ENT_QUOTES, 'UTF-8') . '...'; 
                            ?>
                        </p>
                        <div class="mb-2">
                            <small class="text-muted d-block">
                                <i class="fas fa-calendar text-success me-1"></i>
                                <strong><?php echo date('d.m.Y, H:i', strtotime($nextEvent['event_date'])); ?> Uhr</strong>
                            </small>
                            <?php if (!empty($nextEvent['location'])): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-map-marker-alt text-success me-1"></i>
                                    <?php echo htmlspecialchars($nextEvent['location'], ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <a href="index.php?page=events" class="btn btn-sm btn-success mt-2">
                            <i class="fas fa-arrow-right me-1"></i>Zu den Events
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-body">
                        <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>Keine anstehenden Events</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Projects Cards -->
        <?php if (!empty($latestProjects)): ?>
            <?php 
            // Display up to 2 projects to fill the 3-column row
            $displayProjects = array_slice($latestProjects, 0, 2);
            foreach ($displayProjects as $projectItem): 
            ?>
                <div class="col">
                    <a href="index.php?page=projects#project-<?php echo $projectItem['id']; ?>" 
                       class="text-decoration-none">
                        <div class="card glass-card bento-card h-100 project-card">
                            <div class="card-header bg-info text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Projekt</h6>
                                    <span class="badge bg-light text-dark">
                                        <?php 
                                        $statusLabels = [
                                            'planning' => 'Planung',
                                            'active' => 'Aktiv',
                                            'on_hold' => 'Pausiert',
                                            'completed' => 'Abgeschlossen',
                                            'cancelled' => 'Abgesagt'
                                        ];
                                        echo $statusLabels[$projectItem['status']] ?? $projectItem['status']; 
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title h6"><?php echo htmlspecialchars($projectItem['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text text-muted small flex-grow-1">
                                    <?php 
                                    $desc = strip_tags($projectItem['description']);
                                    echo htmlspecialchars(mb_substr($desc, 0, 100), ENT_QUOTES, 'UTF-8') . '...'; 
                                    ?>
                                </p>
                                <?php if (!empty($projectItem['client'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($projectItem['client'], ENT_QUOTES, 'UTF-8'); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col">
                <div class="card glass-card bento-card h-100">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Projekte</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>Keine aktuellen Projekte</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Row: Inventory and Alumni Stats in 3-column grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 g-md-4">
        <!-- Inventory Teaser -->
        <div class="col">
            <div class="card glass-card bento-card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Inventar</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="mb-2">
                                <i class="fas fa-box fa-2x text-warning mb-2"></i>
                                <h3 class="mb-0"><?php echo $inventoryStats['total_items']; ?></h3>
                                <p class="text-muted mb-0 small">Gegenstände</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-2">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <h3 class="mb-0"><?php echo $inventoryStats['low_stock']; ?></h3>
                                <p class="text-muted mb-0 small">Niedriger Bestand</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">Verwalten Sie Ihr Inventar</small>
                    </div>
                    <?php if ($auth->hasFullAccess()): ?>
                        <div class="text-center mt-3 pt-3 border-top">
                            <a href="index.php?page=inventory_audit" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-history me-2"></i>Inventar-Audit
                            </a>
                        </div>
                    <?php endif; ?>
                    <a href="index.php?page=inventory" class="btn btn-sm btn-warning w-100 mt-2">
                        <i class="fas fa-arrow-right me-1"></i>Zur Verwaltung
                    </a>
                </div>
            </div>
        </div>

        <!-- Alumni Teaser -->
        <div class="col">
            <div class="card glass-card bento-card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Alumni</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="mb-2">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h3 class="mb-0"><?php echo $alumniStats['total_alumni'] ?? 0; ?></h3>
                                <p class="text-muted mb-0 small">Alumni-Mitglieder</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-2">
                                <i class="fas fa-briefcase fa-2x text-success mb-2"></i>
                                <h3 class="mb-0"><?php echo count($alumniStats['by_industry'] ?? []); ?></h3>
                                <p class="text-muted mb-0 small">Branchen</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">Vernetzen Sie sich mit Alumni</small>
                    </div>
                    <?php if ($auth->hasFullAccess()): ?>
                        <div class="text-center mt-3 pt-3 border-top">
                            <a href="index.php?page=alumni_validation" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-user-check me-2"></i>Alumni-Validierung
                            </a>
                        </div>
                    <?php endif; ?>
                    <a href="index.php?page=alumni" class="btn btn-sm btn-primary w-100 mt-2">
                        <i class="fas fa-arrow-right me-1"></i>Zum Netzwerk
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links / Actions Card (Third column in bottom row) -->
        <div class="col">
            <div class="card glass-card bento-card h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Schnellzugriff</h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-grid gap-2">
                        <a href="index.php?page=newsroom" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-newspaper me-2"></i>Alle News
                        </a>
                        <a href="index.php?page=events" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-calendar-alt me-2"></i>Alle Events
                        </a>
                        <a href="index.php?page=projects" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-briefcase me-2"></i>Alle Projekte
                        </a>
                        <?php if ($auth->can('edit_news') || $auth->can('edit_events') || $auth->can('edit_projects')): ?>
                            <hr class="my-2">
                            <a href="index.php?page=admin_dashboard" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-cog me-2"></i>Admin Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
