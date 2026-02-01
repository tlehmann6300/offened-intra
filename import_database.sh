#!/bin/bash

###############################################################################
# IBC-Intra Database Import Script
# 
# This script imports the database schemas and test data from:
# - dbs15253086.sql (User Database)
# - dbs15161271.sql (Content Database)
#
# ⚠️  WARNING: This will OVERWRITE existing database tables!
# ⚠️  Make sure to backup your database before running this script!
#
# Usage: ./import_database.sh
# 
# The script will use environment variables from .env for database credentials
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
    echo "⚠️  Warning: .env file not found, using default values"
fi

# User Database Configuration
USER_DB_NAME="${USER_DB_NAME:-dbs15253086}"
USER_DB_USER="${USER_DB_USER:-dbu4494103}"
USER_DB_PASS="${USER_DB_PASS}"
USER_DB_HOST="${USER_DB_HOST:-db5019508945.hosting-data.io}"

# Content Database Configuration
CONTENT_DB_NAME="${CONTENT_DB_NAME:-dbs15161271}"
CONTENT_DB_USER="${CONTENT_DB_USER:-dbu2067984}"
CONTENT_DB_PASS="${CONTENT_DB_PASS}"
CONTENT_DB_HOST="${CONTENT_DB_HOST:-db5019375140.hosting-data.io}"

echo ""
echo "Database Configuration:"
echo "  User DB Host: $USER_DB_HOST"
echo "  User Database: $USER_DB_NAME"
echo "  User DB User: $USER_DB_USER"
echo ""
echo "  Content DB Host: $CONTENT_DB_HOST"
echo "  Content Database: $CONTENT_DB_NAME"
echo "  Content DB User: $CONTENT_DB_USER"
echo ""

# Check if SQL files exist
USER_SQL_FILE="dbs15253086.sql"
CONTENT_SQL_FILE="dbs15161271.sql"

if [ ! -f "$USER_SQL_FILE" ]; then
    echo "❌ ERROR: SQL file not found: $USER_SQL_FILE"
    exit 1
fi

if [ ! -f "$CONTENT_SQL_FILE" ]; then
    echo "❌ ERROR: SQL file not found: $CONTENT_SQL_FILE"
    exit 1
fi

echo "User DB SQL File: $USER_SQL_FILE"
echo "File size: $(du -h "$USER_SQL_FILE" | cut -f1)"
echo "Lines: $(wc -l < "$USER_SQL_FILE")"
echo ""

echo "Content DB SQL File: $CONTENT_SQL_FILE"
echo "File size: $(du -h "$CONTENT_SQL_FILE" | cut -f1)"
echo "Lines: $(wc -l < "$CONTENT_SQL_FILE")"
echo ""

# Confirmation prompt
echo "⚠️  WARNING: This will OVERWRITE existing tables in both databases"
echo "⚠️  Make sure you have backups before proceeding!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
echo ""

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "❌ Import cancelled by user"
    exit 0
fi

echo "Starting database import..."
echo ""

# ============================================================
# IMPORT USER DATABASE
# ============================================================

echo "=========================================="
echo "Importing User Database (dbs15253086)"
echo "=========================================="
echo ""

# Set password for User DB
if [ -n "$USER_DB_PASS" ]; then
    export MYSQL_PWD="$USER_DB_PASS"
fi

# Test User database connection
echo "[1/4] Testing User database connection..."
if mysql -h "$USER_DB_HOST" -u "$USER_DB_USER" -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✓ User database connection successful"
else
    echo "❌ ERROR: Cannot connect to User database"
    echo "   Please check your credentials and ensure MySQL server is running"
    exit 1
fi

# Import User SQL file
echo ""
echo "[2/4] Importing User database schema and data..."
mysql -h "$USER_DB_HOST" -u "$USER_DB_USER" "$USER_DB_NAME" < "$USER_SQL_FILE" 2>&1
echo "✓ User database import completed"

# Verify User database
echo ""
echo "Verifying User database structure..."
USER_TABLES=$(mysql -h "$USER_DB_HOST" -u "$USER_DB_USER" "$USER_DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | wc -l)
echo "✓ User database tables created: $USER_TABLES"

# Check for admin user
ADMIN_EXISTS=$(mysql -h "$USER_DB_HOST" -u "$USER_DB_USER" "$USER_DB_NAME" -e "SELECT COUNT(*) FROM users WHERE email='tom.lehmann@business-consulting.de';" 2>/dev/null | tail -n 1)
if [ "$ADMIN_EXISTS" -gt 0 ]; then
    echo "✓ Admin user found: tom.lehmann@business-consulting.de"
else
    echo "⚠️  Warning: Admin user not found"
fi

# ============================================================
# IMPORT CONTENT DATABASE
# ============================================================

echo ""
echo "=========================================="
echo "Importing Content Database (dbs15161271)"
echo "=========================================="
echo ""

# Set password for Content DB
if [ -n "$CONTENT_DB_PASS" ]; then
    export MYSQL_PWD="$CONTENT_DB_PASS"
fi

# Test Content database connection
echo "[3/4] Testing Content database connection..."
if mysql -h "$CONTENT_DB_HOST" -u "$CONTENT_DB_USER" -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✓ Content database connection successful"
else
    echo "❌ ERROR: Cannot connect to Content database"
    echo "   Please check your credentials and ensure MySQL server is running"
    exit 1
fi

# Import Content SQL file
echo ""
echo "[4/4] Importing Content database schema and data..."
mysql -h "$CONTENT_DB_HOST" -u "$CONTENT_DB_USER" "$CONTENT_DB_NAME" < "$CONTENT_SQL_FILE" 2>&1
echo "✓ Content database import completed"

# Verify Content database
echo ""
echo "Verifying Content database structure..."
CONTENT_TABLES=$(mysql -h "$CONTENT_DB_HOST" -u "$CONTENT_DB_USER" "$CONTENT_DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | wc -l)
echo "✓ Content database tables created: $CONTENT_TABLES"

echo ""
echo "=========================================="
echo "Database Import Successful!"
echo "=========================================="
echo ""
echo "📝 IMPORTANT NOTES:"
echo "   1. User DB Tables: $USER_TABLES"
echo "   2. Content DB Tables: $CONTENT_TABLES"
echo "   3. Admin login: tom.lehmann@business-consulting.de"
echo "   4. Default password: AdminPass2024!"
echo "   5. CHANGE THE ADMIN PASSWORD IMMEDIATELY after first login!"
echo ""
echo "Next steps:"
echo "   1. Test the application login"
echo "   2. Review and update sample data as needed"
echo ""
