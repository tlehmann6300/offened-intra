# Hybrid Authentication System - Implementation Documentation

## Overview

The Auth.php class has been refactored to support a hybrid authentication system that handles both Microsoft SSO (for Board members/Vorstand) and Email/Password authentication (for Alumni members).

## Problem Statement

The original requirements (from German):

1. **Hybrid-Login**: Separate logic for Microsoft SSO (Vorstand) and Email/Password-Login (Alumni)
2. **Alumni-Management**: Add methods for board members to create Alumni profiles in User-DB
3. **Self-Service**: Create functions for Alumni to change email and password (with password_hash validation)
4. **Security**: Ensure session data is consistently checked across both databases

## Architecture

### Database Structure

The system uses a dual-database architecture:

- **User Database** (`DB_USER_*`): Stores all user accounts (SSO and password-based)
- **Content Database** (`DB_CONTENT_*`): Stores projects, inventory, events, news

### Authentication Methods

Two distinct authentication methods are supported:

1. **Microsoft SSO** (`'microsoft'`):
   - Used by Vorstand (Board members)
   - OAuth2 flow through Microsoft Entra ID (Azure AD)
   - No password stored in database
   - Auto-creates accounts with 'vorstand' role

2. **Email/Password** (`'password'`):
   - Used by Alumni members
   - Requires password stored in database using `password_hash()`
   - Created by Vorstand through `createAlumniAccount()` method
   - Supports self-service email and password changes

## Key Methods

### Authentication Methods

#### `login(string $email, string $password, ?string $recaptchaResponse = null): array`

Main entry point for password-based authentication. Delegates to `loginWithPassword()`.

**Parameters:**
- `$email`: User's email address
- `$password`: User's password
- `$recaptchaResponse`: Optional reCAPTCHA token

**Returns:**
```php
[
    'success' => bool,
    'message' => string
]
```

**Usage:**
```php
$result = $auth->login('alumni@example.com', 'SecureP@ssw0rd!');
if ($result['success']) {
    // Login successful
}
```

#### `loginWithPassword(string $email, string $password, ?string $recaptchaResponse = null): array`

Dedicated method for email/password authentication (Alumni).

**Features:**
- Validates email format
- Checks rate limiting (5 attempts per 15 minutes)
- Verifies password using `password_verify()`
- Creates session with `auth_method = 'password'`
- Logs all authentication attempts

**Security:**
- Rate limiting per IP address
- Failed attempts are logged
- Empty credentials rejected
- Users without passwords are redirected to SSO

#### `loginWithMicrosoft(string $email, string $firstname, string $lastname, string $microsoftId): array`

Dedicated method for Microsoft SSO authentication (Vorstand).

**Parameters:**
- `$email`: Email from Microsoft account
- `$firstname`: First name from Microsoft account
- `$lastname`: Last name from Microsoft account
- `$microsoftId`: Microsoft user ID (reserved for future use)

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'user' => array,          // User data from database
    'is_new_user' => bool     // True if account was just created
]
```

**Features:**
- Auto-creates user accounts with 'vorstand' role
- No password stored (SSO-only)
- Validates email format
- Creates session with `auth_method = 'microsoft'`

**Usage (from microsoft_callback.php):**
```php
$loginResult = $auth->loginWithMicrosoft($userEmail, $firstname, $lastname, $microsoftId);
if ($loginResult['success']) {
    header('Location: index.php?page=home&login=success');
}
```

### Session Management

#### `validateSessionConsistency(): array`

Validates session data against the User database to prevent session hijacking.

**Returns:**
```php
[
    'valid' => bool,
    'message' => string,
    'user' => array  // Current user data from database (if valid)
]
```

**Features:**
- Checks if user still exists in database
- Validates email matches session
- Auto-updates role if changed in database
- Updates user info (firstname, lastname) if changed

**Usage:**
```php
$validation = $auth->validateSessionConsistency();
if (!$validation['valid']) {
    // Session invalid - force logout
    $auth->logout();
    header('Location: index.php?page=login');
}
```

#### `getAuthMethod(): ?string`

Returns the authentication method for the current session.

**Returns:** `'password'`, `'microsoft'`, or `null` (not logged in)

**Usage:**
```php
$authMethod = $auth->getAuthMethod();
if ($authMethod === 'password') {
    // Alumni user - show password change option
} elseif ($authMethod === 'microsoft') {
    // SSO user - hide password change option
}
```

### Alumni Management (Vorstand only)

#### `createAlumniAccount(string $email, string $firstname, string $lastname, string $password): array`

Creates a new Alumni account with email/password authentication.

**Authorization:** Requires `'vorstand'` or `'admin'` role

**Parameters:**
- `$email`: Email address for the account
- `$firstname`: First name
- `$lastname`: Last name
- `$password`: Initial password (will be hashed)

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'user_id' => int  // ID of created user (if successful)
]
```

**Validation:**
- Email format validation
- Password minimum length: 8 characters
- Checks for duplicate email addresses
- Verifies current user has permission

**Usage:**
```php
if ($auth->checkPermission('vorstand')) {
    $result = $auth->createAlumniAccount(
        'newalumni@example.com',
        'Max',
        'Mustermann',
        'InitialP@ssw0rd123'
    );
}
```

### Self-Service Methods (Alumni)

#### `updateEmail(int $userId, string $newEmail, string $currentPassword): array`

Allows Alumni users to change their email address.

**Parameters:**
- `$userId`: User ID
- `$newEmail`: New email address
- `$currentPassword`: Current password for verification

**Features:**
- Validates new email format
- Verifies current password
- Checks for duplicate emails
- Sends confirmation email to new address
- Updates session immediately

