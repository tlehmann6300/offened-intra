<?php
/**
 * Microsoft SSO Login Initiation
 * Redirects user to Microsoft Entra ID (Azure AD) login page
 * 
 * Required Configuration:
 * - MS_CLIENT_ID: Defined in config/config.php
 * - MS_TENANT_ID: Defined in config/config.php
 * - MS_REDIRECT_URI: Defined in config/config.php
 * 
 * Set these values in .env file for production
 */

// Build authorization URL
$authUrl = 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/authorize';

$params = [
    'client_id' => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri' => MS_REDIRECT_URI,
    'response_mode' => 'query',
    'scope' => 'openid profile email User.Read',
    'state' => bin2hex(random_bytes(16)) // CSRF protection
];

$loginUrl = $authUrl . '?' . http_build_query($params);

// Store state in session for verification (optional but recommended)
$_SESSION['oauth_state'] = $params['state'];

logMessage("Microsoft SSO: Redirecting to authorization endpoint");

// Redirect to Microsoft login
header('Location: ' . $loginUrl);
exit;
