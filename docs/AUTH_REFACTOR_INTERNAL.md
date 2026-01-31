# Auth.php Refactoring - Internal Login System

## Overview

This document describes the refactoring of `Auth.php` to implement a pure internal login system with the following features:

1. **Removed Microsoft SSO** - All Microsoft Single Sign-On references have been removed
2. **TOTP 2FA** - Time-based One-Time Password two-factor authentication
3. **Database-based Rate Limiting** - Uses `login_attempts` table instead of JSON files
4. **Secure Password Handling** - All accounts use `password_hash()` and `password_verify()`

## Changes Made

### 1. Removed Microsoft SSO

**Files Deleted:**
- `templates/pages/microsoft_login.php`
- `templates/pages/microsoft_callback.php`

**Code Removed from Auth.php:**
- `loginWithMicrosoft()` method
- All Microsoft SSO references in comments and error messages

**Updated Routes (index.php):**
- Removed `microsoft_login` and `microsoft_callback` from public pages
- Updated `$pagesWithoutLayout` to remove `microsoft_callback`

### 2. Added TOTP 2FA Support

**New Dependencies:**
- Added `sonata-project/google-authenticator` package via Composer
- **Note:** This package is marked as abandoned. Consider migrating to `spomky-labs/otphp` or `pragmarx/google2fa` in the future for continued security updates.

**New Methods in Auth.php:**
- `generateTotpSecret()` - Generate a new TOTP secret for a user
- `verifyTotpCode($secret, $code)` - Verify a TOTP code (private)
- `enableTotp($userId, $secret, $verificationCode)` - Enable 2FA for a user
- `disableTotp($userId, $password)` - Disable 2FA for a user  
- `getTotpQrCodeUrl($email, $secret, $issuer)` - Get QR code URL for setup
- `isTotpEnabled($userId)` - Check if user has 2FA enabled

**Updated Login Flow:**
1. User enters email and password
2. If password is correct and 2FA is enabled:
   - Sets `requires_2fa` flag in response
   - User must enter 6-digit TOTP code
3. System verifies TOTP code before granting access

### 3. Database-Based Rate Limiting

**Migration: 003_add_login_attempts_table.sql**
```sql
CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) DEFAULT 0,
  user_agent VARCHAR(500) DEFAULT NULL,
  KEY idx_ip_time (ip_address, attempt_time),
  KEY idx_email_time (email, attempt_time)
);
```

**Changes to Auth.php:**
- Replaced file-based rate limiting (`login_attempts.json`) with database queries
- `isRateLimited()` now checks both IP and email-based limits
- `recordLoginAttempt()` writes to database with success flag
- `cleanupOldAttempts()` removes old entries (periodically, 1% chance per login)

**Configuration:**
- `MAX_LOGIN_ATTEMPTS = 5` (unchanged)
- `RATE_LIMIT_WINDOW = 900` seconds (15 minutes, unchanged)

### 4. TOTP Database Fields

**Migration: 004_add_totp_to_users.sql**
```sql
ALTER TABLE users 
ADD COLUMN totp_secret VARCHAR(32) DEFAULT NULL,
ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0,
ADD COLUMN totp_verified_at TIMESTAMP NULL DEFAULT NULL;
```

### 5. Updated Login Template

**File: templates/login.php**

**Changes:**
- Removed Microsoft SSO button and branding
- Changed "Admin-Login" to just "Login"
- Changed input from `username` to `email`
- Added TOTP code field (shown when `requires_2fa=1` in URL)
- Updated footer text to mention 2FA

**Form Fields:**
1. Email address (required)
2. Password (required)
3. 6-digit authentication code (shown when 2FA required)

### 6. Updated Login Handler

**File: index.php**

**Changes:**
- Updated `admin_login` handler to accept `email`, `password`, and `totp_code`
- Added handling for `requires_2fa` response
- Redirects to login page with `requires_2fa=1` when 2FA code needed

## Installation & Setup

### 1. Install Dependencies

```bash
composer install
```

This will install the TOTP library (`sonata-project/google-authenticator`).

### 2. Run Database Migrations

Execute the following SQL files on your User database:

```bash
mysql -u <user> -p <database> < migrations/003_add_login_attempts_table.sql
mysql -u <user> -p <database> < migrations/004_add_totp_to_users.sql
```

### 3. Verify Installation

Run the test script to verify all components are working:

```bash
php tests/test_auth_refactor.php
```

Expected output:
- ✓ login_attempts table exists
- ✓ users table has TOTP fields
- ✓ TOTP secret generated successfully
- ✓ QR code URL generated correctly
- ✓ Microsoft SSO methods removed
- ✓ All required methods exist
- ✓ password_hash and password_verify working
- ✓ login() method has correct signature

## Usage

### For Users

