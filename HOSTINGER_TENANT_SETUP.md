# Hostinger Tenant Database Setup

## ⚠️ Important: Local vs Production

**DO NOT create tenant databases on your local machine!**

- Your **local** database uses `root` user with no prefix
- Your **Hostinger** database uses `u876784197_smartclinic` with prefix `u876784197_`

**Always create tenants directly on your production server.**

---

## Step-by-Step: Creating a Tenant on Hostinger

### 1. Create the Database in hPanel

1. Log into Hostinger → hPanel
2. Go to: **Websites** → **Manage** (for your site) → **Databases** → **MySQL Databases**
3. In the "Create a New MySQL Database" section:
   - **Database Name**: Enter `tenant_alamal` (Hostinger will automatically prefix it)
   - The full name will be: `u876784197_tenant_alamal`
4. Click **Create**

### 2. Assign Database User

In the same page, under "Current Databases":

1. Find your database: `u876784197_tenant_alamal`
2. Make sure user `u876784197_smartclinic` (or your main DB user) is assigned
3. Grant **ALL PRIVILEGES**

### 3. Create Tenant via API

Use your API endpoint (Postman/cURL) **targeting your production URL**:

```bash
POST https://your-domain.com/api/tenants
Content-Type: application/json

{
  "id": "_alamal",
  "name": "alamal",
  "address": "clinic address",
  "user_name": "Dr. Ahmad",
  "user_phone": "1234567890",
  "user_email": "doctor@alamal.com",
  "user_password": "secure_password"
}
```

**Important**: Use the exact `id` that matches your database:

- Database name: `u876784197_tenant_alamal`
- Tenant ID: `_alamal`
- Formula: `{TENANCY_DB_PREFIX}{tenant_id}` = `u876784197_tenant` + `_alamal`

---

## Troubleshooting

### Error: "Database does not exist"

✅ **Solution**:

1. Verify database exists in hPanel
2. Check exact name: `u876784197_tenant_alamal`
3. Ensure user `u876784197_smartclinic` has access
4. Confirm `.env` has: `TENANCY_DB_PREFIX=u876784197_tenant`

### Error: "Duplicate entry '\_alamal' for key 'PRIMARY'"

✅ **Solution**: Tenant already exists. Either:

1. Delete the existing tenant first (if it's incomplete)
2. Use a different clinic name/ID

To delete:

```bash
DELETE https://your-domain.com/api/tenants/_alamal
```

### Database Name Mismatch

| Your Input       | Tenant ID Generated | Expected Database Name             |
| ---------------- | ------------------- | ---------------------------------- |
| `alamal`         | `_alamal`           | `u876784197_tenant_alamal`         |
| `Al-Amal Clinic` | `_al_amal_clinic`   | `u876784197_tenant_al_amal_clinic` |
| `عيادة الأمل`    | `_clinic_xyz123`    | `u876784197_tenant_clinic_xyz123`  |

**Rule**: Laravel slugifies the clinic name and adds prefix `_`

---

## Configuration Reference

### Your `.env` on Hostinger:

```env
# Central Database (already exists)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u876784197_smartclinic
DB_USERNAME=u876784197_smartclinic
DB_PASSWORD=your_database_password

# Tenancy Settings
TENANCY_DB_PREFIX=u876784197_tenant
TENANCY_AUTO_CREATE_DB=false
```

### Key Points:

- `TENANCY_DB_PREFIX` includes your Hostinger username: `u876784197_tenant`
- `TENANCY_AUTO_CREATE_DB=false` because shared hosting doesn't allow programmatic DB creation
- All tenant databases must be created manually in hPanel **before** calling the API

---

## Quick Reference: Database Name Format

```
Full Database Name = TENANCY_DB_PREFIX + Tenant ID
                   = u876784197_tenant + _alamal
                   = u876784197_tenant_alamal
```

**In hPanel, create**: `u876784197_tenant_alamal`
**Then in API, use tenant ID**: `_alamal`

---

## Need Help?

1. Run diagnostic script on **production server**:

   ```bash
   php test_tenant_db_connection.php _alamal
   ```

2. Check Laravel logs:

   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Verify database connection:
   - Can you connect to `u876784197_smartclinic` (central)? ✓
   - Can you connect to `u876784197_tenant_alamal` (tenant)? ?
