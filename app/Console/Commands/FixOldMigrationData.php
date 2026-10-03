<?php

namespace App\Console\Commands;

use App\Models\CaseModel;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair, in place, data that an earlier OldDatabaseMigrationSeeder run migrated wrongly.
 *
 * That run ignored the old cases.doctor_id, so most cases (and their bills and session notes)
 * landed on the clinic's first user. It also dropped soft deletes on cases/bills/notes, case
 * item_cost, and bill is_paid / payment date. Re-running the fixed seeder repairs all of that
 * too, but it drops the tenant database and everything added since the migration.
 *
 * SAFE ON A LIVE TENANT:
 * - Only migrated rows are touched. They are paired with their old record by migration order
 *   (the seeder inserted them by old id into empty tables) and verified by created_at and
 *   parent. If any pair does not line up, nothing is written.
 * - doctor_id / created_by is only replaced while it still holds the value the old seeder put
 *   there (or the row was never edited), so a doctor someone set by hand is kept.
 * - Every other field is only touched on rows nobody edited since the migration.
 * - Updates go through the query builder: no model events, automations or updated_at changes,
 *   so running it twice is harmless.
 *
 * Usage:
 *   php artisan old-db:fix-migrated-data --dry-run      # Preview only
 *   php artisan old-db:fix-migrated-data                # Apply (clinic 1 → tenant clinic_1)
 *   php artisan old-db:fix-migrated-data --clinic=105   # Another migrated clinic
 */
class FixOldMigrationData extends Command
{
    protected $signature = 'old-db:fix-migrated-data
                            {--clinic=1 : Old clinic ID that was migrated}
                            {--tenant= : Tenant ID (default: clinic_{clinic})}
                            {--dry-run : Preview without saving}';

    protected $description = 'Fix case/bill/note doctors, soft deletes and payment fields of data migrated by OldDatabaseMigrationSeeder, without re-seeding';

    private string $oldDb = 'mysql_old';

    /** old doctors.id => new users.id */
    private array $doctorIdMap = [];

    /** old users.id of a doctor => new users.id */
    private array $doctorUserIdMap = [];

    /** old case id => ['new_id' => int, 'correct' => ?int, 'buggy' => ?int] */
    private array $cases = [];

    /** The user the old seeder fell back to: the first migrated user. */
    private ?int $firstUserId = null;

    public function handle(): int
    {
        $clinicId = (int) $this->option('clinic');
        $tenantId = $this->option('tenant') ?: "clinic_{$clinicId}";
        $isDryRun = (bool) $this->option('dry-run');

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE — no data will be saved');
        }

        $this->info("Tenant: {$tenantId} | Old clinic: {$clinicId} | Old DB: " . DB::connection($this->oldDb)->getDatabaseName());

