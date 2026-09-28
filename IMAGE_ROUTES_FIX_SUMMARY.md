# Image Routes Fix - Summary

## Problem Found ✅

The endpoint you were calling:

```
GET /api/tenant/images/by-imageable?imageable_type=Patient&imageable_id=5
```

Returns **404 Not Found** because the route doesn't exist in `routes/tenant.php`

## Root Cause

### routes/api.php (Central Database Routes) ✅

```php
Route::middleware('jwt')->group(function () {
    // Custom routes DEFINED
    Route::get('images/by-imageable', [ImageController::class, 'getByImageable'])->name('images.by-imageable');
    Route::get('images/statistics/summary', [ImageController::class, 'statistics'])->name('images.statistics');
    Route::patch('images/{id}/order', [ImageController::class, 'updateOrder'])->name('images.update-order');

    // Standard CRUD
    Route::apiResource('images', ImageController::class);
});
```

### routes/tenant.php (Tenant Database Routes) ❌

```php
Route::middleware('jwt')->group(function () {
    // Custom routes MISSING!
    Route::apiResource('images', ImageController::class);  // Only this
});
```

## Solution Applied

Updated `routes/tenant.php` to include the same custom image routes:

```php
// Image routes
Route::middleware('jwt')->group(function () {
    // Custom routes must come BEFORE apiResource
    Route::get('images/by-imageable', [ImageController::class, 'getByImageable'])->name('images.by-imageable');
    Route::get('images/statistics/summary', [ImageController::class, 'statistics'])->name('images.statistics');
    Route::patch('images/{id}/order', [ImageController::class, 'updateOrder'])->name('images.update-order');

    // Standard CRUD operations
    Route::apiResource('images', ImageController::class);
});
```

## Now Available Routes in Tenant API

### Get Image by Imageable Type & ID

```bash
GET /api/tenant/images/by-imageable?imageable_type=Patient&imageable_id=5
Headers:
  X-Tenant-ID: your_clinic_id
  Authorization: Bearer YOUR_TOKEN
```

**Response:**

```json
{
  "success": true,
  "message": "Images retrieved successfully",
  "data": [
    {
      "id": 5,
      "path": "img1731774597.jpg",
      "disk": "public",
      "type": "general",
      "imageable_type": "Patient",
      "imageable_id": 5,
      "url": "https://api.smartclinic.software/storage/img1731774597.jpg",
      "created_at": "2024-11-16T16:30:11.000000Z"
    }
  ]
}
```

### Get Image Statistics

```bash
GET /api/tenant/images/statistics/summary
Headers:
  X-Tenant-ID: your_clinic_id
  Authorization: Bearer YOUR_TOKEN
```

### Update Image Order

```bash
PATCH /api/tenant/images/{id}/order
Headers:
  X-Tenant-ID: your_clinic_id
  Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "order": 1
}
```

### Standard CRUD Operations (Already working)

```bash
GET /api/tenant/images              # List all
POST /api/tenant/images             # Create/Upload
GET /api/tenant/images/{id}         # Get single
PUT /api/tenant/images/{id}         # Update
DELETE /api/tenant/images/{id}      # Delete
```

## Why This Happened

When routes were created in `routes/api.php` (central database), the corresponding routes were **not added to `routes/tenant.php`** (tenant database routes).

Since images are accessed from both:

- **Central API**: `/api/images/...` (for admin features)
- **Tenant API**: `/api/tenant/images/...` (for clinic features)

Both route files need the same custom endpoints.

## Files Modified

✅ `/routes/tenant.php` - Added missing image custom routes

## Testing

Try your original endpoint now:

```bash
curl -X GET "https://api.smartclinic.software/api/tenant/images/by-imageable?imageable_type=Patient&imageable_id=5" \
  -H "X-Tenant-ID: your_clinic_id" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Expected response: **200 OK** with image data (if image exists and is not soft-deleted)

## Related Issues

If you still get results, make sure:

1. ✅ Image exists in database: `SELECT * FROM images WHERE id = 5`
2. ✅ Image is not soft-deleted: `deleted_at IS NULL`
3. ✅ X-Tenant-ID header is correct
4. ✅ JWT token is valid

## Prevention

Always ensure custom routes are added to BOTH:

- `routes/api.php` (central database routes)
- `routes/tenant.php` (tenant database routes)

When you create a new custom endpoint!

---

**Your endpoint should now work! ✅**
