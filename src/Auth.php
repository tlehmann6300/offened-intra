<?php
declare(strict_types=1);

/**
 * Authentication Class
 * 
 * Handles internal user authentication and session management with:
 * 
 * 1. AUTHENTICATION SYSTEM:
 *    - Email/Password authentication with TOTP 2FA
 *    - All users authenticate against users table in User-DB
 * 
 * 2. TWO-FACTOR AUTHENTICATION (TOTP):
 *    - Time-based One-Time Passwords (6-digit codes)
 *    - Mandatory after password verification
 *    - Uses Google Authenticator compatible implementation
 * 
 * 3. USER MANAGEMENT:
 *    - Create user accounts with email/password via createAlumniAccount()
 *    - Requires 'vorstand' or 'admin' role
 *    - TOTP setup and management
 * 
 * 4. SELF-SERVICE:
 *    - Change email via updateEmail() with password verification
 *    - Change password via updatePassword() with current password verification
 *    - Password strength validation via validatePasswordStrength()
 *    - Enable/disable TOTP 2FA
 * 
 * 5. SECURITY FEATURES:
 *    - Rate limiting (5 attempts per 15 minutes) using login_attempts table
 *    - Account lockout after failed attempts
 *    - Session validation across User database via validateSessionConsistency()
 *    - CSRF token generation and validation
 *    - Secure password hashing with password_hash()
 *    - Role-based access control with hierarchy
 * 
 * DATABASE ARCHITECTURE:
 * - User Database: Stores all user accounts, login attempts, TOTP secrets
 * - Content Database: Stores projects, inventory, events, news
 * 
 * Implements secure login with prepared statements and detailed error handling
 */
class Auth {
    private PDO $pdo;
    private string $logFile;
    private ?SystemLogger $systemLogger;
    private $googleAuth;
    
    // Rate limiting configuration
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds
    
    public function __construct(PDO $pdo, ?SystemLogger $systemLogger = null) {
        $this->pdo = $pdo;
        $this->logFile = BASE_PATH . '/logs/app.log';
        $this->systemLogger = $systemLogger;
        $this->googleAuth = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get client IP address, considering proxy headers
     * 
     * @return string IP address
     */
    private function getClientIp(): string {
        $ip = 'unknown';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the first IP from the X-Forwarded-For list
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP address
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ? $ip : 'unknown';
    }

    /**
     * Check if IP address or email is rate limited
     * Uses database table for persistent rate limiting
     * 
     * @param string $ip IP address to check
     * @param string|null $email Email address to check (optional)
     * @return bool True if rate limited, false otherwise
     */
    private function isRateLimited(string $ip, ?string $email = null): bool {
        try {
            $now = time();
            $windowStart = $now - self::RATE_LIMIT_WINDOW;
            
            // Check IP-based rate limiting
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempt_count 
                FROM login_attempts 
                WHERE ip_address = ? 
                AND success = 0 
                AND attempt_time >= FROM_UNIXTIME(?)
            ");
            $stmt->execute([$ip, $windowStart]);
            $ipAttempts = $stmt->fetchColumn();
            
            if ($ipAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                return true;
            }
            
            // Check email-based rate limiting if email provided
            if ($email) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as attempt_count 
                    FROM login_attempts 
                    WHERE email = ? 
                    AND success = 0 
                    AND attempt_time >= FROM_UNIXTIME(?)
                ");
                $stmt->execute([$email, $windowStart]);
                $emailAttempts = $stmt->fetchColumn();
                
                if ($emailAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                    return true;
                }
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error checking rate limit: " . $e->getMessage());
            // On error, allow login attempt (fail open for availability)
            return false;
        }
    }

    /**
     * Record a login attempt (successful or failed) for rate limiting
     * Stores in database table for persistence and analysis
     * 
     * @param string $ip IP address
     * @param string|null $email Email address (if provided)
     * @param bool $success Whether the attempt was successful
     */
    private function recordLoginAttempt(string $ip, ?string $email = null, bool $success = false): void {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent && strlen($userAgent) > 500) {
                $userAgent = substr($userAgent, 0, 500);
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (ip_address, email, success, user_agent) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$ip, $email, $success ? 1 : 0, $userAgent]);
            
