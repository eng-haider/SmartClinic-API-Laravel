<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting all patients
     */
    public function test_index_returns_all_patients(): void
    {
        // Create test data
        Patient::factory()->count(3)->create();

        // Make request
        $response = $this->getJson('/api/patients');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test searching patients
     */
    public function test_index_with_search_filter(): void
    {
        Patient::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Patient::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $response = $this->getJson('/api/patients?search=John');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test creating a new patient
     */
    public function test_store_creates_new_patient(): void
    {
        $data = [
            'first_name' => 'Ahmed',
            'last_name' => 'Hassan',
            'email' => 'ahmed@example.com',
            'phone' => '01001234567',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'city' => 'Cairo',
            'blood_type' => 'O+',
        ];

        $response = $this->postJson('/api/patients', $data);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.first_name', 'Ahmed');

        $this->assertDatabaseHas('patients', ['email' => 'ahmed@example.com']);
    }

    /**
     * Test validation errors on store
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/patients', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'phone', 'date_of_birth', 'gender']);
    }

    /**
     * Test duplicate phone validation
     */
    public function test_store_validates_duplicate_phone(): void
    {
        Patient::factory()->create(['phone' => '01001234567']);

        $data = [
            'first_name' => 'Ali',
            'last_name' => 'Smith',
            'phone' => '01001234567',
            'date_of_birth' => '1995-03-20',
            'gender' => 'male',
        ];

        $response = $this->postJson('/api/patients', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /**
     * Test showing a patient
     */
    public function test_show_returns_single_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.first_name', $patient->first_name);
    }

    /**
     * Test showing non-existent patient
     */
    public function test_show_returns_404_for_non_existent_patient(): void
    {
        $response = $this->getJson('/api/patients/999');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /**
     * Test updating a patient
     */
    public function test_update_modifies_patient(): void
    {
        $patient = Patient::factory()->create();

        $data = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => $patient->phone,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
        ];

        $response = $this->putJson("/api/patients/{$patient->id}", $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.first_name', 'Updated');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Updated']);
    }

    /**
     * Test deleting a patient
     */
    public function test_destroy_deletes_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->deleteJson("/api/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }
}
