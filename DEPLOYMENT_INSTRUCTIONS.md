# Deployment Instructions - Login Fix

## ⚠️ CRITICAL: Action Required After Deployment

After deploying this fix, you **MUST** run the following command on the server:

```bash
composer install --no-dev --optimize-autoloader
```

**This is not optional!** Without this step, the website will continue to show the maintenance page.

## Quick Deployment Checklist

- [ ] 1. Deploy/pull the latest code to your server
- [ ] 2. Navigate to the application root directory
- [ ] 3. Run: `composer install --no-dev --optimize-autoloader`
- [ ] 4. Verify `vendor/` directory exists
- [ ] 5. Verify `vendor/autoload.php` exists
- [ ] 6. Test the website loads (should show login page or landing page)
- [ ] 7. Test login with admin account: tom.lehmann@business-consulting.de

## What Was Fixed?

### Problem
"Auf die Website komme ich aber der Login geht nicht" - The website was showing a maintenance page and login was not accessible.

### Solution
1. **Installed Composer dependencies** - Required packages for authentication and email
2. **Fixed database column mismatch** - Corrected TOTP/2FA field names in code

## Technical Details

### Dependencies Installed
The following packages are required and will be installed by `composer install`:
- `phpmailer/phpmailer` - Email functionality
- `vlucas/phpdotenv` - Environment configuration
- `sonata-project/google-authenticator` - 2FA support
- Supporting libraries (Symfony polyfills, etc.)

### Code Changes
- Fixed `src/Auth.php` to use correct database column names
- Updated `tests/test_auth_refactor.php` for consistency
- Enhanced documentation in `SETUP.md`

## Verification

After deployment, verify everything works:

1. **Check the website loads:**
   - Open your browser
   - Navigate to your domain (e.g., https://intra.business-consulting.de)
   - You should see either the login page or landing page (NOT maintenance page)

2. **Test login:**
   - Click on login
   - Use test credentials:
     - Email: tom.lehmann@business-consulting.de
     - Password: [your admin password]
   - Login should succeed

3. **Check server logs:**
   ```bash
   tail -f logs/production.log
   ```
   - Should NOT see "vendor/autoload.php not found" errors
   - Should see normal application activity

## Troubleshooting

### Still seeing maintenance page?
**Cause:** Composer dependencies not installed

**Solution:**
```bash
cd /path/to/your/application
composer install --no-dev --optimize-autoloader
```

### Composer not found?
**Cause:** Composer not installed on server

**Solution:**
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Permission denied errors?
**Cause:** Incorrect file permissions

**Solution:**
```bash
# Fix permissions for web server (adjust www-data if needed)
sudo chown -R www-data:www-data vendor/
sudo chmod -R 755 vendor/
```

### Database connection errors?
**Cause:** Database credentials not configured

**Solution:**
1. Create `.env` file with database credentials (see SETUP.md)
2. Or update `config/db.php` with correct credentials
3. Verify database server is accessible

## Important Notes

### About vendor/ Directory
- The `vendor/` directory contains Composer dependencies
- It is **NOT** committed to Git (listed in .gitignore)
- Must be created on each server by running `composer install`
- Should never be manually copied between servers

### About .env File
- Not included in repository for security
- Create it manually on server with production credentials
- See SETUP.md for required variables
- Application will work with defaults but update for production

### About Passwords
- Test user password hash is in database
- For new deployments, you may need to reset passwords
- Use PHP to generate bcrypt hash:
  ```php
  echo password_hash('your-password', PASSWORD_DEFAULT);
  ```

## Support

For detailed information, see:
- `LOGIN_FIX_SUMMARY.md` - Complete fix analysis
- `SETUP.md` - Full setup guide
- `docs/DEPLOYMENT.md` - Deployment documentation

If you encounter issues:
1. Check application logs: `logs/production.log`
2. Check web server logs: `/var/log/apache2/error.log` or nginx equivalent
3. Enable debug mode temporarily: Set `APP_ENV=development` in `.env`

---

**Remember:** After pulling/deploying this fix, you MUST run `composer install`!
