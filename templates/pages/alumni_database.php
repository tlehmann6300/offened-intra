<?php
/**
 * Alumni Database Page
 * Displays alumni profiles with glassmorphism design
 * Real-time search functionality for name, company, and position
 */

// Configuration constants
define('ALUMNI_BIO_PREVIEW_LENGTH_DB', 120);
define('ALUMNI_BADGE_DAYS_THRESHOLD', 7); // Days to show "Neu" or "Aktualisiert" badges

// Initialize Alumni class
require_once BASE_PATH . '/src/Alumni.php';
require_once BASE_PATH . '/src/SystemLogger.php';
$systemLogger = new SystemLogger($pdo);
$alumni = new Alumni($pdo, $systemLogger);

// Handle AJAX search requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    // Handle search action (read-only, no CSRF needed)
    if ($action === 'search') {
        // Check if user is logged in
        if (!$auth->isLoggedIn()) {
            $response = ['success' => false, 'message' => 'Nicht angemeldet'];
            echo json_encode($response);
            exit;
        }
        
        $search = $_POST['search'] ?? null;
        $graduationYear = $_POST['graduation_year'] ?? null;
        
        // Build filters array
        $filters = [];
        if (!empty($graduationYear) && is_numeric($graduationYear)) {
            $filters['graduation_year'] = (int)$graduationYear;
        }
        
        $profiles = $alumni->getAll($search, $filters);
        
        $response = [
            'success' => true,
            'profiles' => $profiles,
            'count' => count($profiles)
        ];
        
        echo json_encode($response);
        exit;
    }
    
    // Validate CSRF token for state-changing actions only
    // Note: 'get' is read-only but included for security as it returns sensitive profile data
    $statefulActions = ['get', 'update'];
    if (in_array($action, $statefulActions, true)) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$auth->verifyCsrfToken($csrfToken)) {
            $response = ['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte laden Sie die Seite neu.'];
            echo json_encode($response);
            exit;
        }
    }
    
    // Handle get action - fetch profile data for editing
    if ($action === 'get') {
        $profileId = (int)($_POST['id'] ?? 0);
        
        if ($profileId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige Profil-ID'];
            echo json_encode($response);
            exit;
        }
        
        $profile = $alumni->getById($profileId);
        
        if (!$profile) {
            $response = ['success' => false, 'message' => 'Profil nicht gefunden'];
            echo json_encode($response);
            exit;
        }
        
        // Check permissions
        $currentUserId = $auth->getUserId();
        $isOwnProfile = ($currentUserId && (int)$profile['created_by'] === (int)$currentUserId);
        $canEditAny = $auth->can('edit_alumni');
        
        if (!$canEditAny && !$isOwnProfile) {
            $response = ['success' => false, 'message' => 'Keine Berechtigung'];
            echo json_encode($response);
            exit;
        }
        
        $response = ['success' => true, 'profile' => $profile];
        echo json_encode($response);
        exit;
    }
    
    // Handle update action
    if ($action === 'update') {
        $profileId = (int)($_POST['id'] ?? 0);
        
        if ($profileId <= 0) {
            $response = ['success' => false, 'message' => 'Ungültige Profil-ID'];
            echo json_encode($response);
            exit;
        }
        
        // Get existing profile to check permissions
        $existingProfile = $alumni->getById($profileId);
        if (!$existingProfile) {
            $response = ['success' => false, 'message' => 'Profil nicht gefunden'];
            echo json_encode($response);
            exit;
        }
        
        // Check permissions
        $currentUserId = $auth->getUserId();
        $isOwnProfile = ($currentUserId && (int)$existingProfile['created_by'] === (int)$currentUserId);
        $canEditAny = $auth->can('edit_alumni');
        
        if (!$canEditAny && !$isOwnProfile) {
            $response = ['success' => false, 'message' => 'Keine Berechtigung'];
            echo json_encode($response);
            exit;
        }
        
        // Validate required fields
        if (empty($_POST['firstname']) || empty($_POST['lastname'])) {
            $response = ['success' => false, 'message' => 'Vor- und Nachname sind erforderlich'];
            echo json_encode($response);
            exit;
        }
        
        // Prepare data for update
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
            'linkedin_url' => $_POST['linkedin_url'] ?? null,
            'is_published' => isset($_POST['is_published']) && $_POST['is_published'] === '1' ? 1 : 0
        ];
        
        // Handle image upload if provided
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $alumni->handleImageUpload($_FILES['profile_picture'], $profileId);
            
            if ($imagePath) {
                // Delete old profile picture if exists
                if (!empty($existingProfile['profile_picture'])) {
                    $alumni->deleteOldProfilePicture($existingProfile['profile_picture']);
                }
                
                $data['profile_picture'] = $imagePath;
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Hochladen des Bildes'];
                echo json_encode($response);
                exit;
            }
        }
        
        // Update profile
        $success = $alumni->update($profileId, $data, $currentUserId);
        
        if ($success) {
            $response = ['success' => true, 'message' => 'Profil erfolgreich aktualisiert'];
        } else {
            $response = ['success' => false, 'message' => 'Fehler beim Aktualisieren des Profils'];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Unknown action
    echo json_encode($response);
    exit;
}

