# Fix: TenantCouldNotBeIdentifiedOnDomainException

## Problem

You were getting this error when accessing the API:

```
Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException
Tenant could not be identified on domain api.smartclinic.software
```

## Root Cause

Your Laravel application has two types of tenant routes:

1. **Header-based routes** (`/api/tenant/*`) - Use `X-Tenant-ID` header
2. **Domain-based routes** (`/` web routes) - Use domain to identify tenant

When you accessed `api.smartclinic.software`, Laravel tried to match it with domain-based routes and looked for a tenant with that domain in the `domains` table. Since no domains were registered, it threw an exception.

## Solution Applied

Added `api.smartclinic.software` to the **central domains** list in `config/tenancy.php`:

```php
'central_domains' => [
    '127.0.0.1',
    'localhost',
    'api.smartclinic.software',    // ✅ Added
    'smartclinic.software',         // ✅ Added
],
```

**What this does:**

- Tells Laravel that these domains are for the **central application**, not tenant-specific
- Prevents the system from trying to identify tenants by these domains
- Allows you to use header-based tenant identification on these domains

## How to Use the API Now

### ✅ For Central/Public Endpoints

Access without tenant context:

```bash
GET https://api.smartclinic.software/api/tenants
GET https://api.smartclinic.software/api/health
POST https://api.smartclinic.software/api/tenants
```

### ✅ For Tenant-Specific Endpoints

Add the `X-Tenant-ID` header:

```bash
GET https://api.smartclinic.software/api/tenant/patients
Headers:
  X-Tenant-ID: _haider

GET https://api.smartclinic.software/api/tenant/doctors
Headers:
  X-Tenant-ID: _alamal
```

### ✅ For Public Patient Links (QR Codes)

Use the `clinic` query parameter:

```bash
GET https://api.smartclinic.software/api/tenant/public/patients/{id}?clinic=_haider
```

## Verification

Run this test to verify the configuration:

```bash
php test_domain_config.php
```

Expected output:

```
✅ SUCCESS: api.smartclinic.software is configured as a central domain.
```

## Cache Clearing

After making changes to config files, always clear the cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## Additional Notes

### If You Want Domain-Based Tenant Access

If you want to access tenants via domains like `haider.smartclinic.software`, you need to:

1. **Register the domain** for each tenant:

   ```bash
   POST /api/tenants/{id}/domains
   {
     "domain": "haider.smartclinic.software"
   }
   ```

2. **Set up DNS/subdomain** to point to your server

3. **Remove the domain from central_domains** if it's a subdomain pattern

### Current Route Structure

| Route Type               | Middleware                  | Access Method        |
| ------------------------ | --------------------------- | -------------------- |
| `/api/tenant/*`          | `InitializeTenancyByHeader` | `X-Tenant-ID` header |
| `/` (web)                | `InitializeTenancyByDomain` | Domain/subdomain     |
| `/api/tenants` (central) | None                        | Direct access        |

## Files Modified

1. ✅ `config/tenancy.php` - Added central domains
2. ✅ Cache cleared

## Summary

The error is now fixed! Your API at `api.smartclinic.software` will work for:

- Central endpoints (tenant management, health checks)
- Tenant-specific endpoints (when using `X-Tenant-ID` header)

The domain-based routes remain available for future web-based tenant access if needed.
