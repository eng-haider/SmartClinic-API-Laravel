# Quick Reference: CompleteDataSeeder

## How to Run

```bash
# Run the seeder
php artisan db:seed --class=CompleteDataSeeder

# Or run all seeders (if enabled in DatabaseSeeder)
php artisan db:seed
```

## Login Credentials

```
Phone:    07700281899
Password: 12345678
Role:     clinic_super_doctor
Email:    haider@smartclinic.com
```

## What Gets Created

### Core Data

- ✅ 1 User (Dr. Haider Al-Temimy)
- ✅ 1 Clinic (SmartClinic Medical Center)

### Lookup Tables

- ✅ 5 Statuses
- ✅ 10 Case Categories
- ✅ 9 Patient Sources (From Where Come)
- ✅ 7 Expense Categories

### Clinical Data

- ✅ 4-5 Patients with notes
- ✅ 4-5 Medical Cases
- ✅ 4-5 Recipes
- ✅ 4-5 Bills
- ✅ 4 Reservations

### Business Data

- ✅ 9 Clinic Settings
- ✅ 5 Clinic Expenses

### Total Records

Approximately **60-70 database records** created

## Features

- ✅ **Idempotent**: Can be run multiple times safely
- ✅ **Duplicate Detection**: Skips existing users and patients
- ✅ **Relationship Aware**: All foreign keys properly linked
- ✅ **Polymorphic Support**: Notes and Bills use polymorphic relations
- ✅ **Real Data**: Iraqi phone numbers and Baghdad addresses

## Testing the Data

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone": "07700281899", "password": "12345678"}'
```

### Get Patients

```bash
GET /api/patients
Authorization: Bearer {token}
```

### Get Cases

```bash
GET /api/cases
Authorization: Bearer {token}
```

### Get Reservations

```bash
GET /api/reservations
Authorization: Bearer {token}
```

### Get Expenses

```bash
GET /api/clinic-expenses
Authorization: Bearer {token}
```

## Resetting Data

To start fresh:

```bash
# Reset database and reseed
php artisan migrate:fresh --seed
```

**Note**: Make sure RoleAndPermissionSeeder runs first!

## Files

- Seeder: `database/seeders/CompleteDataSeeder.php`
- Documentation: `SEEDER_DOCUMENTATION.md`
- This Guide: `SEEDER_QUICK_REFERENCE.md`