// Get search query and filters
$search = $_GET['search'] ?? null;
$graduationYearFilter = $_GET['graduation_year'] ?? null;

// Build filters array for Alumni::getAll()
$filters = [];
if (!empty($graduationYearFilter) && is_numeric($graduationYearFilter)) {
    $filters['graduation_year'] = (int)$graduationYearFilter;
}

// Get all alumni profiles
$profiles = $alumni->getAll($search, $filters);
$stats = $alumni->getStatistics();

// Check if user can edit alumni profiles
// Note: 'edit_alumni' permission is granted via wildcard ('*') to admin and vorstand roles
// This allows them to edit any alumni profile, while regular users can only edit their own
$canEdit = $auth->can('edit_alumni');

// Get current user ID for profile ownership check
$currentUserId = $auth->getUserId();

// Get CSRF token for AJAX requests
$csrfToken = $auth->getCsrfToken();
?>

<div class="container container-xl my-5" data-csrf-token="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Header -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="ibc-heading">
                <span class="word-wrapper"><span class="word">Alumni</span></span>
                <span class="word-wrapper"><span class="word text-gradient">Datenbank</span></span>
            </h1>
            <p class="ibc-lead">
                Durchsuchen Sie unser Alumni-Netzwerk und vernetzen Sie sich
            </p>
        </div>
    </div>

    <!-- Search Bar and Filters -->
    <div class="row mb-4">
        <div class="col-md-8 offset-md-2">
            <div class="mb-3">
                <div class="input-group search-form">
                    <input type="text" class="form-control" id="alumniDatabaseSearch" 
                           placeholder="Nach Name, Firma oder Position suchen..." 
                           value="<?php echo htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           aria-label="Suche nach Alumni">
                    <span class="input-group-text bg-white border-start-0" id="alumniSearchSpinner" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <label for="graduationYearFilter" class="form-label mb-0 text-muted small">Filter:</label>
                <select class="form-select form-select-sm" id="graduationYearFilter" style="width: auto; min-width: 9.375rem;" aria-label="Nach Abschlussjahr filtern">
                    <option value="">Alle Jahrgänge</option>
                    <?php 
                    // Get available graduation years from stats
                    if (!empty($stats['by_year'])): 
                        foreach ($stats['by_year'] as $yearData): 
                            $year = $yearData['graduation_year'];
                            $count = $yearData['count'];
                            $selected = ($graduationYearFilter == $year) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                            Jahrgang <?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $count; ?>)
                        </option>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </select>
                <small class="text-muted ms-auto">Die Ergebnisse werden automatisch aktualisiert</small>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-5">
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
                    <p class="text-muted mb-0">Branchen</p>
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
                    <p class="text-muted mb-0">Jahrgänge</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alumni Profile Cards Grid -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4" id="alumniDatabaseGrid" role="region" aria-live="polite" aria-label="Alumni-Datenbank">
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
                <?php 
                    // Check if current user owns this profile
                    // Note: created_by field indicates profile ownership in the alumni system
                    // Each user can create/manage their own alumni profile
                    $isOwnProfile = ($currentUserId && (int)$profile['created_by'] === (int)$currentUserId);
                    
                    // Calculate if profile is new or recently updated
                    $isNew = false;
                    $isRecentlyUpdated = false;
                    $currentTime = time();
                    $secondsPerDay = 86400; // 60 * 60 * 24
                    
                    if (!empty($profile['created_at'])) {
                        $createdTime = strtotime($profile['created_at']);
                        $daysSinceCreated = ($currentTime - $createdTime) / $secondsPerDay;
                        
                        if ($daysSinceCreated <= ALUMNI_BADGE_DAYS_THRESHOLD) {
                            $isNew = true;
                        }
                    }
                    
                    if (!$isNew && !empty($profile['updated_at']) && !empty($profile['created_at'])) {
                        $updatedTime = strtotime($profile['updated_at']);
                        $createdTime = strtotime($profile['created_at']);
                        $daysSinceUpdated = ($currentTime - $updatedTime) / $secondsPerDay;
                        
                        // Only show "Aktualisiert" if it was updated after creation and within threshold
                        if ($updatedTime > $createdTime && $daysSinceUpdated <= ALUMNI_BADGE_DAYS_THRESHOLD) {
                            $isRecentlyUpdated = true;
                        }
                    }
                ?>
                <div class="col" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3) * 100; ?>">
                    <div class="card glass-card h-100 position-relative" data-profile-id="<?php echo $profile['id']; ?>">
                        <!-- Status Badge -->
                        <?php if ($isNew): ?>
                            <div class="position-absolute top-0 end-0 m-3" style="z-index: 10;">
                                <span class="badge bg-success" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">
                                    <i class="fas fa-star me-1"></i>Neu
                                </span>
                            </div>
                        <?php elseif ($isRecentlyUpdated): ?>
                            <div class="position-absolute top-0 end-0 m-3" style="z-index: 10;">
                                <span class="badge bg-info" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">
                                    <i class="fas fa-sync-alt me-1"></i>Aktualisiert
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-4">
                            <!-- Profile Header with Avatar -->
                            <div class="d-flex align-items-start mb-3">
                                <!-- Profile Picture / Avatar -->
                                <div class="flex-shrink-0 me-3">
                                    <?php if (!empty($profile['profile_picture'])): ?>
                                        <img src="/<?php echo htmlspecialchars($profile['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" 
                                             alt="<?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname'], ENT_QUOTES, 'UTF-8'); ?>" 
                                             class="alumni-avatar-container" 
                                             style="width: 5rem; height: 5rem; border-radius: 50%; object-fit: cover; box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);">
                                    <?php else: ?>
                                        <div class="alumni-avatar-container" style="width: 5rem; height: 5rem; border-radius: 50%; background: linear-gradient(135deg, var(--ibc-blue) 0%, var(--ibc-green) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);">
                                            <i class="fas fa-user fa-2x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Profile Info -->
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1 fw-bold" style="color: var(--ibc-blue);">
                                        <?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>
                                    <?php if (!empty($profile['graduation_year'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-graduation-cap me-1"></i>Jahrgang <?php echo htmlspecialchars($profile['graduation_year'], ENT_QUOTES, 'UTF-8'); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Edit Button (for admins or own profile) -->
                                <?php if ($canEdit || $isOwnProfile): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aktionen">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="#" data-action="edit-alumni-profile" data-profile-id="<?php echo $profile['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>
                                                    <?php echo $isOwnProfile ? 'Profil bearbeiten' : 'Bearbeiten'; ?>
                                                </a>
                                            </li>
                                            <?php if ($canEdit): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" data-action="delete-alumni-profile" data-profile-id="<?php echo $profile['id']; ?>">
                                                        <i class="fas fa-trash me-2"></i>Löschen
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Position and Company -->
                            <?php if (!empty($profile['position']) || !empty($profile['company'])): ?>
                                <div class="mb-3">
                                    <?php if (!empty($profile['position'])): ?>
                                        <p class="mb-1 fw-semibold" style="color: var(--ibc-text-primary);">
                                            <i class="fas fa-briefcase me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($profile['position'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['company'])): ?>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-building me-2"></i>
                                            <?php echo htmlspecialchars($profile['company'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Bio Preview -->
                            <?php if (!empty($profile['bio'])): ?>
                                <p class="card-text text-muted small mb-3" style="line-height: 1.6;">
                                    <?php 
                                    $bioPreview = strlen($profile['bio']) > ALUMNI_BIO_PREVIEW_LENGTH_DB 
                                        ? substr($profile['bio'], 0, ALUMNI_BIO_PREVIEW_LENGTH_DB) . '...' 
                                        : $profile['bio'];
                                    echo htmlspecialchars($bioPreview, ENT_QUOTES, 'UTF-8'); 
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Badges for Industry and Location -->
                            <?php if (!empty($profile['industry']) || !empty($profile['location'])): ?>
                                <div class="mb-3">
                                    <?php if (!empty($profile['industry'])): ?>
                                        <span class="badge bg-info me-2">
                                            <i class="fas fa-industry me-1"></i><?php echo htmlspecialchars($profile['industry'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['location'])): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($profile['location'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Social Contact Links -->
                            <div class="d-flex flex-wrap gap-2 pt-2 border-top alumni-contact-buttons">
                                <?php if (!empty($profile['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       class="btn btn-sm btn-outline-primary flex-fill" 
                                       title="E-Mail senden">
                                        <i class="fas fa-envelope"></i>
                                        <span class="d-none d-sm-inline ms-1">E-Mail</span>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($profile['phone'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       class="btn btn-sm btn-outline-primary flex-fill" 
                                       title="Anrufen">
                                        <i class="fas fa-phone"></i>
                                        <span class="d-none d-sm-inline ms-1">Anrufen</span>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['linkedin_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['linkedin_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-primary flex-fill" 
                                       title="LinkedIn-Profil anzeigen">
                                        <i class="fab fa-linkedin"></i>
                                        <span class="d-none d-sm-inline ms-1">LinkedIn</span>
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

<!-- Alumni Edit Modal -->
<div class="modal fade" id="alumniEditModal" tabindex="-1" aria-labelledby="alumniEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="alumniEditModalLabel">Alumni-Profil bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="alumniEditForm" class="needs-validation" novalidate enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="editProfileId" name="id">
                    
                    <!-- Profile Picture Upload -->
                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold">Profilbild</label>
                        <div class="d-flex flex-column align-items-center">
                            <div id="profilePicturePreview" class="mb-3" style="width: 9.375rem; height: 9.375rem; border-radius: 50%; background: linear-gradient(135deg, var(--ibc-blue) 0%, var(--ibc-green) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1); overflow: hidden;">
                                <i class="fas fa-user fa-4x text-white"></i>
                            </div>
                            <input type="file" class="form-control" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="form-text text-muted mt-2">
                                JPG, PNG, GIF oder WebP. Max. 5MB. Wird automatisch zugeschnitten und als WebP gespeichert.
                            </small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editFirstname" name="firstname" placeholder="Vorname" required>
                                <label for="editFirstname">Vorname *</label>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen Vornamen ein.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editLastname" name="lastname" placeholder="Nachname" required>
                                <label for="editLastname">Nachname *</label>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen Nachnamen ein.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="editEmail" name="email" placeholder="E-Mail">
                                <label for="editEmail">E-Mail</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="editPhone" name="phone" placeholder="Telefon">
                                <label for="editPhone">Telefon</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editCompany" name="company" placeholder="Unternehmen">
                                <label for="editCompany">Unternehmen</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editPosition" name="position" placeholder="Position">
                                <label for="editPosition">Position</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editIndustry" name="industry" placeholder="Branche">
                                <label for="editIndustry">Branche</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="editLocation" name="location" placeholder="Standort">
                                <label for="editLocation">Standort</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="editGraduationYear" name="graduation_year" placeholder="Jahrgang" min="1900" max="2100">
                                <label for="editGraduationYear">Jahrgang</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-floating">
                            <input type="url" class="form-control" id="editLinkedIn" name="linkedin_url" placeholder="LinkedIn URL">
                            <label for="editLinkedIn">LinkedIn URL</label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="form-text text-muted">z.B. https://www.linkedin.com/in/username</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="fab fa-linkedin me-1"></i>
                                LinkedIn-Import
                                <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="API-Anbindung in Vorbereitung"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="editBio" name="bio" placeholder="Biografie" style="height: 6.25rem"></textarea>
                        <label for="editBio">Biografie</label>
                        <small class="form-text text-muted">Kurze Beschreibung der beruflichen Laufbahn</small>
                    </div>
                    
                    <!-- Visibility Checkbox -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="editIsPublished" name="is_published" value="1">
                        <label class="form-check-label" for="editIsPublished">
                            <strong>Mein Profil für aktive Mitglieder sichtbar machen</strong>
                            <br>
                            <small class="text-muted">Wenn aktiviert, ist Ihr Profil für alle aktiven Mitglieder sichtbar</small>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary" id="alumniEditSubmitBtn">
                        <span id="editSubmitBtnText">Speichern</span>
                        <span id="editSubmitSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button id="alumniBackToTop" class="btn btn-primary back-to-top-btn" 
        onclick="scrollToTop()" 
        title="Nach oben scrollen"
        aria-label="Nach oben scrollen"
        style="display: none;">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Custom CSS for Alumni Database -->
<style>
.alumni-avatar-container {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.glass-card:hover .alumni-avatar-container {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15) !important;
}

/* Enhance card hover effect */
#alumniDatabaseGrid .glass-card {
    transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
}

#alumniDatabaseGrid .glass-card:hover {
    transform: translateY(-8px) scale(1.02);
}
</style>

<!-- Set JavaScript context variables for AJAX search -->
<script>
    window.currentUserId = <?php echo json_encode($currentUserId); ?>;
    window.canEditAlumni = <?php echo json_encode($canEdit); ?>;
    window.ALUMNI_BIO_PREVIEW_LENGTH = <?php echo ALUMNI_BIO_PREVIEW_LENGTH_DB; ?>;
</script>

<!-- JavaScript for Alumni Database will be added to main.js -->
