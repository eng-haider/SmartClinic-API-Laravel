# Data Isolation Test Script

This script demonstrates how data is completely isolated between clinics.

## Quick Start

### 1. Fresh Start

```bash
# Reset database
php artisan migrate:fresh

# Create clinics with demo data
php artisan db:seed --class=TenantClinicsSeeder
```

### 2. Verify Data Isolation

#### Check Central Database

```bash
php artisan tinker
```

```php
// Show all clinics in central database
DB::connection('mysql')->table('tenants')->select('id', 'name', 'phone')->get();

// Result: 3 clinics
// clinic_amal, clinic_noor, clinic_shifa
```

#### Check Clinic 1 (عيادة الأمل)

```bash
php artisan tinker
```

```php
// Switch to clinic_amal database
tenancy()->initialize('clinic_amal');

// Show doctors
DB::table('users')->select('name', 'email', 'specialization')->get();
// Result: 2 doctors (د. أحمد محمد, د. سارة علي)

// Show patients
DB::table('patients')->select('name', 'phone')->get();
// Result: 5 patients (محمد أحمد, فاطمة علي, etc.)

// Count records
DB::table('patients')->count(); // 5
DB::table('cases')->count();    // 3
```

#### Check Clinic 2 (عيادة النور)

```php
// Switch to clinic_noor database
tenancy()->initialize('clinic_noor');

// Show doctors
DB::table('users')->select('name', 'email', 'specialization')->get();
// Result: 2 DIFFERENT doctors (د. خالد حسن, د. منى يوسف)

// Show patients
DB::table('patients')->select('name', 'phone')->get();
// Result: 5 DIFFERENT patients (ياسر خالد, نور عبدالله, etc.)

// Count records
DB::table('patients')->count(); // 5 (different from clinic_amal!)
DB::table('cases')->count();    // 3
```

#### Check Clinic 3 (عيادة الشفاء)

```php
// Switch to clinic_shifa database
tenancy()->initialize('clinic_shifa');

// Show doctors
DB::table('users')->select('name', 'email', 'specialization')->get();
// Result: 2 DIFFERENT doctors (د. عمر الكردي, د. ليلى رشيد)

// Show patients
DB::table('patients')->select('name', 'phone')->get();
// Result: 5 DIFFERENT patients (شيرين أحمد, دلشاد رشيد, etc.)

// Count records
DB::table('patients')->count(); // 5 (different from other clinics!)
DB::table('cases')->count();    // 3
```

## 🎯 What This Proves

### Complete Isolation

- Each clinic has **its own database**: `tenant_clinic_amal`, `tenant_clinic_noor`, `tenant_clinic_shifa`
- Each clinic has **different doctors** with different emails
- Each clinic has **different patients** with different names and phones
- **No overlap** between clinics - data is 100% isolated

### Database Structure

```
Central DB: smartclinic_tenants
├── tenants (3 records)
├── domains (0 records)
└── users (0 records - only for central admins)

Clinic DB: tenant_clinic_amal
├── users (2 doctors)
├── patients (5 patients)
├── cases (3 cases)
└── bills (3 bills)

Clinic DB: tenant_clinic_noor
├── users (2 doctors)
├── patients (5 patients)
├── cases (3 cases)
└── bills (3 bills)

Clinic DB: tenant_clinic_shifa
├── users (2 doctors)
├── patients (5 patients)
├── cases (3 cases)
└── bills (3 bills)
```

## 🌐 API Testing

### Login to Clinic 1

```bash
curl -X POST http://localhost:8000/api/tenant/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: clinic_amal" \
  -d '{
    "email": "ahmed@amal.com",
    "password": "password"
  }'
```

### Get Patients from Clinic 1

```bash
curl -X GET http://localhost:8000/api/tenant/patients \
  -H "X-Tenant-ID: clinic_amal" \
  -H "Authorization: Bearer {token}"
```

**Result:** 5 patients from عيادة الأمل only

### Get Patients from Clinic 2 (Different Data!)

```bash
curl -X GET http://localhost:8000/api/tenant/patients \
  -H "X-Tenant-ID: clinic_noor" \
  -H "Authorization: Bearer {token_from_clinic_noor}"
```

**Result:** 5 DIFFERENT patients from عيادة النور only

## 📊 Comparison Table

| Feature                      | Clinic 1 (الأمل)     | Clinic 2 (النور)   | Clinic 3 (الشفاء)     |
| ---------------------------- | -------------------- | ------------------ | --------------------- |
| **Database**                 | tenant_clinic_amal   | tenant_clinic_noor | tenant_clinic_shifa   |
| **Doctors**                  | د. أحمد، د. سارة     | د. خالد، د. منى    | د. عمر، د. ليلى       |
| **Patients**                 | محمد، فاطمة، حسين... | ياسر، نور، كريم... | شيرين، دلشاد، آفين... |
| **Location**                 | بغداد                | البصرة             | أربيل                 |
| **Can Access Other's Data?** | ❌ NO                | ❌ NO              | ❌ NO                 |

## 🔒 Security Proof

Try to access Clinic 2 data with Clinic 1 token:

```bash
# Login to Clinic 1
curl -X POST http://localhost:8000/api/tenant/auth/login \
  -H "X-Tenant-ID: clinic_amal" \
  -d '{"email": "ahmed@amal.com", "password": "password"}'

# Try to use this token with Clinic 2's X-Tenant-ID
curl -X GET http://localhost:8000/api/tenant/patients \
  -H "X-Tenant-ID: clinic_noor" \
  -H "Authorization: Bearer {clinic_amal_token}"
```

**Result:** ❌ Unauthorized! Token is only valid for clinic_amal

## 🎉 Benefits Demonstrated

1. **Complete Isolation:** Each clinic's data is in a separate database
2. **No Data Leakage:** Impossible for one clinic to see another's data
3. **Independent Users:** Same email can exist in different clinics
4. **Easy Backup:** Backup one clinic without affecting others
5. **Scalability:** Each clinic can be moved to different servers

## 🚀 Next Steps

1. **Add More Data:**

   ```bash
   # Add to specific clinic
   php artisan tinker
   tenancy()->initialize('clinic_amal');
   DB::table('patients')->insert([...]);
   ```

2. **Export Clinic Data:**

   ```bash
   mysqldump tenant_clinic_amal > clinic_amal_backup.sql
   ```

3. **Monitor Per Clinic:**
   - Each database has its own logs
   - Track performance per clinic
   - Scale resources per clinic needs

## 📖 Documentation

- **Full Guide:** `docs/TENANCY_GUIDE_AR.md`
- **Quick Reference:** `docs/TENANCY_QUICK_REFERENCE.md`
- **Simple Explanation:** `SIMPLE_EXPLANATION_AR.md`
