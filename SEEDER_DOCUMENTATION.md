# Complete Data Seeder Documentation

## Overview

This documentation explains how to use the `CompleteDataSeeder` to populate your SmartClinic database with comprehensive test data for the account with phone number **07700281899**.

## Login Credentials

- **Phone**: `07700281899`
- **Password**: `12345678`
- **Role**: `clinic_super_doctor`
- **Email**: `haider@smartclinic.com`

## What Gets Seeded

The `CompleteDataSeeder` creates a complete clinic setup with the following data:

### 1. User & Clinic

- ✅ Main user account (Dr. Haider Al-Temimy)
- ✅ Clinic (SmartClinic Medical Center)
- ✅ User assigned as clinic_super_doctor role

### 2. Lookup Tables

- ✅ **5 Statuses**: New, In Progress, Completed, Cancelled, On Hold
- ✅ **10 Case Categories**: General Examination, Teeth Cleaning, Tooth Filling, Root Canal, etc.
- ✅ **9 Patient Sources**: Social Media, Google Search, Friend Referral, Walk-in, etc.
- ✅ **7 Expense Categories**: Rent, Utilities, Salaries, Medical Supplies, etc.

### 3. Clinic Settings

- ✅ **9 Settings**: Registration number, working hours, appointment duration, currency, tax rate, etc.

### 4. Patients

- ✅ **5 Patients** with complete information:
  - Ahmed Mohammed (Male, 35 years)
  - Fatima Hassan (Female, 28 years)
  - Omar Ali (Male, 42 years)
  - Zahra Karim (Female, 25 years)
  - Hussein Jabbar (Male, 50 years)
- ✅ Each patient has initial consultation notes

### 5. Medical Cases

- ✅ **3-6 Cases** distributed across patients
- ✅ Each case includes:
  - Treatment details
  - Status tracking
  - Cost and payment information
  - Associated recipe with medications
  - Bill with payment details
  - Case notes

### 6. Recipes & Medications

- ✅ Recipes for each case with 3 medications:
  - Amoxicillin 500mg
  - Ibuprofen 400mg
  - Chlorhexidine mouthwash

### 7. Bills

- ✅ Bills for each case with:
  - Total amount
  - Paid amount
  - Remaining amount
  - Payment method (cash/card/transfer)
  - Payment status

### 8. Reservations

- ✅ **4 Upcoming appointments** for the next 4 days
- ✅ Each with patient, doctor, date, time, and reason

### 9. Clinic Expenses

- ✅ **5 Expense records**:
  - Rent: 500,000 IQD
  - Utilities: 150,000 IQD
  - Medical Supplies: 300,000 IQD
  - Salaries: 1,000,000 IQD
  - Marketing: 200,000 IQD

## How to Run the Seeder

### Option 1: Run with All Seeders

To run all seeders including the complete data:

1. Uncomment the line in `database/seeders/DatabaseSeeder.php`:

```php
// Change this:
// $this->call(CompleteDataSeeder::class);

// To this:
$this->call(CompleteDataSeeder::class);
```

2. Run the database seeder:

```bash
php artisan db:seed
```

### Option 2: Run Complete Data Seeder Only

To run only the complete data seeder:

```bash
php artisan db:seed --class=CompleteDataSeeder
```

### Option 3: Fresh Migration with Seeding

To reset the database and seed everything from scratch:

```bash
php artisan migrate:fresh --seed
```

**Note**: Make sure to uncomment the `CompleteDataSeeder` line in `DatabaseSeeder.php` first.

### Option 4: Run Complete Data Seeder Standalone

```bash
php artisan db:seed --class=Database\\Seeders\\CompleteDataSeeder
```

## Prerequisites

Before running the seeder, ensure:

1. ✅ Database is configured in `.env` file
2. ✅ Migrations have been run: `php artisan migrate`
3. ✅ Roles and permissions are seeded: `php artisan db:seed --class=RoleAndPermissionSeeder`

## Order of Execution

The seeder automatically handles dependencies in this order:

1. **User & Clinic Creation**
2. **Statuses** (required for cases and reservations)
3. **Case Categories** (required for medical cases)
4. **Patient Sources** (required for patients)
5. **Expense Categories** (required for expenses)
6. **Clinic Settings**
7. **Patients** (with notes)
8. **Medical Cases** (with recipes, recipe items, bills, and notes)
9. **Reservations**
10. **Clinic Expenses**

## Important Notes

### Duplicate Prevention

The seeder uses `firstOrCreate()` for lookup tables to prevent duplicates:

- Statuses
- Case Categories
- Patient Sources
- Expense Categories

### Data Relationships

All data is properly linked:

- All patients belong to the clinic
- All cases belong to patients and the doctor
- All bills are linked to cases
- All recipes are linked to cases
- All expenses belong to the clinic
- All reservations are linked to patients and doctor

### Creator & Updator

All records track who created and updated them:

- `creator_id` and `updator_id` are set to the main user (Dr. Haider Al-Temimy)

## Testing the Data

After seeding, you can:

1. **Login to the API**:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07700281899",
    "password": "12345678"
  }'
```

2. **Check Patients**:

```bash
GET /api/patients
Authorization: Bearer {your_token}
```

3. **Check Cases**:

```bash
GET /api/cases
Authorization: Bearer {your_token}
```

4. **Check Reservations**:

```bash
GET /api/reservations
Authorization: Bearer {your_token}
```

5. **Check Expenses**:

```bash
GET /api/clinic-expenses
Authorization: Bearer {your_token}
```

## Resetting the Data

To reset and reseed:

```bash
# Option 1: Reset entire database
php artisan migrate:fresh --seed

# Option 2: Delete specific clinic data manually via API or database
# Then run the seeder again:
php artisan db:seed --class=CompleteDataSeeder
```

## Customization

To modify the seeded data, edit `/database/seeders/CompleteDataSeeder.php`:

- Change patient information in `seedPatients()` method
- Modify clinic settings in `seedClinicSettings()` method
- Adjust expense amounts in `seedClinicExpenses()` method
- Update medication lists in `seedMedicalCases()` method

## Troubleshooting

### Error: "Phone already registered"

The user with phone 07700281899 already exists. Either:

- Delete the existing user from the database
- Run `php artisan migrate:fresh --seed` to reset everything

### Error: "Role does not exist"

Run the roles seeder first:

```bash
php artisan db:seed --class=RoleAndPermissionSeeder
```

### Error: Foreign key constraint fails

Ensure migrations are up to date:

```bash
php artisan migrate
```

## Summary

The `CompleteDataSeeder` provides a comprehensive, ready-to-use dataset for testing and development. It creates:

- ✅ 1 User (Dr. Haider Al-Temimy)
- ✅ 1 Clinic (SmartClinic Medical Center)
- ✅ 5 Patients
- ✅ 3-6 Medical Cases
- ✅ 3-6 Bills
- ✅ 3-6 Recipes with medications
- ✅ 4 Reservations
- ✅ 5 Clinic Expenses
- ✅ Multiple Notes
- ✅ All lookup tables populated

Total estimated records: **100+ database entries** representing a realistic clinic operation.
