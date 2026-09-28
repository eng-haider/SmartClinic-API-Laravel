# Production Server Deployment Fix

## 🔴 Problem

The API at `https://api.smartclinic.software` is returning **"Endpoint not found"** or **404 errors** for routes that exist in your local code.

**Routes affected:**

- `/api/auth/smart-login`
- `/api/tenants`
- And possibly others

## 🎯 Root Cause

Your **production server** (`api.smartclinic.software`) doesn't have the latest code from your repository. The changes you made locally are not deployed to production yet.

## ✅ Solution: Deploy to Production

### Step 1: Check Current Production Code

SSH into your production server and check what code is running:

```bash
# SSH to your production server
ssh user@api.smartclinic.software

# Navigate to your Laravel project
cd /path/to/smartclinic-api

# Check current git status
git status
git log -1
```

### Step 2: Pull Latest Code

```bash
# Make sure you're on the correct branch
git branch

# Pull latest changes
git pull origin main
```

### Step 3: Clear All Caches

**CRITICAL:** After pulling code, you MUST clear caches:

```bash
# Clear configuration cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear application cache
php artisan cache:clear

# Clear view cache
php artisan view:clear

# Clear compiled files
php artisan clear-compiled

# Optimize for production (optional, but recommended)
php artisan optimize
```

### Step 4: Update Dependencies (if needed)

If you've added new packages or updated `composer.json`:

```bash
composer install --no-dev --optimize-autoloader
```

### Step 5: Restart Services

Depending on your server setup:

```bash
# If using PHP-FPM
sudo systemctl restart php8.2-fpm
# or
sudo service php8.2-fpm restart

# If using Nginx
sudo systemctl restart nginx

# If using Apache
sudo systemctl restart apache2
```

### Step 6: Verify Deployment

Check that the routes are now available:

```bash
php artisan route:list | grep "smart-login"
php artisan route:list | grep "tenants"
```

You should see:

```
POST  api/auth/smart-login  AuthController@smartLogin
GET   api/tenants           TenantController@index
```

## 🧪 Test the Production API

After deployment, test the endpoints:

```bash
# Test smart-login
curl -X POST https://api.smartclinic.software/api/auth/smart-login \
  -H "Content-Type: application/json" \
  -d '{"phone": "07700281899", "password": "12345678"}'

# Test tenants list
curl https://api.smartclinic.software/api/tenants
```

## 🚨 If You're Using Hostinger/cPanel

If your production is on shared hosting (Hostinger):

### Option A: File Manager

1. **Login to hPanel/cPanel**
2. **Go to File Manager**
3. **Navigate to your project directory**
4. **Delete the cache files:**
   - `bootstrap/cache/config.php`
   - `bootstrap/cache/routes-v7.php`
   - `bootstrap/cache/packages.php`
   - `bootstrap/cache/services.php`

5. **Use Terminal (if available):**
   ```bash
   cd public_html  # or your project path
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

### Option B: Git Deployment

If you have Git access on Hostinger:

1. **Open Terminal in hPanel**
2. **Navigate to your project:**
   ```bash
   cd domains/api.smartclinic.software/public_html
   ```
3. **Pull latest code:**
   ```bash
   git pull origin main
   ```
4. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

### Option C: Re-upload Files

If you don't have Git access:

1. **Upload these files via FTP/File Manager:**
   - `routes/api.php`
   - `config/tenancy.php`
   - `app/Http/Controllers/AuthController.php`
   - Any other modified files

2. **Delete cache files** (see Option A)

## 📋 Quick Deployment Checklist

- [ ] SSH or access production server
- [ ] Pull latest code (`git pull origin main`)
- [ ] Clear config cache (`php artisan config:clear`)
- [ ] Clear route cache (`php artisan route:clear`)
- [ ] Clear app cache (`php artisan cache:clear`)
- [ ] Restart PHP-FPM/Apache/Nginx
- [ ] Test endpoints with curl or Postman
- [ ] Verify routes exist (`php artisan route:list`)

## 🔍 Debugging Production Issues

### Check Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

### Check Web Server Logs

```bash
# Nginx
tail -f /var/log/nginx/error.log

# Apache
tail -f /var/log/apache2/error.log
```

### Verify .htaccess (if using Apache)

Make sure `public/.htaccess` exists and has the correct rewrite rules.

### Check File Permissions

```bash
# Set correct permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 🎯 Common Issues After Deployment

### Issue 1: Still Getting 404

**Solution:**

```bash
php artisan route:cache
php artisan config:cache
```

### Issue 2: "Class not found" Errors

**Solution:**

```bash
composer dump-autoload
php artisan clear-compiled
php artisan optimize
```

### Issue 3: Permission Denied Errors

**Solution:**

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## 📞 Testing After Fix

Use this curl command to test:

```bash
curl -v https://api.smartclinic.software/api/tenants
```

Expected response:

```json
{
  "success": true,
  "message": "Tenants retrieved successfully",
  "data": [...]
}
```

## 🚀 Future Deployments

To avoid this issue in the future:

1. **Always clear caches after deploying code**
2. **Use automated deployment** (Git hooks, GitHub Actions, etc.)
3. **Create a deployment script:**

```bash
#!/bin/bash
# deploy.sh

echo "🚀 Deploying SmartClinic API..."

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan optimize
sudo systemctl restart php8.2-fpm

echo "✅ Deployment complete!"
```

Save this as `deploy.sh`, make it executable (`chmod +x deploy.sh`), and run it after pushing changes.

## ✅ Summary

Your local code is correct. The issue is that your production server needs:

1. ✅ Latest code from git repository
2. ✅ Cleared caches (config, routes, views)
3. ✅ Restarted services (PHP-FPM/Apache/Nginx)

After these steps, `https://api.smartclinic.software/api/auth/smart-login` will work! 🎉
