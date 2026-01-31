# Cron Scripts Directory

This directory contains automated scripts that should be scheduled to run periodically via cron jobs.

## Available Scripts

### birthday_check.php

**Purpose:** Automated birthday notifications and admin reporting

**Features:**
- Searches User-DB for users with birthdays matching today's date (day/month)
- Respects privacy setting: Only processes users with `notify_birthday = TRUE`
- Sends personalized birthday congratulations emails via IONOS SMTP
- Sends summary report to board members (vorstand/admin roles)

**Requirements:**
- PHP 8.0+ with CLI support
- Database migration `007_add_birthday_fields_to_users.sql` must be applied
- SMTP configuration in `.env` or `config/config.php`
- Users table with `birthdate` and `notify_birthday` columns

**Usage:**
```bash
php /path/to/intra/cron/birthday_check.php
```

**Cron Setup:**

Run daily at 00:05 AM (recommended time to avoid conflicts):
```bash
5 0 * * * cd /path/to/intra && php cron/birthday_check.php >> logs/birthday_check.log 2>&1
```

Alternative: Run at 6:00 AM for business hours:
```bash
0 6 * * * cd /path/to/intra && php cron/birthday_check.php >> logs/birthday_check.log 2>&1
```

**Output:**
- Console output with detailed execution log
- Email log: `logs/birthday_mail.log`
- Cron log (if redirected): `logs/birthday_check.log`

**Example Output:**
```
=====================================
Birthday Check Script Started
Date: 2026-01-31 00:05:01
=====================================

✓ Connected to User-DB

Checking for birthdays on: 31.01

Found 2 user(s) with birthday today (with notify_birthday = TRUE)

✓ MailService initialized

Sending birthday congratulations emails...
----------------------------------------
Processing: Max Mustermann (30 Jahre) <max.mustermann@example.com>
  ✓ Email sent successfully
Processing: Anna Schmidt (25 Jahre) <anna.schmidt@example.com>
  ✓ Email sent successfully

Sending summary to board members...
----------------------------------------
Sending to: Admin User <admin@example.com>
  ✓ Summary sent successfully

=====================================
Birthday Check Completed
=====================================
Users with birthdays: 2
Emails sent successfully: 2
Emails failed: 0
Board members notified: 1
Execution time: 1.24 seconds
=====================================
```

## Setting up Cron Jobs

### On Linux/Unix Systems

1. Edit your crontab:
   ```bash
   crontab -e
   ```

2. Add the cron job line (adjust paths as needed):
   ```
   5 0 * * * cd /var/www/html/intra && php cron/birthday_check.php >> logs/birthday_check.log 2>&1
   ```

3. Save and exit

4. Verify cron job is registered:
   ```bash
   crontab -l
   ```

### On Shared Hosting (e.g., IONOS)

1. Log into your hosting control panel
2. Navigate to "Cron Jobs" or "Task Scheduler"
3. Create a new cron job with:
   - **Frequency:** Daily at 00:05
   - **Command:** `/usr/bin/php /path/to/intra/cron/birthday_check.php`
   - **Output:** Optional - set email notification for errors

### Testing the Script

Before setting up the cron job, test the script manually:

```bash
cd /path/to/intra
php cron/birthday_check.php
```

This will show you:
- Any configuration issues
- Database connectivity
- Email sending capability
- Users with birthdays today

## Troubleshooting

### Common Issues

1. **"Class 'MailService' not found"**
   - Ensure autoloader is working: `composer install`
   - Check that `src/MailService.php` exists

2. **"Database connection failed"**
   - Verify `.env` file has correct DB_USER_* credentials
   - Check User-DB is accessible

3. **"SMTP configuration not properly defined"**
   - Set SMTP credentials in `.env`:
     ```
     SMTP_HOST=smtp.ionos.de
     SMTP_PORT=587
     SMTP_USER=your-email@domain.com
     SMTP_PASS=your-password
     ```

4. **No emails sent despite birthdays**
   - Check users have `notify_birthday = 1` in database
   - Verify `birthdate` column is populated
   - Check email log: `logs/birthday_mail.log`

5. **Permission denied**
   - Ensure script is executable: `chmod +x cron/birthday_check.php`
   - Check logs directory is writable: `chmod 755 logs/`

### Viewing Logs

```bash
# View birthday check execution log
tail -f logs/birthday_check.log

# View mail service log
tail -f logs/birthday_mail.log

# View system cron log
grep birthday_check /var/log/syslog
```

## Privacy & GDPR Compliance

The birthday notification system respects user privacy:

1. **Opt-out Model:** Birthday notifications are enabled by default, but users can disable them in their settings
2. **Database Field:** `notify_birthday` (TINYINT) controls visibility
   - `1` (TRUE, DEFAULT): Birthday visible, emails sent
   - `0` (FALSE): Birthday hidden, no emails sent
3. **Admin Summary:** Only shows users who have notifications enabled
4. **Data Minimization:** Only necessary data is processed

Users can change their privacy settings at:
`Settings → Privacy → Birthday Notifications`

## Maintenance

### Regular Checks

- Monitor `logs/birthday_check.log` for errors
- Verify emails are being delivered (check with users)
- Review board member list periodically
- Test SMTP connectivity monthly

### Database Migration

Ensure the birthday migration is applied:

```bash
mysql -u username -p database_name < migrations/007_add_birthday_fields_to_users.sql
```

Verify columns exist:
```sql
DESCRIBE users;
```

Should show:
- `birthdate` - DATE NULL
- `notify_birthday` - TINYINT(1) DEFAULT 1

## Support

For issues or questions:
- Check logs first: `logs/birthday_check.log` and `logs/birthday_mail.log`
- Verify database migration was applied
- Test SMTP configuration with `docs/test_mailservice.php`
- Contact IBC IT Support
