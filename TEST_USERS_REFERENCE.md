# Test Users Reference Guide

## Quick Start

Run the seeder to create test users:

```bash
php artisan migrate:fresh --seed
```

Or run only the test users seeder:

```bash
php artisan db:seed --class=TestUsersSeeder
```

---

## Test Users Credentials

All test users have the same password: **`password123`**

### 1. Super Admin (System-Wide Access)

- **Name:** Super Admin
- **Phone:** `201111111111`
- **Email:** `superadmin@smartclinic.com`
- **Role:** `super_admin`
- **Clinic:** None (access to all clinics)
- **Permissions:** Full system access - all patients, cases, bills, clinics, users

**Use this account for:**

- System administration
- Managing multiple clinics
- User management across all clinics
- Testing system-wide features

---

### 2. Clinic Super Doctor (Clinic 1 - Owner)

- **Name:** Dr. Ahmed Hassan
- **Phone:** `201222222222`
- **Email:** `ahmed@smartdental.com`
- **Role:** `clinic_super_doctor`
- **Clinic:** Smart Dental Clinic (ID: 1)
- **Permissions:** Full access to their clinic - all patients, cases, bills, reservations, recipes

**Use this account for:**

- Clinic owner functionality
- Managing clinic settings
- Creating/managing doctors and secretaries
- Viewing all clinic data

---

### 3. Clinic Super Doctor (Clinic 2 - Owner)

- **Name:** Dr. Sarah Mohamed
- **Phone:** `201333333333`
- **Email:** `sarah@advancedmedical.com`
- **Role:** `clinic_super_doctor`
- **Clinic:** Advanced Medical Center (ID: 2)
- **Permissions:** Full access to their clinic - all patients, cases, bills, reservations, recipes

**Use this account for:**

- Testing multi-clinic scenarios
- Clinic owner functionality
- Data isolation between clinics

---

### 4. Doctor (Clinic 1)

- **Name:** Dr. Omar Khalil
- **Phone:** `201444444444`
- **Email:** `omar@smartdental.com`
- **Role:** `doctor`
- **Clinic:** Smart Dental Clinic (ID: 1)
- **Permissions:** View all clinic patients, but only their own cases, bills, reservations, recipes

**Use this account for:**

- Regular doctor functionality
- Testing permission restrictions
- Creating cases for patients
- Viewing only their own work

---

### 5. Doctor (Clinic 2)

- **Name:** Dr. Fatima Ali
- **Phone:** `201555555555`
- **Email:** `fatima@advancedmedical.com`
- **Role:** `doctor`
- **Clinic:** Advanced Medical Center (ID: 2)
- **Permissions:** View all clinic patients, but only their own cases, bills, reservations, recipes

**Use this account for:**

- Testing doctor role in different clinic
- Multi-doctor scenarios
- Permission isolation

---

### 6. Secretary (Clinic 1)

- **Name:** Nadia Ibrahim
- **Phone:** `201666666666`
- **Email:** `nadia@smartdental.com`
- **Role:** `secretary`
- **Clinic:** Smart Dental Clinic (ID: 1)
- **Permissions:** Manage patients, reservations, view cases/bills, create bills

**Use this account for:**

- Front desk operations
- Patient registration
- Appointment scheduling
- Billing operations
- Limited access testing

---

### 7. Secretary (Clinic 2)

- **Name:** Mona Saleh
- **Phone:** `201777777777`
- **Email:** `mona@advancedmedical.com`
- **Role:** `secretary`
- **Clinic:** Advanced Medical Center (ID: 2)
- **Permissions:** Manage patients, reservations, view cases/bills, create bills

**Use this account for:**

- Testing secretary role
- Front desk functionality
- Limited permission scenarios

---

## Clinics Created

### Clinic 1: Smart Dental Clinic

- **ID:** 1
- **Address:** 123 Main Street, Cairo, Egypt
- **WhatsApp:** 201001234567
- **Owner:** Dr. Ahmed Hassan (201222222222)
- **Staff:** Dr. Omar Khalil (doctor), Nadia Ibrahim (secretary)

### Clinic 2: Advanced Medical Center

- **ID:** 2
- **Address:** 456 Healthcare Avenue, Alexandria, Egypt
- **WhatsApp:** 201009876543
- **Owner:** Dr. Sarah Mohamed (201333333333)
- **Staff:** Dr. Fatima Ali (doctor), Mona Saleh (secretary)

---

## Login Examples

### cURL

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "201111111111",
    "password": "password123"
  }'