        return $tenant->run(function () use ($clinicId, $isDryRun) {
            try {
                $this->mapDoctors($clinicId);
                $patientIdMap = $this->mapPatients($clinicId);

                // Plan everything first, so a pairing problem anywhere aborts before any write
                $plans = [
                    'cases' => $this->planCases($clinicId, $patientIdMap),
                    'bills' => $this->planBills(),
                    'notes' => $this->planNotes($clinicId),
                ];
            } catch (\RuntimeException $e) {
                $this->error('❌ ' . $e->getMessage());
                $this->error('   Nothing was changed.');
                return self::FAILURE;
            }

            $this->printSummary($plans);

            $total = array_sum(array_map(fn ($plan) => count($plan['updates']), $plans));
            if ($isDryRun || $total === 0) {
                $this->info($total === 0 ? '✅ Nothing to fix.' : "🔍 {$total} rows would be updated. Run again without --dry-run to apply.");
                return self::SUCCESS;
            }

            DB::transaction(function () use ($plans) {
                foreach ($plans as $table => $plan) {
                    foreach ($plan['updates'] as $id => $changes) {
                        DB::table($table)->where('id', $id)->update($changes);
                    }
                }
            });

            $this->info("✅ Updated {$total} rows.");
            return self::SUCCESS;
        });
    }

    /**
     * Map old doctors to the users the seeder created for them (by the phone it stored,
     * then by a unique name).
     */
    private function mapDoctors(int $clinicId): void
    {
        $oldDoctors = DB::connection($this->oldDb)
            ->table('doctors')
            ->where('clinics_id', $clinicId)
            ->orderBy('id')
            ->get();

        $oldUsers = Schema::connection($this->oldDb)->hasTable('users')
            ? DB::connection($this->oldDb)->table('users')->whereIn('id', $oldDoctors->pluck('user_id'))->get()->keyBy('id')
            : collect();

        $newUsers = DB::table('users')->orderBy('id')->get(['id', 'name', 'phone']);
        $this->firstUserId = $newUsers->first()?->id;

        $this->info('👥 Doctors (old → new user):');

        foreach ($oldDoctors as $oldDoctor) {
            $oldUser = $oldUsers->get($oldDoctor->user_id);
            $name = $oldUser->name ?? $oldDoctor->name;

            // The seeder stored the old phone, or old_{user id} / old_d{doctor id} when it had none
            $phones = array_filter([$oldUser->phone ?? null, 'old_' . $oldDoctor->user_id, 'old_d' . $oldDoctor->id]);
            $newUser = $newUsers->first(fn ($user) => in_array($user->phone, $phones, true));

            if (!$newUser) {
                $sameName = $newUsers->where('name', $name);
                $newUser = $sameName->count() === 1 ? $sameName->first() : null;
            }

            if (!$newUser) {
                $this->warn("   ⚠ {$name} (doctor:{$oldDoctor->id}) has no matching user, their records are left as they are");
                continue;
            }

            $this->doctorIdMap[$oldDoctor->id] = (int) $newUser->id;
            $this->doctorUserIdMap[$oldDoctor->user_id] = (int) $newUser->id;
            $this->line("   {$name} (doctor:{$oldDoctor->id}) → {$newUser->name} (user:{$newUser->id})");
        }
    }

    /**
     * Pair old patients with the migrated ones, verified by created_at.
     */
    private function mapPatients(int $clinicId): array
    {
        $oldPatients = DB::connection($this->oldDb)
            ->table('patients')
            ->where('clinics_id', $clinicId)
            ->orderBy('id')
            ->get(['id', 'created_at']);

        $newPatients = DB::table('patients')->orderBy('id')->limit($oldPatients->count())->get(['id', 'created_at']);

        $map = [];
        foreach ($oldPatients as $i => $oldPatient) {
            $newPatient = $newPatients[$i] ?? null;
            if (!$newPatient || !$this->same($newPatient->created_at, $oldPatient->created_at)) {
                throw new \RuntimeException("Patients do not line up with the old DB at old patient {$oldPatient->id} (new: " . ($newPatient->id ?? 'missing') . ').');
            }
            $map[$oldPatient->id] = (int) $newPatient->id;
        }

        return $map;
    }

    private function planCases(int $clinicId, array $patientIdMap): array
    {
        $oldCases = DB::connection($this->oldDb)
            ->table('cases')
            ->join('patients', 'patients.id', '=', 'cases.patient_id')
            ->where('patients.clinics_id', $clinicId)
            ->orderBy('cases.id')
            ->select('cases.*')
            ->get();

        $newCases = DB::table('cases')
            ->orderBy('id')
            ->limit($oldCases->count())
            ->get(['id', 'patient_id', 'doctor_id', 'item_cost', 'deleted_at', 'created_at', 'updated_at']);

        $caseDoctors = Schema::connection($this->oldDb)->hasTable('CaseDoctor')
            ? DB::connection($this->oldDb)->table('CaseDoctor')->pluck('doctors_id', 'cases_id')->toArray()
            : [];

        $plan = $this->emptyPlan();

        foreach ($oldCases as $i => $oldCase) {
            $newCase = $newCases[$i] ?? null;
            if (!$newCase
                || !$this->same($newCase->created_at, $oldCase->created_at)
                || (int) $newCase->patient_id !== ($patientIdMap[$oldCase->patient_id] ?? null)) {
                throw new \RuntimeException("Cases do not line up with the old DB at old case {$oldCase->id} (new: " . ($newCase->id ?? 'missing') . ').');
            }

            // The doctor the old app shows: cases.doctor_id, then CaseDoctor
            $fromCaseDoctor = isset($caseDoctors[$oldCase->id]) ? ($this->doctorIdMap[$caseDoctors[$oldCase->id]] ?? null) : null;
            $correct = (isset($oldCase->doctor_id) ? ($this->doctorIdMap[$oldCase->doctor_id] ?? null) : null) ?? $fromCaseDoctor;

            // The doctor the old seeder picked: CaseDoctor, then cases.user_id, then the first user
            $buggy = $fromCaseDoctor
                ?? (isset($oldCase->user_id) ? ($this->doctorUserIdMap[$oldCase->user_id] ?? null) : null)
                ?? $this->firstUserId;

            $this->cases[$oldCase->id] = ['new_id' => (int) $newCase->id, 'correct' => $correct, 'buggy' => $buggy];

            $edited = !$this->same($newCase->updated_at, $oldCase->updated_at);
            $changes = [];

            $this->fixDoctor($changes, $plan, $newCase, 'doctor_id', $correct, $buggy, $edited);
            if (!$edited) {
                $this->setIfDifferent($changes, $plan, $newCase, 'item_cost', $oldCase->item_cost ?? 0);
                $this->setIfDifferent($changes, $plan, $newCase, 'deleted_at', $oldCase->deleted_at ?? null);
            }

            $this->addToPlan($plan, $newCase->id, $changes, $edited);
        }

        return $plan;
    }

    private function planBills(): array
    {
        // Same filter as the seeder: case bills of migrated cases, in old id order
        $oldBills = DB::connection($this->oldDb)
            ->table('bills')
            ->where('billable_type', 'like', '%Case%')
            ->orderBy('id')
            ->get()
            ->filter(fn ($bill) => str_contains($bill->billable_type, 'Case') && isset($this->cases[$bill->billable_id]))
            ->values();

        $newBills = DB::table('bills')
            ->orderBy('id')
            ->limit($oldBills->count())
            ->get(['id', 'billable_id', 'billable_type', 'doctor_id', 'is_paid', 'bill_date', 'use_credit', 'deleted_at', 'created_at', 'updated_at']);

        $plan = $this->emptyPlan();

        foreach ($oldBills as $i => $oldBill) {
            $newBill = $newBills[$i] ?? null;
            $case = $this->cases[$oldBill->billable_id];

            if (!$newBill
                || $newBill->billable_type !== CaseModel::class
                || (int) $newBill->billable_id !== $case['new_id']
                || !$this->same($newBill->created_at, $oldBill->created_at)) {
                throw new \RuntimeException("Bills do not line up with the old DB at old bill {$oldBill->id} (new: " . ($newBill->id ?? 'missing') . ').');
            }

            $edited = !$this->same($newBill->updated_at, $oldBill->updated_at);
            $changes = [];

            $this->fixDoctor($changes, $plan, $newBill, 'doctor_id', $case['correct'], $case['buggy'], $edited);
            if (!$edited) {
                $this->setIfDifferent($changes, $plan, $newBill, 'is_paid', (int) (bool) ($oldBill->is_paid ?? $oldBill->PaymentDate));
                $this->setIfDifferent($changes, $plan, $newBill, 'bill_date', $oldBill->PaymentDate);
                $this->setIfDifferent($changes, $plan, $newBill, 'use_credit', (int) (bool) ($oldBill->use_credit ?? 0));
                $this->setIfDifferent($changes, $plan, $newBill, 'deleted_at', $oldBill->deleted_at ?? null);
            }

            $this->addToPlan($plan, $newBill->id, $changes, $edited);
        }

        return $plan;
    }

    private function planNotes(int $clinicId): array
    {
        $oldSessions = DB::connection($this->oldDb)
            ->table('sessions')
            ->join('cases', 'cases.id', '=', 'sessions.case_id')
            ->join('patients', 'patients.id', '=', 'cases.patient_id')
            ->where('patients.clinics_id', $clinicId)
            ->orderBy('sessions.id')
            ->select('sessions.*')
            ->get()
            ->filter(fn ($session) => isset($this->cases[$session->case_id]))
            ->values();

        $newNotes = DB::table('notes')
            ->orderBy('id')
            ->limit($oldSessions->count())
            ->get(['id', 'noteable_id', 'noteable_type', 'content', 'created_by', 'deleted_at', 'created_at', 'updated_at']);

        $plan = $this->emptyPlan();

        foreach ($oldSessions as $i => $oldSession) {
            $newNote = $newNotes[$i] ?? null;
            $case = $this->cases[$oldSession->case_id];

            // The seeder only set the note dates when the session had one
            if (!$newNote
                || $newNote->noteable_type !== CaseModel::class
                || (int) $newNote->noteable_id !== $case['new_id']
                || ($oldSession->date && !$this->same($newNote->created_at, $oldSession->date))) {
                throw new \RuntimeException("Notes do not line up with the old DB at old session {$oldSession->id} (new: " . ($newNote->id ?? 'missing') . ').');
            }

            $content = $oldSession->note ?? '';
            if (empty(trim($content))) {
                $content = '(session without notes)';
            }

            $edited = $newNote->content !== $content
                || ($oldSession->date && !$this->same($newNote->updated_at, $oldSession->date));
            $changes = [];

            // The seeder credited every note to the first user; credit the case's doctor
            $this->fixDoctor($changes, $plan, $newNote, 'created_by', $case['correct'], $this->firstUserId, $edited);
            if (!$edited) {
                $this->setIfDifferent($changes, $plan, $newNote, 'deleted_at', $oldSession->deleted_at ?? null);
            }

            $this->addToPlan($plan, $newNote->id, $changes, $edited);
        }

        return $plan;
    }

    /**
     * Replace a wrong doctor, unless someone set it by hand after the migration
     * (the row was edited and no longer holds the value the old seeder put there).
     */
    private function fixDoctor(array &$changes, array &$plan, object $row, string $column, ?int $correct, ?int $buggy, bool $edited): void
    {
        if (!$correct || $this->same($row->$column, $correct)) {
            return;
        }

        if ($edited && !$this->same($row->$column, $buggy)) {
            $plan['stats']['doctor kept (set by hand)']++;
            return;
        }

        $changes[$column] = $correct;
        $plan['stats']['doctor fixed']++;
    }

    private function setIfDifferent(array &$changes, array &$plan, object $row, string $column, $value): void
    {
        if (!$this->same($row->$column, $value)) {
            $changes[$column] = $value;
            $plan['stats'][$column] = ($plan['stats'][$column] ?? 0) + 1;
        }
    }

    private function addToPlan(array &$plan, $id, array $changes, bool $edited): void
    {
        $plan['stats']['migrated rows']++;
        if ($edited) {
            $plan['stats']['edited after migration']++;
        }
        if ($changes) {
            $plan['updates'][(int) $id] = $changes;
        }
    }

    private function emptyPlan(): array
    {
        return [
            'updates' => [],
            'stats' => ['migrated rows' => 0, 'edited after migration' => 0, 'doctor fixed' => 0, 'doctor kept (set by hand)' => 0],
        ];
    }

    private function printSummary(array $plans): void
    {
        $labels = array_unique(array_merge(...array_map(fn ($plan) => array_keys($plan['stats']), array_values($plans))));

        $rows = [];
        foreach ($labels as $label) {
            $rows[] = array_merge([$label], array_map(fn ($plan) => $plan['stats'][$label] ?? 0, array_values($plans)));
        }
        $rows[] = array_merge(['rows to update'], array_map(fn ($plan) => count($plan['updates']), array_values($plans)));

        $this->newLine();
        $this->table(array_merge([''], array_keys($plans)), $rows);
    }

    /**
     * Compare DB values loosely (ints may come back as strings), keeping null distinct.
     */
    private function same($a, $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return (string) $a === (string) $b;
    }
}
