<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A stable, human-readable public identifier keeps marketing URLs separate
     * from tenant/database identifiers and leaves room for custom domains.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('public_slug')->nullable()->unique()->after('name');
        });

        $used = [];
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use (&$used) {
            $base = Str::slug((string) $tenant->name);
            if ($base === '') {
                $base = Str::slug(ltrim((string) $tenant->id, '_')) ?: 'clinic';
            }

            $slug = $base;
            $suffix = 2;
            while (isset($used[$slug]) || DB::table('tenants')->where('public_slug', $slug)->where('id', '!=', $tenant->id)->exists()) {
                $slug = $base . '-' . $suffix++;
            }

            $used[$slug] = true;
            DB::table('tenants')->where('id', $tenant->id)->update(['public_slug' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn('public_slug');
        });
    }
};
