<?php
/**
 * Events Page
 * Display events with featured 'Next Event' countdown and grid of upcoming events
 * Features: Glassmorphism design, status badges, email notification toggle
 */

// Initialize Event class
require_once BASE_PATH . '/src/Event.php';
require_once BASE_PATH . '/src/NewsService.php';
require_once BASE_PATH . '/src/CalendarService.php';
require_once BASE_PATH . '/src/HelperService.php';
require_once BASE_PATH . '/src/MailService.php';

// Get database connections
$pdoContent = DatabaseManager::getContentConnection();
$pdoUser = DatabaseManager::getUserConnection();
$pdo = $pdoContent; // Legacy compatibility - most services use Content DB

$newsService = new NewsService($pdo);
$event = new Event($pdo, $newsService);
$calendarService = new CalendarService($pdo);
$mailService = new MailService();
$helperService = new HelperService($pdoContent, $pdoUser, $mailService);

// Get next event for countdown
$nextEvent = $event->getNextEvent();

// Get all upcoming events
$upcomingEvents = $event->getUpcoming();

// Check if user has opted in to email notifications
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;
$emailOptIn = false;

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT email_opt_in FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $emailOptIn = $user && $user['email_opt_in'] == 1;
    } catch (PDOException $e) {
        error_log("Error fetching user email opt-in status: " . $e->getMessage());
    }
}

// Handle AJAX requests for email notification toggle and helper registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_notifications') {
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
            exit;
        }
        
        try {
            // Toggle email_opt_in status
            $newStatus = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE users SET email_opt_in = ? WHERE id = ?");
            $success = $stmt->execute([$newStatus, $userId]);
            
            echo json_encode(['success' => $success, 'enabled' => $newStatus === 1]);
            exit;
        } catch (PDOException $e) {
            error_log("Error updating email opt-in status: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
            exit;
        }
    }
    
    if ($action === 'register_helper') {
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
            exit;
        }
        
        // Alumni cannot register for helper slots
        if ($userRole === 'alumni') {
            echo json_encode(['success' => false, 'message' => 'Alumni können sich nicht als Helfer anmelden']);
            exit;
        }
        
        $slotId = (int)($_POST['slot_id'] ?? 0);
        if ($slotId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Slot-ID']);
            exit;
        }
        
        $result = $helperService->registerForSlot($slotId, $userId);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'unregister_helper') {
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
            exit;
        }
        
        // Alumni cannot unregister (consistency with registration)
        if ($userRole === 'alumni') {
            echo json_encode(['success' => false, 'message' => 'Alumni können sich nicht abmelden']);
            exit;
        }
        
        $slotId = (int)($_POST['slot_id'] ?? 0);
        if ($slotId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Slot-ID']);
            exit;
        }
        
        $result = $helperService->unregisterFromSlot($slotId, $userId);
        echo json_encode($result);
        exit;
    }
}

// Helper function to check if date is today
function isToday($dateString) {
    $eventDate = new DateTime($dateString);
    $today = new DateTime();
    return $eventDate->format('Y-m-d') === $today->format('Y-m-d');
}

// Helper function to format date
function formatEventDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d.m.Y H:i');
}

// Helper function to format time only
function formatTime($dateString) {
    $date = new DateTime($dateString);
    return $date->format('H:i');
}

// Note: formatEventDateDisplay removed as it was unused and had dependency on intl extension

// Helper function to get status badge
function getEventStatus($dateString) {
    if (isToday($dateString)) {
        return ['label' => 'Heute', 'class' => 'bg-danger'];
    }
    return ['label' => 'Anstehend', 'class' => 'bg-success'];
}

// Helper function to get progress bar color class
function getProgressBarClass($slotsFilled, $slotsMax) {
    $isFull = $slotsFilled >= $slotsMax;
    
    if ($isFull) {
        return 'bg-danger';  // Red when full
    } elseif ($slotsFilled > 0) {
        return 'bg-primary';  // Blue when partially filled
    } else {
        return 'bg-success';  // Green when empty
    }
}
?>

