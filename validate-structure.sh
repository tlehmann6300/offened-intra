#!/bin/bash
# Validation script for modular structure refactoring

echo "=== IBC-Intra Modular Structure Validation ==="
echo ""

# Check if critical files exist
echo "1. Checking file structure..."
files=(
    "index.php"
    "src/Auth.php"
    "templates/pages/events.php"
    "templates/pages/alumni.php"
    "templates/pages/inventory.php"
    "docs/MODULAR_ARCHITECTURE.md"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ $file missing"
    fi
done

echo ""
echo "2. Checking PHP syntax..."
php -l index.php
php -l src/Auth.php

echo ""
echo "3. Checking for module permission checks in index.php..."
if grep -q "modulePermissions\[" index.php; then
    echo "  ✓ Module permissions array found"
else
    echo "  ✗ Module permissions array not found"
fi

if grep -q "checkPermission" index.php; then
    echo "  ✓ Permission checking code found"
else
    echo "  ✗ Permission checking code not found"
fi

echo ""
echo "4. Checking Auth class methods..."
auth_methods=("isLoggedIn" "checkSessionTimeout" "checkPermission" "getUserRole")
for method in "${auth_methods[@]}"; do
    if grep -q "function $method" src/Auth.php; then
        echo "  ✓ Auth::$method() exists"
    else
        echo "  ✗ Auth::$method() not found"
    fi
done

echo ""
echo "5. Checking session structure consistency..."
# Check microsoft_callback.php and Auth.php use same session variables
session_vars=("user_id" "role" "email" "firstname" "lastname" "last_activity" "auth_method")
for var in "${session_vars[@]}"; do
    auth_count=$(grep -c "\$_SESSION\['$var'\]" src/Auth.php || echo 0)
    ms_count=$(grep -c "\$_SESSION\['$var'\]" templates/pages/microsoft_callback.php || echo 0)
    
    if [ "$auth_count" -gt 0 ] && [ "$ms_count" -gt 0 ]; then
        echo "  ✓ \$_SESSION['$var'] used in both Auth and Microsoft SSO"
    elif [ "$auth_count" -eq 0 ] && [ "$ms_count" -eq 0 ]; then
        echo "  ⚠ \$_SESSION['$var'] not used (might be intentional)"
    else
        echo "  ✗ \$_SESSION['$var'] inconsistent between Auth and Microsoft SSO"
    fi
done

echo ""
echo "6. Checking documentation..."
if [ -f "docs/MODULAR_ARCHITECTURE.md" ]; then
    lines=$(wc -l < docs/MODULAR_ARCHITECTURE.md)
    echo "  ✓ Architecture documentation exists ($lines lines)"
else
    echo "  ✗ Architecture documentation missing"
fi

echo ""
echo "=== Validation Complete ==="
