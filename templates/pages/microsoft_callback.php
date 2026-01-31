<?php
/**
 * Microsoft SSO Callback Handler
 * Processes OAuth2 callback from Microsoft Entra ID (Azure AD)
 * Validates tokens and creates/updates user accounts
 * 
 * Required Configuration:
 * - MS_CLIENT_ID: Defined in config/config.php
 * - MS_CLIENT_SECRET: Defined in config/config.php
 * - MS_TENANT_ID: Defined in config/config.php
 * - MS_REDIRECT_URI: Defined in config/config.php
 * 
 * Set these values in .env file for production
 */

// This page should not use header/footer layout
// Error handling and redirect logic

try {
    // Check if error was returned from Microsoft
    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
        $error_description = htmlspecialchars($_GET['error_description'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8');
        
        logMessage("Microsoft SSO Error: {$error} - {$error_description}", 'ERROR');
        
        // Redirect to login with error
        header('Location: index.php?page=login&error=microsoft_auth_failed');
        exit;
    }
    
    // Check if authorization code was returned
    if (!isset($_GET['code'])) {
        logMessage("Microsoft SSO Error: No authorization code received", 'ERROR');
        header('Location: index.php?page=login&error=no_code');
        exit;
    }
    
    // Verify OAuth state to prevent CSRF attacks
    if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        logMessage("Microsoft SSO Error: Invalid OAuth state - possible CSRF attack", 'ERROR');
        unset($_SESSION['oauth_state']);
        header('Location: index.php?page=login&error=invalid_state');
        exit;
    }
    
    // Clear the state from session after verification
    unset($_SESSION['oauth_state']);
    
    $authCode = $_GET['code'];
    logMessage("Microsoft SSO: Authorization code received");
    
    // Exchange authorization code for access token
    $tokenEndpoint = 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/token';
    
    $tokenParams = [
        'client_id' => MS_CLIENT_ID,
        'client_secret' => MS_CLIENT_SECRET,
        'code' => $authCode,
        'redirect_uri' => MS_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'scope' => 'openid profile email User.Read'
    ];
    
    // Make POST request to get tokens
    $ch = curl_init($tokenEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verify hostname
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logMessage("Microsoft SSO Error: Token exchange failed with HTTP {$httpCode}", 'ERROR');
        logMessage("Response: " . substr($response, 0, 500), 'ERROR');
        header('Location: index.php?page=login&error=token_exchange_failed');
        exit;
    }
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token']) || !isset($tokenData['id_token'])) {
        logMessage("Microsoft SSO Error: Invalid token response", 'ERROR');
        header('Location: index.php?page=login&error=invalid_token_response');
        exit;
    }
    
    $accessToken = $tokenData['access_token'];
    $idToken = $tokenData['id_token'];
    
    logMessage("Microsoft SSO: Access token obtained successfully");
    
    // Decode ID token to get user information (JWT)
    // ID token format: header.payload.signature
    $idTokenParts = explode('.', $idToken);
    if (count($idTokenParts) !== 3) {
        logMessage("Microsoft SSO Error: Invalid ID token format", 'ERROR');
        header('Location: index.php?page=login&error=invalid_id_token');
        exit;
    }
    
    // Decode payload (base64url) with proper padding
    $base64url = $idTokenParts[1];
    // Add padding if necessary
    $remainder = strlen($base64url) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $base64url .= str_repeat('=', $padlen);
    }
    $payload = json_decode(base64_decode(strtr($base64url, '-_', '+/')), true);
    
    // Basic token validation
    if (!isset($payload['email']) || !isset($payload['name'])) {
        logMessage("Microsoft SSO Error: Missing required claims in ID token", 'ERROR');
        header('Location: index.php?page=login&error=missing_claims');
        exit;
    }
    
    // Validate token issuer
    $expectedIssuer = 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/v2.0';
    if (!isset($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
        logMessage("Microsoft SSO Error: Invalid token issuer", 'ERROR');
        header('Location: index.php?page=login&error=invalid_issuer');
        exit;
    }
    
    // Validate token expiration
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        logMessage("Microsoft SSO Error: Token has expired", 'ERROR');
        header('Location: index.php?page=login&error=token_expired');
        exit;
    }
    
    // Validate audience (should be our client ID)
    if (!isset($payload['aud']) || $payload['aud'] !== MS_CLIENT_ID) {
        logMessage("Microsoft SSO Error: Invalid token audience", 'ERROR');
        header('Location: index.php?page=login&error=invalid_audience');
        exit;
    }
    
    $userEmail = $payload['email'];
    $userName = $payload['name'];
    $microsoftId = $payload['sub'] ?? $payload['oid'] ?? null;
    
    logMessage("Microsoft SSO: User info extracted - Email: {$userEmail}, Name: {$userName}");
    
    // Check if user exists in database
    $stmt = $pdo->prepare("SELECT id, email, role, firstname, lastname FROM users WHERE email = ?");
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // User exists - log them in
        logMessage("Microsoft SSO: Existing user found - ID: {$user['id']}");
        
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['firstname'] = $user['firstname'] ?? '';
        $_SESSION['lastname'] = $user['lastname'] ?? '';
        $_SESSION['last_activity'] = time();
        $_SESSION['auth_method'] = 'microsoft';
        
        // Generate CSRF token using Auth class method if available, otherwise fallback
        if (isset($auth)) {
            $auth->generateCsrfToken();
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        logMessage("Microsoft SSO: Login successful for user ID: {$user['id']}");
        
    } else {
        // User doesn't exist - create new account
        logMessage("Microsoft SSO: Creating new user account for {$userEmail}");
        
        try {
            // Parse name into first and last name
            $nameParts = explode(' ', $userName, 2);
            $firstname = $nameParts[0] ?? '';
            $lastname = $nameParts[1] ?? '';
            
            // Insert new user with firstname and lastname into users table
            // Role 'mitglied' is the default lowest privilege level
            $stmt = $pdo->prepare("
                INSERT INTO users (email, firstname, lastname, role, created_at, updated_at) 
                VALUES (?, ?, ?, 'mitglied', NOW(), NOW())
            ");
            $stmt->execute([$userEmail, $firstname, $lastname]);
            $newUserId = (int)$pdo->lastInsertId();
            
            logMessage("Microsoft SSO: New user created - ID: {$newUserId}, Email: {$userEmail}");
            
            // Create session for new user with all necessary data
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['role'] = 'mitglied';
            $_SESSION['email'] = $userEmail;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['last_activity'] = time();
            $_SESSION['auth_method'] = 'microsoft';
            
            // Generate CSRF token using Auth class method if available, otherwise fallback
            if (isset($auth)) {
                $auth->generateCsrfToken();
            } else {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            logMessage("Microsoft SSO: Login successful for new user ID: {$newUserId}");
            
        } catch (PDOException $e) {
            logMessage("Microsoft SSO Error: Failed to create user - " . $e->getMessage(), 'ERROR');
            header('Location: index.php?page=login&error=user_creation_failed');
            exit;
        }
    }
    
    // Redirect to home page
    header('Location: index.php?page=home&login=success');
    exit;
    
} catch (Exception $e) {
    logMessage("Microsoft SSO Critical Error: " . $e->getMessage(), 'CRITICAL');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'CRITICAL');
    header('Location: index.php?page=login&error=unexpected');
    exit;
}
