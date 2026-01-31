# Deployment Guide: Token-Based Invitation System

## Quick Start

This guide will help you deploy the token-based invitation system to production.

## Pre-Deployment Checklist

- [x] Code implemented and tested
- [ ] Database credentials configured
- [ ] SMTP credentials configured
- [ ] Database migration executed
- [ ] System tested in production

## Step-by-Step Deployment

### Step 1: Verify Dependencies

Ensure Composer dependencies are installed:

```bash
cd /path/to/intra
composer install --no-dev --optimize-autoloader
```

Expected output: PHPMailer, Dotenv, and Google Authenticator installed.

### Step 2: Configure Database

Update `config/config.php` with your database credentials:

```php
// User Database (for invitations and authentication)
define('DB_USER_HOST', 'your-host.hosting-data.io');
define('DB_USER_NAME', 'your-database-name');
define('DB_USER_USER', 'your-username');
define('DB_USER_PASS', 'your-password');
```

Or use `.env` file:

```env
DB_USER_HOST=your-host.hosting-data.io
DB_USER_NAME=your-database-name
DB_USER_USER=your-username
DB_USER_PASS=your-password
```

### Step 3: Configure SMTP (IONOS)

Update `config/config.php` with IONOS SMTP settings:

```php
define('SMTP_HOST', 'smtp.ionos.de');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'your-email@your-domain.com');
define('SMTP_PASS', 'your-smtp-password');
define('SMTP_FROM_EMAIL', 'your-email@your-domain.com');
define('SMTP_FROM_NAME', 'IBC Intranet');
```

Or use `.env` file:

```env
SMTP_HOST=smtp.ionos.de
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=your-email@your-domain.com
SMTP_PASS=your-smtp-password
SMTP_FROM_EMAIL=your-email@your-domain.com
SMTP_FROM_NAME=IBC Intranet
```

### Step 4: Run Database Migration

Execute the migration to create the `invitations` table:

**Option A: Command Line**
```bash
mysql -u username -p database_name < migrations/006_add_invitations_table.sql
```

**Option B: phpMyAdmin**
1. Log into phpMyAdmin
2. Select your User database (e.g., `dbs15253086`)
3. Go to "SQL" tab
4. Copy contents of `migrations/006_add_invitations_table.sql`
5. Paste and click "Go"

**Option C: Direct MySQL**
```sql
SOURCE /path/to/migrations/006_add_invitations_table.sql;
```

**Verify Migration:**
```sql
-- Check table exists
SHOW TABLES LIKE 'invitations';

-- Check table structure
DESCRIBE invitations;

-- Should show 8 columns: id, email, token, role, created_by, created_at, expires_at, accepted_at
```

### Step 5: Test SMTP Connection

Create a test file `test_smtp.php`:

```php
<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'src/MailService.php';

$mailService = new MailService();
$result = $mailService->sendEmail(
    'your-test-email@example.com',
    'Test Email - IBC Intranet',
    '<h1>Test</h1><p>If you receive this, SMTP is working!</p>',
    'Test User'
);

echo $result ? "✓ Email sent successfully!\n" : "✗ Email sending failed!\n";

// Check logs
echo "\nCheck logs/mail.log for details.\n";
```

Run: `php test_smtp.php`

Expected output: `✓ Email sent successfully!`

### Step 6: Test the System in Browser

#### A. Login as Admin/Vorstand

1. Navigate to: `https://your-domain.com/index.php?page=login`
2. Login with admin or vorstand credentials
3. Verify login successful

#### B. Access Admin Dashboard

1. Navigate to: `https://your-domain.com/index.php?page=admin_dashboard`
2. Scroll down to "Einladungsverwaltung" section
3. Verify the invitation form is visible

#### C. Create Test Invitation

1. Fill out the form:
   - **E-Mail-Adresse**: Enter a test email address you control
   - **Rolle**: Select "Alumni"
   - **Gültigkeitsdauer**: Select "48 Stunden"
2. Click "Senden"
3. Verify success message appears
4. Check the pending invitations table shows the new invitation

#### D. Check Email Delivery

1. Check the email inbox for the test address
2. Verify invitation email received
3. Check email contains:
   - Proper formatting (HTML)
   - Your name as sender
   - Registration link with token
   - Expiration date
   - Role information

#### E. Test Registration

1. Click the registration link in the email
2. Verify registration form loads
3. Verify email is pre-filled and read-only
4. Fill out:
   - **Vorname**: Test
   - **Nachname**: User
   - **Passwort**: TestPassword123!
   - **Passwort bestätigen**: TestPassword123!
5. Click "Registrierung abschließen"
6. Verify success message
7. Click "Zum Login"
8. Login with new credentials
9. Verify login successful

#### F. Verify Token Used

1. Try to use the registration link again
2. Should show: "Diese Einladung wurde bereits verwendet."
3. Go back to Admin Dashboard
4. Verify invitation is no longer in pending list

#### G. Test Token Expiration (Optional)

1. Create another invitation with 24-hour expiration
2. Manually update database to expire it:
   ```sql
   UPDATE invitations 
   SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
   WHERE email = 'test@example.com';
   ```
3. Try to use the registration link
4. Should show: "Diese Einladung ist abgelaufen."

