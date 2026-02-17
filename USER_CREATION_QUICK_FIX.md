# Quick Reference - User Creation Fix

## What Was Fixed

❌ **Before**: Creating users in tenant databases failed with:
```
Column not found: 1054 Unknown column 'clinic_id' in 'INSERT INTO'
```

✅ **After**: Users are created successfully in both tenant and central databases

## Key Changes

### 1. User Model
- Removed `clinic_id` from fillable array
- Now set manually for central DB users only

### 2. User Creation Flow

#### In Tenant Database (Doctors/Secretaries)
```php
// Create without clinic_id
$user = User::create([
    'name' => $data['name'],
    'phone' => $data['phone'],
    'password' => Hash::make($data['password']),
    'is_active' => true,
]);
```

#### In Central Database (Registration/Tenant Creation)
```php
// Create user
$user = User::on('central')->create($userData);

// Set clinic_id separately
$user->clinic_id = $clinic->id;
$user->save();
```

## Smart Login Support

When creating users in tenant databases (doctors/secretaries), they are **automatically** created in the central database too, so they can use smart login:

```
Tenant DB User Creation
         ↓
    [Success]
         ↓
Check if user exists in Central DB
         ↓
    [If not exists]
         ↓
Create user in Central DB with clinic_id
         ↓
User can now use smart login! ✅
```

## Updated Files

| File | What Changed |
|------|--------------|
| `app/Models/User.php` | Removed `clinic_id` from fillable |
| `app/Services/AuthService.php` | Set clinic_id separately after creation |
| `app/Http/Controllers/TenantController.php` | Set clinic_id separately after creation |
| `app/Repositories/DoctorRepository.php` | Exclude clinic_id + dual creation |
| `app/Repositories/SecretaryRepository.php` | Exclude clinic_id + dual creation |

## Testing Commands

### 1. Create a Doctor (Tenant API)
```bash
curl -X POST "http://localhost:8000/api/doctors" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: your_clinic" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Dr. Ahmad",
    "phone": "07701234567",
    "password": "password123",
    "is_active": true
  }'
```

### 2. Create a Secretary (Tenant API)
```bash
curl -X POST "http://localhost:8000/api/secretaries" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Tenant-ID: your_clinic" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sarah",
    "phone": "07709876543",
    "password": "password123",
    "permissions": ["view-clinic-patients"]
  }'
```

### 3. Test Smart Login
```bash
curl -X POST "http://localhost:8000/api/auth/smart-login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07701234567",
    "password": "password123"
  }'
```

## Database Differences

### Central Database (`users` table)
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    phone VARCHAR(255) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    clinic_id VARCHAR(255),  -- ← HAS THIS
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Tenant Database (`users` table)
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    phone VARCHAR(255) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    -- NO clinic_id column  -- ← DOESN'T HAVE THIS
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Why This Architecture?

### Central Database
- **Purpose**: User authentication and clinic management
- **Has clinic_id**: Links users to their clinics
- **Used for**: Smart login, tenant discovery

### Tenant Databases
- **Purpose**: Clinic-specific data (patients, cases, bills, etc.)
- **No clinic_id**: Data is already isolated by database
- **Used for**: All clinic operations after login

## Common Issues

### Issue: "Column not found: clinic_id"
**Solution**: Make sure you're not including `clinic_id` in mass assignment for tenant users

### Issue: User can't login with smart login
**Solution**: Ensure user exists in **both** central and tenant databases with same phone/password

### Issue: Updating seeders
**Solution**: Update seeders to not use `clinic_id` in mass assignment:
```php
// OLD (will fail in tenant DB)
$user = User::create([
    'name' => 'Test',
    'phone' => '123',
    'clinic_id' => $clinicId,  // ❌ Don't do this
]);

// NEW (works everywhere)
$user = User::create([
    'name' => 'Test',
    'phone' => '123',
]);
// Set clinic_id only for central DB
if (DB::connection()->getName() === 'mysql') {
    $user->clinic_id = $clinicId;
    $user->save();
}
```

## Need Help?

See detailed documentation:
- `CLINIC_ID_FIX_SUMMARY.md` - Complete fix documentation
- `SMART_LOGIN_SUMMARY.md` - Smart login documentation
- `TENANT_CREATION_API.md` - Tenant creation guide

---

✅ **Your system is now fixed and users can be created without errors!**