```

### JavaScript/React

```javascript
const login = async (phone) => {
  const response = await fetch("http://localhost:8000/api/auth/login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      phone: phone,
      password: "password123",
    }),
  });

  const data = await response.json();
  return data;
};

// Login as Super Admin
await login("201111111111");

// Login as Clinic Owner
await login("201222222222");

// Login as Doctor
await login("201444444444");

// Login as Secretary
await login("201666666666");
```

### Postman

1. Create POST request to `http://localhost:8000/api/auth/login`
2. Set Body to raw JSON:

```json
{
  "phone": "201111111111",
  "password": "password123"
}
```

3. Send request and save token from response

---

## Testing Scenarios

### Scenario 1: Multi-Clinic Access

- Login as **Super Admin** (201111111111)
- Should see data from both clinics
- Can manage all users and settings

### Scenario 2: Clinic Isolation

- Login as **Clinic Owner 1** (201222222222)
- Should only see Clinic 1 data
- Login as **Clinic Owner 2** (201333333333)
- Should only see Clinic 2 data

### Scenario 3: Doctor Permissions

- Login as **Doctor 1** (201444444444)
- Create a case for a patient
- Login as **Doctor 2** (201555555555) (same clinic)
- Should NOT see Doctor 1's cases
- But should see all clinic patients

### Scenario 4: Secretary Permissions

- Login as **Secretary** (201666666666)
- Can create patients and reservations
- Can view all cases (read-only)
- Cannot create or edit cases
- Can create and mark bills as paid

---

## Permission Matrix

| Feature                  | Super Admin | Clinic Owner | Doctor | Secretary      |
| ------------------------ | ----------- | ------------ | ------ | -------------- |
| View All Clinics         | ✅          | ❌           | ❌     | ❌             |
| View All Clinic Patients | ✅          | ✅           | ✅     | ✅             |
| Create/Edit Patients     | ✅          | ✅           | ✅     | ✅             |
| Delete Patients          | ✅          | ✅           | ❌     | ❌             |
| View All Clinic Cases    | ✅          | ✅           | ❌     | ✅ (read-only) |
| View Own Cases           | ✅          | ✅           | ✅     | ❌             |
| Create/Edit Cases        | ✅          | ✅           | ✅     | ❌             |
| Delete Cases             | ✅          | ✅           | ❌     | ❌             |
| View All Clinic Bills    | ✅          | ✅           | ❌     | ✅             |
| View Own Bills           | ✅          | ✅           | ✅     | ❌             |
| Create Bills             | ✅          | ✅           | ✅     | ✅             |
| Edit Bills               | ✅          | ✅           | ✅     | ❌             |
| Delete Bills             | ✅          | ✅           | ❌     | ❌             |
| Mark Bill Paid           | ✅          | ✅           | ✅     | ✅             |
| Manage Users             | ✅          | ✅           | ❌     | ❌             |
| Edit Clinic Settings     | ✅          | ✅           | ❌     | ❌             |
| View/Create Reservations | ✅          | ✅           | ✅     | ✅             |
| Create Recipes           | ✅          | ✅           | ✅     | ❌             |

---

## Quick Command Reference

```bash
# Fresh start with all test data
php artisan migrate:fresh --seed

# Run only test users seeder (after migrations)
php artisan db:seed --class=TestUsersSeeder

# Run only roles and permissions
php artisan db:seed --class=RoleAndPermissionSeeder

# Check created users
php artisan tinker
>>> User::with('roles')->get(['id', 'name', 'phone', 'email', 'clinic_id'])

# Check roles
>>> \Spatie\Permission\Models\Role::with('permissions')->get()
```

---

## Troubleshooting

### "Column not found" Error

- Make sure migrations are run first: `php artisan migrate`
- Then run seeder: `php artisan db:seed --class=TestUsersSeeder`

### "Role does not exist" Error

- Run RoleAndPermissionSeeder first: `php artisan db:seed --class=RoleAndPermissionSeeder`

### Login Returns 401

- Check if user exists in database
- Verify password is correct: `password123`
- Check if JWT is configured: `php artisan jwt:secret`

### Token Expires Too Quickly

- Check `.env` file: `JWT_TTL=60` (60 minutes)
- Increase if needed for testing

---

## Notes

- All phone numbers follow Egyptian format (20XXXXXXXXXX)
- All passwords are the same for easy testing: `password123`
- Users are distributed across 2 clinics for isolation testing
- Super Admin has no clinic_id (system-wide access)
- Each clinic has 1 owner, 1 doctor, 1 secretary
