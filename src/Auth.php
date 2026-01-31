<?php
declare(strict_types=1);

/**
 * Authentication Class
 * Handles user authentication and session management
 * Implements secure login with prepared statements and detailed error handling
 */
class Auth {
    private PDO $pdo;
    private string $logFile;
    private ?SystemLogger $systemLogger;
    private string $rateLimitFile;
    
    // Rate limiting configuration
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds
    
    public function __construct(PDO $pdo, ?SystemLogger $systemLogger = null) {
        $this->pdo = $pdo;
        $this->logFile = BASE_PATH . '/logs/app.log';
        $this->rateLimitFile = BASE_PATH . '/logs/login_attempts.json';
        $this->systemLogger = $systemLogger;
        
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
     * Check if IP address is rate limited
     * 
     * @param string $ip IP address to check
     * @return bool True if rate limited, false otherwise
     */
    private function isRateLimited(string $ip): bool {
        $attempts = $this->getLoginAttempts();
        
        if (!isset($attempts[$ip])) {
            return false;
        }
        
        $ipAttempts = $attempts[$ip];
        $now = time();
        
        // Filter out expired attempts
        $recentAttempts = array_filter($ipAttempts, function($timestamp) use ($now) {
            return ($now - $timestamp) <= self::RATE_LIMIT_WINDOW;
        });
        
        // Check if exceeded max attempts
        return count($recentAttempts) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record a failed login attempt for rate limiting
     * 
     * @param string $ip IP address
     */
    private function recordLoginAttempt(string $ip): void {
        $attempts = $this->getLoginAttempts();
        
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = [];
        }
        
        // Add current timestamp
        $attempts[$ip][] = time();
        
        // Clean up old attempts (older than rate limit window)
        $this->cleanupOldAttempts($attempts);
        
        // Save updated attempts
        $this->saveLoginAttempts($attempts);
    }

    /**
     * Get login attempts from file
     * 
     * @return array Login attempts data
     */
    private function getLoginAttempts(): array {
        if (!file_exists($this->rateLimitFile)) {
            return [];
        }
        
        $data = file_get_contents($this->rateLimitFile);
        if ($data === false) {
            $this->log("Warning: Failed to read rate limit file");
            return [];
        }
        
        $attempts = json_decode($data, true);
        return is_array($attempts) ? $attempts : [];
    }

    /**
     * Save login attempts to file
     * 
     * @param array $attempts Login attempts data
     */
    private function saveLoginAttempts(array $attempts): void {
        $json = json_encode($attempts, JSON_PRETTY_PRINT);
        
        // Check if file exists to set permissions on new files
        $fileExists = file_exists($this->rateLimitFile);
        
        $result = file_put_contents($this->rateLimitFile, $json, LOCK_EX);
        
        if ($result === false) {
            $this->log("Warning: Failed to write rate limit file");
        } elseif (!$fileExists) {
            // Set restrictive permissions on new file (read/write for owner only)
            @chmod($this->rateLimitFile, 0600);
        }
    }

    /**
     * Clean up old login attempts from data
     * 
     * @param array &$attempts Login attempts data (passed by reference)
     */
    private function cleanupOldAttempts(array &$attempts): void {
        $now = time();
        
        foreach ($attempts as $ip => $timestamps) {
            // Filter out expired attempts
            $filtered = array_filter($timestamps, function($timestamp) use ($now) {
                return ($now - $timestamp) <= self::RATE_LIMIT_WINDOW;
            });
            
            // Reset array indices for JSON serialization
            $attempts[$ip] = array_values($filtered);
            
            // Remove IP if no recent attempts
            if (empty($attempts[$ip])) {
                unset($attempts[$ip]);
            }
        }
    }

    /**
     * Login method with strict database authentication
     * Only allows users from the database with valid password hashes
     * 
     * @param string $username Username or email
     * @param string $password Password
     * @param string|null $recaptchaResponse reCAPTCHA response token (optional for now)
     * @return array Result array with 'success' (bool) and 'message' (string)
     */
    public function login(string $username, string $password, ?string $recaptchaResponse = null): array {
        try {
            // Get client IP for rate limiting
            $clientIp = $this->getClientIp();
            
            // Check rate limiting
            if ($this->isRateLimited($clientIp)) {
                $this->log("Login blocked: Rate limit exceeded for IP: " . $clientIp);
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($username, false, null, 'Rate limit exceeded');
                }
                $waitMinutes = (int)ceil(self::RATE_LIMIT_WINDOW / 60);
                return [
                    'success' => false,
                    'message' => "Zu viele Anmeldeversuche. Bitte warten Sie {$waitMinutes} Minuten und versuchen Sie es erneut."
                ];
            }
            
            // Log login attempt
            $this->log("Login attempt for user: " . $username);
            
            // Sanitize input
            $username = trim($username);
            $password = trim($password);
            
            // Check for empty credentials
            if (empty($username) || empty($password)) {
                $this->log("Login failed: Empty credentials for user: " . $username);
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($username, false, null, 'Empty credentials');
                }
                return [
                    'success' => false,
                    'message' => 'Bitte geben Sie Nutzername und Passwort ein.'
                ];
            }
            
            // Strict database authentication - fetch user from database
            $stmt = $this->pdo->prepare("SELECT id, email, role, password, firstname, lastname FROM users WHERE email = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->log("Login failed: User not found: " . $username);
                // Record failed attempt for rate limiting
                $this->recordLoginAttempt($clientIp);
                // Log to SystemLogger if available
                if ($this->systemLogger) {
                    $this->systemLogger->logLoginAttempt($username, false, null, 'User not found');
                }
                return [
                    'success' => false,
                    'message' => 'Ungültige Anmeldedaten. Bitte überprüfen Sie Nutzername und Passwort.'
                ];
            }
            
            // Check if user has a password set (for manual authentication)
            if (!empty($user['password'])) {
                // Verify password hash
                if (password_verify($password, $user['password'])) {
                    // Log successful login
                    if ($this->systemLogger) {
                        $this->systemLogger->logLoginAttempt($username, true, (int)$user['id'], null);
                    }
                    return $this->createUserSession($user, 'manual');
                } else {
                    $this->log("Login failed: Invalid password for user: " . $username);
                    // Record failed attempt for rate limiting
                    $this->recordLoginAttempt($clientIp);
                    // Log to SystemLogger if available
                    if ($this->systemLogger) {
                        $this->systemLogger->logLoginAttempt($username, false, (int)$user['id'], 'Invalid password');
                    }
                    return [
                        'success' => false,
                        'message' => 'Ungültiges Passwort. Bitte versuchen Sie es erneut.'
                    ];
                }
            }
            
            // No password set - user must use Microsoft SSO
            $this->log("Login failed: No password set for user: " . $username);
            // Log to SystemLogger if available
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($username, false, (int)$user['id'], 'No password set - SSO required');
            }
            return [
                'success' => false,
                'message' => 'Bitte verwenden Sie Microsoft SSO zur Anmeldung.'
            ];
            
        } catch (PDOException $e) {
            $this->log("Database error during login: " . $e->getMessage());
            // Log to SystemLogger if available
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($username, false, null, 'Database error');
            }
            return [
                'success' => false,
                'message' => 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
            ];
        } catch (Exception $e) {
            $this->log("Unexpected error during login: " . $e->getMessage());
            // Log to SystemLogger if available
            if ($this->systemLogger) {
                $this->systemLogger->logLoginAttempt($username, false, null, 'Unexpected error');
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
     * @param string $authMethod Authentication method (manual, microsoft)
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
        
        // Define permission matrix
        $permissions = [
            'vorstand' => ['*'], // Full access
            'admin' => ['*'], // Full access
            'ressortleiter' => ['edit_news', 'edit_projects', 'edit_events', 'apply_projects', 'edit_own_profile', 'edit_inventory'],
            'alumni' => ['edit_own_profile', 'edit_inventory'],
            'mitglied' => ['edit_own_profile', 'apply_projects'],
        ];
        
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
     * Role hierarchy definition (higher number = more privileges)
     * This constant makes it easier to maintain and modify role hierarchies
     * 
     * @var array<string, int>
     */
    private const ROLE_HIERARCHY = [
        'none' => 0,
        'mitglied' => 1,
        'alumni' => 2,
        'ressortleiter' => 3,
        'vorstand' => 4,
        'admin' => 5,
    ];
    
    /**
     * Check if current user has the required role or higher in the hierarchy
     * This method enforces role-based access control and must be called at the beginning of API actions.
     * The visibility of buttons in the frontend should never be the only security barrier.
     * 
     * Role hierarchy (highest to lowest):
     * - admin: Full system access
     * - vorstand: Board member access
     * - ressortleiter: Department leader access
     * - alumni: Alumni member access
     * - mitglied: Regular member access
     * - none: No access
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
        
        if (!$hasPermission) {
            $this->log("Permission denied: User role '{$currentRole}' (level {$currentLevel}) insufficient for required role '{$requiredRole}' (level {$requiredLevel})");
        }
        
        return $hasPermission;
    }
    
    /**
     * Update user role in database
     * 
     * @param int $userId User ID
     * @param string $role New role to set
     * @return bool True on success, false on failure
     */
    public function updateUserRole(int $userId, string $role): bool {
        try {
            // Validate role
            $validRoles = ['none', 'mitglied', 'alumni', 'vorstand', 'ressortleiter', 'admin'];
            if (!in_array($role, $validRoles, true)) {
                $this->log("Invalid role attempted: {$role} for user ID: {$userId}");
                return false;
            }
            
            $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $result = $stmt->execute([$role, $userId]);
            
            if ($result) {
                // Update session role as well
                $_SESSION['role'] = $role;
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
}
