# Multi-Tenancy Controller Updates

## Overview

After implementing the Stancl Tenancy package with database-per-tenant architecture, controllers needed to be updated to remove redundant `clinic_id` filtering. The `InitializeTenancyByHeader` middleware automatically switches the database based on the `X-Tenant-ID` header, making clinic-based filtering unnecessary.

## Key Changes

### What Changed

- **Removed**: `getClinicIdByRole()` methods that filtered by clinic_id
- **Kept**: Role-based filtering for doctors (they should only see their own data)
- **Updated**: All repository calls to pass `null` instead of `$clinicId`

### Pattern for Updates

**Before:**

```php
private function getClinicIdByRole(): ?int
{
    $user = Auth::user();
    if ($user->hasRole('super_admin')) {
        return null;
    }
    return $user->clinic_id;
}

public function index(Request $request): JsonResponse
{
    $clinicId = $this->getClinicIdByRole();
    $data = $this->repository->getAll($clinicId);
    // ...
}
```

**After:**

```php
// Method removed - no longer needed

public function index(Request $request): JsonResponse
{
    // Multi-tenancy: Database is already isolated by tenant
    $data = $this->repository->getAll(null);
    // ...
}
```

**For controllers with doctor-specific filtering:**

```php
private function getDoctorIdFilter(): ?int
{
    $user = Auth::user();

    // Super doctor and secretary see all in this tenant
    if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary')) {
        return null;
    }

    // Doctor sees only their own data
    if ($user->hasRole('doctor')) {
        return $user->id;
    }

    return null;
}
```

## ✅ Updated Controllers

### 1. ClinicExpenseController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `index()` - Pass null instead of clinic_id
  - `show()` - Pass null
  - `statistics()` - Pass null
  - `unpaid()` - Pass null
  - `byDateRange()` - Pass null

### 2. ReservationController.php

- Removed: `getFiltersByRole()` method (returned array with clinic_id and doctor_id)
- Added: `getDoctorIdFilter()` method (returns only doctor_id)
- Updated methods:
  - `index()` - Only filter by doctor_id for regular doctors
  - `show()` - Only filter by doctor_id

### 3. Report/DashboardReportController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `overview()` - Pass null for clinic_id
  - `today()` - Pass null for clinic_id

### 4. Report/BillReportController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `index()` - Pass null for clinic_id

### 5. Report/ReservationReportController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `summary()` - Pass null for clinic_id
  - `byStatus()` - Pass null for clinic_id
  - `byDoctor()` - Pass null for clinic_id
  - `trend()` - Pass null for clinic_id

### 6. CaseController.php

- Removed: `getClinicIdByRole()` and `getFiltersByRole()` methods
- Added: `getDoctorIdFilter()` method
- Updated methods:
  - `index()` - Only filter by doctor_id for regular doctors
  - `show()` - Only filter by doctor_id

### 7. DoctorController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `index()` - Pass null for clinic_id
  - `show()` - Pass null
  - `active()` - Pass null
  - `searchByEmail()` - Pass null
  - `searchByPhone()` - Pass null

### 8. BillController.php

- Removed: `getClinicIdByRole()` method
- Updated methods:
  - `index()` - Pass null for clinic_id
  - `show()` - Pass null
  - `update()` - Pass null
  - `destroy()` - Pass null
  - `markAsPaid()` - Pass null
  - `markAsUnpaid()` - Pass null
  - `byPatient()` - Pass null
  - `statistics()` - Pass null

### 9. RecipeController.php

- Removed: `getFiltersByRole()` method
- Added: `getDoctorIdFilter()` method
- Updated methods:
  - `index()` - Only filter by doctor_id for regular doctors
- Updated: `canAccessRecipe()` - Removed clinic_id check

### 10. SecretaryController.php

- Updated methods:
  - `index()` - Pass null instead of clinic_id to repository

## ⏳ Remaining Controllers to Update

Based on grep search, these controllers still reference `clinic_id`:

### PatientController.php

- Methods with clinic_id: `index()`, `show()`, `searchByEmail()`, `searchByPhone()`, etc.
- Pattern: Same as DoctorController - remove clinic_id filtering

### NoteController.php

- Needs audit for clinic_id references

### Other potential controllers

- Check any custom controllers in your project

## 🔧 Repository Layer Updates Needed

Many controllers now show lint errors like:

```
Expected type 'int'. Found 'null'.
```

This means repository method signatures need to be updated to accept nullable int:

**Before:**

```php
public function getAll(int $clinicId): Collection
```

**After:**

```php
public function getAll(?int $clinicId = null): Collection
```

### Repositories to Update:

1. ClinicExpenseRepository
2. ReservationRepository
3. ReportsRepository
4. CaseRepository
5. DoctorRepository
6. BillRepository
7. RecipeRepository
8. SecretaryRepository
9. PatientRepository

## 📝 Notes

### Role-Based Filtering Still Applies

- **Super Admin**: Sees all data (but only in current tenant database)
- **Clinic Super Doctor**: Sees all data in tenant
- **Doctor**: Sees only their own data (filtered by doctor_id/user_id)
- **Secretary**: Sees all data in tenant

### Database Isolation

- Each tenant has their own database: `tenant_amal`, `tenant_noor`, `tenant_shifa`
- Middleware switches database automatically based on `X-Tenant-ID` header
- No cross-tenant data access possible

### Testing Required

After all updates are complete, test with different roles:

1. Login as doctor from clinic "amal" → should see only amal data
2. Login as super_doctor from clinic "noor" → should see all noor data
3. Regular doctor should only see their own cases/reservations
4. Secretary should see all data in their clinic

## Next Steps

1. ✅ Update remaining controllers (PatientController, NoteController, etc.)
2. ✅ Update repository method signatures to accept nullable clinic_id
3. ✅ Test all endpoints with different roles and tenants
4. ✅ Update any custom queries in repositories to remove clinic_id where clauses
5. ✅ Consider removing clinic_id column from User model in future migration (optional)

## References

- Tenancy Package: stancl/tenancy v3.x
- Middleware: `InitializeTenancyByHeader`
- Configuration: `config/tenancy.php`
- Demo Data: `database/seeders/TenantClinicsSeeder.php`
