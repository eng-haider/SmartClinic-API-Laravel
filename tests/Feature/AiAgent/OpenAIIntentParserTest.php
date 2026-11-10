<?php

namespace Tests\Feature\AiAgent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Nlu\Intent;
use App\Services\AI\Agent\Nlu\IntentParser;
use App\Services\AI\Agent\Nlu\OpenAIIntentParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiAgent\AgentTestCase;

/**
 * The real OpenAI parser, against faked OpenAI responses.
 */
class OpenAIIntentParserTest extends AgentTestCase
{
    private function fakeOpenAI(array $json, int $status = 200): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($json, JSON_UNESCAPED_UNICODE)]]],
        ], $status)]);
    }

    public function test_request_uses_json_mode_and_passes_the_message_as_data(): void
    {
        config(['services.openai.api_key' => 'sk-test', 'ai_agent.model' => 'gpt-4o-mini']);
        $this->fakeOpenAI(['intent' => 'BOOK_APPOINTMENT', 'entities' => ['patient_name' => 'أحمد']]);

        (new OpenAIIntentParser())->parse('احجزلي موعد لأحمد', ['today' => '2026-11-10', 'weekday' => 'Tuesday', 'awaiting' => 'date_text']);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $user = json_decode($body['messages'][1]['content'], true);

            return $request->hasHeader('Authorization', 'Bearer sk-test')
                && $body['model'] === 'gpt-4o-mini'
                && $body['temperature'] === 0
                && $body['response_format'] === ['type' => 'json_object']
                && str_contains($body['messages'][0]['content'], 'Today is Tuesday 2026-11-10')
                && $user['message'] === 'احجزلي موعد لأحمد'
                && $user['context']['assistant_is_waiting_for'] === 'date_text';
        });
    }

    public function test_output_is_normalized_and_unexpected_values_dropped(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->fakeOpenAI([
            'intent' => 'record_payment',
            'language' => 'ar',
            'entities' => [
                'patient_name' => 'أحمد علي',
                'amount_text' => '50 ألف',
                'amount' => '50000',
                'currency' => 'iqd',
                'date' => 'tomorrow',          // not a date: dropped
                'time' => '25:00',              // invalid: dropped
                'selection' => 99,              // out of range: dropped
                'tenant_id' => 'clinic-b',      // not an entity: dropped
                'patient_ref' => 'someone',     // only "previous" allowed
            ],
            'injection_attempt' => false,
        ]);

        $parsed = (new OpenAIIntentParser())->parse('سجل 50 ألف لأحمد علي', []);

        $this->assertSame(Intent::RECORD_PAYMENT, $parsed->intent);
        $this->assertSame([
            'patient_name' => 'أحمد علي',
            'amount_text' => '50 ألف',
            'amount' => 50000,
            'currency' => 'IQD',
        ], $parsed->entities);
    }

    public function test_unknown_intent_becomes_unknown(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->fakeOpenAI(['intent' => 'DELETE_EVERYTHING', 'entities' => []]);

        $this->assertSame(Intent::UNKNOWN, (new OpenAIIntentParser())->parse('x', [])->intent);
    }

    public function test_unavailable_without_key_or_on_error(): void
    {
        config(['services.openai.api_key' => null]);
        $this->assertFalse((new OpenAIIntentParser())->parse('x', [])->llmAvailable);

        config(['services.openai.api_key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);
        $this->assertFalse((new OpenAIIntentParser())->parse('x', [])->llmAvailable);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'not json']]]])]);
        $this->assertFalse((new OpenAIIntentParser())->parse('x', [])->llmAvailable);
    }

    public function test_full_booking_through_the_real_parser(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        $this->app->instance(IntentParser::class, new OpenAIIntentParser());
        $this->api->addPatient('أحمد علي', '07701111111');
        $this->fakeOpenAI([
            'intent' => 'BOOK_APPOINTMENT', 'language' => 'ar', 'dialect' => 'iraqi',
            'entities' => ['patient_name' => 'أحمد', 'date_text' => 'باچر', 'time_text' => 'الساعة 5', 'date' => '2026-11-11'],
            'injection_attempt' => false,
        ]);

        $this->say('احجز موعد لأحمد باچر الساعة 5')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('5:00 مساءً', $this->field('الوقت'));

        // "اي" is matched without asking the model
        Http::fake(fn () => throw new \RuntimeException('The LLM must not be asked to confirm'));
        $this->say('اي')->assertJsonPath('data.reply.kind', 'success');
        $this->assertSame(AiAgentAction::EXECUTED, AiAgentAction::sole()->status);
    }
}