**1. Login without 2FA:**
```
Email: user@example.com
Password: SecurePassword123!
→ Success
```

**2. Login with 2FA:**
```
Email: user@example.com
Password: SecurePassword123!
→ System prompts for 2FA code
6-digit code: 123456
→ Success
```

### For Administrators

**Setting up 2FA for a user:**

```php
// 1. Generate secret
$secret = $auth->generateTotpSecret();

// 2. Get QR code URL
$qrUrl = $auth->getTotpQrCodeUrl($userEmail, $secret);
// Display QR code to user (they scan with Google Authenticator app)

// 3. User enters verification code
$verificationCode = "123456"; // From user's app

// 4. Enable 2FA
$result = $auth->enableTotp($userId, $secret, $verificationCode);
```

**Disabling 2FA:**

```php
$result = $auth->disableTotp($userId, $userPassword);
```

## Security Features

### Password Security
- All passwords use `password_hash()` with `PASSWORD_DEFAULT` algorithm (bcrypt)
- Passwords are verified with `password_verify()`
- No plaintext passwords are stored

### Rate Limiting
- **IP-based limiting:** Max 5 failed attempts per IP in 15 minutes
- **Email-based limiting:** Max 5 failed attempts per email in 15 minutes
- Locks account/IP after exceeding limit
- Automatic cleanup of old attempts

### TOTP 2FA
- Uses industry-standard TOTP (RFC 6238)
- 6-digit codes, 30-second time windows
- 1 time period drift tolerance (±30 seconds for clock skew)
- Compatible with Google Authenticator, Authy, Microsoft Authenticator

### Session Security
- Session regeneration on login
- CSRF token protection
- Session timeout checks
- Database consistency validation

## API Reference

### Auth Methods

#### `login(string $email, string $password, ?string $totpCode = null): array`
Main login method.

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'requires_2fa' => bool  // Only if 2FA needed
]
```

#### `generateTotpSecret(): string`
Generate a new TOTP secret.

**Returns:** Base32-encoded 16-character secret

#### `enableTotp(int $userId, string $secret, string $verificationCode): array`
Enable 2FA for a user after verifying the setup code.

#### `disableTotp(int $userId, string $password): array`
Disable 2FA for a user (requires password verification).

#### `getTotpQrCodeUrl(string $email, string $secret, string $issuer = 'IBC-Intra'): string`
Get QR code URL for TOTP setup.

**Returns:** `otpauth://totp/...` URL

#### `isTotpEnabled(int $userId): bool`
Check if user has 2FA enabled.

## Troubleshooting

### Database Connection Errors
Ensure the User database credentials in `.env` are correct:
```
DB_USER_HOST=...
DB_USER_NAME=...
DB_USER_USER=...
DB_USER_PASS=...
```

### TOTP Codes Not Working
1. Verify server time is correct (`date`)
2. Ensure user's device time is synchronized
3. Check `totp_secret` is stored correctly in database
4. TOTP allows ±30 seconds clock drift

### Rate Limiting Issues
Check the `login_attempts` table:
```sql
SELECT * FROM login_attempts 
WHERE ip_address = 'xxx.xxx.xxx.xxx' 
ORDER BY attempt_time DESC 
LIMIT 10;
```

Clear rate limits manually if needed:
```sql
DELETE FROM login_attempts 
WHERE ip_address = 'xxx.xxx.xxx.xxx';
```

## Migration Notes

### From Microsoft SSO to Internal Auth

**For existing users with no password:**
1. Admin must create a password for the user using `createAlumniAccount()` or manually:
   ```php
   $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
   $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
   $stmt->execute([$hashedPassword, $userId]);
   ```

2. User can then login with email/password

**TOTP is Optional:**
- Users can login without 2FA if `totp_enabled = 0`
- Administrators should encourage 2FA for security
- 2FA can be made mandatory by modifying `loginWithPassword()`

## Future Enhancements

1. **Migrate TOTP Library:** Replace `sonata-project/google-authenticator` with `spomky-labs/otphp` or `pragmarx/google2fa` (abandoned package)
2. **Backup Codes:** Generate one-time backup codes for 2FA
3. **Recovery Email:** Add password reset via email
4. **Login Notifications:** Email alerts for new logins
5. **Device Trust:** Remember trusted devices for 30 days
6. **Admin Dashboard:** View login attempts and security events

## Related Files

- `/src/Auth.php` - Main authentication class
- `/templates/login.php` - Login form
- `/index.php` - Login handler and routing
- `/config/db.php` - Database configuration
- `/migrations/003_add_login_attempts_table.sql`
- `/migrations/004_add_totp_to_users.sql`
- `/tests/test_auth_refactor.php` - Verification tests

## Support

For issues or questions, please contact the development team or file an issue in the repository.
