<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The central tenant-management endpoints were once reachable with no
 * authentication at all, which let anyone list every clinic (with DB
 * usernames) and DELETE them. These tests pin the X-Admin-Key gate.
 */
class TenantAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-admin-key-not-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.admin_key' => self::KEY]);
    }

    public static function protectedRoutes(): array
    {
        return [
            'list'       => ['GET',    '/api/tenants'],
            'show'       => ['GET',    '/api/tenants/_x'],
            'update'     => ['PUT',    '/api/tenants/_x'],
            'destroy'    => ['DELETE', '/api/tenants/_x'],
            'domains'    => ['GET',    '/api/tenants/_x/domains'],
            'add-domain' => ['POST',   '/api/tenants/_x/domains'],
            'migrate'    => ['POST',   '/api/tenants/_x/migrate'],
            'seed'       => ['POST',   '/api/tenants/_x/seed'],
        ];
    }

    /** @dataProvider protectedRoutes */
    public function test_rejects_request_without_admin_key(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /** @dataProvider protectedRoutes */
    public function test_rejects_request_with_wrong_admin_key(string $method, string $uri): void
    {
        $this->json($method, $uri, [], ['X-Admin-Key' => 'wrong'])
            ->assertStatus(401);
    }

    /** @dataProvider protectedRoutes */
    public function test_rejects_everything_when_key_is_not_configured(string $method, string $uri): void
    {
        config(['tenancy.admin_key' => null]);

        $this->json($method, $uri, [], ['X-Admin-Key' => self::KEY])
            ->assertStatus(401);
    }

    public function test_correct_key_passes_the_gate(): void
    {
        // 404 (not 401) proves the middleware let the request through to the controller.
        $this->json('DELETE', '/api/tenants/_does_not_exist', [], ['X-Admin-Key' => self::KEY])
            ->assertStatus(404);
    }

    public function test_seed_rejects_seeders_outside_the_allow_list(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create(['id' => '_x', 'name' => 'X']));

        // Endpoint used to accept any class name for `db:seed --class=`;
        // OldDatabaseMigrationSeeder truncates every table in the tenant DB.
        $this->json('POST', '/api/tenants/_x/seed', ['seeder' => 'OldDatabaseMigrationSeeder'], ['X-Admin-Key' => self::KEY])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_signup_routes_stay_public(): void
    {
        $this->json('GET', '/api/tenants/preview')->assertStatus(422); // name required → reached controller
        $this->json('POST', '/api/tenants')->assertStatus(422);        // validation → reached controller
    }
}
