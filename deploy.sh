#!/bin/bash

# SmartClinic API - Production Deployment Script
# Run this script on your production server after pushing changes

echo "════════════════════════════════════════════════════════"
echo "    SmartClinic API - Production Deployment"
echo "════════════════════════════════════════════════════════"
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: artisan file not found. Are you in the Laravel project root?${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Step 1: Pulling latest code from repository...${NC}"
git pull origin main
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Git pull failed. Please check for conflicts.${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Code updated${NC}"
echo ""

echo -e "${YELLOW}🧹 Step 2: Clearing all caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
php artisan clear-compiled
echo -e "${GREEN}✅ Caches cleared${NC}"
echo ""

echo -e "${YELLOW}📚 Step 3: Installing/updating dependencies...${NC}"
composer install --no-dev --optimize-autoloader --quiet
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Dependencies updated${NC}"
else
    echo -e "${YELLOW}⚠️  Composer install had warnings (this is usually okay)${NC}"
fi
echo ""

echo -e "${YELLOW}⚡ Step 4: Optimizing application...${NC}"
php artisan optimize
echo -e "${GREEN}✅ Application optimized${NC}"
echo ""

echo -e "${YELLOW}🔄 Step 5: Restarting services...${NC}"
# Try to restart PHP-FPM (might require sudo)
if command -v systemctl &> /dev/null; then
    # Detect PHP version
    if systemctl list-units --type=service | grep -q "php8.2-fpm"; then
        sudo systemctl restart php8.2-fpm 2>/dev/null && echo -e "${GREEN}✅ PHP 8.2-FPM restarted${NC}" || echo -e "${YELLOW}⚠️  Could not restart PHP-FPM (might need sudo)${NC}"
    elif systemctl list-units --type=service | grep -q "php8.1-fpm"; then
        sudo systemctl restart php8.1-fpm 2>/dev/null && echo -e "${GREEN}✅ PHP 8.1-FPM restarted${NC}" || echo -e "${YELLOW}⚠️  Could not restart PHP-FPM (might need sudo)${NC}"
    elif systemctl list-units --type=service | grep -q "php-fpm"; then
        sudo systemctl restart php-fpm 2>/dev/null && echo -e "${GREEN}✅ PHP-FPM restarted${NC}" || echo -e "${YELLOW}⚠️  Could not restart PHP-FPM (might need sudo)${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  systemctl not available. Please restart PHP-FPM manually if needed.${NC}"
fi
echo ""

echo -e "${YELLOW}🔍 Step 6: Verifying deployment...${NC}"
echo "Checking critical routes:"
php artisan route:list | grep -E "smart-login|tenants" | head -5
echo ""

echo "════════════════════════════════════════════════════════"
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo "════════════════════════════════════════════════════════"
echo ""
echo "🧪 Test your API with:"
echo "   curl https://api.smartclinic.software/api/tenants"
echo ""
echo "📋 Monitor logs with:"
echo "   tail -f storage/logs/laravel.log"
echo ""
