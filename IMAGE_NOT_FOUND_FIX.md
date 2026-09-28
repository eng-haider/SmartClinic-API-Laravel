# Image Not Found (404) - Diagnostic Guide

## Problem

When trying to fetch an image with ID 5, the system returns:

```json
{
  "success": false,
  "message": "Image not found"
}
```

But the image exists in the database:

```sql
INSERT INTO `images` (`id`, `path`, `disk`, `type`, `mime_type`, `size`, `width`, `height`, `alt_text`, `order`, `imageable_type`, `imageable_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5, 'img1731774597.jpg', 'public', 'general', NULL, NULL, NULL, NULL, NULL, 0, 'Patient', 5, '2024-11-16 16:30:11', '2024-11-16 16:30:11', NULL);
```

## Root Causes

### 1. ❌ Tenant Context Not Initialized

The images API is in **tenant routes** (multi-tenancy enabled), so you MUST provide the tenant ID.

**Current Behavior:**

```bash
GET /api/images/5          ← ❌ Missing tenant context
```

**Should Be:**

```bash
GET /api/images/5
Headers:
  X-Tenant-ID: your_clinic_id
  Authorization: Bearer YOUR_JWT_TOKEN
```

### 2. ❌ Wrong Database Being Queried

- Images table is in **TENANT** database (not central)
- If tenant context not initialized, Laravel queries the **central database**
- The image record exists in your **tenant database**, not central
- Result: 404 Not Found

### 3. ❌ Soft Delete Issue

The Image model uses `SoftDeletes`, but your image has `deleted_at = NULL`, so this is not the issue. However, if an image was ever soft-deleted, it would be excluded from queries.

## How to Fix

### Check 1: Verify Tenant Context

The image endpoint is in `routes/tenant.php`:

```php
// routes/tenant.php
Route::middleware('jwt')->group(function () {
    Route::apiResource('images', ImageController::class);  // ← IN TENANT ROUTES
});
```

**This means you MUST send tenant ID in every request.**

### Check 2: Send Proper Headers

```bash
# ✅ CORRECT
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: your_clinic_id" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# ❌ WRONG (Missing X-Tenant-ID)
curl -X GET "http://localhost:8000/api/images/5" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Check 3: Verify Image Exists in Correct Database

```bash
# Check which tenant database you're in
SELECT database();

# Verify image exists
SELECT * FROM images WHERE id = 5;

# Check if it's soft deleted
SELECT id, deleted_at FROM images WHERE id = 5;
```

### Check 4: List Images (Debug)

```bash
# This will show you if images exist in the current tenant
curl -X GET "http://localhost:8000/api/images" \
  -H "X-Tenant-ID: your_clinic_id" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**If this returns 0 images but they exist in the database:**

- Tenant context is not properly initialized
- Or the image belongs to a different tenant

## Tenant Context Flow

```
Request comes in
       ↓
Middleware: InitializeTenancyByHeader
  (reads X-Tenant-ID header)
       ↓
Finds tenant in CENTRAL database
       ↓
Switches database connection to TENANT
       ↓
Image query runs against TENANT database
       ↓
Returns image from TENANT database
```

**If any step fails, you get 404.**

## Solution: Add Middleware to Controller

If you want to fetch images WITHOUT tenant context (from central DB), you need to:

1. **Option A**: Move the route to central routes (not tenant routes)

```php
// routes/api.php (CENTRAL ROUTES)
Route::middleware('jwt')->apiResource('images', ImageController::class);
```

2. **Option B**: Keep in tenant routes but always send X-Tenant-ID header

```bash
GET /api/images/5
Headers:
  X-Tenant-ID: your_clinic_id
  Authorization: Bearer YOUR_JWT_TOKEN
```

## Code Analysis

### ImageController (app/Http/Controllers/ImageController.php)

```php
public function show(string|int $id): JsonResponse
{
    try {
        // This queries the CURRENT connection (tenant or central)
        $image = $this->imageRepository->findById((int)$id);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found',  // ← This error
            ], 404);
        }
        // ...
    }
}
```

### ImageRepository (app/Repositories/ImageRepository.php)

```php
public function __construct(Image $image)
{
    parent::__construct($image);  // Uses Image model's connection
}
```

### Image Model (app/Models/Image.php)

```php
class Image extends Model
{
    use HasFactory, SoftDeletes;
    // No explicit $connection = 'tenant';
    // So it uses the CURRENT connection (determined by middleware)
}
```

## Checklist to Fix 404

- [ ] **Add X-Tenant-ID header** to your image API requests
- [ ] **Verify tenant ID is correct** (matches the clinic)
- [ ] **Check image exists** in the tenant's database (not central)
- [ ] **Verify image is not soft-deleted** (deleted_at should be NULL)
- [ ] **Check authorization** (JWT token is valid for that tenant)
- [ ] **Review logs** for tenant initialization errors

## Example: Full Working Request

```bash
# 1. First, login to get JWT token
curl -X POST "http://localhost:8000/api/auth/smart-login" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "07701234567",
    "password": "password123"
  }'

# Response:
{
  "success": true,
  "message": "Login successful",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "clinic_id": "_alamal"  ← Use this as X-Tenant-ID
  }
}

# 2. Now fetch image with tenant context
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: _alamal" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Accept: application/json"

# Expected response:
{
  "success": true,
  "message": "Image retrieved successfully",
  "data": {
    "id": 5,
    "path": "img1731774597.jpg",
    "disk": "public",
    "type": "general",
    "url": "http://localhost:8000/storage/img1731774597.jpg",
    "imageable_type": "Patient",
    "imageable_id": 5,
    "created_at": "2024-11-16T16:30:11.000000Z"
  }
}
```

## Still Getting 404?

### Debug Step 1: Check Tenant Routes

```bash
# Is your endpoint in tenant routes?
grep -r "apiResource.*images" routes/
# Should show: routes/tenant.php
```

### Debug Step 2: Verify Tenant Initialization

```php
// Add to ImageController::show()
dd(DB::connection()->getName());  // Should be 'tenant', not 'mysql'
```

### Debug Step 3: Check Database

```php
// Add to ImageController::show()
dd($this->imageRepository->findById($id)); // See what query returns
```

### Debug Step 4: Check Migration

```bash
# Did migrations run in tenant database?
php artisan migrate --database=tenant

# Is images table created?
php artisan tinker
>>> DB::connection('tenant')->table('images')->count()
```

## Summary

**The image returns 404 because:**

1. ✅ Image exists in the tenant database
2. ✅ Image is not soft-deleted
3. ❌ **Tenant context not initialized** (missing X-Tenant-ID header)
4. ❌ Query runs against **wrong database** (central instead of tenant)

**Fix:** Always include `X-Tenant-ID` header in image API requests

---

Still need help? Check:

- `routes/tenant.php` - Verify images route is here
- `app/Http/Middleware/InitializeTenancyByHeader.php` - Tenant initialization logic
- `MULTITENANCY_DEMO.md` - Multi-tenancy architecture overview
