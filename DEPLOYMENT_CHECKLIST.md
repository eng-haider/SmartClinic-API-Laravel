# Deployment Checklist - Domain Tenant Fix

## ✅ Fix Already Applied Locally

The configuration change is already in your repository:

- `config/tenancy.php` has `api.smartclinic.software` in `central_domains`

## 🚀 To Deploy to Production Server

If you're experiencing this error on your **production server**, follow these steps:

### 1. Pull Latest Changes

```bash
cd /path/to/your/production/smartclinic-api
git pull origin main
```

### 2. Clear Configuration Cache

**IMPORTANT:** Configuration changes only take effect after clearing the cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Verify the Fix

Check that the domain is configured correctly:

```bash
php test_domain_config.php
```

Expected output:

```
✅ SUCCESS: api.smartclinic.software is configured as a central domain.
```

### 4. Test the API

Try accessing your API endpoints:

```bash
# Test central endpoint (no tenant ID needed)
curl https://api.smartclinic.software/api/health

# Test tenant endpoint (with X-Tenant-ID header)
curl -H "X-Tenant-ID: _haider" https://api.smartclinic.software/api/tenant/patients
```

## 🔧 If Using Hostinger/cPanel

If you're on shared hosting:

1. **Access your hosting control panel**
2. **Open File Manager** or use FTP/SSH
3. **Navigate to** your Laravel installation directory
4. **Run the clear commands** via Terminal (if available) or:
   - Delete `bootstrap/cache/config.php` manually
   - Access any route to regenerate cache

## 🎯 Alternative: Manual Config Update

If you can't pull from git, manually update the file:

**File:** `config/tenancy.php`

Find this section (around line 14-22):

```php
'central_domains' => [
    '127.0.0.1',
    'localhost',
],
```

Change it to:

```php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    'api.smartclinic.software',
    'smartclinic.software',
],
```

Then **clear cache** (step 2 above).

## 📋 Quick Reference

### What This Fix Does

- Tells Laravel that `api.smartclinic.software` is a **central domain**
- Prevents tenant identification by domain on this URL
- Allows header-based tenant identification (`X-Tenant-ID`)

### Before Fix

```
Request → api.smartclinic.software
         ↓
System tries to find tenant with this domain
         ↓
❌ Error: TenantCouldNotBeIdentifiedOnDomainException
```

### After Fix

```
Request → api.smartclinic.software
         ↓
System recognizes it as central domain
         ↓
✅ Uses X-Tenant-ID header for tenant routes
✅ Works without tenant ID for central routes
```

## 🧪 Testing

### Test Central Routes (No Tenant Needed)

```bash
GET /api/tenants          # List all tenants
GET /api/health           # Health check
POST /api/tenants         # Create tenant
```

### Test Tenant Routes (Needs X-Tenant-ID Header)

```bash
GET /api/tenant/patients
Headers: X-Tenant-ID: _haider

GET /api/tenant/doctors
Headers: X-Tenant-ID: _alamal
```

## 🐛 Troubleshooting

### Still Getting the Error?

1. **Cache not cleared?**

   ```bash
   php artisan config:clear
   php artisan cache:clear
   # Or restart your web server
   ```

2. **Wrong file edited?**
   - Make sure you edited `config/tenancy.php` (not `config/tenant.php`)
   - Check the file actually saved

3. **Using opcache?**

   ```bash
   php artisan optimize:clear
   # Or restart PHP-FPM
   ```

4. **Still broken?**
   - Check Laravel logs: `storage/logs/laravel.log`
   - Enable debug mode: `APP_DEBUG=true` in `.env`
   - Check web server error logs

## ✅ Success Indicators

You'll know it's working when:

- ✅ Can access `api.smartclinic.software/api/tenants` without error
- ✅ Can access tenant routes with `X-Tenant-ID` header
- ✅ No more `TenantCouldNotBeIdentifiedOnDomainException`

## 📞 Need Help?

Run the diagnostic script:

```bash
php test_domain_config.php
```

Check the logs:

```bash
tail -f storage/logs/laravel.log
```
