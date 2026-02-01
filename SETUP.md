# Setup Guide for IBC-Intra

This guide will help you set up the IBC-Intra application from a fresh clone.

## ⚠️ CRITICAL: Most Common Deployment Issue

**HTTP 500 Error after deployment?** 
→ You forgot to run `composer install`! The `vendor/` directory is not in git and must be created on the server.

**Solution:**
```bash
composer install --no-dev --optimize-autoloader
```

## Prerequisites

- PHP >= 7.4 (with PDO extension)
- Composer
- Node.js >= 14.x
- npm >= 6.x
- MySQL/MariaDB database

## Quick Setup

**IMPORTANT:** After cloning the repository, follow these steps in order:

### 1. Install Composer Dependencies (REQUIRED)

```bash
composer install --no-dev --optimize-autoloader
```

**Why this is required:**
- The `vendor/` directory contains critical dependencies (PHPMailer, Dotenv, Google Authenticator)
- This directory is **not included in git** (listed in `.gitignore`)
- Without these dependencies, you will get **HTTP 500 errors**

This will install required packages:
- phpmailer/phpmailer (Email functionality)
- vlucas/phpdotenv (Environment configuration)
- sonata-project/google-authenticator (2FA support)

### 2. Install Node.js Dependencies and Build Assets

```bash
npm install
npm run build
```

This will:
- Install frontend build tools
- Create minified JavaScript bundles in `assets/js/`
- Create minified CSS files in `assets/css/`

### 3. Configure Environment

#### Option A: Using .env file (Recommended)

Create a `.env` file in the root directory:

```env
# Application Environment
APP_ENV=production

# Content Database
DB_CONTENT_HOST=your-content-host.hosting-data.io
DB_CONTENT_NAME=your_content_db
DB_CONTENT_USER=your_content_user
DB_CONTENT_PASS=your_content_password

# User Database
DB_USER_HOST=your-user-host.hosting-data.io
DB_USER_NAME=your_user_db
DB_USER_USER=your_user_user
DB_USER_PASS=your_user_password

# SMTP Configuration
SMTP_HOST=smtp.ionos.de
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=your-email@your-domain.com
SMTP_PASS=your-smtp-password
SMTP_FROM_EMAIL=your-email@your-domain.com
SMTP_FROM_NAME=IBC Intranet
```

#### Option B: Using default values

The application will work with default values defined in `config/config.php` and `config/db.php`. However, these are test credentials and should be updated for production use.

### 4. Set up Database

Run the database migration scripts:

```bash
# Import the database schema
mysql -u your_user -p your_database < dbs15161271.sql
mysql -u your_user -p your_database < dbs15253086.sql
```

Or use the import script:

```bash
./import_database.sh
```

### 5. Configure Web Server

#### Apache

The `.htaccess` file is already configured. Ensure:
- `mod_rewrite` is enabled
- `AllowOverride All` is set for the document root

#### Nginx

Add this configuration to your server block:

```nginx
location / {
    try_files $uri $uri/ /public/index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 6. Verify Installation

Run the build verification script:

```bash
bash verify-build.sh
```

Run the deployment verification script:

```bash
bash verify_deployment.sh
```

## Troubleshooting

### HTTP 500 Internal Server Error

**Cause:** Missing vendor directory or Composer dependencies

**Solution:**
```bash
composer install
```

**Cause:** Missing node_modules or build artifacts

**Solution:**
```bash
npm install
npm run build
```

**Cause:** Session save path not writable

**Solution:**
- Check PHP session.save_path configuration
- Ensure the directory is writable by the web server user

### HTTP 503 Service Unavailable

**Cause:** Missing configuration files

**Solution:**
- Ensure `config/config.php` exists
- Ensure `config/db.php` exists
- Ensure `src/Auth.php` exists

### Database Connection Errors

**Cause:** Incorrect database credentials

**Solution:**
- Update credentials in `.env` file or `config/db.php`
- Verify database server is accessible
- Check firewall rules

## File Structure

```
offened-intra/
├── assets/           # Static assets (CSS, JS, images)
├── build/            # Build scripts
├── config/           # Configuration files
├── docs/             # Documentation
├── logs/             # Application logs
├── public/           # Public entry point (for web server)
├── src/              # PHP source code
├── templates/        # PHP templates
├── tests/            # Test files
├── vendor/           # Composer dependencies (not in git)
├── node_modules/     # Node.js dependencies (not in git)
├── .htaccess         # Apache configuration
├── composer.json     # PHP dependencies
├── package.json      # Node.js dependencies
└── index.php         # Application entry point (legacy)
```

## Security Considerations

1. **Never commit sensitive files:**
   - `.env` file with production credentials
   - Database dumps with real data
   - Private keys or certificates

2. **Keep dependencies updated:**
   ```bash
   composer update
   npm audit fix
   ```

3. **Use strong passwords:**
   - Database passwords
   - SMTP passwords
   - Admin user passwords

4. **Enable HTTPS:**
   - Configure SSL/TLS certificate
   - Update SITE_URL in .env to use https://

5. **Restrict file permissions:**
   ```bash
   chmod 755 config/
   chmod 644 config/*.php
   chmod 700 logs/
   ```

## Support

For more information, see:
- [DEPLOYMENT.md](docs/DEPLOYMENT.md) - Detailed deployment guide
- [DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) - Step-by-step deployment
- [DATABASE_MIGRATION.md](docs/DATABASE_MIGRATION.md) - Database setup

For issues or questions, contact the IT department.
