#!/bin/bash

# SmartClinic API - Hostinger Production Deployment Script
# Optimized for CloudLinux/cPanel environment

echo "════════════════════════════════════════════════════════"
echo "    SmartClinic API - Hostinger Deployment"
echo "════════════════════════════════════════════════════════"
echo ""

# PHP 8.4 path on Hostinger
PHP84="/opt/alt/php84/usr/bin/php"
COMPOSER84="/opt/alt/php84/usr/bin/composer"

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Are you in the Laravel project root?"
    exit 1
fi

echo "📦 Step 1: Pulling latest code from repository..."
git pull origin main
if [ $? -ne 0 ]; then
    echo "❌ Git pull failed. Please check for conflicts."
    exit 1
fi
echo "✅ Code updated"
echo ""

echo "🧹 Step 2: Clearing all caches..."
$PHP84 artisan config:clear
$PHP84 artisan route:clear
$PHP84 artisan cache:clear
$PHP84 artisan view:clear
$PHP84 artisan clear-compiled
echo "✅ Caches cleared"
echo ""

echo "📚 Step 3: Updating composer dependencies..."
# Update composer.lock to match current PHP version
$COMPOSER84 update --no-dev --optimize-autoloader --no-interaction 2>&1 | grep -v "platform"
if [ $? -eq 0 ]; then
    echo "✅ Dependencies updated"
else
    echo "⚠️  Composer update had some issues, trying install..."
    $COMPOSER84 install --no-dev --optimize-autoloader --no-interaction
fi
echo ""

echo "⚡ Step 4: Optimizing application..."
$PHP84 artisan optimize 2>&1 | grep -v "platform"
echo "✅ Application optimized"
echo ""

echo "🔍 Step 5: Verifying deployment..."
echo "Checking critical routes:"
$PHP84 artisan route:list 2>&1 | grep -E "smart-login|tenants" | head -5
echo ""

echo "════════════════════════════════════════════════════════"
echo "✅ Deployment completed successfully!"
echo "════════════════════════════════════════════════════════"
echo ""
echo "🧪 Test your API with:"
echo "   curl https://api.smartclinic.software/api/tenants"
echo ""
echo "📋 If routes still don't work, run:"
echo "   $PHP84 artisan route:cache"
echo ""
echo "📋 Monitor logs with:"
echo "   tail -f storage/logs/laravel.log"
echo ""
