<?php
/**
 * Registration Page - Token-based Registration
 * 
 * This page allows users to register using an invitation token.
 * The token must be provided via GET parameter: ?page=register&token=...
 */

// Initialize Auth
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/SystemLogger.php';

$userPdo = DatabaseManager::getUserConnection();
$contentPdo = DatabaseManager::getContentConnection();
$auth = new Auth($userPdo, new SystemLogger($contentPdo));

// Get token from URL
$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$invitationData = null;

// If no token, show error
if (empty($token)) {
    $error = 'Kein gültiger Einladungs-Token. Bitte verwenden Sie den Link aus Ihrer Einladungs-E-Mail.';
} else {
    // Validate token
    $validation = $auth->validateInvitationToken($token);
    
    if (!$validation['success']) {
        $error = $validation['message'];
    } else {
        $invitationData = $validation['invitation'];
    }
}

// Check for error/success from form submission
if (isset($_GET['error'])) {
    $error = substr($_GET['error'], 0, 500);
}
if (isset($_GET['success'])) {
    $success = substr($_GET['success'], 0, 500);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Registrierung - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/theme.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                <div class="glass-card p-4 p-md-5">
                    <h2 class="text-center mb-4 login-title">
                        <?php if ($invitationData && empty($success)): ?>
                            🎉 Willkommen beim IBC Intranet
                        <?php else: ?>
                            IBC Intranet - Registrierung
                        <?php endif; ?>
                    </h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Fehler:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-center mt-4">
                            <p class="text-muted">Haben Sie keinen gültigen Einladungs-Link?</p>
                            <p class="text-muted">Bitte wenden Sie sich an einen Administrator.</p>
                            <a href="index.php" class="btn btn-secondary mt-3">Zur Startseite</a>
                        </div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <strong>Erfolg!</strong> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-center mt-4">
                            <p>Sie können sich jetzt mit Ihren neuen Zugangsdaten anmelden.</p>
                            <a href="index.php?page=login" class="btn btn-login w-100 mt-3">Zum Login</a>
                        </div>
                    <?php elseif ($invitationData): ?>
                        <!-- Show registration form -->
                        <div class="mb-4">
                            <div class="alert alert-info" role="alert">
                                <strong>Einladung:</strong> Sie sind eingeladen, sich zu registrieren.<br>
                                <strong>E-Mail:</strong> <?= htmlspecialchars($invitationData['email'], ENT_QUOTES, 'UTF-8') ?><br>
                                <strong>Rolle:</strong> <?= htmlspecialchars(ucfirst($invitationData['role']), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            
                            <form id="registerForm" class="mt-4">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                                
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($invitationData['email'], ENT_QUOTES, 'UTF-8') ?>" 
                                           readonly disabled>
                                    <label for="email">E-Mail-Adresse</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           placeholder="Vorname" required maxlength="100">
                                    <label for="firstname">Vorname *</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           placeholder="Nachname" required maxlength="100">
                                    <label for="lastname">Nachname *</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Passwort" required minlength="8">
                                    <label for="password">Passwort *</label>
                                    <small class="text-muted">Mindestens 8 Zeichen</small>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                           placeholder="Passwort bestätigen" required minlength="8">
                                    <label for="password_confirm">Passwort bestätigen *</label>
                                </div>
                                
                                <div id="formMessage" class="alert" style="display: none;" role="alert"></div>
                                
                                <button type="submit" class="btn btn-login w-100" id="submitBtn">
                                    Registrierung abschließen
                                </button>
                            </form>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Bereits registriert? <a href="index.php?page=login">Zum Login</a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($invitationData && empty($success) && empty($error)): ?>
    <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const formMessage = document.getElementById('formMessage');
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        
        // Hide previous messages
        formMessage.style.display = 'none';
        
        // Validate passwords match
        if (password !== passwordConfirm) {
            formMessage.className = 'alert alert-danger';
            formMessage.textContent = 'Die Passwörter stimmen nicht überein.';
            formMessage.style.display = 'block';
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Wird registriert...';
        
        try {
            const formData = new FormData(this);
            
            const response = await fetch('<?= SITE_URL ?>/api/register_with_token.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message and redirect
                window.location.href = 'index.php?page=register&token=<?= urlencode($token) ?>&success=' + encodeURIComponent(data.message);
            } else {
                // Show error message
                formMessage.className = 'alert alert-danger';
                formMessage.textContent = data.message;
                formMessage.style.display = 'block';
                
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Registrierung abschließen';
            }
        } catch (error) {
            console.error('Error:', error);
            formMessage.className = 'alert alert-danger';
            formMessage.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
            formMessage.style.display = 'block';
            
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.textContent = 'Registrierung abschließen';
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
