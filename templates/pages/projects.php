<?php
/**
 * Projects Page
 * Display active and planning projects
 */

// Initialize Project class
require_once BASE_PATH . '/src/Project.php';
$projectService = new Project($pdo);

// Get all projects (planning and active)
$projects = $projectService->getAll(null, 100, 0);

// Helper function to map status to German display text
function getProjectStatusDisplay($status) {
    $statusMap = [
        'planning' => 'Bewerbung möglich',
        'active' => 'In Bearbeitung',
        'on_hold' => 'Pausiert',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen'
    ];
    return $statusMap[$status] ?? 'Unbekannt';
}

// Helper function to calculate duration from dates
function calculateDuration($startDate, $endDate) {
    if (!$startDate || !$endDate) {
        return 'Noch nicht festgelegt';
    }
    
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        // Ensure end date is after start date
        if ($end < $start) {
            return 'Ungültige Daten';
        }
        
        $diff = $start->diff($end);
        
        if ($diff->y > 0) {
            return $diff->y . ' Jahr' . ($diff->y !== 1 ? 'e' : '');
        } elseif ($diff->m > 0) {
            return $diff->m . ' Monat' . ($diff->m !== 1 ? 'e' : '');
        } else {
            return $diff->d . ' Tag' . ($diff->d !== 1 ? 'e' : '');
        }
    } catch (Exception $e) {
        error_log("Error calculating project duration: " . $e->getMessage());
        return 'Noch nicht festgelegt';
    }
}
?>

<div class="container container-xl my-5">
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Unsere</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Projekte</span></span>
            </h1>
            <p class="ibc-lead">
                Entdecken Sie spannende Projekte und engagieren Sie sich in der Junior Enterprise
            </p>
        </div>
    </div>

    <?php if (!empty($projects)): ?>
    <!-- Projects Grid -->
    <div class="row g-4">
        <?php 
        foreach ($projects as $project): 
            $displayStatus = getProjectStatusDisplay($project['status']);
            $duration = calculateDuration($project['start_date'], $project['end_date']);
            $teamSizeDisplay = $project['team_size'] ? $project['team_size'] . ' Mitglieder' : 'Noch nicht festgelegt';
        ?>
        <div class="col-md-6 col-lg-6">
            <div class="card glass-card h-100 p-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h3 class="card-title h4"><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="badge bg-<?php echo ($project['status'] === 'planning') ? 'success' : 'info'; ?>">
                            <?php echo htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    
                    <p class="card-text text-muted">
                        <?php echo htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    
                    <div class="mt-3 mb-3">
                        <p class="mb-2">
                            <i class="fas fa-clock me-2 text-primary"></i>
                            <strong>Dauer:</strong> <?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-users me-2 text-primary"></i>
                            <strong>Team:</strong> <?php echo htmlspecialchars($teamSizeDisplay, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                    
                    <?php if ($project['status'] === 'planning'): ?>
                        <?php if ($auth->can('apply_projects')): ?>
                            <button class="btn btn-primary w-100 mt-3" onclick="applyToProject('<?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?>')">
                                <i class="fas fa-paper-plane me-2"></i>Jetzt bewerben
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info mt-3 mb-0" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Als Alumni können Sie sich nicht auf Projekte bewerben.</small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 mt-3" disabled>
                            <i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                Zurzeit sind keine Projekte verfügbar.
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Info Box for Alumni -->
    <?php if ($auth->getUserRole() === 'alumni'): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="alert alert-primary" role="alert">
                <h4 class="alert-heading"><i class="fas fa-graduation-cap me-2"></i>Alumni-Status</h4>
                <p>Als Alumni haben Sie Zugriff auf alle Projektinformationen, können sich jedoch nicht auf aktive Projekte bewerben.</p>
                <hr>
                <p class="mb-0">Sie möchten als Mentor oder Berater tätig werden? Kontaktieren Sie den Vorstand für weitere Möglichkeiten.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript functions are now in /assets/js/main.js -->

<style>
/* Additional project-specific styles */
.glass-card {
    transition: all 0.3s ease;
}

.glass-card:hover {
    transform: translateY(-5px);
}

.badge {
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}
</style>
