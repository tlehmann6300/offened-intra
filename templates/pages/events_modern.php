<?php
/**
 * Modern Events Overview Template
 * Features: Modern List View with Large Colored Date Blocks (Day/Month) on Left
 * Design: Clean, lots of white space, IBC-Blue accent color
 */

// Initialize Event class
require_once BASE_PATH . '/src/Event.php';
require_once BASE_PATH . '/src/NewsService.php';
$newsService = new NewsService($pdo);
$event = new Event($pdo, $newsService);

// Get filter parameters
$selectedYear = $_GET['year'] ?? 'all';
$selectedCategory = $_GET['category'] ?? 'all';

// Get all upcoming events - fetch once
$allEvents = $event->getUpcoming();

// Get unique years for filters from all events
$years = [];
foreach ($allEvents as $evt) {
    $year = date('Y', strtotime($evt['event_date']));
    if (!in_array($year, $years)) {
        $years[] = $year;
    }
}
sort($years);

// Filter events based on selection
$upcomingEvents = $allEvents;
if ($selectedYear !== 'all' || $selectedCategory !== 'all') {
    $upcomingEvents = array_filter($upcomingEvents, function($evt) use ($selectedYear, $selectedCategory) {
        if ($selectedYear !== 'all') {
            $eventYear = date('Y', strtotime($evt['event_date']));
            if ($eventYear != $selectedYear) {
                return false;
            }
        }
        // Category filtering could be added here if event categories are implemented
        return true;
    });
}

// Helper function to format date parts
function getDateParts($dateString) {
    $date = new DateTime($dateString);
    return [
        'day' => $date->format('d'),
        'month' => $date->format('M'),
        'month_full' => $date->format('F'),
        'year' => $date->format('Y'),
        'time' => $date->format('H:i'),
        'weekday' => $date->format('l')
    ];
}

// Helper function to get event category color (can be extended with actual categories)
function getEventColor($index) {
    $colors = ['#1a1f3a', '#3b8dd6', '#7c3aed', '#14b8a6', '#f59e0b'];
    return $colors[$index % count($colors)];
}
?>

