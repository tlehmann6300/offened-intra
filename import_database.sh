#!/bin/bash

###############################################################################
# IBC-Intra Database Import Script
# 
# This script imports the comprehensive database schema from:
# create_database_sql/ibc_comprehensive_final.sql
#
# ⚠️  WARNING: This will OVERWRITE existing database tables!
# ⚠️  Make sure to backup your database before running this script!
#
# Usage: ./import_database.sh [database_name] [username] [password] [host]
# 
# If no parameters are provided, script will use environment variables from .env
# or default values for local development
###############################################################################

set -e  # Exit on error

echo "=========================================="
echo "IBC-Intra Database Import"
echo "=========================================="
echo ""

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Load environment variables from .env if it exists
if [ -f .env ]; then
    echo "Loading environment variables from .env..."
    # Safer approach: use set -a to export all variables, then source
    set -a
    source .env
    set +a
    echo "✓ Environment loaded"
else
    echo "⚠️  Warning: .env file not found, using default/provided values"
fi

# Get database credentials from parameters or environment variables
DB_NAME="${1:-${DB_NAME:-ibc_intra}}"
DB_USER="${2:-${DB_USER:-root}}"
DB_PASS="${3:-${DB_PASS}}"
DB_HOST="${4:-${DB_HOST:-localhost}}"

# Set MYSQL_PWD environment variable for secure password handling
# This avoids password exposure in process list
if [ -n "$DB_PASS" ]; then
    export MYSQL_PWD="$DB_PASS"
fi

echo ""
echo "Database Configuration:"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Check if SQL file exists
SQL_FILE="create_database_sql/ibc_comprehensive_final.sql"
if [ ! -f "$SQL_FILE" ]; then
    echo "❌ ERROR: SQL file not found: $SQL_FILE"
    exit 1
fi

echo "SQL File: $SQL_FILE"
echo "File size: $(du -h "$SQL_FILE" | cut -f1)"
echo "Lines: $(wc -l < "$SQL_FILE")"
echo ""

# Confirmation prompt
echo "⚠️  WARNING: This will OVERWRITE existing tables in database '$DB_NAME'"
echo "⚠️  Make sure you have a backup before proceeding!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
echo ""

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "❌ Import cancelled by user"
    exit 0
fi

echo "Starting database import..."
echo ""

# Test database connection first
echo "[1/3] Testing database connection..."
if mysql -h "$DB_HOST" -u "$DB_USER" -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✓ Database connection successful"
else
    echo "❌ ERROR: Cannot connect to database"
    echo "   Please check your credentials and ensure MySQL server is running"
    exit 1
fi

# Create database if it doesn't exist
echo ""
echo "[2/3] Creating database if not exists..."
mysql -h "$DB_HOST" -u "$DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
echo "✓ Database ready"

# Import SQL file
echo ""
echo "[3/3] Importing SQL file..."
mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$SQL_FILE" 2>&1
echo "✓ SQL import completed"

echo ""
echo "=========================================="
echo "Database Import Successful!"
echo "=========================================="
echo ""

# Verify import by checking key tables
echo "Verifying database structure..."
TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | wc -l)
echo "✓ Tables created: $TABLES"

# Check for admin user
ADMIN_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM users WHERE email='admin@ibc-consulting.de';" 2>/dev/null | tail -n 1)
if [ "$ADMIN_EXISTS" -gt 0 ]; then
    echo "✓ Admin user found: admin@ibc-consulting.de"
else
    echo "⚠️  Warning: Admin user not found"
fi

echo ""
echo "📝 IMPORTANT NOTES:"
echo "   1. Admin login: admin@ibc-consulting.de"
echo "   2. Default password: Test12345"
echo "   3. CHANGE THE ADMIN PASSWORD IMMEDIATELY after first login!"
echo "   4. Review user_profiles sample data and adjust as needed"
echo ""
echo "Next steps:"
echo "   1. Run: npm install"
echo "   2. Run: npm run build"
echo "   3. Test the application"
echo ""