            // Clean up old attempts periodically (1% chance on each call)
            if (rand(1, 100) === 1) {
                $this->cleanupOldAttempts();
            }
        } catch (PDOException $e) {
            $this->log("Error recording login attempt: " . $e->getMessage());
        }
    }

    /**
     * Clean up old login attempts from database
     * Removes entries older than the rate limit window
     */
    private function cleanupOldAttempts(): void {
        try {
            $cutoffTime = time() - (self::RATE_LIMIT_WINDOW * 2); // Keep 2x window for analysis
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts 
                WHERE attempt_time < FROM_UNIXTIME(?)
            ");
            $stmt->execute([$cutoffTime]);
        } catch (PDOException $e) {
            $this->log("Error cleaning up old login attempts: " . $e->getMessage());
        }
    }

    /**
     * Login method with strict database authentication
     * Main entry point for password-based authentication
     * Requires email, password, and TOTP code (if 2FA enabled)
     * 
     * @param string $email Email address
     * @param string $password Password
     * @param string|null $totpCode 6-digit TOTP code (required if 2FA enabled)
     * @return array Result array with 'success' (bool), 'message' (string), and optionally 'requires_2fa' (bool)
     */
    public function login(string $email, string $password, ?string $totpCode = null): array {
        // Delegate to password-based authentication method
        return $this->loginWithPassword($email, $password, $totpCode);
    }
    
    /**
     * Password-based authentication with TOTP 2FA
     * Validates email, password, and TOTP code against the User database
     * 
     * @param string $email Email address
     * @param string $password Password
     * @param string|null $totpCode 6-digit TOTP code (required if 2FA enabled)
     * @return array Result array with 'success' (bool), 'message' (string), and optionally 'requires_2fa' (bool)
     */
    public function loginWithPassword(string $email, string $password, ?string $totpCode = null): array {
        try {
            // Get client IP for rate limiting
            $clientIp = $this->getClientIp();
            
            // Sanitize input
            $email = trim($email);
            $password = trim($password);
            
            // Check rate limiting (with email if provided)
            if ($this->isRateLimited($clientIp, $email)) {
                $this->log("Login blocked: Rate limit exceeded for IP: {$clientIp}, Email: {$email}");
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($email, false, null, 'Rate limit exceeded');
                }
                $waitMinutes = (int)ceil(self::RATE_LIMIT_WINDOW / 60);
                return [
                    'success' => false,
                    'message' => "Zu viele Anmeldeversuche. Bitte warten Sie {$waitMinutes} Minuten und versuchen Sie es erneut."
                ];
            }
            
            // Log login attempt
            $this->log("Login attempt for user: " . $email);
            
            // Check for empty credentials
            if (empty($email) || empty($password)) {
                $this->log("Login failed: Empty credentials for user: " . $email);
                $this->recordLoginAttempt($clientIp, $email, false);
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($email, false, null, 'Empty credentials');
                }
                return [
                    'success' => false,
                    'message' => 'Bitte geben Sie E-Mail und Passwort ein.'
                ];
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("Login failed: Invalid email format: " . $email);
                $this->recordLoginAttempt($clientIp, $email, false);
                return [
                    'success' => false,
                    'message' => 'Ungültige E-Mail-Adresse.'
                ];
            }
            
            // Fetch user from database
            $stmt = $this->pdo->prepare("
                SELECT id, email, role, password, firstname, lastname, tfa_secret as totp_secret, tfa_enabled as totp_enabled 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->log("Login failed: User not found: " . $email);
                $this->recordLoginAttempt($clientIp, $email, false);
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($email, false, null, 'User not found');
                }
                return [
                    'success' => false,
                    'message' => 'Ungültige Anmeldedaten. Bitte überprüfen Sie E-Mail und Passwort.'
                ];
            }
            
            // Check if user has a password set
            if (empty($user['password'])) {
                $this->log("Login failed: No password set for user: " . $email);
                $this->recordLoginAttempt($clientIp, $email, false);
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($email, false, (int)$user['id'], 'No password set');
                }
                return [
                    'success' => false,
                    'message' => 'Für dieses Konto ist kein Passwort gesetzt. Bitte kontaktieren Sie den Administrator.'
                ];
            }
            
            // Verify password hash
            if (!password_verify($password, $user['password'])) {
                $this->log("Login failed: Invalid password for user: " . $email);
                $this->recordLoginAttempt($clientIp, $email, false);
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($email, false, (int)$user['id'], 'Invalid password');
                }
                return [
                    'success' => false,
                    'message' => 'Ungültiges Passwort. Bitte versuchen Sie es erneut.'
                ];
            }
            
            // Password verified - now check TOTP if enabled
            if ($user['totp_enabled']) {
                if (empty($totpCode)) {
                    // Password is correct but TOTP code required
                    // Store temporary session marker for TOTP verification
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_time'] = time();
                    
                    return [
                        'success' => false,
                        'requires_2fa' => true,
                        'message' => 'Bitte geben Sie Ihren 6-stelligen Authentifizierungscode ein.'
                    ];
                }
                
                // Verify TOTP code
                if (!$this->verifyTotpCode($user['totp_secret'], $totpCode)) {
                    $this->log("Login failed: Invalid TOTP code for user: " . $email);
                    $this->recordLoginAttempt($clientIp, $email, false);
                    if ($this->systemLogger) {
                        $this->systemLogger->logLoginAttempt($email, false, (int)$user['id'], 'Invalid TOTP code');
                    }
                    return [
                        'success' => false,
                        'requires_2fa' => true,
                        'message' => 'Ungültiger Authentifizierungscode. Bitte versuchen Sie es erneut.'
                    ];
                }
            }
            
            // Clear any pending 2FA session markers
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_time']);
            
            // Authentication successful - record successful attempt
            $this->recordLoginAttempt($clientIp, $email, true);
            
            // Log successful login
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($email, true, (int)$user['id'], null);
            }
            
            return $this->createUserSession($user, 'password');
            
        } catch (PDOException $e) {
            $this->log("Database error during login: " . $e->getMessage());
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($email ?? 'unknown', false, null, 'Database error');
            }
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
            ];
        } catch (Exception $e) {
            $this->log("Unexpected error during login: " . $e->getMessage());
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($email ?? 'unknown', false, null, 'Unexpected error');
            }
            return [
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.'
            ];
        }
    }
    
    /**
     * Create user session after successful authentication
     * 
     * @param array $user User data from database
     * @param string $authMethod Authentication method (currently only 'password')
     * @return array Result array
     */
    private function createUserSession(array $user, string $authMethod): array {
        // Set session variables including all necessary user data
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['firstname'] = $user['firstname'] ?? '';
        $_SESSION['lastname'] = $user['lastname'] ?? '';
        $_SESSION['last_activity'] = time();
        $_SESSION['auth_method'] = $authMethod;
        
        // Generate CSRF token for protection against cross-site request forgery
        $this->generateCsrfToken();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Log successful login
        $this->log("Successful login for user ID: {$user['id']}, email: {$user['email']}, method: {$authMethod}");
        
        return [
            'success' => true,
            'message' => 'Login erfolgreich!'
        ];
    }
    
    /**
     * Generate a new TOTP secret for a user
     * 
     * @return string Base32-encoded secret
     */
    public function generateTotpSecret(): string {
        return $this->googleAuth->generateSecret();
    }
    
    /**
     * Verify a TOTP code against a secret
     * 
     * @param string $secret Base32-encoded TOTP secret
     * @param string $code 6-digit code from authenticator app
     * @return bool True if code is valid
     */
    private function verifyTotpCode(string $secret, string $code): bool {
        try {
            // Allow 1 time period drift (30 seconds) for clock skew
            return $this->googleAuth->checkCode($secret, $code, 1);
        } catch (Exception $e) {
            $this->log("Error verifying TOTP code: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable TOTP 2FA for a user
     * 
     * @param int $userId User ID
     * @param string $secret TOTP secret to store
     * @param string $verificationCode Code to verify setup
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    public function enableTotp(int $userId, string $secret, string $verificationCode): array {
        try {
            // Verify the code before enabling
            if (!$this->verifyTotpCode($secret, $verificationCode)) {
                return [
                    'success' => false,
                    'message' => 'Ungültiger Verifikationscode. Bitte versuchen Sie es erneut.'
                ];
            }
            
            // Enable TOTP for the user
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET tfa_secret = ?, tfa_enabled = 1, totp_verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$secret, $userId]);
            
            $this->log("TOTP 2FA enabled for user ID: {$userId}");
            
            return [
                'success' => true,
                'message' => 'Zwei-Faktor-Authentifizierung erfolgreich aktiviert.'
            ];
        } catch (PDOException $e) {
            $this->log("Database error enabling TOTP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Disable TOTP 2FA for a user
     * 
     * @param int $userId User ID
     * @param string $password User's password for verification
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    public function disableTotp(int $userId, string $password): array {
        try {
            // Verify password before disabling
            $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Ungültiges Passwort.'
                ];
            }
            
            // Disable TOTP for the user
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET tfa_secret = NULL, tfa_enabled = 0, totp_verified_at = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->log("TOTP 2FA disabled for user ID: {$userId}");
            
            return [
                'success' => true,
                'message' => 'Zwei-Faktor-Authentifizierung erfolgreich deaktiviert.'
            ];
        } catch (PDOException $e) {
            $this->log("Database error disabling TOTP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Get QR code URL for TOTP setup
     * 
     * @param string $email User's email
     * @param string $secret TOTP secret
     * @param string $issuer Application name (default: IBC-Intra)
     * @return string QR code URL
     */
    public function getTotpQrCodeUrl(string $email, string $secret, string $issuer = 'IBC-Intra'): string {
        return $this->googleAuth->getURL($email, $issuer, $secret);
    }
    
    /**
     * Check if user has TOTP enabled
     * 
     * @param int $userId User ID
     * @return bool True if TOTP is enabled
     */
    public function isTotpEnabled(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT tfa_enabled FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetchColumn();
            return (bool)$result;
        } catch (PDOException $e) {
            $this->log("Error checking TOTP status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate session consistency across databases
     * Ensures session data matches the user database to prevent session hijacking
     * This method should be called on each request to verify session integrity
     * 
     * @return array Result with 'valid' (bool), 'message' (string), and optionally 'user' (array)
     */
    public function validateSessionConsistency(): array {
        try {
            // Check if user is logged in
            if (!$this->isLoggedIn()) {
                return [
                    'valid' => false,
                    'message' => 'Nicht angemeldet.'
                ];
            }
            
            $userId = $_SESSION['user_id'];
            $sessionEmail = $_SESSION['email'] ?? null;
            $sessionRole = $_SESSION['role'] ?? null;
            
            // Fetch current user data from User database
            $stmt = $this->pdo->prepare("
                SELECT id, email, role, firstname, lastname 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user still exists in database
            if (!$dbUser) {
                $this->log("Session validation failed: User ID {$userId} not found in database");
                return [
                    'valid' => false,
                    'message' => 'Benutzerkonto nicht gefunden. Bitte melden Sie sich erneut an.'
                ];
            }
            
            // Check if email matches (critical for security)
            if ($sessionEmail !== $dbUser['email']) {
                $this->log("Session validation failed: Email mismatch for user ID {$userId}. Session: {$sessionEmail}, DB: {$dbUser['email']}");
                return [
                    'valid' => false,
                    'message' => 'Session-Inkonsistenz erkannt. Bitte melden Sie sich erneut an.'
                ];
            }
            
            // Check if role matches (important for access control)
            if ($sessionRole !== $dbUser['role']) {
                $this->log("Session validation: Role changed for user ID {$userId}. Session: {$sessionRole}, DB: {$dbUser['role']}. Updating session.");
                // Update session with new role from database
                $_SESSION['role'] = $dbUser['role'];
            }
            
            // Update session data if user info changed
            if (($_SESSION['firstname'] ?? '') !== ($dbUser['firstname'] ?? '')) {
                $_SESSION['firstname'] = $dbUser['firstname'] ?? '';
            }
            if (($_SESSION['lastname'] ?? '') !== ($dbUser['lastname'] ?? '')) {
                $_SESSION['lastname'] = $dbUser['lastname'] ?? '';
            }
            
            return [
                'valid' => true,
                'message' => 'Session gültig.',
                'user' => $dbUser
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during session validation: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Fehler bei der Session-Validierung.'
            ];
        }
    }
    
    /**
     * Check session timeout
     * 
     * @return bool True if session is valid, false if timed out
     */
    public function checkSessionTimeout(): bool {
        if (!isset($_SESSION['last_activity'])) {
            // Initialize last_activity for new sessions
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        $timeout = SESSION_LIFETIME;
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->log("Session timeout for user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
            return false;
        }
        
        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Get current user role
     * 
     * @return string|null User role or null if not logged in
     */
    public function getUserRole(): ?string {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null User ID or null if not logged in
     */
    public function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user firstname
     * 
     * @return string|null User firstname or null if not logged in
     */
    public function getFirstname(): ?string {
        return $_SESSION['firstname'] ?? null;
    }
    
    /**
     * Get current user lastname
     * 
     * @return string|null User lastname or null if not logged in
     */
    public function getLastname(): ?string {
        return $_SESSION['lastname'] ?? null;
    }
    
    /**
     * Get current user email
     * 
     * @return string|null User email or null if not logged in
     */
    public function getUserEmail(): ?string {
        return $_SESSION['email'] ?? null;
    }
    
    /**
     * Get current authentication method
     * 
     * @return string|null Authentication method ('password') or null if not logged in
     */
    public function getAuthMethod(): ?string {
        return $_SESSION['auth_method'] ?? null;
    }
    
    /**
     * Get current user full name
     * 
     * @return string|null User full name or null if not logged in
     */
    public function getFullName(): ?string {
        $firstname = $this->getFirstname();
        $lastname = $this->getLastname();
        
        if ($firstname && $lastname) {
            return trim($firstname . ' ' . $lastname);
        } elseif ($firstname) {
            return $firstname;
        } elseif ($lastname) {
            return $lastname;
        }
        
        return null;
    }
    
    /**
     * Logout user
     */
    public function logout(): void {
        $userId = $_SESSION['user_id'] ?? 'unknown';
        $this->log("User logout: user ID: " . $userId);
        
        session_unset();
        session_destroy();
    }
    
    /**
     * Generate CSRF token and store in session
     * Creates a new CSRF token for protecting against cross-site request forgery
     * 
     * @return string The generated CSRF token
     */
    public function generateCsrfToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Get CSRF token from session
     * 
     * @return string|null CSRF token or null if not set
     */
    public function getCsrfToken(): ?string {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Verify CSRF token for POST requests
     * Validates the provided token against the session token
     * 
     * @param string $token Token to verify
     * @return bool True if token is valid, false otherwise
     */
    public function verifyCsrfToken(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Validate CSRF token
     * Alias for verifyCsrfToken for backward compatibility
     * 
     * @param string $token Token to validate
     * @return bool True if token is valid, false otherwise
     * @deprecated Since v1.0 - Use verifyCsrfToken() instead. This method will be removed in v2.0.
     * @see verifyCsrfToken()
     */
    public function validateCsrfToken(string $token): bool {
        return $this->verifyCsrfToken($token);
    }
    
    /**
     * Get the permission matrix for all roles
     * This is the single source of truth for role-based permissions
     * 
     * @return array<string, array<string>> Permission matrix
     */
    private function getPermissionMatrix(): array {
        return [
            'vorstand' => ['*'], // Full access
            'admin' => ['*'], // Full access
            '1v' => ['*'], // Full access - First board member
            '2v' => ['*'], // Full access - Second board member
            '3v' => ['*'], // Full access - Third board member
            'ressortleiter' => ['edit_news', 'edit_projects', 'edit_events', 'apply_projects', 'edit_own_profile', 'edit_inventory'],
            'alumni' => ['edit_own_profile', 'edit_inventory'],
            'mitglied' => ['edit_own_profile', 'apply_projects'],
        ];
    }
    
    /**
     * Check if the current user has permission to perform an action
     * 
     * @param string $action The action to check permission for
     * @return bool True if user has permission, false otherwise
     */
    public function can(string $action): bool {
        $role = $this->getUserRole();
        
        // If no role or role is 'none', deny all actions
        if (!$role || $role === 'none') {
            return false;
        }
        
        // Special case: view_inventory is available to all logged-in users
        if ($action === 'view_inventory' && $this->isLoggedIn()) {
            return true;
        }
        
        // Get permission matrix
        $permissions = $this->getPermissionMatrix();
        
        // Check if role exists in permissions
        if (!isset($permissions[$role])) {
            return false;
        }
        
        // Check if role has wildcard permission (full access)
        if (in_array('*', $permissions[$role], true)) {
            return true;
        }
        
        // Check if role has specific permission
        return in_array($action, $permissions[$role], true);
    }
    
    /**
     * Check if the current user has full admin access (wildcard permissions)
     * This method is useful for UI elements that should only be visible to admin roles
     * 
     * @return bool True if user has full admin access, false otherwise
     */
    public function hasFullAccess(): bool {
        $role = $this->getUserRole();
        
        // If no role or role is 'none', deny access
        if (!$role || $role === 'none') {
            return false;
        }
        
        // Get permission matrix
        $permissions = $this->getPermissionMatrix();
        
        // Check if role has wildcard permission
        return isset($permissions[$role]) && in_array('*', $permissions[$role], true);
    }
    
    /**
     * Role hierarchy definition (higher number = more privileges)
     * This constant makes it easier to maintain and modify role hierarchies
     * 
     * New hierarchy as per requirement:
     * Admin/1V-3V > Ressortleiter > Mitglied > Alumni
     * 
     * @var array<string, int>
     */
    private const ROLE_HIERARCHY = [
        'none' => 0,
        'alumni' => 1,        // Alumni - lowest active role, requires validation
        'mitglied' => 2,      // Regular member
        'ressortleiter' => 3, // Department leader
        '3v' => 4,            // Third board member (3. Vorstand)
        '2v' => 5,            // Second board member (2. Vorstand)
        '1v' => 6,            // First board member (1. Vorstand)
        'vorstand' => 7,      // Board member (general vorstand)
        'admin' => 8,         // Full system access
    ];
    
    /**
     * Check if current user has the required role or higher in the hierarchy
     * This method enforces role-based access control and must be called at the beginning of API actions.
     * The visibility of buttons in the frontend should never be the only security barrier.
     * 
     * Role hierarchy (highest to lowest):
     * - admin: Full system access
     * - vorstand: Board member access (including 1V, 2V, 3V)
     * - ressortleiter: Department leader access
     * - mitglied: Regular member access
     * - alumni: Alumni member access (lowest active role, requires validation)
     * - none: No access
     * 
     * Alumni Validation:
     * - Alumni users with is_alumni_validated = FALSE have restricted access
     * - Only validated alumni (is_alumni_validated = TRUE) have full alumni privileges
     * 
     * @param string $requiredRole The minimum role required
     * @return bool True if user has required role or higher, false otherwise
     * @throws RuntimeException If user is not logged in
     */
    public function checkPermission(string $requiredRole): bool {
        // Check if user is logged in
        if (!$this->isLoggedIn()) {
            $this->log("Permission denied: User not logged in");
            throw new RuntimeException('Nicht angemeldet. Bitte melden Sie sich an.');
        }
        
        $currentRole = $this->getUserRole();
        $userId = $this->getUserId();
        
        // Validate required role
        if (!isset(self::ROLE_HIERARCHY[$requiredRole])) {
            $this->log("Invalid required role specified: {$requiredRole}");
            throw new RuntimeException('Ungültige Rollenberechtigung angegeben.');
        }
        
        // Get role levels
        $currentLevel = self::ROLE_HIERARCHY[$currentRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole];
        
        // Check if current role meets or exceeds required role
        $hasPermission = $currentLevel >= $requiredLevel;
        
        // Additional check for alumni: must be validated to have access
        if ($hasPermission && $currentRole === 'alumni') {
            try {
                $stmt = $this->pdo->prepare("SELECT is_alumni_validated FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // If alumni is not validated, deny access (treat as 'none' role)
                if ($user && !$user['is_alumni_validated']) {
                    $this->log("Permission denied: Alumni user ID {$userId} is not validated yet");
                    $hasPermission = false;
                }
            } catch (PDOException $e) {
                $this->log("Error checking alumni validation status: " . $e->getMessage());
                // On error, deny access for safety
                $hasPermission = false;
            }
        }
        
        if (!$hasPermission) {
            $this->log("Permission denied: User role '{$currentRole}' (level {$currentLevel}) insufficient for required role '{$requiredRole}' (level {$requiredLevel})");
        }
        
        return $hasPermission;
    }
    
    /**
     * Update user role in database
     * 
     * Note: When an admin updates another user's role, that user will need to 
     * log out and back in to see the role change reflected in their session.
     * This is intentional to prevent session hijacking scenarios.
     * 
     * @param int $userId User ID
     * @param string $role New role to set
     * @return bool True on success, false on failure
     */
    public function updateUserRole(int $userId, string $role): bool {
        try {
            // Validate role
            $validRoles = ['none', 'alumni', 'mitglied', 'ressortleiter', '1v', '2v', '3v', 'vorstand', 'admin'];
            if (!in_array($role, $validRoles, true)) {
                $this->log("Invalid role attempted: {$role} for user ID: {$userId}");
                return false;
            }
            
            $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $result = $stmt->execute([$role, $userId]);
            
            if ($result) {
                // Update session role only if updating current user's own role
                // Other users will need to log out and back in to see the change
                if ($userId === $this->getUserId()) {
                    $_SESSION['role'] = $role;
                }
                $this->log("Role updated to {$role} for user ID: {$userId}");
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error updating role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Request alumni status transition for a user
     * When a member requests alumni status:
     * 1. Changes role to 'alumni'
     * 2. Sets is_alumni_validated to FALSE (pending validation)
     * 3. Records timestamp of request
     * 4. Immediately revokes access to active project data
     * 
     * @param int $userId User ID requesting alumni status
     * @return bool True on success, false on failure
     */
    public function requestAlumniStatus(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET role = 'alumni', 
                    is_alumni_validated = 0, 
                    alumni_status_requested_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                // Update session if current user
                if ($userId === $this->getUserId()) {
                    $_SESSION['role'] = 'alumni';
                }
                
                $this->log("Alumni status requested for user ID: {$userId}. Validation pending.");
                
                // Log to system logs if available
                if ($this->systemLogger !== null) {
                    $this->systemLogger->log($userId, 'request_alumni_status', 'users', $userId);
                }
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error requesting alumni status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate an alumni user's status
     * Sets is_alumni_validated to TRUE, allowing full alumni privileges and profile visibility
     * Only callable by admin or vorstand roles
     * 
     * @param int $alumniUserId User ID of the alumni to validate
     * @param int $validatorUserId User ID of the admin/vorstand performing validation
     * @return bool True on success, false on failure
     */
    public function validateAlumniStatus(int $alumniUserId, int $validatorUserId): bool {
        try {
            // Check if validator has permission (admin or vorstand)
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$validatorUserId]);
            $validator = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$validator) {
                $this->log("Validator user ID {$validatorUserId} not found");
                return false;
            }
            
            $validatorRole = $validator['role'];
            $allowedRoles = ['admin', 'vorstand', '1v', '2v', '3v'];
            
            if (!in_array($validatorRole, $allowedRoles, true)) {
                $this->log("Permission denied: User ID {$validatorUserId} (role: {$validatorRole}) cannot validate alumni");
                return false;
            }
            
            // Validate the alumni user
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET is_alumni_validated = 1 
                WHERE id = ? AND role = 'alumni'
            ");
            $result = $stmt->execute([$alumniUserId]);
            
            if ($result) {
                $this->log("Alumni validated: User ID {$alumniUserId} validated by user ID {$validatorUserId}");
                
                // Log to system logs if available
                if ($this->systemLogger !== null) {
                    $this->systemLogger->log($validatorUserId, 'validate_alumni', 'users', $alumniUserId);
                }
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error validating alumni status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all pending alumni validations
     * Returns list of alumni users with is_alumni_validated = FALSE
     * 
     * @return array List of pending alumni users
     */
    public function getPendingAlumniValidations(): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, 
                    email, 
                    firstname, 
                    lastname, 
                    alumni_status_requested_at,
                    created_at
                FROM users 
                WHERE role = 'alumni' AND is_alumni_validated = 0
                ORDER BY alumni_status_requested_at DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("Error fetching pending alumni validations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user is a validated alumni
     * 
     * @param int $userId User ID to check
     * @return bool True if user is validated alumni, false otherwise
     */
    public function isValidatedAlumni(int $userId): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT role, is_alumni_validated 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            return $user['role'] === 'alumni' && (bool)$user['is_alumni_validated'];
        } catch (PDOException $e) {
            $this->log("Error checking alumni validation status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user notification preferences
     * 
     * @param int $userId User ID
     * @return array|null Notification preferences or null on failure
     */
    public function getNotificationPreferences(int $userId): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    notify_news,
                    notify_events,
                    notify_projects
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            $this->log("Error getting notification preferences for user {$userId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user notification preferences
     * 
     * @param int $userId User ID
     * @param array $preferences Notification preferences (notify_news, notify_events, notify_projects)
     * @return bool True on success, false on failure
     */
    public function updateNotificationPreferences(int $userId, array $preferences): bool {
        try {
            $fields = [];
            $values = [];
            
            // Granular notification preferences
            $allowedFields = ['notify_news', 'notify_events', 'notify_projects'];
            
            foreach ($allowedFields as $field) {
                if (isset($preferences[$field])) {
                    $fields[] = "{$field} = ?";
                    $values[] = (int)$preferences[$field];
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->log("Notification preferences updated for user ID: {$userId}");
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error updating notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user settings including notification preferences and alumni profile
     * 
     * @param int $userId User ID
     * @return array|null User settings or null on failure
     */
    public function getUserSettings(int $userId): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id, u.email, u.firstname, u.lastname, u.role,
                    u.project_alerts,
                    a.id as alumni_id, a.is_published as alumni_visible
                FROM users u
                LEFT JOIN alumni a ON a.created_by = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            $this->log("Error getting user settings for user {$userId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get skills for user's alumni profile
     * 
     * @param int $userId User ID
     * @return array List of skills or empty array
     */
    public function getUserSkills(int $userId): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ms.id, ms.skill_name, ms.skill_level, ms.years_experience
                FROM member_skills ms
                INNER JOIN alumni a ON ms.alumni_id = a.id
                WHERE a.created_by = ?
                ORDER BY ms.skill_name ASC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("Error getting user skills for user {$userId}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a skill to user's alumni profile
     * 
     * @param int $userId User ID
     * @param string $skillName Name of the skill
     * @param string $skillLevel Proficiency level
     * @param int|null $yearsExperience Years of experience
     * @return int|false Skill ID on success, false on failure
     */
    public function addUserSkill(int $userId, string $skillName, string $skillLevel = 'intermediate', ?int $yearsExperience = null): int|false {
        try {
            // Get user's alumni profile ID
            $stmt = $this->pdo->prepare("SELECT id FROM alumni WHERE created_by = ?");
            $stmt->execute([$userId]);
            $alumni = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$alumni) {
                $this->log("Cannot add skill: No alumni profile found for user {$userId}");
                return false;
            }
            
            // Validate skill level
            $validLevels = ['beginner', 'intermediate', 'advanced', 'expert'];
            if (!in_array($skillLevel, $validLevels, true)) {
                $skillLevel = 'intermediate';
            }
            
            // Insert skill
            $stmt = $this->pdo->prepare("
                INSERT INTO member_skills (alumni_id, skill_name, skill_level, years_experience)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$alumni['id'], $skillName, $skillLevel, $yearsExperience]);
            
            if ($result) {
                $skillId = (int)$this->pdo->lastInsertId();
                $this->log("Skill '{$skillName}' added for user ID: {$userId}");
                return $skillId;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error adding skill: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a skill from user's alumni profile
     * 
     * @param int $userId User ID (must own the skill)
     * @param int $skillId Skill ID to delete
     * @return bool True on success, false on failure
     */
    public function deleteUserSkill(int $userId, int $skillId): bool {
        try {
            // Delete skill only if it belongs to the user's alumni profile
            $stmt = $this->pdo->prepare("
                DELETE ms FROM member_skills ms
                INNER JOIN alumni a ON ms.alumni_id = a.id
                WHERE ms.id = ? AND a.created_by = ?
            ");
            $result = $stmt->execute([$skillId, $userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->log("Skill ID {$skillId} deleted for user ID: {$userId}");
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            $this->log("Error deleting skill: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update alumni profile visibility
     * 
     * @param int $userId User ID (must own the alumni profile)
     * @param bool $isVisible Visibility status
     * @return bool True on success, false on failure
     */
    public function updateAlumniVisibility(int $userId, bool $isVisible): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE alumni 
                SET is_published = ? 
                WHERE created_by = ?
            ");
            $result = $stmt->execute([(int)$isVisible, $userId]);
            
            if ($result) {
                $this->log("Alumni visibility updated to " . ($isVisible ? 'visible' : 'hidden') . " for user ID: {$userId}");
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("Error updating alumni visibility: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create an alumni account with email and password
     * Only accessible to board members (vorstand) and admins
     * 
     * @param string $email Email address for the alumni account
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param string $password Initial password (will be hashed)
     * @return array Result array with 'success' (bool), 'message' (string), and optionally 'user_id' (int)
     */
    public function createAlumniAccount(string $email, string $firstname, string $lastname, string $password): array {
        try {
            // Check if current user has permission (vorstand or admin only)
            if (!$this->isLoggedIn()) {
                $this->log("Alumni account creation failed: User not logged in");
                return [
                    'success' => false,
                    'message' => 'Sie müssen angemeldet sein, um Alumni-Konten zu erstellen.'
                ];
            }
            
            $currentRole = $this->getUserRole();
            if (!in_array($currentRole, ['vorstand', 'admin'], true)) {
                $this->log("Alumni account creation failed: Insufficient permissions for role: {$currentRole}");
                return [
                    'success' => false,
                    'message' => 'Keine Berechtigung. Nur Vorstand und Admins können Alumni-Konten erstellen.'
                ];
            }
            
            // Validate and sanitize inputs
            $email = trim($email);
            $firstname = trim($firstname);
            $lastname = trim($lastname);
            // Note: Don't trim password - users may intentionally include spaces
            
            // Validate required fields first (before other validations)
            if (empty($email) || empty($firstname) || empty($lastname) || empty($password)) {
                $this->log("Alumni account creation failed: Missing required fields");
                return [
                    'success' => false,
                    'message' => 'Alle Felder sind erforderlich (E-Mail, Vorname, Nachname, Passwort).'
                ];
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("Alumni account creation failed: Invalid email format: {$email}");
                return [
                    'success' => false,
                    'message' => 'Ungültige E-Mail-Adresse.'
                ];
            }
            
            // Validate password strength (minimum 8 characters)
            if (strlen($password) < 8) {
                $this->log("Alumni account creation failed: Password too short");
                return [
                    'success' => false,
                    'message' => 'Das Passwort muss mindestens 8 Zeichen lang sein.'
                ];
            }
            
            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->log("Alumni account creation failed: Email already exists: {$email}");
                return [
                    'success' => false,
                    'message' => 'Ein Konto mit dieser E-Mail-Adresse existiert bereits.'
                ];
            }
            
            // Hash the password using password_hash with default algorithm (bcrypt)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Create the alumni user account
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, firstname, lastname, role, password, created_at, updated_at)
                VALUES (?, ?, ?, 'alumni', ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([$email, $firstname, $lastname, $hashedPassword]);
            
            if ($result) {
                $newUserId = (int)$this->pdo->lastInsertId();
                $this->log("Alumni account created successfully - ID: {$newUserId}, Email: {$email} by user ID: " . $this->getUserId());
                
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logAction(
                        $this->getUserId() ?? 0,
                        'create_alumni_account',
                        'users',
                        $newUserId,
                        "Alumni account created for {$email}"
                    );
                }
                
                return [
                    'success' => true,
                    'message' => 'Alumni-Konto erfolgreich erstellt.',
                    'user_id' => $newUserId
                ];
            }
            
            $this->log("Alumni account creation failed: Database insert failed for email: {$email}");
            return [
                'success' => false,
                'message' => 'Fehler beim Erstellen des Alumni-Kontos.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during alumni account creation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
            ];
        } catch (Exception $e) {
            $this->log("Unexpected error during alumni account creation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.'
            ];
        }
    }
    
    /**
     * Log message to application log file
     * 
     * @param string $message Message to log
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        
        // Get client IP, considering proxy headers
        $ip = 'unknown';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the first IP from the X-Forwarded-For list
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize IP for logging (support both IPv4 and IPv6)
        $ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ? $ip : 'invalid';
        
        $logMessage = "[{$timestamp}] [IP: {$ip}] {$message}" . PHP_EOL;
        
        // Validate log file path to prevent path traversal
        $logDir = dirname($this->logFile);
        $basePath = realpath(BASE_PATH);
        $realLogDir = realpath($logDir);
        
        // Ensure log directory is within BASE_PATH
        if ($realLogDir === false || strpos($realLogDir, $basePath) !== 0) {
            error_log("Invalid log file path: {$this->logFile}. Path traversal attempt blocked.");
            return;
        }
        
        // Write to log file with error handling
        $result = file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            // Fallback to error_log if file writing fails
            error_log("Failed to write to log file: {$this->logFile}. Original message: {$message}");
        }
    }
    
    /**
     * Export all user data as JSON (GDPR: Right to data portability)
     * 
     * @param int $userId User ID
     * @return array Result with success status and data
     */
    public function exportUserData(int $userId): array {
        try {
            $this->log("Data export requested for user ID: {$userId}");
            
            $data = [
                'export_date' => date('Y-m-d H:i:s'),
                'user_id' => $userId
            ];
            
            // Get user basic information
            $stmt = $this->pdo->prepare("SELECT id, email, firstname, lastname, role, auth_source, last_login, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $data['user_info'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data['user_info']) {
                return [
                    'success' => false,
                    'message' => 'Benutzer nicht gefunden'
                ];
            }
            
            // Get user preferences
            $stmt = $this->pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            $data['preferences'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
            // Get notification preferences
            $stmt = $this->pdo->prepare("SELECT notify_news, notify_events, notify_projects FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $data['notification_preferences'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
            // Get alumni profile if exists
            $stmt = $this->pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            $data['alumni_profile'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
            // Get user skills
            $stmt = $this->pdo->prepare("SELECT skill_name, skill_level, years_experience, created_at FROM user_skills WHERE user_id = ?");
            $stmt->execute([$userId]);
            $data['skills'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Get event registrations (if table exists)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ehr.registered_at, ehs.task_name, ehs.start_time, ehs.end_time, e.title as event_title
                    FROM event_helper_registrations ehr
                    JOIN event_helper_slots ehs ON ehr.slot_id = ehs.id
                    JOIN events e ON ehs.event_id = e.id
                    WHERE ehr.user_id = ?
                ");
                $stmt->execute([$userId]);
                $data['event_registrations'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                $data['event_registrations'] = [];
            }
            
            // Get news subscriptions (if table exists)
            try {
                $stmt = $this->pdo->prepare("SELECT subscribed_at FROM news_subscribers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $data['news_subscription'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $e) {
                $data['news_subscription'] = null;
            }
            
            $this->log("Data export completed successfully for user ID: {$userId}");
            
            return [
                'success' => true,
                'data' => $data
            ];
        } catch (PDOException $e) {
            $this->log("Error exporting user data for user ID {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Fehler beim Exportieren der Daten'
            ];
        }
    }
    
    /**
     * Delete user account and all associated data (GDPR: Right to erasure)
     * 
     * @param int $userId User ID
     * @param string $confirmationEmail User's email for confirmation
     * @return array Result with success status and message
     */
    public function deleteUserAccount(int $userId, string $confirmationEmail): array {
        try {
            // Verify user owns this account
            $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Benutzer nicht gefunden'
                ];
            }
            
            // Verify email confirmation
            if (strtolower(trim($user['email'])) !== strtolower(trim($confirmationEmail))) {
                $this->log("Account deletion failed: Email mismatch for user ID {$userId}");
                return [
                    'success' => false,
                    'message' => 'E-Mail-Adresse stimmt nicht überein'
                ];
            }
            
            $this->log("Account deletion initiated for user ID: {$userId}, email: {$confirmationEmail}");
            
            // Begin transaction
            $this->pdo->beginTransaction();
            
            // Delete from user_preferences
            $stmt = $this->pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from alumni_profiles
            $stmt = $this->pdo->prepare("DELETE FROM alumni_profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from user_skills
            $stmt = $this->pdo->prepare("DELETE FROM user_skills WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from event_helper_registrations (if table exists)
            try {
                $stmt = $this->pdo->prepare("DELETE FROM event_helper_registrations WHERE user_id = ?");
                $stmt->execute([$userId]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from news_subscribers (if table exists)
            try {
                $stmt = $this->pdo->prepare("DELETE FROM news_subscribers WHERE user_id = ?");
                $stmt->execute([$userId]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from user_notifications (if table exists)
            try {
                $stmt = $this->pdo->prepare("DELETE FROM user_notifications WHERE user_id = ?");
                $stmt->execute([$userId]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from user_sessions
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Finally, delete the user account
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log out the user
            $this->logout();
            
            $this->log("Account deletion completed successfully for user ID: {$userId}");
            
            return [
                'success' => true,
                'message' => 'Ihr Konto wurde erfolgreich gelöscht'
            ];
        } catch (PDOException $e) {
            // Rollback on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->log("Error deleting user account for user ID {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Fehler beim Löschen des Kontos'
            ];
        }
    }
    
    /**
     * Validate password strength
     * Requirements: Min 12 chars, uppercase, lowercase, number, special char
     * 
     * @param string $password Password to validate
     * @return array Result with 'valid' (bool) and 'message' (string)
     */
    public function validatePasswordStrength(string $password): array {
        // Check minimum length
        if (strlen($password) < 12) {
            return [
                'valid' => false,
                'message' => 'Das Passwort muss mindestens 12 Zeichen lang sein.'
            ];
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Das Passwort muss mindestens einen Großbuchstaben enthalten.'
            ];
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Das Passwort muss mindestens einen Kleinbuchstaben enthalten.'
            ];
        }
        
        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Das Passwort muss mindestens eine Zahl enthalten.'
            ];
        }
        
        // Check for special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Das Passwort muss mindestens ein Sonderzeichen enthalten.'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Passwort erfüllt alle Anforderungen.'
        ];
    }
    
    /**
     * Update user password
     * Requires current password for verification
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password for verification
     * @param string $newPassword New password
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): array {
        try {
            // Get user from database
            $stmt = $this->pdo->prepare("SELECT id, email, password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->log("Password update failed: User not found: {$userId}");
                return [
                    'success' => false,
                    'message' => 'Benutzer nicht gefunden.'
                ];
            }
            
            // Check if user has a password set
            if (empty($user['password'])) {
                $this->log("Password update failed: No password set for user: {$userId}");
                return [
                    'success' => false,
                    'message' => 'Kein Passwort gesetzt. Bitte kontaktieren Sie den Administrator.'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                $this->log("Password update failed: Invalid current password for user: {$userId}");
                return [
                    'success' => false,
                    'message' => 'Das aktuelle Passwort ist nicht korrekt.'
                ];
            }
            
            // Validate new password strength
            $validation = $this->validatePasswordStrength($newPassword);
            if (!$validation['valid']) {
                $this->log("Password update failed: Weak password for user: {$userId}");
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Check that new password is different from current
            if (password_verify($newPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Das neue Passwort muss sich vom aktuellen Passwort unterscheiden.'
                ];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database
            $updateStmt = $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$hashedPassword, $userId]);
            
            if ($result) {
                $this->log("Password updated successfully for user ID: {$userId}");
                
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->log('security', 'password_changed', $userId, "User changed their password");
                }
                
                return [
                    'success' => true,
                    'message' => 'Ihr Passwort wurde erfolgreich geändert.'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Fehler beim Aktualisieren des Passworts.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during password update: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Update user email address
     * Sends confirmation email to new address
     * 
     * @param int $userId User ID
     * @param string $newEmail New email address
     * @param string $currentPassword Password for verification
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    public function updateEmail(int $userId, string $newEmail, string $currentPassword): array {
        try {
            // Validate and sanitize email
            $newEmail = trim($newEmail);
            
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Ungültige E-Mail-Adresse.'
                ];
            }
            
            // Get user from database
            $stmt = $this->pdo->prepare("SELECT id, email, password, firstname, lastname FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->log("Email update failed: User not found: {$userId}");
                return [
                    'success' => false,
                    'message' => 'Benutzer nicht gefunden.'
                ];
            }
            
            // Verify current password
            if (empty($user['password']) || !password_verify($currentPassword, $user['password'])) {
                $this->log("Email update failed: Invalid password for user: {$userId}");
                return [
                    'success' => false,
                    'message' => 'Das Passwort ist nicht korrekt.'
                ];
            }
            
            // Check if email is different from current
            if ($newEmail === $user['email']) {
                return [
                    'success' => false,
                    'message' => 'Die neue E-Mail-Adresse ist identisch mit der aktuellen.'
                ];
            }
            
            // Check if email already exists
            $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$newEmail, $userId]);
            if ($checkStmt->fetch()) {
                $this->log("Email update failed: Email already exists: {$newEmail}");
                return [
                    'success' => false,
                    'message' => 'Diese E-Mail-Adresse wird bereits verwendet.'
                ];
            }
            
            // Update email in database
            $updateStmt = $this->pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$newEmail, $userId]);
            
            if ($result) {
                $this->log("Email updated successfully for user ID: {$userId} from {$user['email']} to {$newEmail}");
                
                // Update session email immediately
                $_SESSION['email'] = $newEmail;
                
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->log('security', 'email_changed', $userId, "Email changed from {$user['email']} to {$newEmail}");
                }
                
                // Send confirmation email
                $emailSent = $this->sendEmailChangeConfirmation($newEmail, $user['firstname'], $user['lastname']);
                
                if (!$emailSent) {
                    $this->log("Warning: Confirmation email failed to send to {$newEmail}");
                    // Still return success since the email was updated in the database
                    // But inform the user that the confirmation email might not have been sent
                }
                
                return [
                    'success' => true,
                    'message' => $emailSent 
                        ? 'Ihre E-Mail-Adresse wurde erfolgreich geändert. Eine Bestätigungsmail wurde an die neue Adresse gesendet.'
                        : 'Ihre E-Mail-Adresse wurde erfolgreich geändert. Die Bestätigungsmail konnte nicht gesendet werden, aber die Änderung wurde gespeichert.'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Fehler beim Aktualisieren der E-Mail-Adresse.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during email update: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Send email change confirmation
     * 
     * @param string $email New email address
     * @param string $firstname User's first name
     * @param string $lastname User's last name
     * @return bool Success status
     */
    private function sendEmailChangeConfirmation(string $email, string $firstname, string $lastname): bool {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $this->log("Warning: PHPMailer not available for email confirmation");
                return false;
            }
            
            // Validate SMTP configuration
            if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS') || 
                empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
                $this->log("Warning: SMTP configuration incomplete, cannot send confirmation email");
                return false;
            }
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = defined('SMTP_SECURE') && constant('SMTP_SECURE') ? constant('SMTP_SECURE') : 'tls';
            $mail->Port       = defined('SMTP_PORT') ? (int)constant('SMTP_PORT') : 587;
            $mail->CharSet    = 'UTF-8';
            
            // Recipients - use SMTP_USER as fallback for from email
            $fromEmail = SMTP_USER;
            $fromName = 'IBC Intranet';
            
            if (defined('SMTP_FROM_EMAIL') && constant('SMTP_FROM_EMAIL') && !empty(constant('SMTP_FROM_EMAIL'))) {
                $fromEmail = constant('SMTP_FROM_EMAIL');
            }
            
            if (defined('SMTP_FROM_NAME') && constant('SMTP_FROM_NAME') && !empty(constant('SMTP_FROM_NAME'))) {
                $fromName = constant('SMTP_FROM_NAME');
            }
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, "{$firstname} {$lastname}");
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'E-Mail-Adresse erfolgreich geändert - IBC Intranet';
            
            $htmlBody = "
            <!DOCTYPE html>
            <html lang='de'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>IBC Intranet</h1>
                    </div>
                    <div class='content'>
                        <h2>E-Mail-Adresse erfolgreich geändert</h2>
                        <p>Hallo {$firstname} {$lastname},</p>
                        <p>Ihre E-Mail-Adresse für das IBC Intranet wurde erfolgreich geändert.</p>
                        <p><strong>Neue E-Mail-Adresse:</strong> {$email}</p>
                        <p>Falls Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie bitte umgehend den Administrator.</p>
                        <p>Mit freundlichen Grüßen,<br>Ihr IBC Team</p>
                    </div>
                    <div class='footer'>
                        <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $htmlBody;
            
            // Generate plain text alternative
            $plainText = "IBC Intranet\n\nE-Mail-Adresse erfolgreich geändert\n\nHallo {$firstname} {$lastname},\n\n";
            $plainText .= "Ihre E-Mail-Adresse für das IBC Intranet wurde erfolgreich geändert.\n\n";
            $plainText .= "Neue E-Mail-Adresse: {$email}\n\n";
            $plainText .= "Falls Sie diese Änderung nicht vorgenommen haben, kontaktieren Sie bitte umgehend den Administrator.\n\n";
            $plainText .= "Mit freundlichen Grüßen,\nIhr IBC Team\n\n";
            $plainText .= "Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.";
            $mail->AltBody = $plainText;
            
            $mail->send();
            return true;
            
        } catch (\Exception $e) {
            $this->log("Email confirmation send failed to {$email}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create an invitation token for a new user
     * Only admins and vorstand can create invitations
     * 
     * @param string $email Email address to invite
     * @param string $role Role to assign (default: 'alumni')
     * @param int $expirationHours Hours until token expires (default: 48)
     * @return array Result with success status, message, and token
     */
    public function createInvitation(string $email, string $role = 'alumni', int $expirationHours = 48): array {
        try {
            // Validate caller has permission
            $callerRole = $this->getUserRole();
            if (!in_array($callerRole, ['admin', 'vorstand'], true)) {
                $this->log("Invitation creation denied: insufficient permissions for role {$callerRole}");
                return [
                    'success' => false,
                    'message' => 'Keine Berechtigung. Nur Admins und Vorstand können Einladungen erstellen.'
                ];
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Ungültige E-Mail-Adresse.'
                ];
            }
            
            // Check if user already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Ein Benutzer mit dieser E-Mail-Adresse existiert bereits.'
                ];
            }
            
            // Check for existing pending invitation
            $stmt = $this->pdo->prepare("
                SELECT id FROM invitations 
                WHERE email = ? AND accepted_at IS NULL AND expires_at > NOW()
            ");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Es existiert bereits eine ausstehende Einladung für diese E-Mail-Adresse.'
                ];
            }
            
            // Generate cryptographic token
            $token = bin2hex(random_bytes(32)); // 64 character hex string
            
            // Calculate expiration time
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expirationHours} hours"));
            
            // Insert invitation
            $stmt = $this->pdo->prepare("
                INSERT INTO invitations (email, token, role, created_by, created_at, expires_at)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            
            $createdBy = $this->getUserId();
            $result = $stmt->execute([$email, $token, $role, $createdBy, $expiresAt]);
            
            if ($result) {
                $invitationId = (int)$this->pdo->lastInsertId();
                $this->log("Invitation created - ID: {$invitationId}, Email: {$email}, Role: {$role}, Created by: {$createdBy}");
                
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logAction(
                        $createdBy,
                        'create_invitation',
                        'invitations',
                        $invitationId,
                        "Invitation created for {$email} with role {$role}"
                    );
                }
                
                return [
                    'success' => true,
                    'message' => 'Einladung erfolgreich erstellt.',
                    'invitation_id' => $invitationId,
                    'token' => $token,
                    'expires_at' => $expiresAt
                ];
            }
            
            $this->log("Invitation creation failed: Database insert failed for email: {$email}");
            return [
                'success' => false,
                'message' => 'Fehler beim Erstellen der Einladung.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during invitation creation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Validate an invitation token
     * 
     * @param string $token The invitation token to validate
     * @return array Result with success status, message, and invitation data
     */
    public function validateInvitationToken(string $token): array {
        try {
            // Query for invitation
            $stmt = $this->pdo->prepare("
                SELECT id, email, role, created_by, created_at, expires_at, accepted_at
                FROM invitations
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                $this->log("Token validation failed: Token not found");
                return [
                    'success' => false,
                    'message' => 'Ungültiger Einladungs-Token.'
                ];
            }
            
            // Check if already accepted
            if ($invitation['accepted_at'] !== null) {
                $this->log("Token validation failed: Token already used - ID: {$invitation['id']}");
                return [
                    'success' => false,
                    'message' => 'Diese Einladung wurde bereits verwendet.'
                ];
            }
            
            // Check if expired
            if (strtotime($invitation['expires_at']) < time()) {
                $this->log("Token validation failed: Token expired - ID: {$invitation['id']}");
                return [
                    'success' => false,
                    'message' => 'Diese Einladung ist abgelaufen.'
                ];
            }
            
            $this->log("Token validated successfully - ID: {$invitation['id']}, Email: {$invitation['email']}");
            
            return [
                'success' => true,
                'message' => 'Token ist gültig.',
                'invitation' => $invitation
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during token validation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Register a new user using an invitation token
     * 
     * @param string $token Invitation token
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param string $password Password
     * @return array Result with success status and message
     */
    public function registerWithInvitation(string $token, string $firstname, string $lastname, string $password): array {
        try {
            // Validate token first
            $validation = $this->validateInvitationToken($token);
            if (!$validation['success']) {
                return $validation;
            }
            
            $invitation = $validation['invitation'];
            $email = $invitation['email'];
            $role = $invitation['role'];
            
            // Validate password strength
            $passwordCheck = $this->validatePasswordStrength($password);
            if (!$passwordCheck['valid']) {
                return [
                    'success' => false,
                    'message' => $passwordCheck['message']
                ];
            }
            
            // Validate names
            if (empty(trim($firstname)) || empty(trim($lastname))) {
                return [
                    'success' => false,
                    'message' => 'Vor- und Nachname sind erforderlich.'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Begin transaction
            $this->pdo->beginTransaction();
            
            try {
                // Create user account
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (email, firstname, lastname, role, password, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $result = $stmt->execute([$email, $firstname, $lastname, $role, $hashedPassword]);
                
                if (!$result) {
                    throw new Exception("Failed to create user account");
                }
                
                $newUserId = (int)$this->pdo->lastInsertId();
                
                // Mark invitation as accepted
                $stmt = $this->pdo->prepare("
                    UPDATE invitations 
                    SET accepted_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$invitation['id']]);
                
                // Commit transaction
                $this->pdo->commit();
                
                $this->log("User registered via invitation - User ID: {$newUserId}, Email: {$email}, Role: {$role}");
                
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logAction(
                        $newUserId,
                        'register_with_invitation',
                        'users',
                        $newUserId,
                        "User registered via invitation: {$email}"
                    );
                }
                
                return [
                    'success' => true,
                    'message' => 'Registrierung erfolgreich. Sie können sich jetzt anmelden.',
                    'user_id' => $newUserId
                ];
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (PDOException $e) {
            $this->log("Database error during registration with invitation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        } catch (Exception $e) {
            $this->log("Error during registration with invitation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Get pending invitations (for admin dashboard)
     * Only admins and vorstand can view invitations
     * 
     * @param int $limit Maximum number of invitations to return
     * @param int $offset Offset for pagination
     * @return array Result with success status and invitations list
     */
    public function getPendingInvitations(int $limit = 50, int $offset = 0): array {
        try {
            // Validate caller has permission
            $callerRole = $this->getUserRole();
            if (!in_array($callerRole, ['admin', 'vorstand'], true)) {
                return [
                    'success' => false,
                    'message' => 'Keine Berechtigung.'
                ];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT i.id, i.email, i.role, i.created_at, i.expires_at,
                       u.firstname, u.lastname
                FROM invitations i
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.accepted_at IS NULL AND i.expires_at > NOW()
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'invitations' => $invitations
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error fetching pending invitations: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
    
    /**
     * Delete an invitation (for canceling invitations)
     * Only admins and vorstand can delete invitations
     * 
     * @param int $invitationId The invitation ID to delete
     * @return array Result with success status and message
     */
    public function deleteInvitation(int $invitationId): array {
        try {
            // Validate caller has permission
            $callerRole = $this->getUserRole();
            if (!in_array($callerRole, ['admin', 'vorstand'], true)) {
                return [
                    'success' => false,
                    'message' => 'Keine Berechtigung.'
                ];
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM invitations WHERE id = ?");
            $result = $stmt->execute([$invitationId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->log("Invitation deleted - ID: {$invitationId} by user ID: " . $this->getUserId());
                
                if ($this->systemLogger) {
                    $this->systemLogger->logAction(
                        $this->getUserId(),
                        'delete_invitation',
                        'invitations',
                        $invitationId,
                        "Invitation deleted"
                    );
                }
                
                return [
                    'success' => true,
                    'message' => 'Einladung erfolgreich gelöscht.'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Einladung nicht gefunden.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error deleting invitation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten.'
            ];
        }
    }
}
