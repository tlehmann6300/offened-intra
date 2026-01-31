# Migration Guide: Microsoft SSO to Internal Authentication

## Overview

This guide helps administrators migrate from the hybrid Microsoft SSO + Password authentication system to a pure internal authentication system with TOTP 2FA.

## Pre-Migration Checklist

- [ ] Backup the User database
- [ ] Review list of all active users
- [ ] Identify users without passwords (SSO-only accounts)
- [ ] Notify users about the authentication changes
- [ ] Prepare temporary passwords for SSO-only users

## Step 1: Database Backup

Before making any changes, create a complete backup of the User database:

```bash
# Backup User database
mysqldump -u <username> -p <database_name> > user_db_backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup
ls -lh user_db_backup_*.sql
```

## Step 2: Identify Users Without Passwords

Run this query to find users who currently use Microsoft SSO (no password set):

```sql
SELECT id, email, firstname, lastname, role, created_at 
FROM users 
WHERE password IS NULL OR password = '';
```

Export this list for reference:

```bash
mysql -u <username> -p <database_name> -e "
SELECT id, email, firstname, lastname, role 
FROM users 
WHERE password IS NULL OR password = ''
" > sso_users.txt
```

## Step 3: Run Database Migrations

Execute the migrations to add required tables and fields:

```bash
# Migration 1: Add login_attempts table
mysql -u <username> -p <database_name> < migrations/003_add_login_attempts_table.sql

# Migration 2: Add TOTP fields to users table
mysql -u <username> -p <database_name> < migrations/004_add_totp_to_users.sql
```

Verify migrations:

```sql
-- Check login_attempts table exists
SHOW TABLES LIKE 'login_attempts';

-- Check TOTP fields in users table
DESCRIBE users;
-- Should show: totp_secret, totp_enabled, totp_verified_at
```

## Step 4: Install Dependencies

Update Composer dependencies to include the TOTP library:

```bash
cd /path/to/intranet
composer install
```

Verify installation:

```bash
composer show sonata-project/google-authenticator
```

## Step 5: Create Passwords for SSO-Only Users

For each user without a password, you have two options:

### Option A: Generate Temporary Passwords (Recommended)

```php
<?php
// Script: create_temp_passwords.php
require_once 'vendor/autoload.php';
require_once 'config/db.php';

$userPdo = DatabaseManager::getUserConnection();

// Get users without passwords
$stmt = $userPdo->query("
    SELECT id, email, firstname, lastname 
    FROM users 
    WHERE password IS NULL OR password = ''
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    // Generate random secure password
    $tempPassword = bin2hex(random_bytes(8)); // 16 character password
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $updateStmt = $userPdo->prepare("
        UPDATE users SET password = ? WHERE id = ?
    ");
    $updateStmt->execute([$hashedPassword, $user['id']]);
    
    echo "User: {$user['email']}\n";
    echo "Temp Password: {$tempPassword}\n";
    echo "---\n";
    
    // TODO: Send email to user with temporary password
    // TODO: Force password change on first login
}
```

### Option B: Let Users Reset Passwords

1. Users visit the login page
2. Click "Forgot Password?" link (if implemented)
3. Receive password reset email
4. Set their own password

## Step 6: Deploy Updated Code

```bash
# Pull latest code
git pull origin main

# Clear any caches
rm -rf logs/login_attempts.json  # Old file-based rate limiting (no longer used)

# Restart web server if needed
sudo systemctl restart apache2  # or nginx
```

## Step 7: Verify Deployment

Run the test script:

```bash
php tests/test_auth_refactor.php
```

Expected output:
```
✓ login_attempts table exists
✓ users table has TOTP fields
✓ TOTP secret generated successfully
✓ Microsoft SSO methods removed
✓ All required methods exist
```

## Step 8: Test Login Flow

1. **Test without 2FA:**
   - Login with email and password
   - Should succeed immediately

2. **Test with 2FA:**
   - Enable 2FA for a test user (see below)
   - Login with email and password
   - Enter 6-digit TOTP code
   - Should succeed

## Enabling TOTP 2FA for Users

Users can enable 2FA from their profile settings:

### Admin Enabling 2FA for a User

```php
<?php
require_once 'vendor/autoload.php';
require_once 'config/db.php';

$userPdo = DatabaseManager::getUserConnection();
$auth = new Auth($userPdo);

// User information
$userId = 123;  // User ID from database
$userEmail = "user@example.com";

// 1. Generate TOTP secret
$secret = $auth->generateTotpSecret();
echo "TOTP Secret: {$secret}\n";

// 2. Generate QR code URL
$qrUrl = $auth->getTotpQrCodeUrl($userEmail, $secret);
echo "QR Code URL: {$qrUrl}\n";
echo "\n";
echo "User should scan this QR code with Google Authenticator app:\n";
echo "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($qrUrl) . "\n";

// 3. User scans QR code and enters verification code
echo "\nEnter 6-digit verification code: ";
$verificationCode = trim(fgets(STDIN));

// 4. Enable 2FA
$result = $auth->enableTotp($userId, $secret, $verificationCode);
if ($result['success']) {
    echo "✓ 2FA enabled successfully!\n";
} else {
    echo "✗ Failed to enable 2FA: {$result['message']}\n";
}
```

