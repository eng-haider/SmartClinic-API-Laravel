<?php

namespace Tests\Unit;

use App\Http\Resources\PublicPatientResource;
use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\Image;
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

    public function test_it_serializes_case_images_without_paths_or_ids(): void
    {
        $case = $this->bareCase(['id' => 99, 'status_id' => 1, 'case_date' => '2026-10-01']);
        $case->setRelation('images', new Collection([
            new Image(['id' => 5, 'path' => 'images/before/a.jpg', 'disk' => 'public', 'type' => 'before', 'tooth_num' => '16', 'alt_text' => 'Internal note']),
        ]));

        $patient = new Patient(['name' => 'Sara Ahmed']);
        $patient->setRelation('cases', new Collection([$case]));

        $payload = (new PublicPatientResource($patient))->toArray(Request::create('/'));
        $image = $payload['current_treatment']['images'][0];

        $this->assertSame(['url', 'type', 'tooth', 'date'], array_keys($image));
        $this->assertSame('before', $image['type']);
        $this->assertSame('16', $image['tooth']);
        $this->assertCount(1, $payload['treatment_timeline'][0]['images']);
    }

    public function test_cases_without_loaded_images_serialize_an_empty_list(): void
    {
        $patient = new Patient(['name' => 'Sara Ahmed']);
        $patient->setRelation('cases', new Collection([$this->bareCase(['id' => 1, 'status_id' => 1])]));

        $payload = (new PublicPatientResource($patient))->toArray(Request::create('/'));

        $this->assertSame([], $payload['current_treatment']['images']);
    }

    /** A case with its display relations preset, so serializing it never queries. */
    private function bareCase(array $attributes): CaseModel
    {
        $case = new CaseModel($attributes);
        foreach (['category', 'status', 'doctor'] as $relation) {
            $case->setRelation($relation, null);
        }

        return $case;
    }
}
