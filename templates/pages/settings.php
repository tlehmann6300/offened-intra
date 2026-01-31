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
            
        case 'update_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validate inputs
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $response = ['success' => false, 'message' => 'Alle Felder sind erforderlich.'];
                break;
            }
            
            // Check if passwords match
            if ($newPassword !== $confirmPassword) {
                $response = ['success' => false, 'message' => 'Die neuen Passwörter stimmen nicht überein.'];
                break;
            }
            
            // Update password
            $response = $auth->updatePassword($userId, $currentPassword, $newPassword);
            break;
            
        case 'update_email':
            $newEmail = $_POST['new_email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Validate inputs
            if (empty($newEmail) || empty($password)) {
                $response = ['success' => false, 'message' => 'E-Mail-Adresse und Passwort sind erforderlich.'];
                break;
            }
            
            // Update email
            $response = $auth->updateEmail($userId, $newEmail, $password);
            
            // Include the new email in response for UI update
            if ($response['success']) {
                $response['new_email'] = $newEmail;
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

    <!-- Account Security Section (for Alumni) -->
    <?php if ($userRole === 'alumni'): ?>
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Konto-Sicherheit
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Verwalten Sie Ihre E-Mail-Adresse und Ihr Passwort für maximale Sicherheit.
                    </p>
                    
                    <!-- Email Change Section -->
                    <div class="mb-5">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-envelope me-2"></i>E-Mail-Adresse ändern
                        </h6>
                        <form id="emailChangeForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update_email">
                            
                            <div class="mb-3">
                                <label for="currentEmail" class="form-label">Aktuelle E-Mail-Adresse</label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="currentEmail" 
                                    value="<?php echo htmlspecialchars($auth->getUserEmail(), ENT_QUOTES, 'UTF-8'); ?>" 
                                    disabled
                                >
                            </div>
                            
                            <div class="mb-3">
                                <label for="newEmail" class="form-label">Neue E-Mail-Adresse <span class="text-danger">*</span></label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="newEmail" 
                                    name="new_email"
                                    placeholder="neue@email.de"
                                    required
                                >
                                <small class="text-muted">Eine Bestätigung wird an die neue Adresse gesendet.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="emailPassword" class="form-label">Aktuelles Passwort zur Bestätigung <span class="text-danger">*</span></label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="emailPassword" 
                                    name="password"
                                    required
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-2"></i>E-Mail-Adresse ändern
                            </button>
                        </form>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Password Change Section -->
                    <div>
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-key me-2"></i>Passwort ändern
                        </h6>
                        <form id="passwordChangeForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Aktuelles Passwort <span class="text-danger">*</span></label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="currentPassword" 
                                    name="current_password"
                                    required
                                >
                            </div>
                            
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">Neues Passwort <span class="text-danger">*</span></label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="newPassword" 
                                    name="new_password"
                                    required
                                >
                                <div class="form-text">
                                    <strong>Passwort-Anforderungen:</strong>
                                    <ul class="small mb-0 mt-1">
                                        <li>Mindestens 12 Zeichen</li>
                                        <li>Mindestens ein Großbuchstabe (A-Z)</li>
                                        <li>Mindestens ein Kleinbuchstabe (a-z)</li>
                                        <li>Mindestens eine Zahl (0-9)</li>
                                        <li>Mindestens ein Sonderzeichen (!@#$%^&*)</li>
                                    </ul>
                                </div>
                                <div id="passwordStrengthIndicator" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Neues Passwort bestätigen <span class="text-danger">*</span></label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="confirmPassword" 
                                    name="confirm_password"
                                    required
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-lock me-2"></i>Passwort ändern
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
    
    // Password strength indicator
    const newPasswordInput = document.getElementById('newPassword');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const indicator = document.getElementById('passwordStrengthIndicator');
            
            if (password.length === 0) {
                indicator.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Check length
            if (password.length >= 12) {
                strength += 20;
            } else {
                feedback.push('Mindestens 12 Zeichen');
            }
            
            // Check for uppercase
            if (/[A-Z]/.test(password)) {
                strength += 20;
            } else {
                feedback.push('Ein Großbuchstabe');
            }
            
            // Check for lowercase
            if (/[a-z]/.test(password)) {
                strength += 20;
            } else {
                feedback.push('Ein Kleinbuchstabe');
            }
            
            // Check for number
            if (/[0-9]/.test(password)) {
                strength += 20;
            } else {
                feedback.push('Eine Zahl');
            }
            
            // Check for special character
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 20;
            } else {
                feedback.push('Ein Sonderzeichen');
            }
            
            let strengthClass = 'danger';
            let strengthText = 'Schwach';
            
            if (strength >= 100) {
                strengthClass = 'success';
                strengthText = 'Stark';
            } else if (strength >= 60) {
                strengthClass = 'warning';
                strengthText = 'Mittel';
            }
            
            let html = '<div class="progress" style="height: 5px;">';
            html += `<div class="progress-bar bg-${strengthClass}" role="progressbar" style="width: ${strength}%" aria-valuenow="${strength}" aria-valuemin="0" aria-valuemax="100"></div>`;
            html += '</div>';
            html += `<small class="text-${strengthClass}"><strong>${strengthText}</strong></small>`;
            
            if (feedback.length > 0) {
                html += '<br><small class="text-muted">Fehlt noch: ' + feedback.join(', ') + '</small>';
            }
            
            indicator.innerHTML = html;
        });
    }
    
    // Handle email change form
    const emailChangeForm = document.getElementById('emailChangeForm');
    if (emailChangeForm) {
        emailChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Wird geändert...';
            
            const formData = new FormData(this);
            
            fetch('index.php?page=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastLocal(data.message, 'success');
                    // Update the displayed current email using the value from server response
                    if (data.new_email) {
                        document.getElementById('currentEmail').value = data.new_email;
                    }
                    // Reset form on success
                    document.getElementById('newEmail').value = '';
                    document.getElementById('emailPassword').value = '';
                } else {
                    showToastLocal(data.message || 'Fehler beim Ändern der E-Mail-Adresse', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToastLocal('Fehler beim Ändern der E-Mail-Adresse', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
    
    // Handle password change form
    const passwordChangeForm = document.getElementById('passwordChangeForm');
    if (passwordChangeForm) {
        passwordChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Client-side validation
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                showToastLocal('Die neuen Passwörter stimmen nicht überein.', 'danger');
                return;
            }
            
            // Validate password strength
            if (newPassword.length < 12) {
                showToastLocal('Das Passwort muss mindestens 12 Zeichen lang sein.', 'danger');
                return;
            }
            
            if (!/[A-Z]/.test(newPassword)) {
                showToastLocal('Das Passwort muss mindestens einen Großbuchstaben enthalten.', 'danger');
                return;
            }
            
            if (!/[a-z]/.test(newPassword)) {
                showToastLocal('Das Passwort muss mindestens einen Kleinbuchstaben enthalten.', 'danger');
                return;
            }
            
            if (!/[0-9]/.test(newPassword)) {
                showToastLocal('Das Passwort muss mindestens eine Zahl enthalten.', 'danger');
                return;
            }
            
            if (!/[^A-Za-z0-9]/.test(newPassword)) {
                showToastLocal('Das Passwort muss mindestens ein Sonderzeichen enthalten.', 'danger');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Wird geändert...';
            
            const formData = new FormData(this);
            
            fetch('index.php?page=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToastLocal(data.message, 'success');
                    // Reset form on success
                    this.reset();
                    document.getElementById('passwordStrengthIndicator').innerHTML = '';
                } else {
                    showToastLocal(data.message || 'Fehler beim Ändern des Passworts', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToastLocal('Fehler beim Ändern des Passworts', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
});
</script>
