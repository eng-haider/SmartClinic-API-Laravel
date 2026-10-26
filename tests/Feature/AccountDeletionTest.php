<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    private AccountDeletionService $deletions;

    protected function setUp(): void
    {
        parent::setUp();

        // Central and tenant tables share the in-memory database here.
        config(['tenancy.database.central_connection' => config('database.default')]);

        foreach (['account_deletion_requests', 'model_has_permissions', 'model_has_roles', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('clinic_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('api');
            $table->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('central_user_id')->nullable();
            $table->unsignedBigInteger('tenant_user_id')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('deletes_clinic')->default(false);
            $table->timestamp('purge_after');
            $table->timestamp('purged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'clinic_super_doctor']);

        $this->deletions = new AccountDeletionService();
    }

    private function user(string $phone, bool $owner = true): User
    {
        $user = User::create([
            'name' => 'Dr ' . $phone,
            'phone' => $phone,
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        if ($owner) {
            DB::table('model_has_roles')->insert([
                'role_id' => 1,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->id,
            ]);
        }

        return $user;
    }

    public function test_wrong_password_is_rejected_and_account_stays_active(): void
    {
        $user = $this->user('07700000001');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->deletions->request($user, 'wrong', '_clinic');
        } finally {
            $this->assertTrue($user->fresh()->is_active);
            $this->assertSame(0, AccountDeletionRequest::count());
        }
    }

    public function test_request_deactivates_and_schedules_purge(): void
    {
        $user = $this->user('07700000001');

        $request = $this->deletions->request($user, 'secret123', '_clinic');

        $this->assertFalse($user->fresh()->is_active);
        $this->assertTrue($request->deletes_clinic);
        $this->assertEqualsWithDelta(
            now()->addDays(AccountDeletionService::GRACE_DAYS)->timestamp,
            $request->purge_after->timestamp,
            5
        );
    }

    public function test_clinic_is_kept_while_another_owner_remains(): void
    {
        $user = $this->user('07700000001');
        $this->user('07700000002');

        $this->assertFalse($this->deletions->request($user, 'secret123', '_clinic')->deletes_clinic);
    }

    public function test_staff_deletion_never_deletes_the_clinic(): void
    {
        $this->user('07700000001');
        $staff = $this->user('07700000002', owner: false);

        $this->assertFalse($this->deletions->request($staff, 'secret123', '_clinic')->deletes_clinic);
    }

    public function test_demo_clinic_is_never_deleted(): void
    {
        $user = $this->user('07700000001');

        $this->assertFalse($this->deletions->request($user, 'secret123', 'tenant_test')->deletes_clinic);
    }

    public function test_purge_removes_central_login_and_clears_phone(): void
    {
        $user = $this->user('07700000001');
        $request = $this->deletions->request($user, 'secret123', null);
        $request->forceFill(['purge_after' => now()->subMinute()])->save();

        $this->assertSame(1, AccountDeletionRequest::due()->count());
        $this->deletions->purge($request->fresh());

        $this->assertNull(User::withTrashed()->find($user->id));
        $this->assertNotNull($request->fresh()->purged_at);
        $this->assertNull($request->fresh()->phone);
        $this->assertSame(0, AccountDeletionRequest::due()->count());
    }
}