### Step 7: Verify Logging

Check that actions are being logged:

```bash
# Check application logs
tail -n 50 logs/app.log

# Check mail logs
tail -n 50 logs/mail.log

# Check system logs (database)
```

Expected in logs:
- "Invitation created - ID: X"
- "Invitation email sent successfully to: ..."
- "Token validated successfully - ID: X"
- "User registered via invitation - User ID: X"

### Step 8: Set Permissions

Ensure proper file permissions:

```bash
# Make logs writable
chmod 755 logs/
chmod 644 logs/*.log

# Protect sensitive files
chmod 600 config/config.php
chmod 600 .env

# Make cache/tmp writable (if exists)
chmod 755 cache/ tmp/
```

## Post-Deployment Verification

### Database Check

```sql
-- Count pending invitations
SELECT COUNT(*) as pending_count 
FROM invitations 
WHERE accepted_at IS NULL AND expires_at > NOW();

-- View recent invitations
SELECT 
  email,
  role,
  created_at,
  expires_at,
  CASE 
    WHEN accepted_at IS NOT NULL THEN 'Used'
    WHEN expires_at < NOW() THEN 'Expired'
    ELSE 'Pending'
  END as status
FROM invitations 
ORDER BY created_at DESC 
LIMIT 10;

-- Count registrations via invitation
SELECT COUNT(*) as registered_count
FROM invitations 
WHERE accepted_at IS NOT NULL;
```

### Log Check

```bash
# Check for errors
grep -i error logs/app.log | tail -20
grep -i error logs/mail.log | tail -20

# Check invitation activity
grep -i invitation logs/app.log | tail -20
```

### Performance Check

```sql
-- Check indexes exist
SHOW INDEX FROM invitations;

-- Should show indexes for:
-- - token (unique)
-- - email
-- - created_by
-- - expires_at
-- - accepted_at
```

## Troubleshooting

### Email Not Sending

**Check SMTP Configuration:**
```php
echo defined('SMTP_HOST') ? "✓ SMTP_HOST: " . SMTP_HOST : "✗ SMTP_HOST not defined";
echo defined('SMTP_USER') ? "✓ SMTP_USER: " . SMTP_USER : "✗ SMTP_USER not defined";
```

**Test with verbose errors:**
```php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

**Check mail logs:**
```bash
tail -f logs/mail.log
```

### Database Connection Issues

**Verify credentials:**
```php
try {
    $pdo = DatabaseManager::getUserConnection();
    echo "✓ Database connected\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
```

### Token Validation Fails

**Check token in database:**
```sql
SELECT * FROM invitations WHERE token = 'your-token-here';
```

**Check expiration:**
```sql
SELECT 
  *,
  CASE 
    WHEN expires_at > NOW() THEN 'Valid'
    ELSE 'Expired'
  END as status
FROM invitations 
WHERE token = 'your-token-here';
```

## Maintenance

### Clean Up Old Invitations

Periodically remove old accepted or expired invitations:

```sql
-- Delete accepted invitations older than 30 days
DELETE FROM invitations 
WHERE accepted_at IS NOT NULL 
  AND accepted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Delete expired invitations older than 7 days
DELETE FROM invitations 
WHERE accepted_at IS NULL 
  AND expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Monitor Invitation Usage

Create a monitoring query:

```sql
-- Daily invitation statistics
SELECT 
  DATE(created_at) as date,
  COUNT(*) as total_invitations,
  SUM(CASE WHEN accepted_at IS NOT NULL THEN 1 ELSE 0 END) as accepted,
  SUM(CASE WHEN accepted_at IS NULL AND expires_at > NOW() THEN 1 ELSE 0 END) as pending,
  SUM(CASE WHEN accepted_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired
FROM invitations 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

## Security Recommendations

1. **HTTPS Only**: Ensure invitation links use HTTPS
2. **Strong Passwords**: Enforce password strength during registration
3. **Rate Limiting**: Monitor invitation creation for abuse
4. **Audit Logs**: Regularly review SystemLogger for suspicious activity
5. **Token Length**: Keep tokens at 64 characters (32 bytes)
6. **Expiration**: Use reasonable expiration times (24-72 hours recommended)

## Rollback Plan

If issues occur, you can safely rollback:

```sql
-- Remove invitations table
DROP TABLE IF EXISTS invitations;

-- Remove new users created via invitation (optional, use with caution)
-- Check created_at timestamps to identify invitation-based registrations
SELECT * FROM users 
WHERE created_at > 'YYYY-MM-DD HH:MM:SS' 
ORDER BY created_at DESC;
```

Note: Rolling back does not affect existing code. The system will gracefully handle a missing table by showing database errors.

## Support

For issues:
1. Check logs: `logs/app.log` and `logs/mail.log`
2. Review `docs/invitation_system.md`
3. Run offline test: `php tests/test_invitation_offline.php`
4. Contact system administrator

## Success Criteria

- [ ] Database migration successful
- [ ] SMTP configuration working
- [ ] Test invitation sent successfully
- [ ] Test email received
- [ ] Registration completed successfully
- [ ] Token marked as used
- [ ] New user can login
- [ ] All logs showing proper activity
- [ ] No errors in logs

Once all items are checked, the system is successfully deployed! 🎉
