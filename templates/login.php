<?php
/**
 * Login Page - Internal Authentication
 * 
 * This template uses exclusively the design system from theme.css
 * with a centered glass-card layout.
 */

// Handle error and success messages from URL parameters
$error = '';
$success = '';
$requires2fa = false;
$message2fa = '';

// Check for timeout parameter
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.';
}

// Check for 2FA requirement
if (isset($_GET['requires_2fa']) && $_GET['requires_2fa'] === '1') {
    $requires2fa = true;
    if (isset($_GET['message'])) {
        $message2fa = substr($_GET['message'], 0, 500);
    }
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
                    
                    <?php if ($requires2fa && $message2fa): ?>
                        <div class="alert alert-info" role="alert">
                            <strong>Info:</strong> <?= htmlspecialchars($message2fa, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <p class="text-center mb-3"><strong>Login</strong></p>
                        <form method="POST" action="index.php?page=admin_login" id="loginForm">
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" placeholder="E-Mail" required>
                                <label for="email">E-Mail-Adresse</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Passwort" required>
                                <label for="password">Passwort</label>
                            </div>
                            
                            <div class="form-floating mb-3" id="totpCodeField" style="<?= $requires2fa ? '' : 'display: none;' ?>">
                                <input type="text" class="form-control" id="totp_code" name="totp_code" placeholder="2FA Code" maxlength="6" pattern="\d{6}" <?= $requires2fa ? 'required' : '' ?>>
                                <label for="totp_code">6-stelliger Authentifizierungscode</label>
                                <small class="text-muted">Geben Sie den Code aus Ihrer Authenticator-App ein</small>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100">Anmelden</button>
                        </form>
                    </div>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Sicherer Login mit Zwei-Faktor-Authentifizierung
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
