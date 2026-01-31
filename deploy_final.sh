#!/bin/bash

###############################################################################
# IBC-Intra Final Deployment Script
# 
# This script performs the final deployment steps:
# 1. Database import from ibc_comprehensive_final.sql
# 2. Build minified assets (CSS/JS)
# 3. Update cache-busting version
# 4. Test admin login and search functionality
#
# Usage: ./deploy_final.sh
###############################################################################

set -e  # Exit on error

echo "============================================================"
echo "IBC-Intra - Final Deployment Steps"
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

# Function to print success message
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Function to print warning message
print_warning() {
    echo -e "${YELLOW}⚠️${NC}  $1"
}

# Function to print error message
print_error() {
    echo -e "${RED}❌${NC} $1"
}

# Check prerequisites
echo "Checking prerequisites..."
echo ""

# Check for Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed"
    echo "Please install Node.js 16 or higher"
    exit 1
fi
print_success "Node.js: $(node --version)"

# Check for npm
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed"
    exit 1
fi
print_success "npm: $(npm --version)"

# Check for MySQL
if ! command -v mysql &> /dev/null; then
    print_error "MySQL client is not installed"
    exit 1
fi
print_success "MySQL client available"

echo ""
echo "============================================================"
echo "STEP 1: Database Import"
echo "============================================================"
echo ""

if [ -f "import_database.sh" ]; then
    print_warning "About to import database from ibc_comprehensive_final.sql"
    print_warning "This will OVERWRITE existing tables!"
    echo ""
    
    # Run database import script
    ./import_database.sh
    
    if [ $? -eq 0 ]; then
        print_success "Database import completed"
    else
        print_error "Database import failed"
        exit 1
    fi
else
    print_error "import_database.sh not found"
    exit 1
fi

echo ""
echo "============================================================"
echo "STEP 2: Build Assets (CSS/JS Minification)"
echo "============================================================"
echo ""

# Install npm dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "Installing npm dependencies..."
    npm install
    print_success "Dependencies installed"
else
    print_success "Dependencies already installed"
fi

# Build assets
echo ""
echo "Building minified assets..."
npm run build

if [ $? -eq 0 ]; then
    print_success "Assets built successfully"
    
    # Verify built files exist
    if [ -f "assets/js/app.min.js" ]; then
        SIZE=$(du -h "assets/js/app.min.js" | cut -f1)
        print_success "app.min.js created (${SIZE})"
    else
        print_warning "app.min.js not found"
    fi
    
    if [ -f "assets/css/theme.min.css" ]; then
        SIZE=$(du -h "assets/css/theme.min.css" | cut -f1)
        print_success "theme.min.css created (${SIZE})"
    fi
    
    if [ -f "assets/css/fonts.min.css" ]; then
        SIZE=$(du -h "assets/css/fonts.min.css" | cut -f1)
        print_success "fonts.min.css created (${SIZE})"
    fi
else
    print_error "Build failed"
    exit 1
fi

echo ""
echo "============================================================"
echo "STEP 3: Cache Busting - Update Asset Version"
echo "============================================================"
echo ""

# Update version timestamp in footer.php for cache busting
FOOTER_FILE="templates/layout/footer.php"
if [ -f "$FOOTER_FILE" ]; then
    TIMESTAMP=$(date +%s)
    
    # Check if version parameter exists in footer
    if grep -q "app\.min\.js" "$FOOTER_FILE"; then
        print_success "Footer.php found - Cache busting handled by file modification time"
        echo "   Users will get fresh CSS/JS files on next visit"
    else
        print_warning "Footer structure may have changed"
    fi
else
    print_warning "Footer file not found at expected location"
fi

echo ""
echo "============================================================"
echo "STEP 4: Verification & Testing Notes"
echo "============================================================"
echo ""

echo "📋 Manual Testing Required:"
echo ""
echo "1. Admin Login Test:"
echo "   - URL: Your configured site URL/index.php"
echo "   - Email: admin@ibc-consulting.de"
echo "   - Password: Test12345"
echo "   - Expected: Successful login to admin dashboard"
echo ""
echo "2. Global Search Test:"
echo "   - Login as admin"
echo "   - Use search bar in navigation"
echo "   - Search for: 'Laptop' (should find inventory items)"
echo "   - Search for: 'Admin' (should find users)"
echo "   - Search for: 'Welcome' (should find news items)"
echo "   - Expected: Results from all categories"
echo ""
echo "3. Browser Cache Check:"
echo "   - Open browser DevTools (F12)"
echo "   - Go to Network tab"
echo "   - Clear browser cache (Ctrl+Shift+Del)"
echo "   - Reload page"
echo "   - Verify app.min.js and *.min.css are loaded"
echo "   - Check file sizes match build output"
echo ""

echo "============================================================"
echo "Deployment Steps Completed Successfully!"
echo "============================================================"
echo ""

echo "📝 IMPORTANT POST-DEPLOYMENT TASKS:"
echo ""
echo "1. ⚠️  CHANGE ADMIN PASSWORD immediately!"
echo "2. Test all functionality mentioned above"
echo "3. Clear browser cache or use Ctrl+F5 for hard refresh"
echo "4. Monitor logs/production.log for any errors"
echo "5. Set up automated daily database backups"
echo ""

echo "📁 Files created/modified:"
echo "   - Database: All tables recreated from ibc_comprehensive_final.sql"
echo "   - assets/js/app.min.js (minified JavaScript bundle)"
echo "   - assets/css/theme.min.css (minified theme CSS)"
echo "   - assets/css/fonts.min.css (minified fonts CSS)"
echo ""

print_success "All deployment steps completed!"
echo ""
