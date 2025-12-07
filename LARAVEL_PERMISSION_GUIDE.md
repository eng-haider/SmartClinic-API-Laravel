# Laravel Permission Setup Guide

Complete guide for role-based access control (RBAC) using Spatie Laravel Permission in SmartClinic API.

## Table of Contents

1. [Overview](#overview)
2. [Roles](#roles)
3. [Permissions](#permissions)
4. [Usage](#usage)
5. [API Integration](#api-integration)
6. [Examples](#examples)
7. [Database Schema](#database-schema)
8. [Best Practices](#best-practices)

---

## Overview

**Spatie Laravel Permission v6.23.0** provides powerful role and permission management:

✅ Role-based access control (RBAC)
✅ Fine-grained permission management
✅ Dynamic role and permission assignment
✅ Middleware for route protection
✅ Blade directives for frontend control
✅ Database-backed roles and permissions

---

## Roles

The system includes 5 default roles:

### 1. **Admin**

- Full system access
- All permissions granted
- Can manage users, roles, and permissions
- Can manage patients

**Permissions:**

- view-patients, create-patient, edit-patient, delete-patient, search-patient
- view-users, create-user, edit-user, delete-user
- manage-permissions, manage-roles

### 2. **Doctor**

- Access to patient management
- Can view and edit patient records
- Can manage assigned patients
- Cannot delete patients or manage users

**Permissions:**

- view-patients
- create-patient
- edit-patient
- search-patient
- view-users

### 3. **Nurse**

- Limited patient access
- Can view and create patient records
- Cannot edit existing patient records
- Cannot manage users

**Permissions:**

- view-patients
- create-patient
- edit-patient
- search-patient

### 4. **Receptionist**

- Front-desk operations
- Can create and search patients
- Can view patients
- Limited data access

**Permissions:**

- view-patients
- create-patient
- search-patient

### 5. **User** (Default)

- Regular user with minimal access
- Can only view and search patients
- No creation or modification rights

**Permissions:**

- view-patients
- search-patient

---

## Permissions

### Patient Permissions

```
view-patients        - View patient list and details
create-patient       - Create new patient records
edit-patient         - Edit existing patient records
delete-patient       - Delete patient records
search-patient       - Search patients
```

### User Management Permissions

```
view-users           - View users list
create-user          - Create new users
edit-user            - Edit user information
delete-user          - Delete users
```

### System Permissions

```
manage-permissions   - Manage system permissions
manage-roles         - Manage user roles
```

---

## Usage

### Check if User Has Permission

```php
// In controller
if (Auth::user()->hasPermissionTo('edit-patient')) {
    // User can edit patients
}

// In blade template
@if(Auth::user()->hasPermissionTo('edit-patient'))
    <button>Edit Patient</button>
@endif
```

### Check if User Has Role

```php
// In controller
if (Auth::user()->hasRole('admin')) {
    // User is admin
}

if (Auth::user()->hasAnyRole(['admin', 'doctor'])) {
    // User is admin or doctor
}

// In blade template
@role('admin')
    <p>Welcome Admin</p>
@endrole

@hasanyrole('admin|doctor')
    <p>Welcome Doctor or Admin</p>
@endhasanyrole
```

### Assign Role to User

```php
$user = User::find(1);

// Assign single role
$user->assignRole('doctor');

// Assign multiple roles
$user->assignRole(['doctor', 'nurse']);

// Sync roles (replaces existing)
$user->syncRoles(['admin']);
```

### Assign Permission to User

```php
$user = User::find(1);

// Give permission directly to user
$user->givePermissionTo('edit-patient');

// Give multiple permissions
$user->givePermissionTo(['edit-patient', 'delete-patient']);
```

### Check Permissions in Code

```php
$user = Auth::user();

// Check single permission
if ($user->can('edit-patient')) {
    // User can edit patients
}

// Check multiple permissions (all)
if ($user->canAll(['edit-patient', 'delete-patient'])) {
    // User has all permissions
}

// Check multiple permissions (any)
if ($user->canAny(['edit-patient', 'delete-patient'])) {
    // User has at least one permission
}
```

---

## API Integration

### Protected Routes with Permissions

Update `routes/api.php`:

```php
// Patient routes with permission checks
Route::middleware(['jwt', 'permission:view-patients'])->group(function () {
    Route::get('patients', [PatientController::class, 'index']);
    Route::get('patients/{id}', [PatientController::class, 'show']);
});

Route::middleware(['jwt', 'permission:create-patient'])->group(function () {
    Route::post('patients', [PatientController::class, 'store']);
});

Route::middleware(['jwt', 'permission:edit-patient'])->group(function () {
    Route::put('patients/{id}', [PatientController::class, 'update']);
});

Route::middleware(['jwt', 'permission:delete-patient'])->group(function () {
    Route::delete('patients/{id}', [PatientController::class, 'destroy']);
});
```

### Check Permission in Controller

```php
public function update(Request $request, $id)
{
    // Check permission
    if (!auth()->user()->can('edit-patient')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action',
        ], 403);
    }

    // Update logic
    $patient = Patient::find($id);
    $patient->update($request->validated());

    return response()->json([
        'success' => true,
        'message' => 'Patient updated successfully',
        'data' => $patient,
    ]);
}
```

### Authorization Policy

Create a policy class for complex authorization logic:

```bash
php artisan make:policy PatientPolicy
```

```php
// app/Policies/PatientPolicy.php
namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        return $user->can('view-patients');
    }

    public function create(User $user): bool
    {
        return $user->can('create-patient');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('edit-patient');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('delete-patient');
    }
}
```

Register in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;
use App\Models\Patient;
use App\Policies\PatientPolicy;

public function boot()
{
    Gate::policy(Patient::class, PatientPolicy::class);
}
```

Use in controller:

```php
$this->authorize('view', $patient);  // Checks policy
```

---

## Examples

### Example 1: Create User with Role

```php
$user = User::create([
    'name' => 'Dr. Ahmed',
    'email' => 'ahmed@clinic.com',
    'phone' => '201001234567',
    'password' => bcrypt('password123'),
    'role' => 'doctor',
]);

// Assign role
$user->assignRole('doctor');

// Grant additional permission
$user->givePermissionTo('manage-roles');
```

### Example 2: Update User Permissions

```php
$user = User::find(1);

// Check current role
if ($user->hasRole('nurse')) {
    // Promote to doctor
    $user->syncRoles('doctor');
}

// Add additional permission
$user->givePermissionTo('delete-patient');
```

### Example 3: Check User Capabilities

```php
$user = Auth::user();

// Check role
if ($user->hasRole('admin')) {
    // Show admin panel
}

// Check permission
if ($user->hasPermissionTo('create-patient')) {
    // Show create patient button
}

// Get all permissions
$permissions = $user->getPermissionsViaRoles();

// Get all roles
$roles = $user->roles;
```

### Example 4: Seeders with Roles

```bash
php artisan db:seed --class=RoleAndPermissionSeeder
php artisan db:seed --class=DatabaseSeeder
```

This creates:

- All roles and permissions
- Test users with assigned roles:
  - Admin User (admin@example.com)
  - Doctor User (doctor@example.com)
  - Nurse User (nurse@example.com)
  - Receptionist User (receptionist@example.com)
  - Regular User (user@example.com)

---

## Database Schema

### Permission Tables

**roles**

```
id          - Role ID
name        - Role name (admin, doctor, etc)
guard_name  - Guard name (web, api)
created_at
updated_at
```

**permissions**

```
id          - Permission ID
name        - Permission name (create-patient, etc)
guard_name  - Guard name
created_at
updated_at
```

**model_has_roles**

```
role_id     - Foreign key to roles
model_id    - User ID
model_type  - Model class (User)
```

**model_has_permissions**

```
permission_id - Foreign key to permissions
model_id      - User ID
model_type    - Model class (User)
```

**role_has_permissions**

```
permission_id - Foreign key to permissions
role_id       - Foreign key to roles
```

---

## Best Practices

### 1. Use Roles for Broad Access Control

```php
// Good: Use roles for job titles/positions
if ($user->hasRole('doctor')) {
    // Doctor-specific logic
}
```

### 2. Use Permissions for Specific Actions

```php
// Good: Use permissions for specific actions
if ($user->can('delete-patient')) {
    // Allow deletion
}
```

### 3. Grant Permissions via Roles

```php
// Good: Assign role which includes permissions
$user->assignRole('doctor');  // Gets all doctor permissions

// Avoid: Assigning many individual permissions
// $user->givePermissionTo('view-patients');
// $user->givePermissionTo('create-patient');
// ...
```

### 4. Cache Permissions

Spatie Permission caches roles and permissions automatically. Clear cache when making changes:

```php
// After modifying roles/permissions
app()['cache']->forget('spatie.permission.cache');
```

Or in your seeder:

```php
// Reset cached roles and permissions
app()['cache']->forget('spatie.permission.cache');
```

### 5. Audit Permission Checks

Log permission denials for security:

```php
if (!Auth::user()->can('edit-patient')) {
    Log::warning('User denied permission', [
        'user_id' => Auth::id(),
        'permission' => 'edit-patient',
        'ip' => request()->ip(),
    ]);
}
```

### 6. Use Policies for Complex Logic

For complex authorization logic, use Laravel Policies instead of checking permissions directly:

```php
// In controller
$this->authorize('update', $patient);

// In policy
public function update(User $user, Patient $patient): bool
{
    return $user->can('edit-patient') &&
           ($user->hasRole('admin') || $patient->doctor_id === $user->id);
}
```

### 7. Document Your Permissions

Keep a centralized list of all permissions:

```php
// config/permissions.php
return [
    'patient' => [
        'view' => 'View patient records',
        'create' => 'Create new patient',
        'edit' => 'Edit patient records',
        'delete' => 'Delete patient records',
    ],
];
```

---

## Testing

### Test Permissions in Feature Tests

```php
// tests/Feature/PatientControllerTest.php

public function test_user_without_permission_cannot_create_patient()
{
    $user = User::factory()->create();
    $user->assignRole('receptionist');  // No create permission

    $response = $this->actingAs($user)->postJson('/api/patients', [
        // patient data
    ]);

    $response->assertStatus(403);
}

public function test_user_with_permission_can_create_patient()
{
    $user = User::factory()->create();
    $user->assignRole('doctor');  // Has create permission

    $response = $this->actingAs($user)->postJson('/api/patients', [
        // patient data
    ]);

    $response->assertStatus(201);
}
```

---

## Artisan Commands

```bash
# List all permissions
php artisan permission:list

# List permissions for a role
php artisan permission:list-role {role}

# Create permission
php artisan permission:create-permission {name}

# Create role
php artisan permission:create-role {name}

# Assign permission to role
php artisan permission:assign {permission} {role}
```

---

## Configuration

Edit `config/permission.php` to customize:

```php
return [
    // Table names
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    // Cache settings
    'cache' => [
        'expiration_time' => 86400 * 24,
        'key' => 'spatie.permission.cache',
    ],

    // Guard names
    'guard_names' => ['web', 'api'],
];
```

---

## Troubleshooting

### Permissions Not Working

**Problem:** User has role but permission check fails

**Solution:** Clear permission cache:

```php
app()['cache']->forget('spatie.permission.cache');
```

Or in code:

```php
auth()->user()->forgetCachedPermissions();
```

### Role Not Assigned

**Problem:** User role not showing in `$user->roles`

**Solution:** Verify role exists:

```php
$user->assignRole('doctor');  // Creates if doesn't exist (if enabled)
```

### Test Failures with Permissions

**Problem:** Tests fail because permissions not seeded

**Solution:** Use `RefreshDatabase` and run seeders in test:

```php
public function setUp(): void
{
    parent::setUp();
    $this->seed(RoleAndPermissionSeeder::class);
}
```

---

## Links

- **Documentation:** https://spatie.be/docs/laravel-permission
- **GitHub:** https://github.com/spatie/laravel-permission
- **Laravel Authorization:** https://laravel.com/docs/authorization

---

## Next Steps

1. **Seed Roles and Permissions:**

   ```bash
   php artisan db:seed --class=RoleAndPermissionSeeder
   php artisan db:seed --class=DatabaseSeeder
   ```

2. **Update API Routes:**
   Add permission middleware to patient routes

3. **Test Permissions:**
   Create feature tests to verify authorization

4. **Create UI Components:**
   Build permission-based UI elements using Blade directives

5. **Monitor Access:**
   Log permission denials for security auditing
