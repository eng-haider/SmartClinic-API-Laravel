<?php

namespace App\Services\AI\Tools;

use App\Models\Patient;

class SearchPatientTool implements AIToolInterface
{
    public function name(): string
    {
        return 'search_patient';
    }

    public function description(): string
    {
        return 'Searches for patients by name in the database and returns their details.';
    }

    public function execute(array $params): string
    {
        $patientName = $params['entities']['patient_name'] ?? '';
        $lines = [];

        if (empty($patientName)) {
            return "No patient name provided for search.";
        }

        $patients = Patient::where('name', 'like', "%{$patientName}%")
            ->with(['doctor:id,name'])
            ->withCount(['cases', 'bills', 'reservations'])
            ->limit(10)
            ->get();

        if ($patients->isEmpty()) {
            return "No patients found matching: \"{$patientName}\"";
        }

        $lines[] = "--- Patient Search Results for \"{$patientName}\" ---";
        $lines[] = "Found: " . $patients->count() . " patient(s)";
        $lines[] = "";

        foreach ($patients as $patient) {
            $lines[] = "Patient: {$patient->name}";
            $lines[] = "  ID: {$patient->id}";
            $lines[] = "  Age: " . ($patient->age ?? 'N/A');
            $lines[] = "  Sex: {$patient->sex_label}";
            $lines[] = "  Phone: " . ($patient->phone ?? 'N/A');
            $lines[] = "  Address: " . ($patient->address ?? 'N/A');
            $lines[] = "  Doctor: " . ($patient->doctor->name ?? 'N/A');
            $lines[] = "  Systemic Conditions: " . ($patient->systemic_conditions ?? 'None');
            $lines[] = "  Cases: {$patient->cases_count}";
            $lines[] = "  Bills: {$patient->bills_count}";
            $lines[] = "  Appointments: {$patient->reservations_count}";
            $lines[] = "  Registered: {$patient->created_at->toDateString()}";

            // Get financial summary for this patient
            $totalBilled = $patient->bills()->sum('price');
            $lines[] = "  Total Billed: {$totalBilled}";

            // Recent cases
            $recentCases = $patient->cases()->with(['category:id,name', 'status:id,name'])->latest()->take(5)->get();
            if ($recentCases->isNotEmpty()) {
                $lines[] = "  Recent Cases:";
                foreach ($recentCases as $case) {
                    $catName = $case->category->name ?? 'Unknown';
                    $statusName = $case->status->name ?? 'Unknown';
                    $lines[] = "    - {$catName} | {$statusName} | {$case->price} | {$case->created_at->toDateString()}";
                }
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }
}
