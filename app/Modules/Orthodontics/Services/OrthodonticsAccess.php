<?php

namespace App\Modules\Orthodontics\Services;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The server-side gate in front of every orthodontics endpoint.
 *
 * Checked on every request (the frontend only hides things):
 *  1. authenticated user            - jwt middleware
 *  2. token issued for this clinic  - EnsureTokenMatchesTenant middleware
 *  3. clinic switched the module on - EnsureOrthodonticsEnabled middleware
 *  4. permission (view / manage)    - here
 *  5. the case exists in this clinic's database, and the user may see it
 *     (doctors without view-clinic-cases only see their own cases) - here
 *  6. the case category is orthodontic - here
 * Every child record (file, analysis, study) is then looked up through the
 * case, so an id from another case or clinic is simply not found.
 */
class OrthodonticsAccess
{
    public function __construct(private OrthodonticsModule $module)
    {
    }

    /**
     * Resolve a case the user may work on in the orthodontics workspace.
     *
     * @param  'view'|'manage'  $ability
     */
    public function case(Authenticatable $user, int $caseId, string $ability = 'view'): CaseModel
    {
        if (!$this->module->enabled()) {
            throw OrthodonticsException::disabled();
        }

        $this->authorize($user, $ability);

        $query = CaseModel::query()->with(['category', 'patient', 'doctor']);
        $doctorId = $this->doctorScope($user);
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        $case = $query->find($caseId);
        if (!$case) {
            throw OrthodonticsException::notFound('Case');
        }

        if (!$this->module->caseIsEligible($case)) {
            throw OrthodonticsException::notEligible();
        }

        return $case;
    }

    public function authorize(Authenticatable $user, string $ability): void
    {
        $permission = (string) config('orthodontics.permissions.' . $ability);
        if (!$permission || !$this->can($user, $permission)) {
            throw OrthodonticsException::forbidden($permission ?: $ability);
        }
    }

    public function can(Authenticatable $user, string $permission): bool
    {
        // checkPermissionTo() returns false (instead of throwing) when the
        // permission row does not exist yet in this clinic's database.
        if (method_exists($user, 'checkPermissionTo')) {
            return (bool) $user->checkPermissionTo($permission);
        }

        return method_exists($user, 'hasPermissionTo') && (bool) $user->hasPermissionTo($permission);
    }

    /**
     * Same rule as CaseController: doctors without view-clinic-cases only see
     * their own cases; everyone else sees the clinic's cases.
     */
    private function doctorScope(Authenticatable $user): ?int
    {
        if (!$user instanceof User) {
            return null;
        }
        foreach (['clinic_super_doctor', 'secretary', 'super_admin'] as $role) {
            if ($user->hasRole($role)) {
                return null;
            }
        }
        if ($this->can($user, 'view-clinic-cases')) {
            return null;
        }

        return $user->hasRole('doctor') ? (int) $user->id : null;
    }
}
