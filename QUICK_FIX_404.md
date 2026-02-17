# 🚨 QUICK FIX: Production API Not Found (404)

## Problem

`https://api.smartclinic.software/api/auth/smart-login` returns **404 Not Found**

## Cause

Production server doesn't have your latest code.

## ✅ Solution (3 Steps)

### 1️⃣ SSH to Production Server

```bash
ssh your-user@api.smartclinic.software
cd /path/to/your/laravel/project
```

### 2️⃣ Run This Command

```bash
./deploy.sh
```

Or manually:

```bash
git pull origin main && \
php artisan config:clear && \
php artisan route:clear && \
php artisan cache:clear && \
php artisan optimize
```

### 3️⃣ Test

```bash
curl https://api.smartclinic.software/api/tenants
```

---

## 📱 If Using Hostinger/cPanel

1. **Login to hPanel**
2. **Open Terminal or File Manager**
3. **Navigate to project:** `cd domains/api.smartclinic.software/public_html`
4. **Delete these files:**
   - `bootstrap/cache/config.php`
   - `bootstrap/cache/routes-v7.php`
5. **Refresh the page**

---

## 🔍 Verify Routes Exist on Server

```bash
php artisan route:list | grep smart-login
```

Should show:

```
POST  api/auth/smart-login  AuthController@smartLogin
```

---

## 📞 Need More Help?

See full guide: `PRODUCTION_DEPLOYMENT_FIX.md`