<div class="container container-xl my-5">
    <!-- Page Title -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="display-4 mb-3">
                <span class="text-gradient-premium">Events</span>
            </h1>
            <p class="lead">Verpasse keine wichtigen Veranstaltungen und Events</p>
        </div>
    </div>

    <!-- Notification Center Toggle -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-bell me-2"></i>Notification Center
                        </h5>
                        <p class="text-muted small mb-0">Event- & News-Updates per E-Mail erhalten</p>
                    </div>
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            role="switch" 
                            id="emailNotificationToggle"
                            <?php echo $emailOptIn ? 'checked' : ''; ?>
                            style="cursor: pointer; width: 3rem; height: 1.5rem;"
                        >
                        <label class="form-check-label visually-hidden" for="emailNotificationToggle">
                            E-Mail-Benachrichtigungen aktivieren
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($nextEvent): ?>
    <!-- Next Event - Featured with Countdown -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="next-event-container position-relative rounded-5" style="overflow: hidden; min-height: 31.25rem;">
                <!-- Background Image -->
                <?php if (!empty($nextEvent['image_path'])): ?>
                    <img 
                        src="/<?php echo htmlspecialchars($nextEvent['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                        alt="<?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        class="position-absolute top-0 start-0 w-100 h-100"
                        style="object-fit: cover; z-index: 1;"
                    >
                <?php else: ?>
                    <div 
                        class="position-absolute top-0 start-0 w-100 h-100 bg-gradient-animated"
                        style="z-index: 1;"
                    ></div>
                <?php endif; ?>
                
                <!-- Glassmorphism Overlay -->
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 2;"></div>
                
                <!-- Content -->
                <div class="position-relative p-4 p-md-5" style="z-index: 3; min-height: 31.25rem; display: flex; flex-direction: column; justify-content: center;">
                    <span class="badge <?php echo getEventStatus($nextEvent['event_date'])['class']; ?> mb-3" style="width: fit-content; font-size: 1rem; padding: 0.5rem 1rem;">
                        <i class="fas fa-star me-1"></i>Next Event - <?php echo getEventStatus($nextEvent['event_date'])['label']; ?>
                    </span>
                    
                    <h2 class="display-4 mb-3">
                        <?php echo htmlspecialchars($nextEvent['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                    
                    <!-- Countdown Timer -->
                    <div class="countdown-timer mb-4">
                        <div class="row g-3">
                            <div class="col-3 col-md-auto">
                                <div class="countdown-box glass-card p-3 text-center h-100">
                                    <div class="countdown-value display-5 fw-bold text-primary" id="countdown-days">0</div>
                                    <div class="countdown-label small text-muted">Tage</div>
                                </div>
                            </div>
                            <div class="col-3 col-md-auto">
                                <div class="countdown-box glass-card p-3 text-center h-100">
                                    <div class="countdown-value display-5 fw-bold text-primary" id="countdown-hours">0</div>
                                    <div class="countdown-label small text-muted">Stunden</div>
                                </div>
                            </div>
                            <div class="col-3 col-md-auto">
                                <div class="countdown-box glass-card p-3 text-center h-100">
                                    <div class="countdown-value display-5 fw-bold text-primary" id="countdown-minutes">0</div>
                                    <div class="countdown-label small text-muted">Minuten</div>
                                </div>
                            </div>
                            <div class="col-3 col-md-auto">
                                <div class="countdown-box glass-card p-3 text-center h-100">
                                    <div class="countdown-value display-5 fw-bold text-primary" id="countdown-seconds">0</div>
                                    <div class="countdown-label small text-muted">Sekunden</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="lead mb-3">
                        <?php echo nl2br(htmlspecialchars($nextEvent['description'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                    
                    <div class="event-details mb-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt text-primary fa-lg me-3"></i>
                                    <div>
                                        <small class="text-muted d-block">Datum & Uhrzeit</small>
                                        <strong><?php echo formatEventDate($nextEvent['event_date']); ?> Uhr</strong>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($nextEvent['location'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-map-marker-alt text-primary fa-lg me-3"></i>
                                    <div>
                                        <small class="text-muted d-block">Ort</small>
                                        <strong><?php echo htmlspecialchars($nextEvent['location'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($nextEvent['max_participants'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-users text-primary fa-lg me-3"></i>
                                    <div>
                                        <small class="text-muted d-block">Max. Teilnehmer</small>
                                        <strong><?php echo htmlspecialchars((string)$nextEvent['max_participants'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($nextEvent['creator_firstname']) || !empty($nextEvent['creator_lastname'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user text-primary fa-lg me-3"></i>
                                    <div>
                                        <small class="text-muted d-block">Organisiert von</small>
                                        <strong><?php echo htmlspecialchars(trim($nextEvent['creator_firstname'] . ' ' . $nextEvent['creator_lastname']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Calendar Action Buttons -->
                    <div class="calendar-actions mt-4">
                        <div class="d-flex flex-wrap gap-3">
                            <a href="/generate_ics.php?event_id=<?php echo $nextEvent['id']; ?>" 
                               class="btn btn-primary btn-lg btn-export-calendar"
                               download>
                                <i class="fas fa-calendar-plus me-2"></i>In Kalender speichern
                            </a>
                            <a href="<?php 
                                echo htmlspecialchars($calendarService->generateGoogleCalendarUrl($nextEvent['id']), ENT_QUOTES, 'UTF-8');
                            ?>" 
                               class="btn btn-outline-primary btn-lg btn-export-calendar"
                               target="_blank"
                               rel="noopener noreferrer">
                                <i class="fab fa-google me-2"></i>Google Calendar
                            </a>
                        </div>
                    </div>
                    
                    <?php 
                    // Get helper slots for next event
                    $helperSlots = $helperService->getHelperSlotsByEvent($nextEvent['id']);
                    if (!empty($helperSlots)): 
                    ?>
                    <!-- Helper Recruiting Section -->
                    <div class="helper-recruiting mt-5 pt-4" style="border-top: 2px solid rgba(var(--rgb-ibc-green), 0.2);">
                        <h4 class="mb-4">
                            <i class="fas fa-hands-helping text-primary me-2"></i>
                            Helfende Hände gesucht
                        </h4>
                        <p class="text-muted mb-4">Hilf uns dabei, dieses Event unvergesslich zu machen! Melde dich für einen oder mehrere Helfer-Slots an.</p>
                        
                        <div class="helper-slots d-flex flex-nowrap overflow-auto pb-3">
                            <?php foreach ($helperSlots as $slot): 
                                $isUserRegistered = $helperService->isUserRegistered($slot['id'], $userId);
                                $percentFilled = $slot['slots_max'] > 0 ? ($slot['slots_filled'] / $slot['slots_max']) * 100 : 0;
                                $isFull = $slot['slots_filled'] >= $slot['slots_max'];
                                $isAlumni = $userRole === 'alumni';
                            ?>
                            <div class="helper-slot-card glass-card p-4 mb-3 me-3 flex-shrink-0 h-100" style="min-width: 320px;" data-slot-id="<?php echo $slot['id']; ?>" data-event-id="<?php echo $nextEvent['id']; ?>">
                                <div class="d-flex flex-column h-100">
                                    <div class="mb-3">
                                        <h5 class="mb-2">
                                            <i class="fas fa-tasks text-primary me-2"></i>
                                            <?php echo htmlspecialchars($slot['task_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h5>
                                        <div class="text-muted small">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatTime($slot['start_time']); ?> - <?php echo formatTime($slot['end_time']); ?> Uhr
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="progress-info mb-2">
                                            <span class="text-muted small">
                                                <span class="slots-filled"><?php echo $slot['slots_filled']; ?></span> / 
                                                <span class="slots-max"><?php echo $slot['slots_max']; ?></span> Plätze belegt
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar <?php echo getProgressBarClass($slot['slots_filled'], $slot['slots_max']); ?> slot-progress" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentFilled; ?>%"
                                                 aria-valuenow="<?php echo $slot['slots_filled']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo $slot['slots_max']; ?>">
                                                <span class="fw-bold"><?php echo round($percentFilled); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-auto">
                                        <div class="d-flex flex-column gap-2">
                                            <?php if ($isUserRegistered): ?>
                                                <button class="btn btn-danger btn-helper-unregister w-100" 
                                                        data-slot-id="<?php echo $slot['id']; ?>"
                                                        <?php echo $isAlumni ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-user-minus me-2"></i>Abmelden
                                                </button>
                                                <a href="/generate_ics.php?slot_id=<?php echo $slot['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm btn-export-calendar w-100"
                                                   download>
                                                    <i class="fas fa-calendar-plus me-2"></i>Slot-Export
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-helper-register w-100" 
                                                        data-slot-id="<?php echo $slot['id']; ?>"
                                                        <?php echo ($isFull || $isAlumni) ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-user-plus me-2"></i>
                                                    <?php echo $isFull ? 'Voll' : 'Anmelden'; ?>
                                                </button>
                                                <?php if ($isAlumni): ?>
                                                    <small class="d-block text-muted text-center mt-1">Alumni können sich nicht anmelden</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Events Grid -->
    <?php 
    // Filter out the next event from the upcoming events list
    $otherEvents = array_filter($upcomingEvents, function($evt) use ($nextEvent) {
        return !$nextEvent || $evt['id'] !== $nextEvent['id'];
    });
    ?>
    
    <?php if (!empty($otherEvents)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="mb-4">
                <i class="fas fa-calendar-check me-2"></i>Weitere anstehende Events
            </h3>
        </div>
    </div>
    
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-5" id="eventsGrid">
        <?php foreach ($otherEvents as $evt): ?>
        <div class="col">
            <div class="card h-100 event-card">
                <?php if (!empty($evt['image_path'])): ?>
                    <img 
                        src="/<?php echo htmlspecialchars($evt['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                        class="card-img-top" 
                        alt="<?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        style="height: 12.5rem; object-fit: cover;"
                    >
                <?php else: ?>
                    <div class="bg-gradient-animated" style="height: 12.5rem;"></div>
                <?php endif; ?>
                
                <!-- Status Badge -->
                <span class="badge <?php echo getEventStatus($evt['event_date'])['class']; ?> position-absolute top-0 end-0 m-3" style="font-size: 0.85rem;">
                    <?php echo getEventStatus($evt['event_date'])['label']; ?>
                </span>
                
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-3">
                        <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                    
                    <p class="card-text text-muted flex-grow-1 mb-3">
                        <?php 
                        $desc = strip_tags($evt['description']);
                        echo htmlspecialchars(strlen($desc) > 120 ? substr($desc, 0, 117) . '...' : $desc, ENT_QUOTES, 'UTF-8'); 
                        ?>
                    </p>
                    
                    <div class="event-meta mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            <small><?php echo formatEventDate($evt['event_date']); ?> Uhr</small>
                        </div>
                        <?php if (!empty($evt['location'])): ?>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <small><?php echo htmlspecialchars($evt['location'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($evt['max_participants'])): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-users text-primary me-2"></i>
                            <small>Max. <?php echo htmlspecialchars((string)$evt['max_participants'], ENT_QUOTES, 'UTF-8'); ?> Teilnehmer</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Calendar Action Buttons -->
                    <div class="calendar-actions mt-3">
                        <div class="d-flex flex-column gap-2">
                            <a href="/generate_ics.php?event_id=<?php echo $evt['id']; ?>" 
                               class="btn btn-primary btn-sm w-100 btn-export-calendar"
                               download>
                                <i class="fas fa-calendar-plus me-2"></i>In Kalender speichern
                            </a>
                            <a href="<?php 
                                echo htmlspecialchars($calendarService->generateGoogleCalendarUrl($evt['id']), ENT_QUOTES, 'UTF-8');
                            ?>" 
                               class="btn btn-outline-primary btn-sm w-100 btn-export-calendar"
                               target="_blank"
                               rel="noopener noreferrer">
                                <i class="fab fa-google me-2"></i>Google Calendar
                            </a>
                        </div>
                    </div>
                    
                    <?php 
                    // Get helper slots for this event
                    $evtHelperSlots = $helperService->getHelperSlotsByEvent($evt['id']);
                    if (!empty($evtHelperSlots)): 
                    ?>
                    <!-- Helper Slots Summary -->
                    <div class="helper-slots-summary mt-3 pt-3" style="border-top: 1px solid rgba(var(--rgb-ibc-green), 0.2);">
                        <h6 class="mb-2">
                            <i class="fas fa-hands-helping text-primary me-1"></i>
                            Helfer gesucht!
                        </h6>
                        <?php 
                        $totalSlots = 0;
                        $totalFilled = 0;
                        foreach ($evtHelperSlots as $slot) {
                            $totalSlots += $slot['slots_max'];
                            $totalFilled += $slot['slots_filled'];
                        }
                        $percentFilled = $totalSlots > 0 ? ($totalFilled / $totalSlots) * 100 : 0;
                        ?>
                        <div class="small text-muted mb-2">
                            <?php echo $totalFilled; ?> / <?php echo $totalSlots; ?> Plätze belegt
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar <?php echo getProgressBarClass($totalFilled, $totalSlots); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $percentFilled; ?>%"
                                 aria-valuenow="<?php echo $totalFilled; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="<?php echo $totalSlots; ?>">
                            </div>
                        </div>
                        <small class="text-muted mt-1 d-block"><?php echo count($evtHelperSlots); ?> Slot<?php echo count($evtHelperSlots) > 1 ? 's' : ''; ?> verfügbar</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php elseif (!$nextEvent): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info text-center glass-card p-5 h-100">
                <i class="fas fa-calendar-times fa-3x mb-3 text-primary"></i>
                <h4>Keine anstehenden Events</h4>
                <p class="mb-0">Zurzeit sind keine Events geplant. Schauen Sie später wieder vorbei!</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Countdown and Notification Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper function to get CSRF token
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : null;
    }
    
    // Clear event notification badge on page load
    const csrfToken = getCsrfToken();
    const headers = {
        'Content-Type': 'application/json'
    };
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    
    fetch('/api/clear_event_notif.php', {
        method: 'POST',
        headers: headers
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide the badge in the navigation
            const eventsBadge = document.getElementById('eventsBadge');
            if (eventsBadge) {
                eventsBadge.style.display = 'none';
            }
            
            // Hide the notification badge on the bell icon
            const notificationBadge = document.getElementById('notificationBadge');
            if (notificationBadge) {
                notificationBadge.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('Error clearing event notification:', error);
    });
    
    // Confetti animation throttling to prevent excessive resource usage
    let lastConfettiTime = 0;
    const confettiCooldown = 3000; // 3 seconds cooldown
    
    function triggerConfetti() {
        const now = Date.now();
        if (now - lastConfettiTime >= confettiCooldown && window.confetti && typeof window.confetti === 'function') {
            window.confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });
            lastConfettiTime = now;
        }
    }
    
    <?php if ($nextEvent): ?>
    // Countdown Timer with validation
    <?php 
    // Use DateTime for more robust date parsing
    try {
        $eventDateTime = new DateTime($nextEvent['event_date']);
        $eventTimestamp = $eventDateTime->getTimestamp();
    } catch (Exception $e) {
        error_log("Invalid event date: " . $nextEvent['event_date'] . " - " . $e->getMessage());
        // Default to 24 hours from now
        $eventDateTime = new DateTime();
        $eventDateTime->modify('+1 day');
        $eventTimestamp = $eventDateTime->getTimestamp();
    }
    ?>
    const eventDate = new Date('<?php echo $eventDateTime->format('c'); ?>').getTime();
    
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = eventDate - now;
        
        if (distance < 0) {
            document.getElementById('countdown-days').textContent = '0';
            document.getElementById('countdown-hours').textContent = '0';
            document.getElementById('countdown-minutes').textContent = '0';
            document.getElementById('countdown-seconds').textContent = '0';
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        document.getElementById('countdown-days').textContent = days;
        document.getElementById('countdown-hours').textContent = hours;
        document.getElementById('countdown-minutes').textContent = minutes;
        document.getElementById('countdown-seconds').textContent = seconds;
    }
    
    // Update countdown immediately
    updateCountdown();
    
    // Update countdown every second
    setInterval(updateCountdown, 1000);
    <?php endif; ?>
    
    // Email Notification Toggle
    const notificationToggle = document.getElementById('emailNotificationToggle');
    
    if (notificationToggle) {
        notificationToggle.addEventListener('change', function() {
            const isEnabled = this.checked;
            
            fetch('index.php?page=events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_notifications&enabled=' + isEnabled
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const message = isEnabled 
                        ? 'E-Mail-Benachrichtigungen aktiviert' 
                        : 'E-Mail-Benachrichtigungen deaktiviert';
                    
                    // Create toast notification
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-success position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + message;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                } else {
                    // Revert toggle state on failure
                    notificationToggle.checked = !isEnabled;
                    
                    // Show error message
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-danger position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Fehler: ' + (data.message || 'Unbekannter Fehler');
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert toggle state on error
                notificationToggle.checked = !isEnabled;
                
                // Show error message
                const toast = document.createElement('div');
                toast.className = 'alert alert-danger position-fixed bottom-0 end-0 m-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Verbindungsfehler';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        });
    }
    
    // Helper Registration - One-Click Sign-up
    // Function to show toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed bottom-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.style.minWidth = '250px';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        toast.innerHTML = `<i class="fas fa-${icon} me-2"></i>${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Function to update slot progress bar
    function updateSlotProgress(slotCard, slotsFilled, slotsMax) {
        const percentFilled = slotsMax > 0 ? (slotsFilled / slotsMax) * 100 : 0;
        const isFull = slotsFilled >= slotsMax;
        
        // Update text
        slotCard.querySelector('.slots-filled').textContent = slotsFilled;
        slotCard.querySelector('.slots-max').textContent = slotsMax;
        
        // Update progress bar
        const progressBar = slotCard.querySelector('.slot-progress');
        progressBar.style.width = percentFilled + '%';
        progressBar.setAttribute('aria-valuenow', slotsFilled);
        progressBar.setAttribute('aria-valuemax', slotsMax);
        progressBar.querySelector('span').textContent = Math.round(percentFilled) + '%';
        
        // Update color based on status
        // Remove all color classes first
        progressBar.classList.remove('bg-success', 'bg-danger', 'bg-primary');
        
        if (isFull) {
            // Full slots are red
            progressBar.classList.add('bg-danger');
        } else if (slotsFilled > 0) {
            // Partially filled slots are blue
            progressBar.classList.add('bg-primary');
        } else {
            // Empty slots are green
            progressBar.classList.add('bg-success');
        }
    }
    
    // Function to add register listener
    function addRegisterListener(button) {
        button.addEventListener('click', function() {
            const slotId = this.getAttribute('data-slot-id');
            const slotCard = this.closest('.helper-slot-card');
            
            // Disable button during request
            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Lädt...';
            
            fetch('index.php?page=events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=register_helper&slot_id=' + slotId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Trigger confetti animation (throttled)
                    triggerConfetti();
                    
                    // Update progress bar
                    if (data.slots_filled !== undefined && data.slots_max !== undefined) {
                        updateSlotProgress(slotCard, data.slots_filled, data.slots_max);
                    }
                    
                    // Replace register button with unregister button and export button
                    const buttonContainer = this.closest('.d-flex.flex-column');
                    const eventId = slotCard.getAttribute('data-event-id') || '';
                    if (!eventId) {
                        console.error('Event ID not found for slot card');
                        showToast('Fehler: Event-ID nicht gefunden', 'danger');
                        return;
                    }
                    
                    buttonContainer.innerHTML = `
                        <button class="btn btn-danger btn-helper-unregister w-100" data-slot-id="${slotId}">
                            <i class="fas fa-user-minus me-2"></i>Abmelden
                        </button>
                        <a href="/generate_ics.php?slot_id=${slotId}" 
                           class="btn btn-outline-primary btn-sm btn-export-calendar w-100"
                           download>
                            <i class="fas fa-calendar-plus me-2"></i>Slot-Export
                        </a>
                    `;
                    
                    // Add event listener to new unregister button
                    const unregisterBtn = buttonContainer.querySelector('.btn-helper-unregister');
                    addUnregisterListener(unregisterBtn);
                    
                    // Add event listener to new export button
                    const exportBtn = buttonContainer.querySelector('.btn-export-calendar');
                    if (exportBtn) {
                        exportBtn.addEventListener('click', function(e) {
                            const icon = this.querySelector('i');
                            const originalIconClass = icon ? icon.className : '';
                            
                            if (icon) {
                                icon.className = 'fas fa-spinner fa-spin me-2';
                            }
                            
                            this.style.pointerEvents = 'none';
                            this.style.opacity = '0.7';
                            
                            setTimeout(() => {
                                if (icon && originalIconClass) {
                                    icon.className = originalIconClass;
                                }
                                this.style.pointerEvents = '';
                                this.style.opacity = '';
                            }, 2000);
                        });
                    }
                    
                } else {
                    showToast(data.message, 'danger');
                    this.disabled = false;
                    this.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Verbindungsfehler', 'danger');
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    }
    
    // Helper Registration Buttons
    document.querySelectorAll('.btn-helper-register').forEach(button => {
        addRegisterListener(button);
    });
    
    // Function to add unregister listener
    function addUnregisterListener(button) {
        button.addEventListener('click', function() {
            const slotId = this.getAttribute('data-slot-id');
            const slotCard = this.closest('.helper-slot-card');
            
            // Disable button during request
            this.disabled = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Lädt...';
            
            fetch('index.php?page=events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=unregister_helper&slot_id=' + slotId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update progress bar
                    if (data.slots_filled !== undefined && data.slots_max !== undefined) {
                        updateSlotProgress(slotCard, data.slots_filled, data.slots_max);
                    }
                    
                    // Replace unregister button with register button
                    const buttonContainer = this.closest('.d-flex.flex-column');
                    const isFull = data.slots_filled >= data.slots_max;
                    buttonContainer.innerHTML = `
                        <button class="btn btn-primary btn-helper-register w-100" data-slot-id="${slotId}" ${isFull ? 'disabled' : ''}>
                            <i class="fas fa-user-plus me-2"></i>${isFull ? 'Voll' : 'Anmelden'}
                        </button>
                    `;
                    
                    // Add event listener to new register button if not full
                    if (!isFull) {
                        const registerBtn = buttonContainer.querySelector('.btn-helper-register');
                        if (registerBtn) {
                            addRegisterListener(registerBtn);
                        }
                    }
                    
                } else {
                    showToast(data.message, 'danger');
                    this.disabled = false;
                    this.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Verbindungsfehler', 'danger');
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    }
    
    // Helper Unregistration Buttons
    document.querySelectorAll('.btn-helper-unregister').forEach(button => {
        addUnregisterListener(button);
    });
    
    // Calendar Export Buttons - Add loading animation
    document.querySelectorAll('.btn-export-calendar').forEach(button => {
        button.addEventListener('click', function(e) {
            // Don't prevent default - we want the download/navigation to proceed
            
            // Get original icon
            const icon = this.querySelector('i');
            const originalIconClass = icon ? icon.className : '';
            
            // Add loading state
            if (icon) {
                icon.className = 'fas fa-spinner fa-spin me-2';
            }
            
            // Disable button temporarily to prevent double-clicks
            this.style.pointerEvents = 'none';
            this.style.opacity = '0.7';
            
            // Reset after a short delay (the download will start in parallel)
            setTimeout(() => {
                if (icon && originalIconClass) {
                    icon.className = originalIconClass;
                }
                this.style.pointerEvents = '';
                this.style.opacity = '';
            }, 2000);
        });
    });
});
</script>

<style>
/* Additional styles for events page */
.next-event-container {
    transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);
}

.next-event-container:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.countdown-box {
    min-width: 80px;
    transition: transform var(--transition-smooth);
}

.countdown-box:hover {
    transform: scale(1.05);
}

.countdown-value {
    line-height: 1.2;
}

.event-card {
    position: relative;
}

.event-card:hover {
    transform: translateY(-10px) scale(1.005);
    box-shadow: var(--shadow-hover);
}

/* Switch styling for better visibility and accessibility */
.form-check-input:checked {
    background-color: var(--ibc-green);
    border-color: var(--ibc-green);
    position: relative;
}

.form-check-input:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 1rem;
    font-weight: bold;
    margin-left: 0.5rem; /* Offset to the right side of the switch */
}

.form-check-input:focus {
    border-color: var(--ibc-green-accessible);
    box-shadow: 0 0 0 0.25rem rgba(var(--rgb-ibc-green), 0.25);
}

/* Responsive countdown boxes */
@media (max-width: 767.98px) {
    .countdown-box {
        min-width: 60px;
    }
    
    .countdown-value {
        font-size: 1.5rem !important;
    }
    
    .countdown-label {
        font-size: 0.7rem;
    }
}

/* Helper Recruiting Styles */
.helper-recruiting {
    animation: fadeIn 0.5s ease-in;
}

.helper-slot-card {
    transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);
}

.helper-slot-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.progress {
    border-radius: 15px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
    font-size: 0.875rem;
}

.btn-helper-register,
.btn-helper-unregister {
    transition: all 0.3s ease;
    min-width: 120px;
}

.btn-helper-register:hover:not(:disabled) {
    transform: scale(1.05);
}

.btn-helper-unregister:hover:not(:disabled) {
    transform: scale(1.05);
}

.helper-slots-summary {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive helper slots */
@media (max-width: 991.98px) {
    .helper-slot-card .col-lg-5,
    .helper-slot-card .col-lg-4,
    .helper-slot-card .col-lg-3 {
        text-align: center;
    }
    
    .btn-helper-register,
    .btn-helper-unregister {
        width: 100%;
    }
}

</style>
