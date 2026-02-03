<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // permissions table
        Schema::create($tableNames['permissions'] ?? 'permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // roles table
        Schema::create($tableNames['roles'] ?? 'roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // model_has_permissions pivot table
        Schema::create($tableNames['model_has_permissions'] ?? 'model_has_permissions', function (Blueprint $table) use ($pivotPermission, $columnNames) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key'] ?? 'model_id');
            $table->index([$columnNames['model_morph_key'] ?? 'model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary');
        });

        // model_has_roles pivot table
        Schema::create($tableNames['model_has_roles'] ?? 'model_has_roles', function (Blueprint $table) use ($pivotRole, $columnNames) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key'] ?? 'model_id');
            $table->index([$columnNames['model_morph_key'] ?? 'model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->onDelete('cascade');

            $table->primary([$pivotRole, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary');
        });

        // role_has_permissions pivot table
        Schema::create($tableNames['role_has_permissions'] ?? 'role_has_permissions', function (Blueprint $table) use ($pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Clear cache only if cache table exists
        try {
            if (Schema::hasTable('cache')) {
                app('cache')
                    ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
                    ->forget(config('permission.cache.key'));
            }
        } catch (\Exception $e) {
            // Cache clearing failed, continue anyway
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::drop($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::drop($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::drop($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::drop($tableNames['roles'] ?? 'roles');
        Schema::drop($tableNames['permissions'] ?? 'permissions');
    }
};
