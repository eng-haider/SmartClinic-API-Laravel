# Image 404 Issue - Additional Diagnosis (When X-Tenant-ID is Sent)

## Scenario

You are sending `X-Tenant-ID` header, but still getting 404 "Image not found"

```json
{
  "success": false,
  "message": "Image not found"
}
```

## Possible Causes (When X-Tenant-ID IS Sent)

### 1. ❌ Image is Soft-Deleted

The Image model uses `SoftDeletes` trait. If `deleted_at` is not NULL, the image will be excluded.

```sql
-- ❌ This will NOT be returned by normal queries
SELECT * FROM images WHERE id = 5;  -- excluded if deleted_at IS NOT NULL

-- ✅ This WILL include soft-deleted records
SELECT * FROM images WHERE id = 5 AND deleted_at IS NOT NULL;
```

**Check your database:**

```sql
SELECT id, path, deleted_at FROM images WHERE id = 5;
```

If `deleted_at` is NOT NULL → **This is the problem!**

### 2. ❌ Image Doesn't Exist in Tenant Database

The image exists in the **central database** but not in the **tenant database**.

**Check:**

```bash
# Login to get clinic_id
# Then check if image exists in that tenant

mysql> SELECT DATABASE();  -- Should show tenant database
mysql> SELECT * FROM images WHERE id = 5;
```

### 3. ❌ Imageable Model Doesn't Exist

Your image has:

- `imageable_type`: `Patient`
- `imageable_id`: `5`

If Patient with ID 5 doesn't exist, Laravel's `morphTo()` might fail silently.

```sql
-- Check if Patient 5 exists
SELECT * FROM patients WHERE id = 5;
```

### 4. ❌ Database Connection Issue

The `X-Tenant-ID` header is parsed but tenant context not properly initialized.

**Signs:**

- Tenant ID header received but not processed
- Query runs on central DB instead of tenant DB
- Image exists in your tenant but not in the DB being queried

## How to Diagnose

### Step 1: Run Direct Query Test

```bash
# SSH into your server/local machine
cd /path/to/project

# Test 1: Raw SQL check
php artisan tinker
>>> DB::select("SELECT * FROM images WHERE id = 5")
```

If this returns data, but `Image::find(5)` returns NULL, then **soft delete is the issue**.

### Step 2: Check Soft Delete Status

```bash
php artisan tinker
>>> Image::withTrashed()->find(5)
```

If this returns data but `Image::find(5)` returns NULL:
**The image IS soft-deleted!**

### Step 3: Run Full Diagnostic

```bash
# This will test multiple query methods
php artisan tinker
>>> include 'test_image_retrieval.php'
```

This will show you:

- ✅/❌ Raw query result
- ✅/❌ Query builder result
- ✅/❌ Eloquent result
- ✅/❌ Eloquent with soft deletes
- ✅/❌ Repository result
- ✅/❌ All other images
- ✅/❌ Current database
- ✅/❌ Imageable model

### Step 4: Check Tenant Context

Add this to your ImageController::show() temporarily for debugging:

```php
public function show(string|int $id): JsonResponse
{
    // ADD THIS FOR DEBUGGING
    \Log::info('Image request', [
        'id' => $id,
        'connection' => DB::connection()->getName(),
        'database' => DB::connection()->getDatabaseName(),
        'tenant_id' => request()->header('X-Tenant-ID'),
    ]);

    try {
        $image = $this->imageRepository->findById((int)$id);
        // ...
    }
}
```

Then check logs:

```bash
tail -f storage/logs/laravel.log
```

## Solution by Problem Type

### If Image is Soft-Deleted

**Option A: Restore the image**

```bash
php artisan tinker
>>> $image = Image::withTrashed()->find(5);
>>> $image->restore();
```

**Option B: Permanently delete**

```bash
php artisan tinker
>>> Image::withTrashed()->find(5)->forceDelete();
```

### If Image is in Central DB, Not Tenant DB

Copy the image record to the correct tenant database:

```bash
php artisan tinker
>>> $image = DB::connection('mysql')->table('images')->where('id', 5)->first();
>>> DB::connection('tenant')->table('images')->insert((array)$image);
```

### If Imageable Model Missing

The image references Patient 5 which doesn't exist. This shouldn't prevent the query, but it's worth checking:

```bash
php artisan tinker
>>> DB::table('patients')->find(5)
```

If NULL, you might want to:

1. Update the image to reference a valid patient
2. Delete the image

```bash
php artisan tinker
>>> Image::withTrashed()->find(5)->forceDelete();
```

### If Tenant Context Not Initializing

Check middleware is working:

```php
// Add to ImageController temporarily
public function show(string|int $id): JsonResponse
{
    $tenantId = request()->header('X-Tenant-ID');
    $connection = DB::connection()->getName();
    $database = DB::connection()->getDatabaseName();

    // Log for debugging
    \Log::info('Image controller debug', compact('tenantId', 'connection', 'database'));

    // Should show:
    // connection: tenant
    // database: u876784197_tenant_xxxxx (or similar)

    // ...rest of code
}
```

## Quick Fix Checklist

- [ ] Run `test_image_retrieval.php` to see exact issue
- [ ] Check if image is soft-deleted: `Image::withTrashed()->find(5)`
- [ ] Check if image exists in correct database: `DB::getDatabaseName()`
- [ ] Verify X-Tenant-ID header is being sent
- [ ] Check Laravel logs for errors: `tail -f storage/logs/laravel.log`
- [ ] Verify Patient ID 5 exists: `DB::table('patients')->find(5)`
- [ ] Check database connection: `DB::connection()->getName()`

## Still Stuck?

Create a test file with this content and share the output:

```php
php artisan tinker

// Show me what you get:
>>> Image::find(5)
>>> Image::withTrashed()->find(5)
>>> DB::table('images')->where('id', 5)->first()
>>> DB::table('patients')->find(5)
>>> DB::connection()->getName()
>>> DB::connection()->getDatabaseName()
```

Then I can give you the exact fix!

## API Request for Diagnosis

Try this to see actual error:

```bash
curl -X GET "http://localhost:8000/api/images/5" \
  -H "X-Tenant-ID: _your_clinic_id" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -v  # Shows verbose output including headers
```

Check the response headers and body for clues.

---

**Key Point**: If you're sending X-Tenant-ID and still getting 404, the issue is **NOT** about database context switching. It's about the image record itself - either:

1. It's soft-deleted
2. It doesn't exist in this tenant's database
3. There's a query/model issue

Run the diagnostic tests above to narrow it down!
