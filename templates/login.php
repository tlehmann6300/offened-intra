<?php
/**
 * Login Page - Microsoft SSO as Primary Authentication
 * 
 * This template uses exclusively the design system from theme.css
 * with a centered glass-card layout.
 */

// Handle error and success messages from URL parameters
$error = '';
$success = '';

// Check for timeout parameter
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.';
}

// Check for error parameter - sanitize input
if (isset($_GET['error']) && !empty($_GET['error'])) {
    // Limit to reasonable length to prevent abuse
    $error = substr($_GET['error'], 0, 500);
}

// Check for success parameter - sanitize input
if (isset($_GET['success']) && !empty($_GET['success'])) {
    // Limit to reasonable length to prevent abuse
    $success = substr($_GET['success'], 0, 500);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Login - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/theme.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                <div class="glass-card p-4 p-md-5">
                    <h2 class="text-center mb-4 login-title">IBC-Intra Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Fehler:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <strong>Erfolg:</strong> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mb-4">
                        <p class="lead mb-4">Melden Sie sich mit Ihrem Microsoft-Konto an</p>
                        
                        <a href="index.php?page=microsoft_login" class="btn btn-microsoft w-100 d-flex align-items-center justify-content-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="me-2" aria-hidden="true">
                                <rect x="0" y="0" width="9.5" height="9.5" fill="#f25022"/>
                                <rect x="0" y="10.5" width="9.5" height="9.5" fill="#00a4ef"/>
                                <rect x="10.5" y="0" width="9.5" height="9.5" fill="#7fba00"/>
                                <rect x="10.5" y="10.5" width="9.5" height="9.5" fill="#ffb900"/>
                            </svg>
                            Mit Microsoft anmelden
                        </a>
                    </div>
                    
                    <div class="divider">oder</div>
                    
                    <div class="mb-4">
                        <p class="text-center mb-3"><strong>Admin-Login</strong></p>
                        <form method="POST" action="index.php?page=admin_login">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Benutzername" required>
                                <label for="username">Benutzername</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Passwort" required>
                                <label for="password">Passwort</label>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100">Anmelden</button>
                        </form>
                    </div>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Sicherer Single Sign-On über Microsoft Entra ID
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