## Step 9: User Communication

### Email Template for Users

**Subject:** Important: Authentication System Update

Dear [Name],

We have updated our authentication system to improve security. Here's what you need to know:

**What Changed:**
- Microsoft Single Sign-On (SSO) is no longer available
- You now login with your email address and password
- Optional: Two-Factor Authentication (2FA) is now available

**Your Action Required:**
[If user had no password]
1. Your temporary password is: **[TEMP_PASSWORD]**
2. Login at: [LOGIN_URL]
3. Change your password immediately after login

**Recommended:**
- Enable Two-Factor Authentication (2FA) for enhanced security
- Use a strong, unique password

**Need Help?**
Contact IT support at [SUPPORT_EMAIL]

Best regards,
IT Team

---

## Step 10: Monitor Login Activity

After deployment, monitor the `login_attempts` table for issues:

```sql
-- Check recent login attempts
SELECT 
    ip_address, 
    email, 
    attempt_time, 
    success,
    COUNT(*) as attempt_count
FROM login_attempts
WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address, email, success
ORDER BY attempt_time DESC;

-- Check failed login patterns
SELECT 
    ip_address,
    COUNT(*) as failed_attempts,
    MAX(attempt_time) as last_attempt
FROM login_attempts
WHERE success = 0
  AND attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
HAVING failed_attempts >= 5
ORDER BY failed_attempts DESC;
```

## Rollback Procedure (If Needed)

If you need to rollback the changes:

1. **Restore database backup:**
   ```bash
   mysql -u <username> -p <database_name> < user_db_backup_YYYYMMDD_HHMMSS.sql
   ```

2. **Revert code changes:**
   ```bash
   git checkout <previous_commit_hash>
   composer install
   ```

3. **Restart web server:**
   ```bash
   sudo systemctl restart apache2
   ```

## Troubleshooting

### Users Can't Login

**Symptom:** Users get "Invalid credentials" error

**Solutions:**
1. Check if user has a password set:
   ```sql
   SELECT id, email, password FROM users WHERE email = 'user@example.com';
   ```
2. If password is NULL, create one (see Step 5)
3. Verify email format is correct

### Rate Limiting Issues

**Symptom:** "Too many login attempts" error

**Solutions:**
1. Clear rate limits for user:
   ```sql
   DELETE FROM login_attempts WHERE email = 'user@example.com';
   DELETE FROM login_attempts WHERE ip_address = 'xxx.xxx.xxx.xxx';
   ```
2. Increase rate limit window (not recommended):
   ```php
   // In Auth.php
   private const RATE_LIMIT_WINDOW = 1800; // 30 minutes instead of 15
   ```

### TOTP Codes Not Working

**Symptom:** 2FA codes always fail

**Solutions:**
1. Check server time is correct:
   ```bash
   date
   ntpdate -q pool.ntp.org
   ```
2. Verify user's device time is synchronized
3. Check TOTP secret in database:
   ```sql
   SELECT totp_secret, totp_enabled FROM users WHERE id = 123;
   ```
4. TOTP allows ±30 seconds clock drift

### Database Connection Errors

**Symptom:** "Database error" on login page

**Solutions:**
1. Check database credentials in `.env`:
   ```bash
   cat .env | grep DB_USER
   ```
2. Test database connection:
   ```bash
   mysql -u <username> -p -h <host> <database>
   ```
3. Check database server is running

## Post-Migration Tasks

- [ ] Update user documentation/wiki
- [ ] Update onboarding materials
- [ ] Remove Microsoft Azure AD app registration (if no longer needed)
- [ ] Update password policy documentation
- [ ] Consider implementing password reset functionality
- [ ] Set up monitoring alerts for failed login attempts
- [ ] Review and update security documentation

## Security Recommendations

1. **Enforce 2FA for Administrators:**
   ```sql
   -- Check which admins don't have 2FA
   SELECT id, email, role, totp_enabled 
   FROM users 
   WHERE role IN ('admin', 'vorstand') 
   AND totp_enabled = 0;
   ```

2. **Regular Password Audits:**
   - Implement password expiration (e.g., 90 days)
   - Check for weak passwords (add password strength requirements)
   - Monitor for compromised passwords (Have I Been Pwned API)

3. **Monitor Login Patterns:**
   - Set up alerts for unusual login times
   - Alert on multiple failed login attempts
   - Track login locations (if available)

## Support Contacts

- **Technical Issues:** IT Support <it-support@company.com>
- **Security Concerns:** Security Team <security@company.com>
- **Emergency Contact:** On-Call Engineer +XX-XXX-XXX-XXXX

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-31  
**Author:** Development Team
