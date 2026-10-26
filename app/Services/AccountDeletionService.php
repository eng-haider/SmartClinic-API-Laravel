<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\Clinic;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Account deletion required by the App Store and Google Play.
 *
 * A request deactivates the account at once (login is refused from then on)
 * and the purge job removes it after GRACE_DAYS, so support can still undo a
 * mistaken request. Deleting a staff account anonymises that user but keeps
 * the clinic's records; deleting a clinic's only owner deletes the clinic.
 */
class AccountDeletionService
{
    public const GRACE_DAYS = 30;

    private const OWNER_ROLE = 'clinic_super_doctor';

    /** The shared demo clinic is never deleted along with one of its users. */
    private const PROTECTED_TENANTS = ['tenant_test', '_test'];

    /**
     * Deactivate the signed-in tenant user and schedule the purge.
     *
     * @throws \InvalidArgumentException when the password does not match
     */
    public function request(User $user, string $password, ?string $tenantId): AccountDeletionRequest
    {
        if (!Hash::check($password, $user->password)) {
            throw new \InvalidArgumentException('Password is incorrect');
        }

        $centralUser = User::on($this->centralConnection())
            ->where('phone', $user->phone)
            ->first();

        $deletionRequest = AccountDeletionRequest::create([
            'tenant_id'       => $tenantId,
            'central_user_id' => $centralUser?->id,
            'tenant_user_id'  => $user->id,
            'phone'           => $user->phone,
            'deletes_clinic'  => $this->isSoleOwner($user, $tenantId),
            'purge_after'     => now()->addDays(self::GRACE_DAYS),
        ]);

        $user->forceFill(['is_active' => false])->save();
        $centralUser?->forceFill(['is_active' => false])->save();

        Log::info('Account deletion requested', [
            'request_id'     => $deletionRequest->id,
            'tenant_id'      => $tenantId,
            'deletes_clinic' => $deletionRequest->deletes_clinic,
        ]);

        return $deletionRequest;
    }

    /**
     * Permanently remove what a due request covers. Safe to re-run: every
     * step tolerates records that are already gone.
     */
    public function purge(AccountDeletionRequest $deletionRequest): void
    {
        $tenant = $deletionRequest->tenant_id
            ? Tenant::find($deletionRequest->tenant_id)
            : null;

        if ($deletionRequest->deletes_clinic && $tenant) {
            $this->purgeClinic($tenant);
        } else {
            if ($tenant && $deletionRequest->tenant_user_id) {
                $this->anonymiseTenantUser($tenant, $deletionRequest->tenant_user_id);
            }
            if ($deletionRequest->central_user_id) {
                User::on($this->centralConnection())
                    ->withTrashed()
                    ->find($deletionRequest->central_user_id)
                    ?->forceDelete();
            }
        }

        $deletionRequest->forceFill([
            'purged_at' => now(),
            'phone'     => null,
        ])->save();

        Log::info('Account deletion purged', [
            'request_id'     => $deletionRequest->id,
            'tenant_id'      => $deletionRequest->tenant_id,
            'deletes_clinic' => $deletionRequest->deletes_clinic,
        ]);
    }

    /**
     * The clinic goes with its owner only when nobody else can run it.
     */
    private function isSoleOwner(User $user, ?string $tenantId): bool
    {
        if (!$tenantId || in_array($tenantId, self::PROTECTED_TENANTS, true)) {
            return false;
        }

        $db = DB::connection($user->getConnectionName());
        $ownerRoleId = $db->table('roles')->where('name', self::OWNER_ROLE)->value('id');
        if (!$ownerRoleId) {
            return false;
        }

        $owners = $db->table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', $ownerRoleId)
            ->whereIn('model_has_roles.model_type', [$user->getMorphClass(), User::class])
            ->where('users.is_active', true)
            ->whereNull('users.deleted_at')
            ->pluck('users.id');

        return $owners->contains($user->id) && $owners->count() === 1;
    }

    /**
     * Same steps as TenantController::destroy, plus the clinic's central
     * logins, which would otherwise point at a clinic that no longer exists.
     */
    private function purgeClinic(Tenant $tenant): void
    {
        User::on($this->centralConnection())
            ->withTrashed()
            ->where('clinic_id', $tenant->id)
            ->get()
            ->each
            ->forceDelete();

        Clinic::find($tenant->id)?->forceDelete();
        $tenant->delete();
    }

    /**
     * Strip the user's personal data but keep the row (soft-deleted), so the
     * cases, bills and notes the clinic owns still resolve their author.
     */
    private function anonymiseTenantUser(Tenant $tenant, int $tenantUserId): void
    {
        $connection = $this->connectTenant($tenant);
        $user = User::on($connection)->withTrashed()->find($tenantUserId);
        if (!$user) {
            return;
        }

        $user->images()->where('type', 'profile')->get()->each->delete();

        $fields = [
            'name'      => 'Deleted user',
            'phone'     => 'deleted-' . $user->id . '-' . Str::random(8),
            'email'     => null,
            'password'  => Hash::make(Str::random(40)),
            'is_active' => false,
        ];
        if (Schema::connection($connection)->hasColumn('users', 'onesignal_player_id')) {
            $fields['onesignal_player_id'] = null;
        }

        $user->forceFill($fields)->save();
        if (!$user->trashed()) {
            $user->delete();
        }
    }

    /**
     * Point the `tenant` connection at the clinic's pool database, the same
     * way AuthService does (tenancy()->initialize() would re-prefix the name).
     */
    private function connectTenant(Tenant $tenant): string
    {
        $central = config('database.connections.' . $this->centralConnection());

        config([
            'database.connections.tenant.host'     => $central['host'] ?? null,
            'database.connections.tenant.port'     => $central['port'] ?? null,
            'database.connections.tenant.database' => $tenant->db_name,
            'database.connections.tenant.username' => $tenant->db_username,
            'database.connections.tenant.password' => $tenant->db_password,
        ]);
        DB::purge('tenant');

        return 'tenant';
    }

    private function centralConnection(): string
    {
        return config('tenancy.database.central_connection');
    }
}
