# Grant Database Permissions for Tenant Database

## Copy and paste this SQL into phpMyAdmin:

```sql
-- Grant all privileges to the user for the tenant database
GRANT ALL PRIVILEGES ON `u876784197_tenant_alamal`.*
TO 'u876784197_smartclinic'@'127.0.0.1';

GRANT ALL PRIVILEGES ON `u876784197_tenant_alamal`.*
TO 'u876784197_smartclinic'@'localhost';

FLUSH PRIVILEGES;
```

## To run this in phpMyAdmin:

1. Open phpMyAdmin from Hostinger hPanel
2. Click on the "SQL" tab at the top
3. Paste the SQL above
4. Click "Go" button
5. You should see: "MySQL returned an empty result set (i.e. zero rows)"

## To verify it worked:

Run this query:

```sql
SHOW GRANTS FOR 'u876784197_smartclinic'@'127.0.0.1';
```

You should see something like:

```
GRANT ALL PRIVILEGES ON `u876784197_tenant_alamal`.* TO 'u876784197_smartclinic'@'127.0.0.1'
```

## After granting permissions:

Test the connection again:

```bash
php test_tenant_db_connection.php _alamal
```

You should now see:

```
✅ SUCCESS! Connected to database 'u876784197_tenant_alamal'
```
