#!/bin/bash

###############################################################################
# IBC-Intra Deployment Verification Script
# 
# This script verifies that the deployment was successful by checking:
# 1. Database tables exist
# 2. Admin user exists
# 3. Built assets exist
# 4. Permissions are correct
#
# Usage: ./verify_deployment.sh
###############################################################################

set -e  # Exit on error

echo "============================================================"
echo "IBC-Intra Deployment Verification"
echo "============================================================"
echo ""

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Function to print success message
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Function to print warning message
print_warning() {
    echo -e "${YELLOW}⚠️${NC}  $1"
    ((WARNINGS++))
}

# Function to print error message
print_error() {
    echo -e "${RED}❌${NC} $1"
    ((ERRORS++))
}

# Load environment variables from .env if it exists
if [ -f .env ]; then
    # Safer approach: use set -a to export all variables, then source
    set -a
    source .env 2>/dev/null || true
    set +a
fi

# Get database credentials
DB_NAME="${DB_NAME:-ibc_intra}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
DB_HOST="${DB_HOST:-localhost}"

# Set MYSQL_PWD environment variable for secure password handling
if [ -n "$DB_PASS" ]; then
    export MYSQL_PWD="$DB_PASS"
fi

echo "============================================================"
echo "1. Checking Database"
echo "============================================================"
echo ""

# Test database connection
if mysql -h "$DB_HOST" -u "$DB_USER" -e "USE $DB_NAME; SELECT 1;" > /dev/null 2>&1; then
    print_success "Database connection successful"
    
    # Check table count
    TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | wc -l)
    if [ "$TABLE_COUNT" -ge 16 ]; then
        print_success "Database tables: $TABLE_COUNT (expected 16+)"
    else
        print_error "Database tables: $TABLE_COUNT (expected 16+)"
    fi
    
    # Check for admin user
    ADMIN_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM users WHERE email='admin@ibc-consulting.de';" 2>/dev/null | tail -n 1)
    if [ "$ADMIN_COUNT" -gt 0 ]; then
        print_success "Admin user exists (admin@ibc-consulting.de)"
    else
        print_error "Admin user not found"
    fi
    
    # Check for inventory items
    INVENTORY_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM inventory;" 2>/dev/null | tail -n 1)
    if [ "$INVENTORY_COUNT" -gt 0 ]; then
        print_success "Inventory items: $INVENTORY_COUNT"
    else
        print_warning "No inventory items found"
    fi
    
    # Check for news items
    NEWS_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM news;" 2>/dev/null | tail -n 1)
    if [ "$NEWS_COUNT" -gt 0 ]; then
        print_success "News items: $NEWS_COUNT"
    else
        print_warning "No news items found"
    fi
    
else
    print_error "Cannot connect to database"
fi

echo ""
echo "============================================================"
echo "2. Checking Dependencies"
echo "============================================================"
echo ""

# Check for Composer dependencies
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    print_success "Composer vendor directory exists"
    
    # Check critical dependencies
    if [ -f "vendor/phpmailer/phpmailer/src/PHPMailer.php" ]; then
        print_success "PHPMailer installed"
    else
        print_error "PHPMailer not found - Run 'composer install'"
    fi
    
    if [ -f "vendor/vlucas/phpdotenv/src/Dotenv.php" ]; then
        print_success "Dotenv installed"
    else
        print_error "Dotenv not found - Run 'composer install'"
    fi
    
    if [ -f "vendor/sonata-project/google-authenticator/src/GoogleAuthenticator.php" ]; then
        print_success "Google Authenticator installed"
    else
        print_error "Google Authenticator not found - Run 'composer install'"
    fi
else
    print_error "Composer dependencies not installed"
    print_error "Run: composer install --no-dev --optimize-autoloader"
fi

echo ""
echo "============================================================"
echo "3. Checking Built Assets"
echo "============================================================"
echo ""

# Check for minified JavaScript
if [ -f "assets/js/app.min.js" ]; then
    SIZE=$(du -h "assets/js/app.min.js" | cut -f1)
    print_success "app.min.js exists (${SIZE})"
else
    print_error "app.min.js not found - Run 'npm run build'"
fi

# Check for minified CSS
if [ -f "assets/css/theme.min.css" ]; then
    SIZE=$(du -h "assets/css/theme.min.css" | cut -f1)
    print_success "theme.min.css exists (${SIZE})"
else
    print_error "theme.min.css not found - Run 'npm run build'"
fi

if [ -f "assets/css/fonts.min.css" ]; then
    SIZE=$(du -h "assets/css/fonts.min.css" | cut -f1)
    print_success "fonts.min.css exists (${SIZE})"
else
    print_error "fonts.min.css not found - Run 'npm run build'"
fi

# Check for node_modules
if [ -d "node_modules" ]; then
    print_success "Node modules installed"
else
    print_warning "node_modules not found - Run 'npm install'"
fi

echo ""
echo "============================================================"
echo "4. Checking File Structure"
echo "============================================================"
echo ""

# Check for critical directories
DIRS=("config" "src" "templates" "assets" "api")
for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        print_success "Directory exists: $dir"
    else
        print_error "Directory missing: $dir"
    fi
done

# Check for critical files
FILES=(
    "index.php"
    "config/config.php"
    "config/db.php"
    "templates/layout/header.php"
    "templates/layout/footer.php"
    "api/global_search.php"
)
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        print_success "File exists: $file"
    else
        print_error "File missing: $file"
    fi
done

echo ""
echo "============================================================"
echo "5. Checking Permissions"
echo "============================================================"
echo ""

# Check logs directory
if [ -d "logs" ]; then
    if [ -w "logs" ]; then
        print_success "Logs directory is writable"
    else
        print_warning "Logs directory is not writable"
    fi
else
    print_warning "Logs directory does not exist"
fi

# Check uploads directory
if [ -d "assets/uploads" ]; then
    if [ -w "assets/uploads" ]; then
        print_success "Uploads directory is writable"
    else
        print_warning "Uploads directory is not writable"
    fi
else
    print_warning "Uploads directory does not exist"
fi

echo ""
echo "============================================================"
echo "Verification Summary"
echo "============================================================"
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed!${NC}"
    echo ""
    echo "Deployment is ready for testing."
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  $WARNINGS warning(s) found${NC}"
    echo ""
    echo "Deployment is mostly ready, but review warnings above."
    exit 0
else
    echo -e "${RED}❌ $ERRORS error(s) and $WARNINGS warning(s) found${NC}"
    echo ""
    echo "Please fix the errors above before using the application."
    exit 1
fi
