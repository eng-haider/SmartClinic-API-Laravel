# Shared Hosting Setup Guide (Hostinger/cPanel)

## Overview

On shared hosting environments like Hostinger or cPanel, database creation is restricted. You cannot create databases programmatically using `CREATE DATABASE` commands. Instead, you must create databases manually through your hosting control panel.

## Configuration

### 1. Environment Variables

Add this to your `.env` file:

```env
# Set to false on shared hosting (Hostinger, cPanel, etc.)
# Set to true on VPS/local with CREATE DATABASE privileges
TENANCY_AUTO_CREATE_DB=false
```

### 2. Database Naming Convention

On shared hosting, databases are typically prefixed with your username:

- **Tenant ID**: `_clinicname` (example: `_haideraltemimy`)
- **Configured prefix**: `tenant` (from `config/tenancy.php`)
- **Expected database**: `tenant_haideraltemimy`
- **Actual database on Hostinger**: `u876784197_tenant_haideraltemimy`

The `u876784197_` prefix is automatically added by Hostinger.

## Creating a New Tenant on Shared Hosting

### Step 1: Create Database in Hosting Panel

Before calling the tenant creation API, you must:

1. **Log into your hosting panel** (hPanel for Hostinger, cPanel for others)

2. **Navigate to Databases**:
   - Hostinger: `Websites` → `Manage` → `Databases` → `MySQL Databases`
   - cPanel: `Databases` → `MySQL Databases`

3. **Create a new database**:
   - Database name: `tenant_[tenant_id]`
   - Example: If creating tenant with ID `_haideraltemimy`, create database: `tenant_haideraltemimy`
   - The hosting panel will add its own prefix automatically (e.g., `u876784197_tenant_haideraltemimy`)

4. **Grant access**:
   - Ensure your main database user has **ALL PRIVILEGES** on the new database
   - On Hostinger, this is usually done automatically
   - On cPanel, you may need to add the user to the database manually

### Step 2: Call Tenant Creation API

Once the database exists, call your tenant creation endpoint:

```bash
POST /api/tenants
Content-Type: application/json

{
  "id": "_haideraltemimy",
  "name": "Haider Clinic",
  "address": "123 Medical St",
  "user_name": "Dr. Haider",
  "user_phone": "1234567890",
  "user_email": "haider@example.com",
  "user_password": "SecurePassword123"
}
```

### Step 3: System Behavior

The system will:

1. ✅ Skip the `CREATE DATABASE` command (since `TENANCY_AUTO_CREATE_DB=false`)
2. ✅ Verify the database exists and is accessible
3. ✅ Run migrations on the tenant database
4. ✅ Seed roles and permissions
5. ✅ Create the admin user

If the database doesn't exist, you'll get a clear error message:

```json
{
  "success": false,
  "message": "Database 'tenant_haideraltemimy' does not exist. On shared hosting, you must create it manually...",
  "message_ar": "..."
}
```

## Bulk Tenant Creation

If you need to create multiple tenants:

### Option 1: Pre-create Multiple Databases

1. Create all databases upfront in your hosting panel:
   - `tenant_clinic1`
   - `tenant_clinic2`
   - `tenant_clinic3`

2. Call the API for each tenant:

```bash
# Clinic 1
POST /api/tenants {"id": "_clinic1", ...}

# Clinic 2
POST /api/tenants {"id": "_clinic2", ...}

# Clinic 3
POST /api/tenants {"id": "_clinic3", ...}
```

### Option 2: Create on Demand

1. Call API (will fail with helpful message)
2. Note the required database name from error
3. Create database in hosting panel
4. Retry API call

## Database Access Verification

To verify your database user has correct permissions:

```sql
-- Connect to MySQL via phpMyAdmin or SSH
SHOW GRANTS FOR 'u876784197_smartclinic'@'127.0.0.1';

-- You should see:
GRANT ALL PRIVILEGES ON `u876784197_tenant_%`.* TO 'u876784197_smartclinic'@'127.0.0.1';
```

If not, contact your hosting support to grant wildcard access to `tenant_*` databases.

## VPS / Local Development

On VPS or local development with full MySQL access:

```env
# .env
TENANCY_AUTO_CREATE_DB=true
```

The system will automatically create databases for new tenants.

## Troubleshooting

### Error: Access denied for user to database

**Cause**: Database doesn't exist or user lacks access.

**Solution**:

1. Verify database exists in hosting panel
2. Check database name matches: `prefix + tenant_id`
3. Ensure DB user has ALL PRIVILEGES on the database

### Error: Database not found

**Cause**: Database name mismatch.

**Solution**:

1. Check tenant ID (should start with underscore: `_clinicname`)
2. Database should be: `tenant_clinicname` (no underscore prefix in DB name)
3. Hosting may add its own prefix: `u876784197_tenant_clinicname`

### Migrations Fail

**Cause**: Database exists but migrations can't run.

**Solution**:

1. Check DB user has ALTER, CREATE, DROP privileges
2. Verify character set (utf8mb4) is supported
3. Check storage limits on your hosting plan

## Best Practices

1. **Document tenant IDs**: Keep a list of tenant IDs and their database names
2. **Backup before tenant creation**: Always backup before creating new tenants
3. **Test on staging**: Test tenant creation on a staging environment first
4. **Monitor disk space**: Each tenant database consumes storage
5. **Database limits**: Check your hosting plan's database limit

## Migration Path

If migrating from auto-creation to manual creation:

1. Set `TENANCY_AUTO_CREATE_DB=false` in production `.env`
2. Document existing tenant databases
3. For new tenants, follow the manual creation process above
4. Existing tenants continue working without changes

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Hostinger error logs in hPanel
3. Verify database permissions in phpMyAdmin
4. Contact hosting support for permission issues
