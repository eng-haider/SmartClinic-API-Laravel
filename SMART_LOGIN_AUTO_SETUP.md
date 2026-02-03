# Smart Login with Auto-Setup Guide

## Overview

The **smart-login** endpoint now automatically creates and configures tenant databases if they don't exist. This makes the login process seamless - you only need to create the database in your hosting panel once.

## What Changed

### Before (Old Behavior)

```json
{
  "success": false,
  "message": "Database u876784197_tenant_alamal does not exist."
}
```

### After (New Behavior)

Smart-login now:

1. ✅ Checks if tenant record exists (creates if missing)
2. ✅ Checks if tenant database exists (provides clear instructions if not)
3. ✅ Auto-runs migrations if database is empty
4. ✅ Auto-seeds default data (roles, permissions, settings)
5. ✅ Auto-creates user in tenant database
6. ✅ Returns JWT token for immediate use

## Step-by-Step Setup

### Option 1: Using the Helper Script (Recommended)

#### Step 1: Create Clinic & User in Central Database

```bash
php create_clinic_and_user.php
```

**Edit the script first** to configure your clinic:

```php
$clinicId = 'alamal';  // Tenant ID (used in database name)
$clinicName = 'Al-Amal Dental Clinic';
$userPhone = '07801234567';  // Login phone
$userPassword = 'password123';  // Login password
```

This creates:

- ✅ Clinic record in central database
- ✅ User record in central database
- ✅ Links user to clinic

#### Step 2: Create Tenant Database in hPanel

Go to your hosting control panel and create:

**Database Name:** `u876784197_tenant_alamal`

- Replace `alamal` with your `$clinicId`

**Database User:** `u876784197_tenant_alamal` (same as database name)

**Password:** Use the value from `TENANT_DB_PASSWORD` in your `.env` file

#### Step 3: Test Smart-Login

```bash
curl -X POST http://your-domain.com/api/auth/smart-login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07801234567",
    "password": "password123"
  }'
```

**What Happens Automatically:**

1. Authenticates against central database ✅
2. Finds clinic "alamal" ✅
3. Creates tenant record (if missing) ✅
4. Connects to `u876784197_tenant_alamal` ✅
5. Runs migrations ✅
6. Seeds roles & permissions ✅
7. Creates user in tenant database ✅
8. Returns JWT token ✅

### Option 2: Using Registration (Full Auto)

If you want everything automated:

```bash
curl -X POST http://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Dr. Ahmed",
    "phone": "07801234567",
    "password": "password123",
    "password_confirmation": "password123",
    "clinic_name": "alamal",
    "clinic_address": "Baghdad, Iraq",
    "clinic_phone": "07809876543",
    "clinic_email": "alamal@clinic.com"
  }'
```

This does everything including creating the clinic AND user.

## Success Response

```json
{
  "success": true,
  "message": "Login successful",
  "message_ar": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "Dr. Ahmed",
      "phone": "07801234567",
      "email": "ahmed@alamal.com",
      "is_active": true
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "tenant_id": "alamal",
    "clinic_name": "Al-Amal Dental Clinic"
  }
}
```

## Error Handling

### Database Doesn't Exist

```json
{
  "success": false,
  "message": "Database 'u876784197_tenant_alamal' does not exist. Please create it in your hosting panel...",
  "message_ar": "فشل تسجيل الدخول: بيانات الدخول غير صحيحة"
}
```

**Solution:** Create the database in hPanel with the exact name specified.

### Invalid Credentials

```json
{
  "success": false,
  "message": "Invalid phone number or password",
  "message_ar": "فشل تسجيل الدخول: بيانات الدخول غير صحيحة"
}
```

**Solution:** Check phone number and password.

### User Not Associated with Clinic

```json
{
  "success": false,
  "message": "User is not associated with any clinic",
  "message_ar": "فشل تسجيل الدخول: بيانات الدخول غير صحيحة"
}
```

**Solution:** Ensure user has a `clinic_id` set in central database.

## Database Naming Convention

| Clinic ID        | Tenant Database Name               |
| ---------------- | ---------------------------------- |
| `alamal`         | `u876784197_tenant_alamal`         |
| `al_amal_clinic` | `u876784197_tenant_al_amal_clinic` |
| `clinic_123`     | `u876784197_tenant_clinic_123`     |

**Formula:** `{TENANCY_DB_PREFIX}{clinic_id}`

## Troubleshooting

### Check What Databases Exist

```bash
php check_databases.php
```

This shows:

- ✅ Central database status
- ✅ All tenant databases
- ✅ Existing clinics and users
- ✅ Configuration status

### Manually Create Records

If you prefer SQL:

```sql
-- Insert clinic
INSERT INTO clinics (id, name, address, phone, email, created_at, updated_at)
VALUES ('alamal', 'Al-Amal Clinic', 'Baghdad', '07809876543', 'alamal@clinic.com', NOW(), NOW());

-- Insert user
INSERT INTO users (name, phone, email, password, clinic_id, is_active, created_at, updated_at)
VALUES ('Dr. Ahmed', '07801234567', 'ahmed@alamal.com', '$2y$10$...hashed...', 'alamal', 1, NOW(), NOW());
```

### Reset Everything

To start fresh:

```sql
-- In central database
DELETE FROM users WHERE clinic_id = 'alamal';
DELETE FROM clinics WHERE id = 'alamal';
DELETE FROM tenants WHERE id = 'alamal';

-- Drop tenant database (or do in hPanel)
DROP DATABASE u876784197_tenant_alamal;
```

## Testing Checklist

- [ ] Central database has clinic record
- [ ] Central database has user record with correct `clinic_id`
- [ ] Tenant database created in hPanel: `u876784197_tenant_{clinic_id}`
- [ ] Tenant database user created: same name as database
- [ ] Password matches `TENANT_DB_PASSWORD` in `.env`
- [ ] Phone number and password are correct
- [ ] Smart-login returns JWT token

## Next Steps

After successful login:

1. Use the JWT token in `Authorization: Bearer {token}` header
2. Access protected endpoints like `/api/patients`, `/api/cases`, etc.
3. The system automatically knows which tenant database to use
4. All data is isolated per clinic

## Support

If you still get errors:

1. Run `php check_databases.php` to see current state
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify `.env` has correct `TENANT_DB_PASSWORD`
4. Ensure database exists in hPanel with exact name
5. Test database connection manually

---

**✅ Your system is now ready for multi-tenant smart login!**
