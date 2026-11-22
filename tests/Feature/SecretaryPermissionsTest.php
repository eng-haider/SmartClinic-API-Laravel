<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use App\Repositories\SecretaryRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A secretary has only what the clinic ticked for them: the role grants
 * nothing that the permissions dialog cannot take away, and the booking
 * endpoints check the permissions they were missing.
 */
class SecretaryPermissionsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_11_23_000001_make_secretary_role_permissions_optional.php';

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated memory database, like PatientPhotoTest: never touch a clinic.
        config([
            'database.default' => 'secretary_permissions_test',
            'database.connections.secretary_permissions_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('secretary_permissions_test');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        (require base_path('database/migrations/tenant/2025_12_07_200612_create_permission_tables.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_booking_endpoints_refuse_users_without_the_permission(): void
    {
        // May add patients and book, but not see or change the book.
        $this->actAs(['view-clinic-patients', 'create-reservation']);

        $this->getJson('/api/reservations')->assertForbidden();
        $this->getJson('/api/reservations/5')->assertForbidden();
        $this->putJson('/api/reservations/5', [])->assertForbidden();
        $this->patchJson('/api/reservations/5/status', ['status_id' => 2])->assertForbidden();
        $this->deleteJson('/api/reservations/5')->assertForbidden();
        $this->getJson('/api/tenant/booking-requests')->assertForbidden();
        $this->deleteJson('/api/tenant/booking-requests/5')->assertForbidden();
    }

    public function test_any_view_permission_opens_the_book(): void
    {
        foreach (['view-clinic-reservations', 'view-own-reservations', 'view-all-reservations'] as $view) {
            $this->actAs([$view]);
            $this->assertNotSame(403, $this->getJson('/api/reservations')->status(), $view);
        }
    }

    public function test_a_permission_the_clinic_never_seeded_is_refused_not_an_error(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Secretary']);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('hasPermissionTo')->andThrow(PermissionDoesNotExist::create('view-all-reservations', 'web'));
        $this->actingAs($user);

        $this->getJson('/api/reservations')->assertForbidden();
    }

    public function test_migration_moves_role_permissions_onto_each_secretary(): void
    {
        $role = $this->secretaryRoleAsBefore();
        $first = $this->user('First');
        $second = $this->user('Second');
        $first->assignRole($role);
        $second->assignRole($role);

        (require base_path(self::MIGRATION))->up();

        $role->refresh()->load('permissions');
        $this->assertSame(['view-own-clinic'], $role->permissions->pluck('name')->all());
        foreach ([$first, $second] as $secretary) {
            $secretary = $secretary->fresh();
            // Nothing changes for them on the day of the update...
            $this->assertTrue($secretary->hasPermissionTo('view-clinic-reservations'));
            $this->assertTrue($secretary->hasPermissionTo('create-patient'));
            $this->assertContains('view-reports', $secretary->getDirectPermissions()->pluck('name'));
        }

        // ...and unticking in the dialog now really takes it away.
        $first = $first->fresh();
        $first->syncPermissions(['create-patient']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $first = $first->fresh();
        $this->assertFalse($first->hasPermissionTo('view-clinic-reservations'));
        $this->assertTrue($first->hasPermissionTo('create-patient'));
        $this->assertNotContains('view-clinic-reservations', $first->getAllPermissions()->pluck('name'));
    }

    public function test_migration_after_the_role_was_already_synced_keeps_secretaries_working(): void
    {
        // The seeder ran first and emptied the role from the new config.
        $role = $this->secretaryRoleAsBefore();
        $role->syncPermissions(config('rolesAndPermissions.roles.secretary.permissions'));
        $secretary = $this->user('Secretary');
        $secretary->assignRole($role);

        (require base_path(self::MIGRATION))->up();

        $this->assertTrue($secretary->fresh()->hasPermissionTo('view-clinic-reservations'));
    }

    public function test_new_secretary_gets_the_defaults_unless_the_clinic_ticks_otherwise(): void
    {
        $role = $this->secretaryRoleAsBefore();
        $role->syncPermissions(config('rolesAndPermissions.roles.secretary.permissions'));
        $repository = app(SecretaryRepository::class);

        // The mobile app sends no list: the usual permissions.
        $fromApp = $repository->create(['name' => 'From app', 'phone' => '0771', 'password' => 'secret12']);
        $this->assertTrue($fromApp->fresh()->hasPermissionTo('view-clinic-reservations'));
        $this->assertTrue($fromApp->fresh()->hasPermissionTo('create-bill'));

        // The dialog sends what was ticked, even nothing at all.
        $none = $repository->create(['name' => 'Nothing', 'phone' => '0772', 'password' => 'secret12', 'permissions' => []]);
        $this->assertSame(['view-own-clinic'], $none->fresh()->getAllPermissions()->pluck('name')->all());
    }

    /** The secretary role as the old config built it. */
    private function secretaryRoleAsBefore(): Role
    {
        $names = array_merge(
            config('rolesAndPermissions.roles.secretary.permissions'),
            config('rolesAndPermissions.roles.secretary.default_permissions'),
        );
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create(['name' => 'secretary', 'guard_name' => 'web']);
        // The bill ones had already moved off the role in November.
        $role->givePermissionTo(array_diff($names, ['view-clinic-bills', 'create-bill', 'mark-bill-paid']));

        return $role;
    }

    private function user(string $name): User
    {
        return User::create(['name' => $name, 'phone' => uniqid(), 'password' => 'x']);
    }

    private function actAs(array $permissions): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Secretary']);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(fn (string $p) => in_array($p, $permissions, true));
        $this->actingAs($user);
    }
}
