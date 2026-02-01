# Multi-Tenancy Demo - How It Works

## 🎯 The Difference Explained Simply

### Before (Single Database):
```
Database: smartclinic
├── patients (clinic_id = 1, 2, 3...)
├── cases (clinic_id = 1, 2, 3...)
├── bills (clinic_id = 1, 2, 3...)
└── users (clinic_id = 1, 2, 3...)
```
**Problem:** All clinics share ONE database, filter by `clinic_id`

###  After (Multi-Tenancy):
```
Database: smartclinic_tenants (CENTRAL)
├── tenants (clinic info)
└── domains

Database: tenant_clinic_amal (CLINIC 1)
├── patients (only clinic_amal data)
├── cases (only clinic_amal data)
├── bills (only clinic_amal data)
└── users (only clinic_amal staff)

Database: tenant_clinic_noor (CLINIC 2)
├── patients (only clinic_noor data)
├── cases (only clinic_noor data)
├── bills (only clinic_noor data)
└── users (only clinic_noor staff)
```
**Benefit:** Each clinic has SEPARATE database - complete isolation!

---

## 📊 Live Demo

### Step 1: Check Central Database

```bash
# What's in the central database?
php artisan tinker --execute="
\$tables = DB::select('SHOW TABLES');
echo '=== CENTRAL DATABASE TABLES ==='. PHP_EOL;
foreach(\$tables as \$table) {
    echo array_values((array)\$table)[0] . PHP_EOL;
}
"
```

**Output:**
```
=== CENTRAL DATABASE TABLES ===
cache
cache_locks
domains
failed_jobs
job_batches
jobs
migrations
password_reset_tokens
sessions
setting_definitions  ← Global settings catalog
tenants              ← Clinic information
users                ← Central admins only
```

**Notice:** NO patient, case, or bill tables here!

---

### Step 2: Create First Clinic

```bash
# Create clinic via Tinker
php artisan tinker --execute="
\$tenant = App\Models\Tenant::create([
    'id' => 'clinic_baghdad',
    'name' => 'عيادة بغداد للأسنان',
    'address' => 'بغداد - الكرادة'
]);
echo 'Created: ' . \$tenant->name . PHP_EOL;
"
```

**What Happens Automatically:**
1. ✅ Record added to `tenants` table in central DB
2. ✅ New database created: `tenant_clinic_baghdad`
3. ✅ All 16 migrations run in the new database
4. ✅ Seeder creates roles, permissions, statuses, categories

---

### Step 3: Check Tenant Database

```bash
# List all databases
php artisan tinker --execute="
\$databases = DB::select('SHOW DATABASES');
echo '=== TENANT DATABASES ==='. PHP_EOL;
foreach(\$databases as \$db) {
    \$dbName = array_values((array)\$db)[0];
    if (str_starts_with(\$dbName, 'tenant_')) {
        echo \$dbName . PHP_EOL;
    }
}
"
```

**Output:**
```
=== TENANT DATABASES ===
tenant_clinic_baghdad  ← NEW!
```

---

### Step 4: Check Tables in Tenant Database

```bash
# Connect to tenant DB and show tables
php artisan tenants:run clinic_baghdad --option="--execute=DB::select('SHOW TABLES')"
```

**Output:**
```
Tables in tenant_clinic_baghdad:
- users                        ← Clinic staff
- patients                     ← Clinic patients
- cases                        ← Medical cases
- bills                        ← Billing
- reservations                 ← Appointments
- recipes                      ← Prescriptions
- notes                        ← Patient notes
- images                       ← Medical images
- clinic_settings              ← Clinic-specific settings
- clinic_expenses              ← Expenses
- clinic_expense_categories    
- case_categories              
- statuses                     
- from_where_comes             
- recipe_items                 
- roles                        ← Clinic roles
- permissions                  ← Clinic permissions
- model_has_roles              
- model_has_permissions        
- role_has_permissions         
```

**Notice:** Complete isolated database for this clinic!

---

### Step 5: Create Second Clinic

