<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The clinic's patients, cases and bills as flat rows, for the "Export data"
 * option in Settings (the browser turns them into an Excel file).
 *
 * This is the whole patient file of the clinic, so only whoever manages the
 * clinic's settings may download it. Plain queries with joins keep it quick
 * and light for clinics with many thousands of records.
 */
class ClinicDataExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
        $this->middleware('permission:edit-clinic-settings');
    }

    public function index(): JsonResponse
    {
        // Large clinics: building the rows may take longer than PHP's default
        set_time_limit(300);

        return response()->json([
            'success' => true,
            'data' => [
                'generated_at' => now()->toDateTimeString(),
                'patients' => $this->patients(),
                'cases' => $this->cases(),
                'bills' => $this->bills(),
            ],
        ]);
    }

    private function patients(): array
    {
        return DB::table('patients as p')
            ->leftJoin('users as d', 'd.id', '=', 'p.doctor_id')
            ->leftJoin('from_where_comes as f', 'f.id', '=', 'p.from_where_come_id')
            ->whereNull('p.deleted_at')
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.name', 'p.phone', 'p.phone2', 'p.sex', 'p.age', 'p.birth_date',
                'p.address', 'p.systemic_conditions', 'p.notes', 'p.credit_balance',
                'd.name as doctor_name', 'f.name as source_name', 'f.name_ar as source_name_ar',
                'p.created_at',
            ])
            ->all();
    }

    private function cases(): array
    {
        return DB::table('cases as c')
            ->leftJoin('patients as p', 'p.id', '=', 'c.patient_id')
            ->leftJoin('users as d', 'd.id', '=', 'c.doctor_id')
            ->leftJoin('case_categories as cat', 'cat.id', '=', 'c.case_categores_id')
            ->leftJoin('statuses as s', 's.id', '=', 'c.status_id')
            ->whereNull('c.deleted_at')
            ->orderBy('c.id')
            ->get([
                'c.id', 'c.patient_id', 'p.name as patient_name', 'p.phone as patient_phone',
                'd.name as doctor_name', 'cat.name as category_name',
                's.name_ar as status_name_ar', 's.name_en as status_name_en',
                'c.tooth_num', 'c.price', 'c.is_paid', 'c.case_date', 'c.notes', 'c.created_at',
            ])
            ->all();
    }

    /**
     * Patients' payments only: bills without a patient belong to clinic expenses.
     */
    private function bills(): array
    {
        return DB::table('bills as b')
            ->leftJoin('patients as p', 'p.id', '=', 'b.patient_id')
            ->leftJoin('users as d', 'd.id', '=', 'b.doctor_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.creator_id')
            // Cases are stored as App\Models\Case (see CaseModel::getMorphClass)
            ->leftJoin('cases as c', function ($join) {
                $join->on('c.id', '=', 'b.billable_id')
                    ->whereIn('b.billable_type', ['App\\Models\\Case', 'App\\Models\\CaseModel']);
            })
            ->leftJoin('case_categories as cat', 'cat.id', '=', 'c.case_categores_id')
            ->whereNull('b.deleted_at')
            ->whereNotNull('b.patient_id')
            ->orderBy('b.id')
            ->get([
                'b.id', 'b.patient_id', 'p.name as patient_name', 'p.phone as patient_phone',
                'b.billable_id as case_id', 'cat.name as category_name',
                'b.price', 'b.use_credit', 'd.name as doctor_name', 'u.name as creator_name',
                'b.bill_date', 'b.created_at',
            ])
            ->all();
    }
}
