# IBC-Intra - Intranet Portal

Internal business consulting intranet platform for managing members, projects, inventory, events, and alumni.

## 🚀 Quick Start (After Cloning)

**⚠️ CRITICAL FIRST STEP** - Run this immediately after cloning:

```bash
composer install --no-dev --optimize-autoloader
```

**Without this step, you will get HTTP 500 errors!** The `vendor/` directory is not in git.

## Full Setup

See [SETUP.md](SETUP.md) for complete setup instructions.

See [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) for deployment steps.

## Quick Deployment Steps

1. **Install Composer dependencies** (REQUIRED):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Install Node dependencies** (if assets need building):
   ```bash
   npm install
   npm run build
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your database and SMTP credentials
   ```

4. **Run database migrations** (if needed):
   ```bash
   mysql -u user -p database < migrations/xxx_migration.sql
   ```

5. **Set permissions**:
   ```bash
   chmod 755 logs/
   chmod 755 assets/uploads/
   ```

6. **Verify deployment**:
   ```bash
   bash verify_deployment.sh
   ```

## Common Issues

### HTTP 500 Error
**Problem:** Missing vendor directory  
**Solution:** `composer install --no-dev --optimize-autoloader`

### "Class not found" errors  
**Problem:** Autoloader not generated  
**Solution:** `composer dump-autoload --optimize`

### Outdated CSS/JS
**Problem:** Assets not built  
**Solution:** `npm install && npm run build`

## Tech Stack

- **Backend:** PHP 7.4+ with PDO
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Database:** MySQL/MariaDB (dual-database architecture)
- **Email:** PHPMailer with SMTP
- **Authentication:** Custom Auth system with 2FA support
- **Build Tools:** npm for asset minification

## Project Structure

```
offened-intra/
├── api/              # API endpoints
├── assets/           # Static assets (CSS, JS, images)
├── config/           # Configuration files
├── docs/             # Documentation
├── logs/             # Application logs (not in git)
├── migrations/       # Database migration scripts
├── src/              # PHP source code (classes)
├── templates/        # PHP templates
├── vendor/           # Composer dependencies (not in git)
├── .htaccess         # Apache configuration
├── composer.json     # PHP dependencies
├── index.php         # Main entry point
└── package.json      # Node.js dependencies
```

## Features

- **User Management:** Role-based access control (Admin, Vorstand, Ressortleiter, Mitglied, Alumni)
- **Projects:** Project planning and tracking
- **Inventory:** Asset management system
- **Events:** Event calendar and management
- **News:** Internal news feed
- **Alumni:** Alumni database and management
- **Authentication:** Email/password with optional 2FA
- **Invitations:** Token-based user invitation system
- **Global Search:** Search across all content types

## Security

- Role-based access control
- Password hashing with bcrypt
- CSRF protection
- SQL injection prevention via PDO prepared statements
- XSS protection via input sanitization
- Session security with httponly cookies
- Optional 2FA with Google Authenticator

## Documentation

- [SETUP.md](SETUP.md) - Complete setup guide
- [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) - Deployment checklist
- [docs/](docs/) - Detailed documentation for each feature

## Support

For questions or issues:
1. Check [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)
2. Review logs: `tail -f logs/production.log`
3. Contact IT department

## License

Internal use only - IBC Consulting
