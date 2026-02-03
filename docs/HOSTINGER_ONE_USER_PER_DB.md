# ============================================

# HOSTINGER TENANT DATABASE SETUP GUIDE

# ============================================

## Problem: One User Per Database Limitation

On Hostinger shared hosting, **each MySQL database can only have ONE user**.
You cannot reuse the same database user (like `u876784197_smartclinic`) for multiple databases.

This is different from VPS/cPanel hosting where one user can access many databases.

---

## Solution: Use Per-Tenant Database Users

### Naming Convention:

- **Database name** = **Username** = `u876784197_tenant_{tenant_id}`
- **Password** = Same password for all tenant databases (set in `.env`)

Example:

```
Tenant ID: _alamal
Database: u876784197_tenant_alamal
Username: u876784197_tenant_alamal  ← Same as database name!
Password: your_tenant_db_password   ← Same for all tenants
```

---

## Step-by-Step Setup

### 1. Add to Your `.env` File:

```env
# Tenant Database Configuration
TENANT_DB_PASSWORD=your_secure_password_here

# Note: Use the SAME password for all tenant databases you create in hPanel
```

**Important**: Choose a strong password and use it for ALL tenant databases.

---

### 2. Create Tenant Database in Hostinger hPanel

For each new tenant, you must create the database BEFORE calling the API:

1. **Log into Hostinger** → hPanel
2. Go to: **Websites** → **Manage** → **Databases** → **MySQL Databases**
3. **Create New Database**:
   - **Database name**: `tenant_alamal` (Hostinger adds prefix → `u876784197_tenant_alamal`)
   - **Username**: Will be auto-created as `u876784197_tenant_alamal` (same as DB name)
   - **Password**: Use the SAME password you set in `TENANT_DB_PASSWORD`
4. Click **Create**

---

### 3. Create Tenant via API

Now you can call your API to create the tenant:

```bash
POST https://your-domain.com/api/tenants
Content-Type: application/json

{
  "id": "_alamal",
  "name": "Al-Amal Clinic",
  "address": "123 Main St",
  "user_name": "Dr. Ahmad",
  "user_phone": "1234567890",
  "user_email": "doctor@alamal.com",
  "user_password": "doctorpassword"
}
```

The system will:

- ✅ Use database: `u876784197_tenant_alamal`
- ✅ Connect with username: `u876784197_tenant_alamal`
- ✅ Use password from `.env`: `TENANT_DB_PASSWORD`
- ✅ Run migrations
- ✅ Create the doctor user

---

## Quick Reference

### For Each New Tenant:

| Step | Action            | Details                                                      |
| ---- | ----------------- | ------------------------------------------------------------ |
| 1    | Choose tenant ID  | e.g., `_alamal`                                              |
| 2    | Calculate DB name | `u876784197_tenant` + `_alamal` = `u876784197_tenant_alamal` |
| 3    | Create in hPanel  | Database + User with same name, use `TENANT_DB_PASSWORD`     |
| 4    | Call API          | POST `/api/tenants` with tenant details                      |

---

## Troubleshooting

### Error: "Access denied for user"

**Cause**: Database user password doesn't match `TENANT_DB_PASSWORD` in `.env`

**Fix**:

1. Check `.env`: `TENANT_DB_PASSWORD=...`
2. In hPanel, check the password for database user `u876784197_tenant_alamal`
3. They must match exactly!
4. If different, either:
   - Update `.env` to match hPanel password, OR
   - Delete and recreate the database in hPanel with correct password

### Error: "Database does not exist"

**Cause**: You didn't create the database in hPanel first

**Fix**:

1. Go to hPanel → Databases
2. Create database: `u876784197_tenant_alamal`
3. With user: `u876784197_tenant_alamal`
4. With password: Same as `TENANT_DB_PASSWORD`
5. Try API call again

### Error: "Duplicate entry"

**Cause**: Tenant already exists (partial creation from previous attempt)

**Fix**:

1. Delete the tenant: `DELETE /api/tenants/_alamal`
2. Or use cleanup script: `php cleanup_failed_tenant.php _alamal`
3. Try creating again

---

## Testing

Test the connection before creating tenant:

```bash
php test_tenant_db_connection.php _alamal
```

Expected output:

```
✅ SUCCESS! Connected to database 'u876784197_tenant_alamal'
⚠️  WARNING: Database exists but has NO TABLES.
   You need to run migrations for this tenant.
```

---

## Important Notes

✅ **DO**:

- Use the SAME password for all tenant databases (set in `TENANT_DB_PASSWORD`)
- Create database + user in hPanel BEFORE calling API
- Use database name as username (Hostinger does this automatically)

❌ **DON'T**:

- Try to reuse `u876784197_smartclinic` for tenant databases (won't work!)
- Create tenant via API before creating database in hPanel
- Use different passwords for each tenant database (keep it simple!)

---

## Migration from Central User

If you previously tried to use `u876784197_smartclinic` for tenants:

1. Delete all tenant databases in hPanel
2. Update `.env` with `TENANT_DB_PASSWORD`
3. Recreate tenant databases with their own users
4. Recreate tenants via API

---

## Summary

```
┌─────────────────────────────────────────────────────────┐
│  Hostinger Limitation: 1 Database = 1 User              │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Central DB:  u876784197_smartclinic                    │
│  User:        u876784197_smartclinic                    │
│  Password:    [from DB_PASSWORD]                        │
│                                                          │
│  Tenant DB:   u876784197_tenant_alamal                  │
│  User:        u876784197_tenant_alamal  ← MUST BE SAME! │
│  Password:    [from TENANT_DB_PASSWORD] ← SHARED!       │
│                                                          │
└─────────────────────────────────────────────────────────┘
```
