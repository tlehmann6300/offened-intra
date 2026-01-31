# Token-Based Invitation System

## Overview

The token-based invitation system allows administrators and board members (Vorstand) to invite new users to the IBC Intranet securely. Users can only register through unique, time-limited invitation links sent via email.

## Features

- **Secure Token Generation**: Uses cryptographically secure random tokens (64 characters)
- **Email Integration**: Sends beautiful HTML invitation emails via IONOS SMTP
- **Token Expiration**: Configurable expiration time (1-168 hours, default: 48 hours)
- **Role Assignment**: Specify user role during invitation (Alumni, Mitglied, Ressortleiter)
- **One-Time Use**: Tokens cannot be reused after registration
- **Admin Dashboard**: Easy-to-use interface for managing invitations
- **CSRF Protection**: All forms protected against cross-site request forgery
- **Audit Trail**: All invitation actions logged via SystemLogger

## Database Schema

### Invitations Table

```sql
CREATE TABLE `invitations` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `role` VARCHAR(50) NOT NULL DEFAULT 'alumni',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_email` (`email`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_accepted_at` (`accepted_at`),
  KEY `idx_pending_invitations` (`email`, `accepted_at`, `expires_at`)
);
```

## Installation

### 1. Run Database Migration

Run the migration to create the `invitations` table:

```bash
# Option 1: MySQL command line
mysql -u username -p database_name < migrations/006_add_invitations_table.sql

# Option 2: phpMyAdmin
# Copy contents of migrations/006_add_invitations_table.sql and execute in SQL tab

# Option 3: Direct SQL execution
mysql> SOURCE /path/to/migrations/006_add_invitations_table.sql;
```

### 2. Verify SMTP Configuration

Ensure IONOS SMTP is configured in `config/config.php`:

```php
define('SMTP_HOST', 'smtp.ionos.de');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'your-email@domain.com');
define('SMTP_PASS', 'your-password');
define('SMTP_FROM_EMAIL', 'your-email@domain.com');
define('SMTP_FROM_NAME', 'IBC Intranet');
```

### 3. Test the System

1. Log in as an admin or Vorstand member
2. Navigate to Admin Dashboard
3. Find the "Einladungsverwaltung" (Invitation Management) section
4. Create a test invitation

## Usage Guide

### For Administrators

#### Creating an Invitation

1. **Navigate to Admin Dashboard**
   - Go to `index.php?page=admin_dashboard`
   - Scroll to "Einladungsverwaltung" section

2. **Fill Out the Form**
   - **E-Mail-Adresse**: Enter the recipient's email address
   - **Rolle**: Select the role to assign (Alumni, Mitglied, Ressortleiter)
   - **Gültigkeitsdauer**: Choose expiration time (24h, 48h, 72h, 1 week)

3. **Send Invitation**
   - Click "Senden" button
   - System generates token, saves to database, and sends email
   - Success message confirms invitation was sent

4. **View Pending Invitations**
   - All pending invitations are listed in the table below
   - Shows: Email, Role, Created by, Created at, Expires at
   - Tokens expiring soon are highlighted in red

5. **Delete/Cancel Invitations**
   - Click "Löschen" button next to any invitation
   - Confirm deletion in dialog
   - Invitation is removed from database

#### Managing Invitations

**Check Invitation Status**
```sql
-- View all pending invitations
SELECT * FROM invitations 
WHERE accepted_at IS NULL 
  AND expires_at > NOW()
ORDER BY created_at DESC;

-- View all invitations (including used/expired)
SELECT 
  i.*,
  u.firstname,
  u.lastname
FROM invitations i
LEFT JOIN users u ON i.created_by = u.id
ORDER BY i.created_at DESC;
```

**Clean Up Expired Invitations**
```sql
-- Delete expired, unaccepted invitations
DELETE FROM invitations 
WHERE accepted_at IS NULL 
  AND expires_at < NOW();
```

### For Invited Users

#### Receiving an Invitation

1. **Check Email**
   - Look for email from IBC Intranet
   - Subject: "Einladung zur Registrierung - IBC Intranet"
   - Contains your role and expiration date

2. **Click Registration Link**
   - Link format: `https://your-domain.com/index.php?page=register&token=...`
   - Token is unique and time-limited

3. **Complete Registration**
   - Email address is pre-filled (read-only)
   - Enter: Vorname (First name)
   - Enter: Nachname (Last name)
   - Enter: Passwort (Password, min 8 characters)
   - Confirm: Passwort bestätigen (Password confirmation)

4. **Submit Registration**
   - Click "Registrierung abschließen"
   - Account is created with specified role
   - Token is marked as used and cannot be reused

5. **Login**
   - Redirected to login page
   - Use new email and password to log in
   - Set up 2FA if required

## API Endpoints

### 1. Send Invitation

**Endpoint**: `POST /api/send_invitation.php`

**Required Role**: admin, vorstand

**Parameters**:
```javascript
{
  csrf_token: string,      // CSRF protection token
  email: string,           // Email address to invite
  role: string,            // Role to assign (alumni, mitglied, ressortleiter)
  expiration_hours: int    // Hours until expiration (1-168, default: 48)
}
```

**Response**:
```javascript
{
  success: boolean,
  message: string,
  invitation_id: int,      // Only on success
  email_sent: boolean      // Whether email was sent successfully
}
```

**Example**:
```javascript
const formData = new FormData();
formData.append('csrf_token', csrfToken);
formData.append('email', 'newuser@example.com');
formData.append('role', 'alumni');
formData.append('expiration_hours', '48');

const response = await fetch('/api/send_invitation.php', {
  method: 'POST',
  body: formData
});

const data = await response.json();
if (data.success) {
  console.log('Invitation sent!', data);
}
```

### 2. Register with Token

**Endpoint**: `POST /api/register_with_token.php`

**Required Role**: None (public)

**Parameters**:
```javascript
{
  token: string,           // Invitation token
  firstname: string,       // User's first name
  lastname: string,        // User's last name
  password: string,        // User's password
  password_confirm: string // Password confirmation
}
```

**Response**:
```javascript
{
  success: boolean,
  message: string,
  user_id: int            // Only on success
}
```

### 3. Delete Invitation

**Endpoint**: `POST /api/delete_invitation.php`

**Required Role**: admin, vorstand

**Parameters**:
```javascript
{
  csrf_token: string,     // CSRF protection token
  invitation_id: int      // ID of invitation to delete
}
```

**Response**:
```javascript
{
  success: boolean,
  message: string
}
```

## Security Features

### Token Generation
- Uses PHP's `random_bytes(32)` for cryptographic security
- Converted to 64-character hexadecimal string
- Stored as unique index in database

### Token Validation
- Checks token exists in database
- Verifies token has not been used (accepted_at IS NULL)
- Confirms token has not expired (expires_at > NOW())
- Single validation function prevents timing attacks

### Email Security
- No passwords or sensitive data in emails
- Links use HTTPS (when configured)
- Tokens are one-time use only
- Clear expiration time shown to user

### CSRF Protection
- All admin forms require CSRF tokens
- Tokens generated and validated via Auth class
- Prevents cross-site request forgery attacks

### Rate Limiting
- Inherits existing rate limiting from Auth system
- Uses login_attempts table for tracking
- Prevents invitation spam

### Access Control
- Only admin and vorstand can create invitations
- Only admin and vorstand can delete invitations
- Anyone with valid token can register (by design)

## Email Template

The invitation email includes:

- **Header**: Welcoming gradient design with IBC branding
- **Personalization**: Shows who sent the invitation (if logged in)
- **Role Information**: Displays assigned role
- **Expiration Date**: Clear expiration time
- **Call-to-Action Button**: Large, prominent registration button
- **Fallback Link**: Copy-pasteable URL if button doesn't work
- **Security Note**: Explains one-time use and expiration
- **Footer**: Professional disclaimer and copyright

Both HTML and plain text versions are included for maximum compatibility.

## Troubleshooting

### Email Not Sent

**Check SMTP Configuration**:
```php
// In config/config.php
var_dump(SMTP_HOST);
var_dump(SMTP_USER);
var_dump(SMTP_PASS);
```

**Test SMTP Connection**:
```php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'src/MailService.php';

$mailService = new MailService();
$result = $mailService->sendEmail(
    'test@example.com',
    'Test Email',
    '<p>This is a test.</p>',
    'Test User'
);

var_dump($result); // Should be true
```

**Check Logs**:
```bash
# Check mail service logs
tail -f logs/mail.log

# Check application logs
tail -f logs/app.log
```

### Token Validation Fails

**Check Token Exists**:
```sql
SELECT * FROM invitations WHERE token = 'your-token-here';
```

**Check Token Status**:
```sql
SELECT 
  id,
  email,
  accepted_at,
  expires_at,
  CASE 
    WHEN accepted_at IS NOT NULL THEN 'Used'
    WHEN expires_at < NOW() THEN 'Expired'
    ELSE 'Valid'
  END as status
FROM invitations 
WHERE token = 'your-token-here';
```

### Registration Fails

**Check User Table**:
```sql
-- Check if email already exists
SELECT * FROM users WHERE email = 'user@example.com';
```

**Check Password Requirements**:
- Minimum 8 characters
- Must pass `validatePasswordStrength()` check

**Check Database Permissions**:
- User database connection must have INSERT permissions
- Check that transactions are supported (InnoDB)

## Code Structure

### Backend Files

- `src/Auth.php`: Core authentication class with invitation methods
  - `createInvitation()`: Generate and save invitation
  - `validateInvitationToken()`: Check token validity
  - `registerWithInvitation()`: Complete registration
  - `getPendingInvitations()`: List invitations
  - `deleteInvitation()`: Remove invitation

- `src/MailService.php`: Email service with SMTP
  - `sendInvitationEmail()`: Send formatted invitation email

- `api/send_invitation.php`: API endpoint for creating invitations
- `api/register_with_token.php`: API endpoint for registration
- `api/delete_invitation.php`: API endpoint for deleting invitations

### Frontend Files

- `templates/pages/register.php`: Registration form page
- `templates/components/invitation_management.php`: Admin UI component
- `templates/pages/admin_dashboard.php`: Includes invitation management

### Database Files

- `migrations/006_add_invitations_table.sql`: Database migration

## Best Practices

### For Administrators

1. **Use Appropriate Roles**
   - Alumni: Former members with limited access
   - Mitglied: Active members with full access
   - Ressortleiter: Department leaders with management access

2. **Set Reasonable Expiration Times**
   - 24h: Urgent invitations
   - 48h: Standard (default, recommended)
   - 72h: When recipient may be busy
   - 1 week: Maximum, for special cases only

3. **Monitor Pending Invitations**
   - Regularly check the pending invitations list
   - Delete expired invitations to keep database clean
   - Follow up with users who haven't registered

4. **Keep Records**
   - System automatically logs all invitation actions
   - Use SystemLogger for audit trail
   - Check logs/app.log for detailed information

### For Development

1. **Testing**
   - Always test in development environment first
   - Use test email addresses (e.g., mailinator.com)
   - Verify SMTP credentials before production use

2. **Error Handling**
   - All API endpoints return JSON with success/message
   - Frontend handles errors gracefully with alerts
   - Backend logs all errors to logs/app.log

3. **Database Maintenance**
   - Periodically clean up old accepted invitations
   - Monitor table size and performance
   - Add indexes if query performance degrades

## Future Enhancements

Possible improvements for future versions:

- **Bulk Invitations**: Upload CSV file with multiple email addresses
- **Custom Messages**: Allow admins to add personal message to invitation
- **Invitation Templates**: Pre-defined invitation templates for different roles
- **Reminder Emails**: Automatically remind users with pending invitations
- **Statistics Dashboard**: Track invitation acceptance rates
- **API Key Authentication**: Alternative to web-based invitation sending
- **Webhook Integration**: Notify external systems of new registrations

## Support

For issues or questions:

1. Check logs: `logs/app.log` and `logs/mail.log`
2. Review this documentation
3. Contact IT department or system administrator
4. Check GitHub issues for known problems

## License

This invitation system is part of the IBC Intranet project and is subject to the same license terms as the main application.