**Returns:**
```php
[
    'success' => bool,
    'message' => string
]
```

#### `updatePassword(int $userId, string $currentPassword, string $newPassword): array`

Allows Alumni users to change their password.

**Parameters:**
- `$userId`: User ID
- `$currentPassword`: Current password for verification
- `$newPassword`: New password

**Features:**
- Verifies current password
- Validates new password strength (see below)
- Ensures new password differs from current
- Logs password change

**Returns:**
```php
[
    'success' => bool,
    'message' => string
]
```

#### `validatePasswordStrength(string $password): array`

Validates password against security requirements.

**Requirements:**
- Minimum 12 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

**Returns:**
```php
[
    'valid' => bool,
    'message' => string  // Specific requirement that failed
]
```

## Security Features

### Rate Limiting

- **Limit:** 5 failed login attempts per IP address
- **Window:** 15 minutes
- **Storage:** JSON file (`logs/login_attempts.json`)
- **Auto-cleanup:** Old attempts are automatically removed

### Session Security

- **CSRF Protection:** Tokens generated for all sessions
- **Session Regeneration:** ID regenerated after successful login
- **Timeout Check:** Sessions expire after configured lifetime
- **Consistency Validation:** Session data validated against database

### Password Security

- **Hashing:** Uses `password_hash()` with `PASSWORD_DEFAULT` (bcrypt)
- **Verification:** Uses `password_verify()` with timing attack protection
- **Strength Validation:** Enforces complex password requirements
- **No Plain Text:** Passwords never stored in plain text

### Database Security

- **Prepared Statements:** All queries use PDO prepared statements
- **Input Validation:** Email format, password strength validated
- **SQL Injection Prevention:** Parameterized queries throughout
- **Error Logging:** Errors logged without exposing sensitive data

## Migration Guide

### For Existing Code

If you have existing code using the old `login()` method, no changes are required. The method signature remains compatible and delegates to the new `loginWithPassword()` method.

**Before:**
```php
$result = $auth->login($username, $password);
```

**After (still works):**
```php
$result = $auth->login($email, $password);
```

**New explicit methods (recommended):**
```php
// For password-based authentication
$result = $auth->loginWithPassword($email, $password);

// For Microsoft SSO authentication
$result = $auth->loginWithMicrosoft($email, $firstname, $lastname, $microsoftId);
```

### For Microsoft SSO Callback

Update your `microsoft_callback.php` to use the new method:

**Before:**
```php
// Manual session creation
$_SESSION['user_id'] = $user['id'];
$_SESSION['auth_method'] = 'microsoft';
// etc...
```

**After:**
```php
$loginResult = $auth->loginWithMicrosoft($userEmail, $firstname, $lastname, $microsoftId);
if ($loginResult['success']) {
    // Session already created
    header('Location: index.php?page=home&login=success');
}
```

## Testing

### Unit Tests

A test script (`test_auth_refactoring.php`) validates:

1. PHP syntax validation
2. All required methods exist
3. Method signatures are correct
4. Documentation is comprehensive
5. Password hashing is properly implemented

**Run tests:**
```bash
php test_auth_refactoring.php
```

### Integration Testing

For full integration testing with database:

1. Create test users with both authentication methods
2. Test login flows for both methods
3. Verify session consistency validation
4. Test self-service email/password changes
5. Test Alumni account creation by Vorstand

## Best Practices

### For Developers

1. **Always use prepared statements** for database queries
2. **Validate input** before processing (email format, password strength)
3. **Log security events** (login attempts, password changes)
4. **Check permissions** before sensitive operations
5. **Use consistent error messages** to avoid information leakage

### For Alumni Users

1. **Use strong passwords** (12+ characters with complexity)
2. **Change default passwords** immediately after account creation
3. **Keep email addresses current** for account recovery
4. **Report suspicious activity** to administrators

### For Vorstand Users

1. **Use Microsoft SSO** for authentication (more secure)
2. **Create Alumni accounts** only for verified members
3. **Use strong initial passwords** when creating Alumni accounts
4. **Verify email addresses** before creating accounts

## Troubleshooting

### "No password set - SSO required" Error

**Cause:** User account exists but has no password (SSO-only account)

**Solution:** Either:
- Use Microsoft SSO login button
- Contact Vorstand to add password via `createAlumniAccount()`

### "Rate limit exceeded" Error

**Cause:** Too many failed login attempts from your IP address

**Solution:** Wait 15 minutes before trying again

### Session Invalid After Login

**Cause:** Session validation failing due to database inconsistency

**Solution:**
1. Check database connection is working
2. Verify user still exists in database
3. Clear browser cookies and try again

## Future Enhancements

Potential improvements for future versions:

1. **Two-Factor Authentication (2FA)** for password-based accounts
2. **Password reset via email** with secure tokens
3. **Account linking** to connect SSO and password accounts
4. **Audit trail** using the reserved `$microsoftId` parameter
5. **IP allowlist/blocklist** for additional security
6. **Geo-blocking** for suspicious login locations

## References

- [PHP password_hash() Documentation](https://www.php.net/manual/en/function.password-hash.php)
- [Microsoft Entra ID OAuth2 Flow](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

## Version History

- **v2.0.0** (2026-01-31): Hybrid authentication system implemented
  - Added `loginWithPassword()` and `loginWithMicrosoft()` methods
  - Added `validateSessionConsistency()` for security
  - Enhanced documentation and self-service features
  - Changed default SSO role from 'mitglied' to 'vorstand'

- **v1.0.0**: Original authentication system
  - Single `login()` method
  - Basic session management
