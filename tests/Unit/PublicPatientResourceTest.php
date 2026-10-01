<?php

namespace Tests\Unit;

use App\Http\Resources\PublicPatientResource;
use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\Patient;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicPatientResourceTest extends TestCase
{
    public function test_it_only_serializes_patient_safe_portal_fields(): void
    {
        $case = new CaseModel([
            'id' => 99,
            'status_id' => 1,
            'tooth_num' => '16',
            'notes' => 'Internal clinician note that must never be public',
            'price' => 250000,
            'case_date' => '2026-10-01',
        ]);
        $case->setRelation('category', new CaseCategory(['id' => 7, 'name' => 'Root canal', 'name_en' => 'Root canal']));
        $case->setRelation('status', new Status(['id' => 1, 'name_en' => 'In progress']));
        $case->setRelation('doctor', new User(['id' => 4, 'name' => 'Dr. Noor']));

        $patient = new Patient([
            'id' => 42,
            'name' => 'Sara Ahmed',
            'phone' => '07701234567',
            'systemic_conditions' => 'Private medical information',
        ]);
        $patient->setRelation('cases', new Collection([$case]));
        $patient->setRelation('reservations', new Collection());

        $payload = (new PublicPatientResource($patient, ['name' => 'North Clinic', 'currency' => 'IQD'], [
            'total' => 250000,
            'paid' => 100000,
            'remaining' => 150000,
        ]))->toArray(Request::create('/'));

        $this->assertSame('Sara Ahmed', $payload['name']);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('systemic_conditions', $payload);
        $this->assertArrayNotHasKey('notes', $payload['current_treatment']);
        $this->assertArrayNotHasKey('id', $payload['current_treatment']);
        $this->assertSame(150000, $payload['financial_summary']['remaining']);
        $this->assertSame([], $payload['prescriptions']);
        $this->assertSame([], $payload['documents']);
    }
}
