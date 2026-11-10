<?php

namespace Tests\Feature;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ClinicDisplaySettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately isolated memory database: never migrate or reset a clinic.
        config([
            'database.default' => 'display_settings_test',
            'database.connections.display_settings_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('display_settings_test');

        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('clinic_settings')->insert([
            ['setting_key' => 'patient_age_input', 'setting_value' => 'age', 'setting_type' => 'string'],
            ['setting_key' => 'waiting_list_style', 'setting_value' => 'cards', 'setting_type' => 'string'],
            ['setting_key' => 'enable_departments', 'setting_value' => '1', 'setting_type' => 'boolean'],
            ['setting_key' => 'tax_rate', 'setting_value' => '15', 'setting_type' => 'integer'],
            ['setting_key' => 'whatsapp_number', 'setting_value' => '07700000000', 'setting_type' => 'string'],
            ['setting_key' => 'clinic_name', 'setting_value' => 'North Clinic', 'setting_type' => 'string'],
        ]);

        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
    }

    private function actingAsSecretary(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 2, 'name' => 'Secretary']);
        $user->shouldReceive('hasPermissionTo')->andReturn(false);
        $user->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($user);
    }

    public function test_staff_without_the_settings_permission_read_the_display_settings(): void
    {
        $this->actingAsSecretary();

        $response = $this->getJson('/api/clinic-settings/display')->assertOk();

        $values = collect($response->json('data.display.settings'))->pluck('setting_value', 'setting_key');
        $this->assertSame('age', $values['patient_age_input']);
        $this->assertSame('cards', $values['waiting_list_style']);
        $this->assertTrue($values['enable_departments']);
    }

    public function test_it_returns_nothing_outside_the_display_category(): void
    {
        $this->actingAsSecretary();

        $data = $this->getJson('/api/clinic-settings/display')->assertOk()->json('data');

        $this->assertSame(['display'], array_keys($data));
        $this->assertStringNotContainsString('tax_rate', json_encode($data));
        $this->assertStringNotContainsString('whatsapp_number', json_encode($data));
    }

    public function test_the_full_settings_stay_behind_the_permission(): void
    {
        $this->actingAsSecretary();

        $this->getJson('/api/clinic-settings')->assertForbidden();
    }
}
