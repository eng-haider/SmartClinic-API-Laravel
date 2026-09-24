# Android APK updates

The mobile app checks this public endpoint after the signed-in shell opens:

```text
GET /api/public/app-versions/latest?platform=android
```

## First deployment

1. Create the central table:

   ```bash
   php artisan migrate --force
   ```

   On hosting without Artisan access, import `database/sql/app_versions.sql`
   after changing or removing its sample `INSERT` as needed.

2. Configure one permanent Android release signing key. Every update APK must
   use the same application ID and the same signing certificate as the APK
   already installed on users' devices.

3. Increase both values in the Flutter `pubspec.yaml`, for example:

   ```yaml
   version: 1.0.3+7
   ```

4. Build the release APK and upload it to the HTTPS URL stored in `apk_url`.
   For the sample URL, the web-server file is:

   ```text
   public/downloads/smartclinic.apk
   ```

5. Only after the APK is available, add the release row:

   ```sql
   INSERT INTO app_versions
     (platform, version, build_number, force_update, apk_url, message,
      is_active, released_at, created_at, updated_at)
   VALUES
     ('android', '1.0.3', 7, 0,
      'https://api.smartclinic.software/downloads/smartclinic.apk',
      'إضافة ميزات جديدة وإصلاح بعض المشاكل.',
      1, NOW(), NOW(), NOW());
   ```

`build_number`, not the visible version text, decides whether the installed
app is older. Always increase it. Set `force_update` to `1` only when users
must not dismiss the dialog.

This flow is for APKs distributed directly by Smart Clinic. Google Play builds
should use Google Play's in-app update mechanism instead of requesting unknown
app installation permission.
