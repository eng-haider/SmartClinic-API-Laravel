-- Grant permissions for tenant database
-- Run this on your Hostinger server via phpMyAdmin or SSH

-- Replace with your actual database user and tenant database name
GRANT ALL PRIVILEGES ON `u876784197_tenant_alamal`.* TO 'u876784197_smartclinic'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `u876784197_tenant_alamal`.* TO 'u876784197_smartclinic'@'localhost';
FLUSH PRIVILEGES;

-- Verify permissions
SHOW GRANTS FOR 'u876784197_smartclinic'@'127.0.0.1';
SHOW GRANTS FOR 'u876784197_smartclinic'@'localhost';
