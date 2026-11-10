<?php

namespace Tests\Support\AiAgent;

use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use App\Services\AI\Agent\Api\ApiClient;
use App\Services\AI\Agent\CurrentTenant;
use App\Services\AI\Agent\GeneralAnswerer;
use App\Services\AI\Agent\Nlu\IntentParser;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agent conversations over the real agent endpoints, with the clinic API and
 * the LLM replaced by FakeClinicApi and ScriptedIntentParser. Agent state lives
 * in an isolated in-memory database built from the real tenant migration.
 */
abstract class AgentTestCase extends TestCase
{
    protected FakeClinicApi $api;
    protected ScriptedIntentParser $nlu;
    protected ?string $conversationId = null;
    protected ?array $lastReply = null;
    protected object $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'agent_test',
            'database.connections.agent_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'ai_agent.timezone' => 'Asia/Baghdad',
            'ai_agent.confirmation_ttl_minutes' => 10,
            'services.openai.api_key' => null,
        ]);
        DB::purge('agent_test');
        (require base_path('database/migrations/tenant/2026_11_10_000001_create_ai_agent_tables.php'))->up();

        $this->withoutMiddleware([JwtMiddleware::class, InitializeTenancyByHeader::class]);
        $this->freeze(Carbon::create(2026, 11, 10, 10, 0, 0, 'Asia/Baghdad')); // Tuesday morning

        $this->api = new FakeClinicApi();
        $this->nlu = new ScriptedIntentParser();
        $this->app->instance(ApiClient::class, $this->api);
        $this->app->instance(IntentParser::class, $this->nlu);

        $this->tenant = new class extends CurrentTenant {
            public ?string $value = 'clinic-a';

            public function id(): ?string
            {
                return $this->value;
            }
        };
        $this->app->instance(CurrentTenant::class, $this->tenant);
        $this->app->instance(GeneralAnswerer::class, new class extends GeneralAnswerer {
            public function answer(string $tenantId, string $question): ?array
            {
                return ['answer' => 'general answer', 'sources' => []];
            }
        });

        $this->actingAsUser(7);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    protected function freeze(Carbon $now): void
    {
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now->toImmutable());
    }

    protected function actingAsUser(int $id): void
    {
        $user = new User();
        $user->forceFill(['id' => $id, 'name' => 'Staff ' . $id]);
        $this->actingAs($user);
        $this->api->user['id'] = $id;
    }

    protected function say(string $message, array $extra = []): TestResponse
    {
        $response = $this->postJson('/api/tenant/ai/chat', array_filter([
            'message' => $message,
            'conversation_id' => $this->conversationId,
        ]) + $extra);

        return $this->remember($response);
    }

    protected function choose(string $label): TestResponse
    {
        $option = collect($this->lastReply['options'] ?? [])->first(fn ($o) => str_contains($o['label'], $label));
        $this->assertNotNull($option, "No option containing [{$label}] in: " . json_encode($this->lastReply['options'] ?? [], JSON_UNESCAPED_UNICODE));

        return $this->remember($this->postJson('/api/tenant/ai/chat', [
            'option_id' => $option['id'],
            'conversation_id' => $this->conversationId,
        ]));
    }

    protected function pressConfirm(?string $actionId = null): TestResponse
    {
        $actionId ??= $this->lastReply['action']['id'];

        return $this->remember($this->postJson("/api/tenant/ai/chat/actions/{$actionId}/confirm", ['conversation_id' => $this->conversationId]));
    }

    protected function pressCancel(?string $actionId = null): TestResponse
    {
        $actionId ??= $this->lastReply['action']['id'];

        return $this->remember($this->postJson("/api/tenant/ai/chat/actions/{$actionId}/cancel", ['conversation_id' => $this->conversationId]));
    }

    protected function remember(TestResponse $response): TestResponse
    {
        if ($response->json('data.conversation_id')) {
            $this->conversationId = $response->json('data.conversation_id');
        }
        if ($response->json('data.reply')) {
            $this->lastReply = $response->json('data.reply');
        }

        return $response;
    }

    protected function field(string $label): ?string
    {
        return collect($this->lastReply['fields'] ?? [])->firstWhere('label', $label)['value'] ?? null;
    }
}
