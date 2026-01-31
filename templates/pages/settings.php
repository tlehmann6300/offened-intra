<?php
/**
 * Settings Page
 * Allows users to manage their notification preferences and profile visibility
 */

$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unbekannte Aktion'];
    
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$auth->verifyCsrfToken($csrfToken)) {
        $response = ['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte laden Sie die Seite neu.'];
        echo json_encode($response);
        exit;
    }
    
    switch ($action) {
        case 'update_notification_preferences':
            // Handle project notification preference
            $preferences = [
                'notify_projects' => isset($_POST['notify_projects']) ? 1 : 0
            ];
            
            if ($auth->updateNotificationPreferences($userId, $preferences)) {
                $response = ['success' => true, 'message' => 'Benachrichtigungseinstellungen erfolgreich gespeichert'];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Speichern der Benachrichtigungseinstellungen'];
            }
            break;
            
        case 'update_alumni_visibility':
            if ($userRole !== 'alumni') {
                $response = ['success' => false, 'message' => 'Diese Funktion ist nur für Alumni verfügbar'];
                break;
            }
            
            $isVisible = isset($_POST['is_visible']) && $_POST['is_visible'] === '1';
            
            if ($auth->updateAlumniVisibility($userId, $isVisible)) {
                $response = ['success' => true, 'message' => 'Sichtbarkeitseinstellung erfolgreich gespeichert'];
            } else {
                $response = ['success' => false, 'message' => 'Fehler beim Speichern der Sichtbarkeitseinstellung'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Get user settings for display
$userSettings = $auth->getUserSettings($userId);
$notificationPreferences = $auth->getNotificationPreferences($userId);

$csrfToken = $auth->getCsrfToken();
if (!$csrfToken) {
    $csrfToken = $auth->generateCsrfToken();
}
?>

<div class="container container-xl my-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 mb-2">
                <i class="fas fa-cog me-2"></i>Einstellungen
            </h1>
            <p class="text-muted">Verwalten Sie Ihre Benachrichtigungen und Profil-Sichtbarkeit</p>
        </div>
    </div>

    <!-- Notification Preferences Section -->
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Benachrichtigungseinstellungen
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Wählen Sie aus, über welche Themen Sie E-Mail-Benachrichtigungen erhalten möchten.
                    </p>
                    
                    <form id="notificationPreferencesForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="update_notification_preferences">
                        
                        <!-- Projects Notifications (Projekt-Alerts) -->
                        <div class="form-check form-switch mb-3">
                            <input 
                                class="form-check-input notification-toggle" 
                                type="checkbox" 
                                role="switch" 
                                id="notifyProjects" 
                                name="notify_projects"
                                <?php echo ($notificationPreferences && isset($notificationPreferences['notify_projects']) && $notificationPreferences['notify_projects']) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="notifyProjects">
                                <strong>Projekt-Alerts per E-Mail</strong>
                                <br>
                                <small class="text-muted">Erhalten Sie Updates über neue Projekt-Ausschreibungen und Projekt-Updates</small>
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Display & Accessibility Section -->
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Anzeige & Barrierefreiheit
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Passen Sie die Darstellung für optimale Lesbarkeit an.
                    </p>
                    
                    <!-- High-Contrast Mode Toggle -->
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            role="switch" 
                            id="highContrastMode"
                        >
                        <label class="form-check-label" for="highContrastMode">
                            <strong>Hoher Kontrast-Modus</strong>
                            <br>
                            <small class="text-muted">
                                Deaktiviert Unschärfe-Effekte und verwendet solide Farben für bessere Lesbarkeit bei schlechten Lichtverhältnissen und auf älteren Geräten. Erfüllt WCAG 2.1 AA Standards.
                            </small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'alumni'): ?>
    <!-- Alumni Profile Visibility Section -->
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i>Alumni-Profil Sichtbarkeit
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Steuern Sie, ob Ihr Profil im Alumni-Netzwerk für andere Mitglieder sichtbar ist.
                    </p>
                    
                    <form id="alumniVisibilityForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="update_alumni_visibility">
                        
                        <div class="form-check form-switch">
                            <input 
                                class="form-check-input alumni-visibility-toggle" 
                                type="checkbox" 
                                role="switch" 
                                id="alumniVisible" 
                                name="is_visible"
                                value="1"
                                <?php echo ($userSettings && $userSettings['alumni_visible']) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="alumniVisible">
                                <strong class="text-info">Profil im Alumni-Netzwerk sichtbar machen</strong>
                                <br>
                                <small class="text-muted">
                                    Wenn aktiviert, wird Ihr Profil in der Alumni-Datenbank angezeigt und ist für andere Mitglieder sichtbar.
                                </small>
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Settings Page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
    
    // Storage helper functions (defined locally in case main.js hasn't loaded yet)
    function getStorageItem(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            try {
                return sessionStorage.getItem(key);
            } catch (err) {
                return null;
            }
        }
    }

    function setStorageItem(key, value) {
        try {
            localStorage.setItem(key, value);
            return true;
        } catch (e) {
            try {
                sessionStorage.setItem(key, value);
                return true;
            } catch (err) {
                return false;
            }
        }
    }
    
    // Toast function (use global if available, otherwise define locally)
    function showToastLocal(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(message, type);
            return;
        }
        
        // Fallback: simple alert if showToast is not available
        alert(message);
    }
    
    // Initialize high-contrast mode from localStorage
    const highContrastToggle = document.getElementById('highContrastMode');
    if (highContrastToggle) {
        const highContrastMode = getStorageItem('high_contrast_mode') === 'true';
        highContrastToggle.checked = highContrastMode;
        
        // Apply high-contrast mode on page load
        if (highContrastMode) {
            document.body.classList.add('high-contrast-mode');
        }
        
        // Handle high-contrast mode toggle
        highContrastToggle.addEventListener('change', function() {
            const isEnabled = this.checked;
            
            if (isEnabled) {
                document.body.classList.add('high-contrast-mode');
                setStorageItem('high_contrast_mode', 'true');
                showToastLocal('Hoher Kontrast-Modus aktiviert.', 'success');
            } else {
                document.body.classList.remove('high-contrast-mode');
                setStorageItem('high_contrast_mode', 'false');
                showToastLocal('Hoher Kontrast-Modus deaktiviert.', 'success');
            }
            
            // Reload page after a short delay to ensure all elements are properly updated
            // This is necessary because some elements may be loaded dynamically after page load
            setTimeout(() => {
                window.location.reload();
            }, 200);
        });
    }
    
    // Handle notification preference toggles
    document.querySelectorAll('.notification-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const formData = new FormData(document.getElementById('notificationPreferencesForm'));
            
            fetch('index.php?page=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastLocal(data.message, 'success');
                } else {
                    showToastLocal(data.message || 'Fehler beim Speichern', 'danger');
                    // Revert toggle on error
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToastLocal('Fehler beim Speichern der Einstellungen', 'danger');
                // Revert toggle on error
                this.checked = !this.checked;
            });
        });
    });
    
    // Handle alumni visibility toggle
    const alumniVisibilityToggle = document.querySelector('.alumni-visibility-toggle');
    if (alumniVisibilityToggle) {
        alumniVisibilityToggle.addEventListener('change', function() {
            const formData = new FormData(document.getElementById('alumniVisibilityForm'));
            
            fetch('index.php?page=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastLocal(data.message, 'success');
                } else {
                    showToastLocal(data.message || 'Fehler beim Speichern', 'danger');
                    // Revert toggle on error
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToastLocal('Fehler beim Speichern der Sichtbarkeitseinstellung', 'danger');
                // Revert toggle on error
                this.checked = !this.checked;
            });
        });
    }
});
</script>
