<?php

namespace App\Repositories\Reports;

use App\Models\Bill;
use App\Models\CaseModel;
use App\Models\ClinicExpense;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Status;
use App\Models\CaseCategory;
use App\Models\FromWhereCome;
use App\Models\ClinicExpenseCategory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsRepository
{
    /**
     * ============================
     * DASHBOARD OVERVIEW
     * ============================
     */

    /**
     * Get dashboard overview statistics
     */
    public function getDashboardOverview(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return [
            'patients' => $this->getPatientsSummary($clinicId, $dateFrom, $dateTo),
            'bills' => $this->getBillsSummary($clinicId, $dateFrom, $dateTo),
            'reservations' => $this->getReservationsSummary($clinicId, $dateFrom, $dateTo),
            'cases' => $this->getCasesSummary($clinicId, $dateFrom, $dateTo),
            'expenses' => $this->getExpensesSummary($clinicId, $dateFrom, $dateTo),
        ];
    }

    /**
     * Get today's summary for quick stats
     */
    public function getTodaySummary(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        
        return [
            'new_patients' => $this->getNewPatientsCount($clinicId, $today, $today),
            'reservations_today' => $this->getReservationsCountByDate($today),
            'revenue_today' => $this->getRevenueByDateRange($clinicId, $today, $today),
            'cases_today' => $this->getCasesCountByDateRange($clinicId, $today, $today),
            'expenses_today' => $this->getExpensesTotalByDateRange($clinicId, $today, $today),
        ];
    }

    /**
     * ============================
     * PATIENT STATISTICS
     * ============================
     */

    /**
     * Get patients summary counts
     */
    public function getPatientsSummary(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Patient::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $total = (clone $query)->count();
        $male = (clone $query)->where('sex', 1)->count();
        $female = (clone $query)->where('sex', 2)->count();
        
        return [
            'total' => $total,
            'male' => $male,
            'female' => $female,
            'male_percentage' => $total > 0 ? round(($male / $total) * 100, 2) : 0,
            'female_percentage' => $total > 0 ? round(($female / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get new patients count by date range
     */
    public function getNewPatientsCount(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $query = Patient::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return $query->count();
    }

    /**
     * Get patients grouped by source (from_where_come)
     */
    public function getPatientsBySource(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Patient::query()
            ->select('from_where_come_id', DB::raw('COUNT(*) as count'))
            ->with('fromWhereCome:id,name,name_ar')
            ->groupBy('from_where_come_id');
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'source_id' => $item->from_where_come_id,
                'source_name' => $item->fromWhereCome?->name ?? 'Unknown',
                'source_name_ar' => $item->fromWhereCome?->name_ar ?? 'غير معروف',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get patients grouped by doctor
     */
    public function getPatientsByDoctor(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Patient::query()
            ->select('doctor_id', DB::raw('COUNT(*) as count'))
            ->with('doctor:id,name,email')
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id');
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'doctor_id' => $item->doctor_id,
                'doctor_name' => $item->doctor?->name ?? 'Unknown',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get patients trend (grouped by period)
     */
    public function getPatientsTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Patient::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return $this->groupByPeriod($query, 'created_at', $period);
    }

    /**
     * Get patients age distribution
     */
    public function getPatientsAgeDistribution(): array
    {
        $query = Patient::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $results = $query->select(
            DB::raw("
                CASE 
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 30 THEN '18-30'
                    WHEN age BETWEEN 31 AND 45 THEN '31-45'
                    WHEN age BETWEEN 46 AND 60 THEN '46-60'
                    WHEN age > 60 THEN '60+'
                    ELSE 'Unknown'
                END as age_group
            "),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('age_group')
        ->orderByRaw("FIELD(age_group, '0-17', '18-30', '31-45', '46-60', '60+', 'Unknown')")
        ->get();
        
        return $results->toArray();
    }

    /**
     * ============================
     * BILL/REVENUE STATISTICS
     * ============================
     */

    /**
     * Get bills summary
     */
    public function getBillsSummary(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Bill::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $totalBills = (clone $query)->count();
        $paidBills = (clone $query)->where('is_paid', true)->count();
        $unpaidBills = (clone $query)->where('is_paid', false)->count();
        $totalRevenue = (clone $query)->where('is_paid', true)->sum('price') ?? 0;
        $totalOutstanding = (clone $query)->where('is_paid', false)->sum('price') ?? 0;
        
        return [
            'total_bills' => $totalBills,
            'paid_bills' => $paidBills,
            'unpaid_bills' => $unpaidBills,
            'total_revenue' => (int) $totalRevenue,
            'total_outstanding' => (int) $totalOutstanding,
            'collection_rate' => $totalBills > 0 ? round(($paidBills / $totalBills) * 100, 2) : 0,
        ];
    }

    /**
     * Get revenue by date range
     */
    public function getRevenueByDateRange(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $query = Bill::query()->where('is_paid', true);
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return (int) ($query->sum('price') ?? 0);
    }

    /**
     * Get revenue trend
     */
    public function getRevenueTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Bill::query()->where('is_paid', true);
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return $this->groupByPeriodWithSum($query, 'created_at', 'price', $period);
    }

    /**
     * Get revenue by doctor
     */
    public function getRevenueByDoctor(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Bill::query()
            ->select('doctor_id', DB::raw('SUM(price) as total_revenue'), DB::raw('COUNT(*) as bills_count'))
            ->with('doctor:id,name,email')
            ->where('is_paid', true)
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id');
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'doctor_id' => $item->doctor_id,
                'doctor_name' => $item->doctor?->name ?? 'Unknown',
                'total_revenue' => (int) $item->total_revenue,
                'bills_count' => $item->bills_count,
            ];
        })->toArray();
    }

    /**
     * Get bills by payment status
     */
    public function getBillsByPaymentStatus(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Bill::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $paid = (clone $query)->where('is_paid', true)->count();
        $unpaid = (clone $query)->where('is_paid', false)->count();
        $total = $paid + $unpaid;
        
        return [
            ['status' => 'paid', 'count' => $paid, 'percentage' => $total > 0 ? round(($paid / $total) * 100, 2) : 0],
            ['status' => 'unpaid', 'count' => $unpaid, 'percentage' => $total > 0 ? round(($unpaid / $total) * 100, 2) : 0],
        ];
    }

    /**
     * ============================
     * RESERVATION STATISTICS
     * ============================
     */

    /**
     * Get reservations summary
     */
    public function getReservationsSummary(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Reservation::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'reservation_start_date');
        
        $total = (clone $query)->count();
        $waiting = (clone $query)->where('is_waiting', true)->count();
        $notWaiting = (clone $query)->where('is_waiting', false)->count();
        
        return [
            'total' => $total,
            'waiting' => $waiting,
            'confirmed' => $notWaiting,
            'waiting_percentage' => $total > 0 ? round(($waiting / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get reservations count for a specific date
     */
    public function getReservationsCountByDate(string $date): int
    {
        $query = Reservation::query()
            ->whereDate('reservation_start_date', '<=', $date)
            ->whereDate('reservation_end_date', '>=', $date);
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        return $query->count();
    }

    /**
     * Get reservations by status
     */
    public function getReservationsByStatus(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Reservation::query()
            ->select('status_id', DB::raw('COUNT(*) as count'))
            ->with('status:id,name_ar,name_en,color')
            ->whereNotNull('status_id')
            ->groupBy('status_id');
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'reservation_start_date');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'status_id' => $item->status_id,
                'status_name' => $item->status?->name_en ?? 'Unknown',
                'status_name_ar' => $item->status?->name_ar ?? 'غير معروف',
                'color' => $item->status?->color ?? '#000000',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get reservations by doctor
     */
    public function getReservationsByDoctor(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Reservation::query()
            ->select('doctor_id', DB::raw('COUNT(*) as count'))
            ->with('doctor:id,name,email')
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id');
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'reservation_start_date');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'doctor_id' => $item->doctor_id,
                'doctor_name' => $item->doctor?->name ?? 'Unknown',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get reservations trend
     */
    public function getReservationsTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Reservation::query();
        
        if ($clinicId) {
            $query->where('clinics_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'reservation_start_date');
        
        return $this->groupByPeriod($query, 'reservation_start_date', $period);
    }

    /**
     * ============================
     * CASE STATISTICS
     * ============================
     */

    /**
     * Get cases summary
     */
    public function getCasesSummary(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CaseModel::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $total = (clone $query)->count();
        $paid = (clone $query)->where('is_paid', true)->count();
        $unpaid = (clone $query)->where('is_paid', false)->count();
        $totalValue = (clone $query)->sum('price') ?? 0;
        
        return [
            'total' => $total,
            'paid' => $paid,
            'unpaid' => $unpaid,
            'total_value' => (int) $totalValue,
            'paid_percentage' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get cases count by date range
     */
    public function getCasesCountByDateRange(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $query = CaseModel::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return $query->count();
    }

    /**
     * Get cases by category
     */
    public function getCasesByCategory(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CaseModel::query()
            ->select('case_categores_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(price) as total_value'))
            ->with('category:id,name')
            ->whereNotNull('case_categores_id')
            ->groupBy('case_categores_id');
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'category_id' => $item->case_categores_id,
                'category_name' => $item->category?->name ?? 'Unknown',
                'count' => $item->count,
                'total_value' => (int) $item->total_value,
            ];
        })->toArray();
    }

    /**
     * Get cases by status
     */
    public function getCasesByStatus(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CaseModel::query()
            ->select('status_id', DB::raw('COUNT(*) as count'))
            ->with('status:id,name_ar,name_en,color')
            ->whereNotNull('status_id')
            ->groupBy('status_id');
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'status_id' => $item->status_id,
                'status_name' => $item->status?->name_en ?? 'Unknown',
                'status_name_ar' => $item->status?->name_ar ?? 'غير معروف',
                'color' => $item->status?->color ?? '#000000',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get cases by doctor
     */
    public function getCasesByDoctor(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CaseModel::query()
            ->select('doctor_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(price) as total_value'))
            ->with('doctor:id,name,email')
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id');
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'doctor_id' => $item->doctor_id,
                'doctor_name' => $item->doctor?->name ?? 'Unknown',
                'count' => $item->count,
                'total_value' => (int) $item->total_value,
            ];
        })->toArray();
    }

    /**
     * Get cases trend
     */
    public function getCasesTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CaseModel::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'created_at');
        
        return $this->groupByPeriod($query, 'created_at', $period);
    }

    /**
     * ============================
     * EXPENSE STATISTICS
     * ============================
     */

    /**
     * Get expenses summary
     */
    public function getExpensesSummary(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ClinicExpense::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'date');
        
        $total = (clone $query)->count();
        $paid = (clone $query)->where('is_paid', true)->count();
        $unpaid = (clone $query)->where('is_paid', false)->count();
        $totalAmount = (clone $query)->selectRaw('SUM(COALESCE(quantity, 1) * price) as total')->value('total') ?? 0;
        $paidAmount = (clone $query)->where('is_paid', true)->selectRaw('SUM(COALESCE(quantity, 1) * price) as total')->value('total') ?? 0;
        $unpaidAmount = (clone $query)->where('is_paid', false)->selectRaw('SUM(COALESCE(quantity, 1) * price) as total')->value('total') ?? 0;
        
        return [
            'total_expenses' => $total,
            'paid_expenses' => $paid,
            'unpaid_expenses' => $unpaid,
            'total_amount' => round((float) $totalAmount, 2),
            'paid_amount' => round((float) $paidAmount, 2),
            'unpaid_amount' => round((float) $unpaidAmount, 2),
        ];
    }

    /**
     * Get expenses total by date range
     */
    public function getExpensesTotalByDateRange(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): float
    {
        $query = ClinicExpense::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'date');
        
        return round((float) ($query->selectRaw('SUM(COALESCE(quantity, 1) * price) as total')->value('total') ?? 0), 2);
    }

    /**
     * Get expenses by category
     */
    public function getExpensesByCategory(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ClinicExpense::query()
            ->select('clinic_expense_category_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(COALESCE(quantity, 1) * price) as total_amount'))
            ->with('category:id,name')
            ->whereNotNull('clinic_expense_category_id')
            ->groupBy('clinic_expense_category_id');
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'date');
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            return [
                'category_id' => $item->clinic_expense_category_id,
                'category_name' => $item->category?->name ?? 'Unknown',
                'count' => $item->count,
                'total_amount' => round((float) $item->total_amount, 2),
            ];
        })->toArray();
    }

    /**
     * Get expenses trend
     */
    public function getExpensesTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ClinicExpense::query();
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        $this->applyDateFilter($query, $dateFrom, $dateTo, 'date');
        
        return $this->groupByPeriodWithSum($query, 'date', DB::raw('COALESCE(quantity, 1) * price'), $period);
    }

    /**
     * ============================
     * FINANCIAL REPORTS
     * ============================
     */

    /**
     * Get profit/loss report
     */
    public function getProfitLossReport(?string|int $clinicId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $revenue = $this->getRevenueByDateRange($clinicId, $dateFrom, $dateTo);
        $expenses = $this->getExpensesTotalByDateRange($clinicId, $dateFrom, $dateTo);
        $profitLoss = $revenue - $expenses;
        
        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'profit_loss' => round($profitLoss, 2),
            'profit_margin' => $revenue > 0 ? round(($profitLoss / $revenue) * 100, 2) : 0,
            'is_profit' => $profitLoss >= 0,
        ];
    }

    /**
     * Get profit/loss trend
     */
    public function getProfitLossTrend(?string|int $clinicId = null, string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $revenueTrend = $this->getRevenueTrend($clinicId, $period, $dateFrom, $dateTo);
        $expensesTrend = $this->getExpensesTrend($clinicId, $period, $dateFrom, $dateTo);
        
        // Combine trends
        $combined = [];
        $allPeriods = array_unique(array_merge(
            array_column($revenueTrend, 'period'),
            array_column($expensesTrend, 'period')
        ));
        sort($allPeriods);
        
        foreach ($allPeriods as $periodKey) {
            $revenue = 0;
            $expenses = 0;
            
            foreach ($revenueTrend as $r) {
                if ($r['period'] === $periodKey) {
                    $revenue = $r['total'];
                    break;
                }
            }
            
            foreach ($expensesTrend as $e) {
                if ($e['period'] === $periodKey) {
                    $expenses = $e['total'];
                    break;
                }
            }
            
            $combined[] = [
                'period' => $periodKey,
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit_loss' => round($revenue - $expenses, 2),
            ];
        }
        
        return $combined;
    }

    /**
     * ============================
     * DOCTOR PERFORMANCE
     * ============================
     */

    /**
     * Get doctor performance statistics
     */
    public function getDoctorPerformance(?string|int $clinicId = null, ?int $doctorId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['doctor', 'clinic_super_doctor', 'super_admin']);
            });
        
        if ($clinicId) {
            $query->where('clinic_id');
        }
        
        if ($doctorId) {
            $query->where('id', $doctorId);
        }
        
        $doctors = $query->get();
        
        $result = [];
        foreach ($doctors as $doctor) {
            $casesQuery = CaseModel::where('doctor_id', $doctor->id);
            $billsQuery = Bill::where('doctor_id', $doctor->id)->where('is_paid', true);
            $reservationsQuery = Reservation::where('doctor_id', $doctor->id);
            $patientsQuery = Patient::where('doctor_id', $doctor->id);
            
            if ($clinicId) {
                $casesQuery->where('clinic_id');
                $billsQuery->where('clinics_id');
                $reservationsQuery->where('clinics_id');
                $patientsQuery->where('clinics_id');
            }
            
            $this->applyDateFilter($casesQuery, $dateFrom, $dateTo, 'created_at');
            $this->applyDateFilter($billsQuery, $dateFrom, $dateTo, 'created_at');
            $this->applyDateFilter($reservationsQuery, $dateFrom, $dateTo, 'reservation_start_date');
            $this->applyDateFilter($patientsQuery, $dateFrom, $dateTo, 'created_at');
            
            $result[] = [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'doctor_email' => $doctor->email,
                'total_patients' => $patientsQuery->count(),
                'total_cases' => $casesQuery->count(),
                'total_reservations' => $reservationsQuery->count(),
                'total_revenue' => (int) ($billsQuery->sum('price') ?? 0),
            ];
        }
        
        return $result;
    }

    /**
     * ============================
     * HELPER METHODS
     * ============================
     */

    /**
     * Apply date filter to query
     */
    protected function applyDateFilter($query, ?string $dateFrom, ?string $dateTo, string $column = 'created_at'): void
    {
        if ($dateFrom) {
            // Start of the day
            $query->where($column, '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            // End of the day (23:59:59)
            $query->where($column, '<=', $dateTo . ' 23:59:59');
        }
    }

    /**
     * Group query by period (day, week, month, year)
     */
    protected function groupByPeriod($query, string $column, string $period = 'month'): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m',
        };
        
        return $query
            ->select(DB::raw("DATE_FORMAT({$column}, '{$format}') as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    /**
     * Group query by period with sum
     */
    protected function groupByPeriodWithSum($query, string $dateColumn, $sumColumn, string $period = 'month'): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m',
        };
        
        $sumExpression = is_string($sumColumn) ? "SUM({$sumColumn})" : "SUM({$sumColumn->getValue(DB::connection()->getQueryGrammar())})";
        
        return $query
            ->select(DB::raw("DATE_FORMAT({$dateColumn}, '{$format}') as period"), DB::raw("{$sumExpression} as total"))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'total' => round((float) $item->total, 2),
                ];
            })
            ->toArray();
    }
}