<!-- Custom CSS for Events Overview -->
<style>
    /* IBC-Blue CSS Variable */
    :root {
        --ibc-blue-accent: #1a1f3a;
        --ibc-blue-light: #2d3561;
        --ibc-blue-lighter: #4d5578;
    }
    
    /* Clean, modern design with lots of white space */
    .events-modern-container {
        padding: 4rem 0;
        background-color: #fafbfc;
    }
    
    .events-header {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .events-header h1 {
        font-size: 3rem;
        font-weight: 700;
        color: var(--ibc-blue-accent);
        margin-bottom: 1rem;
    }
    
    .events-header p {
        font-size: 1.25rem;
        color: #6c757d;
    }
    
    /* Filter Buttons */
    .events-filters {
        background: white;
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        margin-bottom: 3rem;
    }
    
    .filter-group-label {
        font-weight: 600;
        color: var(--ibc-blue-accent);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .btn-filter {
        border: 2px solid #e9ecef;
        background: white;
        color: #495057;
        padding: 0.5rem 1.25rem;
        border-radius: 2rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-filter:hover {
        border-color: var(--ibc-blue-accent);
        color: var(--ibc-blue-accent);
        background: #f8f9fa;
    }
    
    .btn-filter.active {
        background: var(--ibc-blue-accent);
        border-color: var(--ibc-blue-accent);
        color: white;
    }
    
    /* Modern List View */
    .events-list {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    /* Event Item */
    .event-item {
        background: white;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: row;
    }
    
    .event-item:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        transform: translateY(-4px);
    }
    
    /* Date Block - Large Colored Block on Left */
    .event-date-block {
        min-width: 140px;
        max-width: 140px;
        padding: 2rem 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: white;
        position: relative;
    }
    
    .event-date-day {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .event-date-month {
        font-size: 1.25rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .event-date-year {
        font-size: 0.875rem;
        font-weight: 500;
        opacity: 0.9;
        margin-top: 0.25rem;
    }
    
    /* Event Content - Right Side */
    .event-content {
        flex: 1;
        padding: 2rem;
        display: flex;
        flex-direction: column;
    }
    
    .event-header-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .event-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--ibc-blue-accent);
        margin-bottom: 0.5rem;
    }
    
    .event-time {
        color: #6c757d;
        font-size: 1rem;
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .event-time i {
        margin-right: 0.5rem;
        color: var(--ibc-blue-accent);
    }
    
    .event-location {
        color: #6c757d;
        font-size: 1rem;
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .event-location i {
        margin-right: 0.5rem;
        color: var(--ibc-blue-accent);
    }
    
    .event-description {
        color: #495057;
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        flex: 1;
    }
    
    .event-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e9ecef;
    }
    
    .event-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    .event-meta-item {
        display: flex;
        align-items: center;
    }
    
    .event-meta-item i {
        margin-right: 0.375rem;
    }
    
    .btn-event-details {
        padding: 0.625rem 1.5rem;
        background: var(--ibc-blue-accent);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-event-details:hover {
        background: var(--ibc-blue-light);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 31, 58, 0.3);
    }
    
    /* Status Badge */
    .event-status-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(255, 255, 255, 0.95);
        color: #dc3545;
        padding: 0.375rem 0.75rem;
        border-radius: 1rem;
        font-weight: 600;
        font-size: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .event-status-badge.upcoming {
        color: #28a745;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }
    
    .empty-state-icon {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        color: var(--ibc-blue-accent);
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        color: #6c757d;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .events-header h1 {
            font-size: 2rem;
        }
        
        .event-item {
            flex-direction: column;
        }
        
        .event-date-block {
            min-width: 100%;
            max-width: 100%;
            padding: 1.5rem;
        }
        
        .event-content {
            padding: 1.5rem;
        }
        
        .event-title {
            font-size: 1.5rem;
        }
        
        .event-footer {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .events-filters {
            padding: 1.5rem;
        }
    }
</style>

<div class="events-modern-container">
    <div class="container">
        <!-- Header -->
        <div class="events-header">
            <h1>Event Übersicht</h1>
            <p>Entdecken Sie bevorstehende Events und Veranstaltungen</p>
        </div>
        
        <!-- Filters -->
        <div class="events-filters">
            <div class="row">
                <!-- Year Filter -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="filter-group-label">Nach Jahr filtern</div>
                    <div class="btn-group flex-wrap" role="group" aria-label="Jahr Filter">
                        <a href="?page=events_modern&year=all&category=<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>" 
                           class="btn btn-filter <?php echo $selectedYear === 'all' ? 'active' : ''; ?>">
                            Alle
                        </a>
                        <?php foreach ($years as $year): ?>
                            <a href="?page=events_modern&year=<?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>&category=<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="btn btn-filter <?php echo $selectedYear == $year ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Category Filter (Placeholder for future implementation) -->
                <div class="col-md-6">
                    <div class="filter-group-label">Nach Kategorie filtern</div>
                    <div class="btn-group flex-wrap" role="group" aria-label="Kategorie Filter">
                        <a href="?page=events_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=all" 
                           class="btn btn-filter <?php echo $selectedCategory === 'all' ? 'active' : ''; ?>">
                            Alle
                        </a>
                        <a href="?page=events_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=workshop" 
                           class="btn btn-filter <?php echo $selectedCategory === 'workshop' ? 'active' : ''; ?>">
                            Workshop
                        </a>
                        <a href="?page=events_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=networking" 
                           class="btn btn-filter <?php echo $selectedCategory === 'networking' ? 'active' : ''; ?>">
                            Networking
                        </a>
                        <a href="?page=events_modern&year=<?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?>&category=social" 
                           class="btn btn-filter <?php echo $selectedCategory === 'social' ? 'active' : ''; ?>">
                            Social
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Events List -->
        <?php if (empty($upcomingEvents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Keine Events gefunden</h3>
                <p>Für die ausgewählten Filter wurden keine Events gefunden.</p>
            </div>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($upcomingEvents as $index => $evt): ?>
                    <?php 
                    $dateParts = getDateParts($evt['event_date']);
                    $color = getEventColor($index);
                    $isToday = date('Y-m-d') === date('Y-m-d', strtotime($evt['event_date']));
                    ?>
                    
                    <div class="event-item">
                        <!-- Date Block -->
                        <div class="event-date-block" style="background: <?php echo $color; ?>;">
                            <?php if ($isToday): ?>
                                <div class="event-status-badge">Heute</div>
                            <?php else: ?>
                                <div class="event-status-badge upcoming">Anstehend</div>
                            <?php endif; ?>
                            
                            <div class="event-date-day"><?php echo $dateParts['day']; ?></div>
                            <div class="event-date-month"><?php echo strtoupper($dateParts['month']); ?></div>
                            <div class="event-date-year"><?php echo $dateParts['year']; ?></div>
                        </div>
                        
                        <!-- Event Content -->
                        <div class="event-content">
                            <div class="event-header-row">
                                <div>
                                    <h3 class="event-title">
                                        <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    
                                    <div class="event-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $dateParts['time']; ?> Uhr
                                        <span class="ms-2 text-muted">
                                            (<?php echo $dateParts['weekday']; ?>)
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($evt['location'])): ?>
                                        <div class="event-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($evt['location'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($evt['description'])): ?>
                                <div class="event-description">
                                    <?php 
                                    $descPreview = substr($evt['description'], 0, 200);
                                    if (strlen($evt['description']) > 200) {
                                        $descPreview .= '...';
                                    }
                                    echo htmlspecialchars($descPreview, ENT_QUOTES, 'UTF-8'); 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="event-footer">
                                <div class="event-meta">
                                    <?php if (!empty($evt['max_participants'])): ?>
                                        <div class="event-meta-item">
                                            <i class="fas fa-users"></i>
                                            Max. <?php echo htmlspecialchars($evt['max_participants'], ENT_QUOTES, 'UTF-8'); ?> Teilnehmer
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($evt['creator_firstname'])): ?>
                                        <div class="event-meta-item">
                                            <i class="fas fa-user"></i>
                                            Organisiert von <?php echo htmlspecialchars($evt['creator_firstname'] . ' ' . $evt['creator_lastname'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="?page=events&event_id=<?php echo $evt['id']; ?>" class="btn-event-details">
                                    <i class="fas fa-arrow-right me-2"></i>Details ansehen
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