```bash
php artisan tinker --execute="
\$tenant = App\Models\Tenant::create([
    'id' => 'clinic_basra',
    'name' => 'عيادة البصرة',
    'address' => 'البصرة - العشار'
]);
echo 'Created: ' . \$tenant->name . PHP_EOL;
"
```

**Result:**
- New database: `tenant_clinic_basra`
- Completely separate from `tenant_clinic_baghdad`

---

## 🔐 Data Isolation Example

###  Add Patient to Clinic Baghdad

```bash
# Using API with tenant header
POST /api/tenant/patients
Headers:
  X-Tenant-ID: clinic_baghdad
  Authorization: Bearer {token}
  
Body:
{
  "name": "أحمد محمد",
  "phone": "07701234567",
  "age": 35
}
```

**Stored in:** `tenant_clinic_baghdad.patients`

### Add Patient to Clinic Basra

```bash
POST /api/tenant/patients
Headers:
  X-Tenant-ID: clinic_basra
  Authorization: Bearer {token}
  
Body:
{
  "name": "علي حسن",
  "phone": "07709876543",
  "age": 28
}
```

**Stored in:** `tenant_clinic_basra.patients`

**Result:** 
- ✅ Two separate databases
- ✅ Two separate patient records
- ✅ No mixing of data!

---

## 📈 Data Flow Diagram

```
User Request:
POST /api/tenant/patients
Header: X-Tenant-ID: clinic_baghdad

                ↓

Middleware: InitializeTenancyByHeader
- Reads X-Tenant-ID
- Finds tenant in central DB
- Switches to tenant database

                ↓

Database Connection Changed:
FROM: smartclinic_tenants
  TO: tenant_clinic_baghdad

                ↓

Controller Saves Patient:
INSERT INTO patients...
(Automatically goes to tenant_clinic_baghdad)

                ↓

Response Sent to User
```

---

## 🎓 Key Concepts

### 1. Central Database
- **Purpose:** Manage clinics
- **Contains:** Tenant info, domains, global settings
- **Tables:** tenants, domains, users (admins), setting_definitions

### 2. Tenant Databases
- **Purpose:** Store clinic data
- **One per clinic:** Each clinic = separate database
- **Contains:** patients, cases, bills, users (staff), etc.

### 3. Automatic Switching
- **Header:** `X-Tenant-ID: clinic_xxx`
- **Middleware:** Automatically switches database connection
- **Transparent:** Your code doesn't change!

---

## 📝 Migration Comparison

### Central Migrations (6 files)
```bash
database/migrations/
├── create_users_table.php           # Central admins
├── create_cache_table.php           
├── create_jobs_table.php            
├── create_tenants_table.php         # ← Clinic info
├── create_domains_table.php         # ← Clinic domains
└── create_setting_definitions_table.php  # ← Global catalog
```

### Tenant Migrations (16 files)
```bash
database/migrations/tenant/
├── create_users_table.php           # Clinic staff
├── create_patients_table.php        # ← Clinic data
├── create_cases_table.php           # ← Clinic data
├── create_bills_table.php           # ← Clinic data
├── create_reservations_table.php    # ← Clinic data
├── ... (11 more tables)
└── create_clinic_expenses_table.php
```

---

## ✨ Benefits Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Data Isolation** | ❌ Shared DB, filter by clinic_id | ✅ Separate DB per clinic |
| **Performance** | ❌ Slow queries (many clinic_ids) | ✅ Fast (only one clinic) |
| **Security** | ⚠️ One breach = all clinics exposed | ✅ Breach affects only one clinic |
| **Backup** | ❌ Must backup/restore all clinics | ✅ Backup/restore individual clinic |
| **Scaling** | ⚠️ One large database | ✅ Distribute across servers |
| **Customization** | ❌ Same schema for all | ✅ Can customize per clinic |

---

## 🚀 Next Steps

1. Create your first clinic using the API or Tinker
2. Log in with `X-Tenant-ID` header
3. Create patients, cases, bills in that clinic
4. Create second clinic and verify data isolation

**All data is automatically isolated - no code changes needed!** 🎉

---

**Date:** February 1, 2026  
**Status:** Ready for production use
