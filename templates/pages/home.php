<?php
// Note: Classes are loaded by the router (index.php) before this template
// so News, Event, and Project classes are available

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

// Fetch data for dashboard
$latestNews = $news->getLatest(3);
$nextEvent = $event->getNextEvent();
$latestProjects = $project->getLatest(3);

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
    <div class="row g-3 g-md-4">
        <!-- Left Column: News (Large Tile) -->
        <div class="col-12 col-lg-8">
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h3 mb-0"><i class="fas fa-newspaper me-2 text-primary"></i>Aktuelles</h2>
                    <a href="index.php?page=newsroom" class="btn btn-sm btn-outline-primary">Alle News</a>
                </div>
                
                <div class="bento-tile-large">
                    <?php if (!empty($latestNews)): ?>
                        <div class="row row-cols-1 g-3">
                            <?php foreach ($latestNews as $newsItem): ?>
                                <div class="col">
                                    <div class="card glass-card bento-card h-100">
                                        <div class="row g-0">
                                            <?php if (!empty($newsItem['image_path'])): ?>
                                                <div class="col-md-4">
                                                    <div class="ratio ratio-16x9 ratio-md-1x1">
                                                        <img src="<?php echo htmlspecialchars($newsItem['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                             class="img-fluid rounded-start" 
                                                             alt="<?php echo htmlspecialchars($newsItem['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                             style="object-fit: cover; height: 100%;">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="<?php echo !empty($newsItem['image_path']) ? 'col-md-8' : 'col-12'; ?>">
                                                <div class="card-body">
                                                    <?php if (!empty($newsItem['category'])): ?>
                                                        <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($newsItem['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <h5 class="card-title"><?php echo htmlspecialchars($newsItem['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                                    <p class="card-text text-muted">
                                                        <?php 
                                                        $content = strip_tags($newsItem['content']);
                                                        echo htmlspecialchars(mb_substr($content, 0, 120), ENT_QUOTES, 'UTF-8') . '...'; 
                                                        ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('d.m.Y', strtotime($newsItem['created_at'])); ?>
                                                        </small>
                                                        <a href="index.php?page=newsroom#news-<?php echo $newsItem['id']; ?>" class="btn btn-sm btn-primary">
                                                            Lesen
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Keine aktuellen News verfügbar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Event and Projects (Stacked Tiles) -->
        <div class="col-12 col-lg-4">
            <!-- Nächstes Event Section -->
            <div class="mb-3 mb-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0"><i class="fas fa-calendar-alt me-2 text-success"></i>Nächstes Event</h2>
                    <a href="index.php?page=events" class="btn btn-sm btn-outline-success">Alle Events</a>
                </div>
                
                <div class="bento-tile-small">
                    <?php if ($nextEvent): ?>
                        <div class="card glass-card bento-card h-100">
                            <?php if (!empty($nextEvent['image_path'])): ?>
                                <div class="ratio ratio-16x9">
                                    <img src="<?php echo htmlspecialchars($nextEvent['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         style="object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-3">
                                <h5 class="card-title h6 mb-2"><?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text small text-muted mb-3">
                                    <?php 
                                    $desc = strip_tags($nextEvent['description']);
                                    echo htmlspecialchars(mb_substr($desc, 0, 100), ENT_QUOTES, 'UTF-8') . '...'; 
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
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>Keine Events
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Neue Projekte Section -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0"><i class="fas fa-briefcase me-2 text-info"></i>Neue Projekte</h2>
                    <a href="index.php?page=projects" class="btn btn-sm btn-outline-info">Alle Projekte</a>
                </div>
                
                <div class="bento-tile-small">
                    <?php if (!empty($latestProjects)): ?>
                        <div class="row row-cols-1 g-2">
                            <?php foreach ($latestProjects as $projectItem): ?>
                                <div class="col">
                                    <a href="index.php?page=projects#project-<?php echo $projectItem['id']; ?>" 
                                       class="text-decoration-none">
                                        <div class="card glass-card bento-card h-100 project-card">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                    <h6 class="mb-0 flex-grow-1"><?php echo htmlspecialchars($projectItem['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                    <span class="badge bg-<?php echo $projectItem['status'] === 'active' ? 'success' : 'primary'; ?> flex-shrink-0">
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
                                                <p class="mb-2 text-muted small">
                                                    <?php 
                                                    $desc = strip_tags($projectItem['description']);
                                                    echo htmlspecialchars(mb_substr($desc, 0, 100), ENT_QUOTES, 'UTF-8') . '...'; 
                                                    ?>
                                                </p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if (!empty($projectItem['client'])): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-building me-1"></i>
                                                            <?php echo htmlspecialchars($projectItem['client'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>Keine Projekte
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
