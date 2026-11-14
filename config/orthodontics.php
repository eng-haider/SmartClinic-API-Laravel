<?php

/*
|--------------------------------------------------------------------------
| Smart Clinic Orthodontics module
|--------------------------------------------------------------------------
|
| An optional, per-clinic module. A clinic switches it on with the
| `enable_orthodontics` clinic setting, and its tools only appear inside cases
| whose category is flagged `is_orthodontic`. See docs/ORTHODONTICS_MODULE.md.
|
*/

return [

    // Clinic setting that switches the module on for one clinic (tenant).
    'setting_key' => 'enable_orthodontics',

    // Spatie permissions. Reading the workspace and changing it are separate so
    // a clinic can let a secretary view analyses without editing them.
    'permissions' => [
        'view' => 'view-orthodontics',
        'manage' => 'manage-orthodontics',
    ],

    // Tenant disk the original uploads are kept on. The tenant 'local' disk
    // lives under storage/tenant{id}/app, outside the public/ folder that the
    // signed /file/tenant route serves, so files are only reachable through
    // the authenticated orthodontics API.
    'disk' => env('ORTHODONTICS_DISK', 'local'),

    // Upload limits in kilobytes, per file kind group.
    'max_upload_kb' => [
        'image' => (int) env('ORTHODONTICS_MAX_IMAGE_KB', 30 * 1024),
        'model' => (int) env('ORTHODONTICS_MAX_MODEL_KB', 200 * 1024),
        // One DICOM upload request (a batch of instances), not the whole study.
        'dicom_batch' => (int) env('ORTHODONTICS_MAX_DICOM_BATCH_KB', 100 * 1024),
    ],

    'cbct' => [
        // 'local'   - DICOM instances are stored on the tenant's private disk.
        // 'orthanc' - instances are forwarded to an isolated Orthanc server that
        //             only this API can reach (Laravel stays the gatekeeper).
        'driver' => env('ORTHODONTICS_CBCT_DRIVER', 'local'),

        // Hard ceiling on instances per study, against runaway uploads.
        'max_instances' => (int) env('ORTHODONTICS_CBCT_MAX_INSTANCES', 5000),

        // Studies left in 'uploading' longer than this are marked failed by
        // `php artisan orthodontics:cleanup-cbct`, and their partial files removed.
        'stale_upload_hours' => (int) env('ORTHODONTICS_CBCT_STALE_HOURS', 24),

        'orthanc' => [
            'url' => env('ORTHANC_URL'),
            'username' => env('ORTHANC_USERNAME'),
            'password' => env('ORTHANC_PASSWORD'),
            'timeout' => (int) env('ORTHANC_TIMEOUT', 120),
        ],
    ],

    // Moyers mixed-dentition probability table. Deliberately empty: enter the
    // rows from a verified printed source (and say which) before enabling it.
    // Format:
    //   'moyers_table' => [
    //       'level' => 75,
    //       'source' => 'Moyers, Handbook of Orthodontics, 4th ed. (1988), p. ...',
    //       // [sum of 4 mandibular incisors, maxillary C+P1+P2, mandibular C+P1+P2] in mm, ascending
    //       'rows' => [[19.5, ..., ...], [20.0, ..., ...], ...],
    //   ],
    // Predictions are interpolated between rows and never extrapolated.
    'moyers_table' => null,

    // Optional automated cephalometric landmark detection. Nothing is detected
    // unless a provider is configured; manual tracing always works on its own.
    // 'http' posts the radiograph to ORTHODONTICS_LANDMARK_URL and expects
    // {"landmarks": {"S": {"x": .., "y": ..}, ...}} back (pixel coordinates).
    'landmark_detection' => [
        'provider' => env('ORTHODONTICS_LANDMARK_PROVIDER'), // null | 'http'
        'url' => env('ORTHODONTICS_LANDMARK_URL'),
        'token' => env('ORTHODONTICS_LANDMARK_TOKEN'),
        'timeout' => (int) env('ORTHODONTICS_LANDMARK_TIMEOUT', 60),
    ],
];
