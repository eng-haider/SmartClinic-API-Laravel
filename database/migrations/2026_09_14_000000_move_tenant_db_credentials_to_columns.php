<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `db_name`, `db_username` and `db_password` were missing from
 * Tenant::getCustomColumns(), so VirtualColumn stripped them off the real
 * columns and buried them in the `data` JSON blob. Rows written by raw SQL
 * put them in the real columns instead, so credentials ended up in one of
 * two places depending on how the row was created — tenant connections then
 * failed intermittently with "credentials are not configured" or access denied.
 *
 * The columns are custom columns now; this moves existing rows over.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keys = ['db_name', 'db_username', 'db_password'];

        foreach (DB::table('tenants')->get() as $tenant) {
            $data = json_decode($tenant->data ?? '{}', true) ?: [];
            $update = [];

            foreach ($keys as $key) {
                // The real column wins when set; otherwise recover from `data`.
                if (empty($tenant->{$key}) && ! empty($data[$key])) {
                    $update[$key] = $data[$key];
                }
                unset($data[$key]);
            }

            $update['data'] = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';

            DB::table('tenants')->where('id', $tenant->id)->update($update);
        }
    }

    public function down(): void
    {
        $keys = ['db_name', 'db_username', 'db_password'];

        foreach (DB::table('tenants')->get() as $tenant) {
            $data = json_decode($tenant->data ?? '{}', true) ?: [];

            foreach ($keys as $key) {
                if (! empty($tenant->{$key})) {
                    $data[$key] = $tenant->{$key};
                }
            }

            DB::table('tenants')->where('id', $tenant->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}',
            ]);
        }
    }
};
