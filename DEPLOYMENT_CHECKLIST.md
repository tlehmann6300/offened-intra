# IBC-Intra Deployment Checklist

## 🚨 Critical: Follow This Checklist for Every Deployment

This checklist prevents the most common deployment issues, especially **HTTP 500 errors**.

---

## Pre-Deployment Steps

- [ ] **Backup database** before making any changes
- [ ] Review all code changes in the deployment
- [ ] Test changes in a staging environment (if available)

---

## Deployment Steps (IN ORDER)

### 1. Pull/Upload Code
```bash
# If using git:
git pull origin main

# Or upload files via FTP/SFTP
```

### 2. Install Composer Dependencies (⚠️ CRITICAL)
```bash
composer install --no-dev --optimize-autoloader
```

**Why this is critical:**
- The `vendor/` directory is **NOT in git** (it's in `.gitignore`)
- Without it, you will get **HTTP 500 errors**
- Contains PHPMailer, Dotenv, and Google Authenticator

**Verification:**
```bash
# Check that vendor directory exists
ls -la vendor/autoload.php

# Should show: vendor/autoload.php exists
```

### 3. Install Node Dependencies (if assets changed)
```bash
npm install
```

### 4. Build Assets (if CSS/JS changed)
```bash
npm run build
```

**Verification:**
```bash
# Check built files exist
ls -la assets/js/app.min.js
ls -la assets/css/theme.min.css
```

### 5. Configure Environment
```bash
# Copy .env.example if needed
cp .env.example .env

# Edit .env with production values
nano .env
```

**Required variables:**
- Database credentials (Content & User DB)
- SMTP credentials
- Site URL

### 6. Set File Permissions
```bash
chmod 755 logs/
chmod 755 assets/uploads/
chmod 644 config/*.php
```

### 7. Run Database Migrations (if schema changed)
```bash
# Example:
mysql -u username -p database_name < migrations/latest_migration.sql
```

---

## Post-Deployment Verification

### Automated Checks
```bash
# Run deployment verification script
bash verify_deployment.sh
```

Expected result: All checks should pass ✓

### Manual Checks

- [ ] **Homepage loads** without errors
- [ ] **Login works** with test credentials
- [ ] **Check error logs** for issues:
  ```bash
  tail -f logs/production.log
  ```
- [ ] **Database connection** works (try loading a page with data)
- [ ] **Email sending** works (test password reset)

---

## Common Issues & Solutions

### Issue: HTTP 500 Error
**Cause:** Missing vendor directory

**Solution:**
```bash
composer install --no-dev --optimize-autoloader
```

### Issue: "Class not found" errors
**Cause:** Composer autoloader not generated

**Solution:**
```bash
composer dump-autoload --optimize
```

### Issue: CSS/JS not loading or outdated
**Cause:** Assets not built

**Solution:**
```bash
npm install
npm run build
# Then clear browser cache
```

### Issue: Session errors
**Cause:** Session directory not writable

**Solution:**
```bash
chmod 755 logs/
# Or check PHP session.save_path
```

### Issue: Database connection failed
**Cause:** Wrong credentials in .env or config files

**Solution:**
- Check `.env` file values
- Verify database server is accessible
- Test connection manually:
  ```bash
  mysql -h hostname -u username -p database_name
  ```

---

## Rollback Plan

If deployment fails:

1. **Restore database backup** (if migrations were run)
   ```bash
   mysql -u username -p database_name < backup.sql
   ```

2. **Revert code changes**
   ```bash
   git reset --hard HEAD~1
   # Or restore previous code from backup
   ```

3. **Re-run composer install**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

---

## Success Criteria

✅ Deployment is successful when:
- Homepage loads without errors
- Users can log in
- All functionality works as expected
- No errors in logs/production.log
- verify_deployment.sh passes all checks

---

## Additional Resources

- [SETUP.md](SETUP.md) - Detailed setup instructions
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) - Feature-specific deployment guides
- [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) - Step-by-step deployment walkthrough

---

## Need Help?

If you encounter issues not covered here:
1. Check logs: `tail -f logs/production.log`
2. Enable debug mode: Set `APP_ENV=development` in `.env`
3. Contact IT support with error logs
