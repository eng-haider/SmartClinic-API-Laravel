# Clinic ID Column Fix - Summary

## Problem

When creating users (doctors, secretaries) in tenant databases, the system was trying to insert `clinic_id` column which doesn't exist in tenant `users` tables:

```json
{
  "success": false,
  "message": "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'clinic_id' in 'INSERT INTO' (Connection: tenant, SQL: insert into `users` (`name`, `email`, `phone`, `password`, `is_active`, `clinic_id`, `updated_at`, `created_at`) values (...))"
}
```

## Root Cause

- **Central database** `users` table has `clinic_id` column (to link users to clinics)
- **Tenant database** `users` tables do NOT have `clinic_id` column (not needed - data is already isolated by database)
- User model had `clinic_id` in fillable array, causing it to be included in all insert operations

## Solution

### 1. Removed `clinic_id` from User Model Fillable

**File**: `app/Models/User.php`

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    // 'clinic_id', // REMOVED - set manually for central DB only
    'is_active',
];
```

### 2. Updated Central Database User Creation

For operations that create users in the central database (registration, tenant creation), we now set `clinic_id` separately:

**File**: `app/Services/AuthService.php`

```php
$user = $this->userRepository->create($userData);

// Set clinic_id separately for central database users
$user->clinic_id = $clinic->id;
$user->save();
```

**File**: `app/Http/Controllers/TenantController.php`

```php
$centralUser = User::on($centralConnection)->create([
    'name' => $validated['user_name'],
    'phone' => $validated['user_phone'],
    'email' => $validated['user_email'] ?? null,
    'password' => Hash::make($validated['user_password']),
    'is_active' => true,
]);

// Set clinic_id separately
$centralUser->clinic_id = $tenantId;
$centralUser->save();
```

### 3. Updated Repositories to Exclude clinic_id

**File**: `app/Repositories/DoctorRepository.php`

```php
public function create(array $data): User
{
    // Remove clinic_id from data for tenant databases
    $userData = collect($data)->except(['clinic_id'])->toArray();

    $doctor = User::create($userData);
    // ...
}
```

**File**: `app/Repositories/SecretaryRepository.php`

```php
public function create(array $data): User
{
    // Don't include clinic_id in tenant database creation
    $secretary = User::create([
        'name' => $data['name'],
        'phone' => $data['phone'],
        'password' => Hash::make($data['password']),
        'is_active' => $data['is_active'] ?? true,
    ]);
    // ...
}
```

### 4. Added Dual User Creation for Smart Login

When creating doctors/secretaries in tenant databases, the system now also creates them in the central database to enable smart login:

**File**: `app/Repositories/DoctorRepository.php` & `SecretaryRepository.php`

```php
// Create user in tenant database first
$doctor = User::create($userData);

// Also create in central database for smart login
try {
    $authUser = Auth::user();
    if ($authUser && $authUser->clinic_id) {
        $centralConnection = config('tenancy.database.central_connection');

        $existingCentralUser = User::on($centralConnection)
            ->where('phone', $doctor->phone)
            ->first();

        if (!$existingCentralUser) {
            $centralUser = User::on($centralConnection)->create([
                'name' => $doctor->name,
                'phone' => $doctor->phone,
                'email' => $doctor->email ?? null,
                'password' => $doctor->password,
                'is_active' => $doctor->is_active,
            ]);

            $centralUser->clinic_id = $authUser->clinic_id;
            $centralUser->save();
        }
    }
} catch (\Exception $e) {
    Log::warning('Failed to create user in central database', [
        'phone' => $doctor->phone,
        'error' => $e->getMessage(),
    ]);
}
```

## Files Modified

1. ✅ `app/Models/User.php` - Removed `clinic_id` from fillable
2. ✅ `app/Services/AuthService.php` - Set `clinic_id` separately after user creation
3. ✅ `app/Http/Controllers/TenantController.php` - Set `clinic_id` separately for central users
4. ✅ `app/Repositories/DoctorRepository.php` - Exclude `clinic_id` and create dual records
5. ✅ `app/Repositories/SecretaryRepository.php` - Exclude `clinic_id` and create dual records

## Database Structure

### Central Database (`smartclinic_central` or `u876784197_smartclinic`)

```sql
users
├── id
├── name
├── phone
├── email
├── password
├── clinic_id       ← EXISTS (links to clinics table)
├── is_active
└── ...
```

### Tenant Databases (`tenant__clinic_id` or `u876784197_tenant_clinic_id`)

```sql
users
├── id
├── name
├── phone
├── email
├── password
├── is_active       ← NO clinic_id column
└── ...
```

## Benefits

1. ✅ **No more column not found errors** when creating users in tenant databases
2. ✅ **Smart login works** - users created in tenant DBs are also added to central DB
3. ✅ **Clean architecture** - `clinic_id` only exists where it's needed (central DB)
4. ✅ **Multi-tenancy isolation** - Tenant databases don't need clinic_id since data is already isolated by database

## Testing

### Test Creating a Doctor

```bash
POST /api/doctors
Headers:
  Authorization: Bearer YOUR_JWT_TOKEN
  X-Tenant-ID: your_clinic_id
  Content-Type: application/json

Body:
{
  "name": "Dr. Ahmad",
  "phone": "07701234567",
  "password": "password123",
  "is_active": true
}
```

**Expected Result**:

- ✅ Doctor created in tenant database
- ✅ Doctor also created in central database (for smart login)
- ✅ No error about clinic_id column

### Test Creating a Secretary

```bash
POST /api/secretaries
Headers:
  Authorization: Bearer YOUR_JWT_TOKEN
  X-Tenant-ID: your_clinic_id
  Content-Type: application/json

Body:
{
  "name": "Sarah",
  "phone": "07709876543",
  "password": "password123",
  "permissions": ["view-clinic-patients", "create-patient"]
}
```

**Expected Result**:

- ✅ Secretary created in tenant database
- ✅ Secretary also created in central database (for smart login)
- ✅ No error about clinic_id column

### Test Smart Login

```bash
POST /api/auth/smart-login
Headers:
  Content-Type: application/json

Body:
{
  "phone": "07701234567",
  "password": "password123"
}
```

**Expected Result**:

- ✅ User authenticated from central database
- ✅ Tenant context initialized
- ✅ User data loaded from tenant database
- ✅ JWT token returned

## Important Notes

1. **Existing Users**: Users already in tenant databases won't have records in central DB until they're updated or you run a migration script
2. **Password Sync**: When updating passwords, remember to update both central and tenant databases
3. **User Deletion**: Consider implementing cascade deletion or sync deletion between databases
4. **Backward Compatibility**: Old seeders/scripts that use `clinic_id` in mass assignment will need to be updated

## Summary

The fix ensures that:

- ✅ Tenant users tables don't require `clinic_id` column
- ✅ Central database users have `clinic_id` set properly
- ✅ New users created in tenant DBs are also added to central DB for smart login
- ✅ No column not found errors when creating doctors/secretaries

🎉 **Your system now properly handles user creation across multi-tenant architecture!**
