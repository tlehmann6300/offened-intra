<?php
/**
 * Alumni Network Page
 * Displays alumni profiles and networking opportunities
 * Provides search and filter functionality for alumni connections
 */

// Configuration constants
define('ALUMNI_BIO_PREVIEW_LENGTH', 150);

// Initialize Alumni class
require_once BASE_PATH . '/src/Alumni.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);
$alumni = new Alumni($pdo, $systemLogger);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    // Validate CSRF token for state-changing actions
    $statefulActions = ['create', 'update', 'delete'];
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
            // AJAX search with filter support
            if (!$auth->isLoggedIn()) {
                $response = ['success' => false, 'message' => 'Nicht angemeldet'];
                break;
            }
            
            $search = $_POST['search'] ?? null;
            $filters = [
                'graduation_year' => $_POST['graduation_year'] ?? '',
                'industry' => $_POST['industry'] ?? 'all',
                'location' => $_POST['location'] ?? 'all'
            ];
            
            $profiles = $alumni->getAll($search, $filters);
            $response = [
                'success' => true,
                'profiles' => $profiles,
                'count' => count($profiles)
            ];
            break;
            
        case 'create':
            if (!$auth->can('edit_alumni')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            // Validate required fields
            if (empty($_POST['firstname']) || empty($_POST['lastname'])) {
                $response = ['success' => false, 'message' => 'Vor- und Nachname sind erforderlich'];
                break;
            }
            
            $data = [
                'firstname' => $_POST['firstname'] ?? '',
                'lastname' => $_POST['lastname'] ?? '',
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'company' => $_POST['company'] ?? null,
                'position' => $_POST['position'] ?? null,
                'industry' => $_POST['industry'] ?? null,
                'location' => $_POST['location'] ?? null,
                'graduation_year' => !empty($_POST['graduation_year']) ? (int)$_POST['graduation_year'] : null,
                'bio' => $_POST['bio'] ?? null,
                'linkedin_url' => $_POST['linkedin_url'] ?? null
            ];
            
            $profileId = $alumni->create($data, $auth->getUserId());
            if ($profileId) {
                $response = ['success' => true, 'message' => 'Alumni-Profil erfolgreich erstellt', 'id' => $profileId];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Erstellen des Profils'];
            }
            break;
            
        case 'update':
            if (!$auth->can('edit_alumni')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $profileId = (int)($_POST['id'] ?? 0);
            
            // Validate profile ID
            if ($profileId <= 0) {
                $response = ['success' => false, 'message' => 'Ungültige Profil-ID'];
                break;
            }
            
            // Validate required fields
            if (empty($_POST['firstname']) || empty($_POST['lastname'])) {
                $response = ['success' => false, 'message' => 'Vor- und Nachname sind erforderlich'];
                break;
            }
            
            $data = [
                'firstname' => $_POST['firstname'] ?? '',
                'lastname' => $_POST['lastname'] ?? '',
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'company' => $_POST['company'] ?? null,
                'position' => $_POST['position'] ?? null,
                'industry' => $_POST['industry'] ?? null,
                'location' => $_POST['location'] ?? null,
                'graduation_year' => !empty($_POST['graduation_year']) ? (int)$_POST['graduation_year'] : null,
                'bio' => $_POST['bio'] ?? null,
                'linkedin_url' => $_POST['linkedin_url'] ?? null
            ];
            
            if ($alumni->update($profileId, $data, $auth->getUserId())) {
                $response = ['success' => true, 'message' => 'Alumni-Profil erfolgreich aktualisiert'];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Aktualisieren des Profils'];
            }
            break;
            
        case 'delete':
            if (!$auth->can('edit_alumni')) {
                $response = ['success' => false, 'message' => 'Keine Berechtigung'];
                break;
            }
            
            $profileId = (int)($_POST['id'] ?? 0);
            
            // Validate profile ID
            if ($profileId <= 0) {
                $response = ['success' => false, 'message' => 'Ungültige Profil-ID'];
                break;
            }
            
            if ($alumni->delete($profileId, $auth->getUserId())) {
                $response = ['success' => true, 'message' => 'Alumni-Profil erfolgreich gelöscht'];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Löschen des Profils'];
            }
            break;
            
        case 'get':
            $profileId = (int)($_POST['id'] ?? 0);
            $profile = $alumni->getById($profileId);
            if ($profile) {
                $response = ['success' => true, 'profile' => $profile];
            } else {
                $response = ['success' => false, 'message' => 'Profil nicht gefunden'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get search query
$search = $_GET['search'] ?? null;

// Get all alumni profiles
$profiles = $alumni->getAll($search);
$stats = $alumni->getStatistics();

// Check if user can edit alumni
$canEdit = $auth->can('edit_alumni');

// Get CSRF token for AJAX requests
$csrfToken = $auth->getCsrfToken();
?>

<div class="container container-xl my-5" data-csrf-token="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Header -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Alumni</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Netzwerk</span></span>
            </h1>
            <p class="ibc-lead">
                Vernetzen Sie sich mit ehemaligen Mitgliedern der Junior Enterprise
            </p>
        </div>
    </div>

    <!-- Search Bar and Add Button -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="input-group search-form">
                <input type="text" class="form-control" id="alumniSearchInput" 
                       placeholder="Nach Name, Unternehmen oder Position suchen..." 
                       value="<?php echo htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       aria-label="Suche nach Alumni">
                <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display: none;">
                    <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                </span>
            </div>
            <small class="text-muted">Die Ergebnisse werden während der Eingabe automatisch aktualisiert</small>
        </div>
        <?php if ($canEdit): ?>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#alumniModal" data-action="create-alumni">
                    <i class="fas fa-plus me-2"></i>Neues Profil
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-users text-primary"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo $stats['total_alumni']; ?></h3>
                    <p class="text-muted mb-0">Alumni-Mitglieder</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-briefcase text-success"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo count($stats['by_industry']); ?></h3>
                    <p class="text-muted mb-0">Branchen vertreten</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card glass-card text-center p-4 h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-graduation-cap text-info"></i>
                    </h5>
                    <h3 class="mb-0"><?php echo count($stats['by_year']); ?></h3>
                    <p class="text-muted mb-0">Jahrgänge aktiv</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alumni Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="alumniGrid" role="region" aria-live="polite" aria-label="Alumni-Liste">
        <?php if (empty($profiles)): ?>
            <div class="col-12">
                <div class="card glass-card text-center py-5 px-4 h-100">
                    <div class="card-body">
                        <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                        <h4>Keine Alumni-Profile gefunden</h4>
                        <p class="text-muted">
                            <?php if ($search): ?>
                                Ihre Suche ergab keine Treffer. Versuchen Sie andere Suchbegriffe.
                            <?php else: ?>
                                Das Alumni-Netzwerk wird derzeit aufgebaut. Schauen Sie bald wieder vorbei!
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($profiles as $index => $profile): ?>
                <div class="col" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3) * 100; ?>">
                    <div class="card glass-card alumni-card h-100 p-4" data-profile-id="<?php echo $profile['id']; ?>">
                        <!-- Profile Header -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="alumni-avatar me-3">
                                <i class="fas fa-user fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1 fw-bold">
                                    <?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                                </h5>
                                <?php if (!empty($profile['graduation_year'])): ?>
                                    <small class="text-muted">Jahrgang <?php echo htmlspecialchars($profile['graduation_year'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if ($canEdit): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" data-action="edit-alumni" data-profile-id="<?php echo $profile['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Bearbeiten
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" data-action="delete-alumni" data-profile-id="<?php echo $profile['id']; ?>">
                                                <i class="fas fa-trash me-2"></i>Löschen
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="card-body p-0">
                            <?php if (!empty($profile['company']) || !empty($profile['position'])): ?>
                                <div class="mb-3">
                                    <?php if (!empty($profile['position'])): ?>
                                        <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($profile['position'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['company'])): ?>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($profile['company'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile['bio'])): ?>
                                <p class="card-text text-muted small mb-3">
                                    <?php echo htmlspecialchars(substr($profile['bio'], 0, ALUMNI_BIO_PREVIEW_LENGTH), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (strlen($profile['bio']) > ALUMNI_BIO_PREVIEW_LENGTH): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <div class="mb-3">
                                <?php if (!empty($profile['industry'])): ?>
                                    <span class="badge bg-info me-2">
                                        <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($profile['industry'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['location'])): ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($profile['location'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Contact Links -->
                            <div class="d-flex gap-2">
                                <?php if (!empty($profile['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       class="btn btn-sm btn-outline-primary" title="E-Mail senden">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['linkedin_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['linkedin_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       target="_blank" rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-primary" title="LinkedIn-Profil">
                                        <i class="fab fa-linkedin"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Alumni Modal (Create/Edit) -->
<?php if ($canEdit): ?>
<div class="modal fade" id="alumniModal" tabindex="-1" aria-labelledby="alumniModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="alumniModalLabel">Neues Alumni-Profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="alumniForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="profileId" name="id">
                    <input type="hidden" id="formAction" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profileFirstname" name="firstname" placeholder="Vorname" required>
                                <label for="profileFirstname">Vorname *</label>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen Vornamen ein.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profileLastname" name="lastname" placeholder="Nachname" required>
                                <label for="profileLastname">Nachname *</label>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen Nachnamen ein.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="profileEmail" name="email" placeholder="E-Mail">
                                <label for="profileEmail">E-Mail</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="profilePhone" name="phone" placeholder="Telefon">
                                <label for="profilePhone">Telefon</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profileCompany" name="company" placeholder="Unternehmen">
                                <label for="profileCompany">Unternehmen</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profilePosition" name="position" placeholder="Position">
                                <label for="profilePosition">Position</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profileIndustry" name="industry" placeholder="Branche">
                                <label for="profileIndustry">Branche</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="profileLocation" name="location" placeholder="Standort">
                                <label for="profileLocation">Standort</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="profileGraduationYear" name="graduation_year" placeholder="Jahrgang" min="1900" max="2100">
                                <label for="profileGraduationYear">Jahrgang</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="url" class="form-control" id="profileLinkedIn" name="linkedin_url" placeholder="LinkedIn URL">
                        <label for="profileLinkedIn">LinkedIn URL</label>
                        <small class="form-text">z.B. https://www.linkedin.com/in/username</small>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="profileBio" name="bio" placeholder="Biografie" style="height: 100px"></textarea>
                        <label for="profileBio">Biografie</label>
                        <small class="form-text">Kurze Beschreibung der beruflichen Laufbahn</small>
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

<!-- Back to Top Button -->
<button id="alumniBackToTop" class="btn btn-primary back-to-top-btn" 
        onclick="scrollToTop()" 
        title="Nach oben scrollen"
        aria-label="Nach oben scrollen">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Note: JavaScript functions will be added to /assets/js/main.js for alumni functionality -->
