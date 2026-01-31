#!/bin/bash

# Build Verification Script
# This script verifies that the build process works correctly

echo "🔍 IBC-Intra Build Verification"
echo "================================"
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed"
    exit 1
fi
echo "✅ Node.js is installed: $(node --version)"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed"
    exit 1
fi
echo "✅ npm is installed: $(npm --version)"

# Check if package.json exists
if [ ! -f "package.json" ]; then
    echo "❌ package.json not found"
    exit 1
fi
echo "✅ package.json found"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "⚠️  node_modules not found, running npm install..."
    npm install
fi
echo "✅ node_modules directory exists"

# Run the build
echo ""
echo "🔨 Running build process..."
echo ""
npm run build

# Check if minified files were created
echo ""
echo "📋 Checking generated files..."

if [ ! -f "assets/js/app.min.js" ]; then
    echo "❌ assets/js/app.min.js not created"
    exit 1
fi
echo "✅ assets/js/app.min.js created"

if [ ! -f "assets/css/fonts.min.css" ]; then
    echo "❌ assets/css/fonts.min.css not created"
    exit 1
fi
echo "✅ assets/css/fonts.min.css created"

if [ ! -f "assets/css/theme.min.css" ]; then
    echo "❌ assets/css/theme.min.css not created"
    exit 1
fi
echo "✅ assets/css/theme.min.css created"

# Get file sizes
echo ""
echo "📊 File Size Analysis:"
echo "----------------------"

# Original JS files
js_original=$(du -ch assets/js/main.js assets/js/navbar-responsive.js assets/js/news.js assets/js/pull-to-refresh.js assets/js/search.js | grep total | awk '{print $1}')
js_minified=$(du -h assets/js/app.min.js | awk '{print $1}')
echo "JavaScript:"
echo "  Original (5 files): $js_original"
echo "  Minified (1 file):  $js_minified"

# CSS files
css_fonts_original=$(du -h assets/css/fonts.css | awk '{print $1}')
css_fonts_minified=$(du -h assets/css/fonts.min.css | awk '{print $1}')
css_theme_original=$(du -h assets/css/theme.css | awk '{print $1}')
css_theme_minified=$(du -h assets/css/theme.min.css | awk '{print $1}')

echo ""
echo "CSS:"
echo "  fonts.css:      $css_fonts_original → $css_fonts_minified"
echo "  theme.css:      $css_theme_original → $css_theme_minified"

# Validate JavaScript syntax
echo ""
echo "🔍 Validating JavaScript syntax..."
if node -c assets/js/app.min.js &> /dev/null; then
    echo "✅ JavaScript syntax is valid"
else
    echo "❌ JavaScript syntax error detected"
    exit 1
fi

# Security check
echo ""
echo "🔒 Running security audit..."
npm audit --production 2>&1 | grep -E "(found 0 vulnerabilities|vulnerabilities)" | head -1

echo ""
echo "================================"
echo "✨ Build verification complete!"
echo ""
echo "📖 Next steps:"
echo "  1. Review the generated files in assets/js/ and assets/css/"
echo "  2. Test the application in a browser"
echo "  3. Check the BUILD.md for more information"
echo ""
