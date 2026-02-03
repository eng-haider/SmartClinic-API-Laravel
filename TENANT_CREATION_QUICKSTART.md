# Quick Reference: Tenant Creation on Hostinger

## Setup (One-Time)

Add to `.env`:

```env
TENANCY_AUTO_CREATE_DB=false
```

## Creating a New Tenant

### Step 1: Generate Database Name

Run the helper script:

```bash
php generate_tenant_db_names.php "Clinic Name"
```

Example output:

```
Tenant #1: Clinic Name
├─ Tenant ID (for API): _clinic_name
├─ Database Name: tenant_clinic_name
└─ Create in Hosting Panel: u876784197_tenant_clinic_name
   ⚠️  Use this exact name in your hosting panel!
```

### Step 2: Create Database in hPanel

1. Log into Hostinger hPanel
2. Go to: **Websites** → **Manage** → **Databases** → **MySQL Databases**
3. Click **"Create a new MySQL database"**
4. Enter database name: `tenant_clinic_name`
   - Hostinger will add prefix automatically: `u876784197_tenant_clinic_name`
5. Ensure your main DB user has access (usually automatic)

### Step 3: Call API

```bash
POST https://your-domain.com/api/tenants
Content-Type: application/json

{
  "id": "_clinic_name",
  "name": "Clinic Name",
  "address": "123 Medical St",
  "user_name": "Dr. Admin",
  "user_phone": "1234567890",
  "user_email": "admin@clinic.com",
  "user_password": "SecurePass123"
}
```

### Step 4: Success!

The system will:

- ✅ Verify database exists
- ✅ Run migrations
- ✅ Create roles/permissions
- ✅ Create admin user
- ✅ Return success message

## Common Issues

### ❌ Database Not Found Error

**Error Message:**

```json
{
  "success": false,
  "message": "Database 'tenant_clinic_name' does not exist..."
}
```

**Solution:**

- Database not created in hPanel yet
- Create it first, then retry API call

### ❌ Access Denied Error

**Error Message:**

```
SQLSTATE[42000]: Access denied for user...
```

**Solution:**

- DB user doesn't have access to the new database
- In hPanel → Databases → check user permissions
- Contact Hostinger support if needed

## Naming Rules

- **Clinic Name:** Any text (e.g., "Dr. Smith Clinic")
- **Tenant ID:** Auto-generated, starts with `_` (e.g., `_dr_smith_clinic`)
- **Database Name:** `tenant` + tenant ID (e.g., `tenant_dr_smith_clinic`)
- **Hostinger adds prefix:** `u876784197_` (e.g., `u876784197_tenant_dr_smith_clinic`)

## Bulk Creation

To create 5 clinics at once:

```bash
# 1. Generate all database names
php generate_tenant_db_names.php \
  "Clinic One" \
  "Clinic Two" \
  "Clinic Three" \
  "Clinic Four" \
  "Clinic Five"

# 2. Create all databases in hPanel (copy names from output)

# 3. Call API for each clinic
for clinic in clinic_one clinic_two clinic_three clinic_four clinic_five; do
  curl -X POST https://your-domain.com/api/tenants \
    -H "Content-Type: application/json" \
    -d "{\"id\":\"_$clinic\", \"name\":\"...\", ...}"
done
```

## Important Notes

⚠️ **Always create database in hPanel BEFORE calling API**

✅ **Database name in hPanel:** Use the name from helper script

✅ **Tenant ID in API:** Use the ID from helper script (starts with `_`)

📝 **Keep a log:** Document each tenant ID and database name

🔐 **Secure passwords:** Use strong passwords for admin users

💾 **Backup:** Always backup before creating new tenants

## Documentation

- Full guide: `docs/SHARED_HOSTING_SETUP.md`
- Helper script: `generate_tenant_db_names.php`
- API docs: `docs/TENANT_CREATION_API.md`
