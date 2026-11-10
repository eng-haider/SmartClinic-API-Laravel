<?php

namespace Tests\Feature\AiAgent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Nlu\Intent;
use Carbon\Carbon;
use Tests\Support\AiAgent\AgentTestCase;

/**
 * One chatbot, one endpoint: POST /ai/chat answers questions and prepares
 * actions, for the new chat UIs and for clients written for the old
 * question-only chatbot.
 */
class ChatbotEndpointTest extends AgentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->api->addPatient('أحمد علي', '07701111111');
        $this->nlu->on('احجز لأحمد علي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);
    }

    public function test_an_older_client_sending_question_only_can_book_by_typing_yes(): void
    {
        // The released phone app: {question} in, data.answer out, no conversation id
        $card = $this->postJson('/api/tenant/ai/chat', ['question' => 'احجز لأحمد علي باچر الساعة 5'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.question', 'احجز لأحمد علي باچر الساعة 5')
            ->json('data.answer');

        $this->assertStringContainsString('تمام، راح أحجز الموعد بالتفاصيل التالية:', $card);
        $this->assertStringContainsString('الوقت: 5:00 مساءً', $card);
        $this->assertStringContainsString('(اكتب «نعم» للتأكيد أو «لا» للإلغاء)', $card);
        $this->assertSame([], $this->api->writes());

        $done = $this->postJson('/api/tenant/ai/chat', ['question' => 'نعم'])->assertOk()->json('data.answer');

        $this->assertStringStartsWith('✅ تم حجز الموعد بنجاح.', $done);
        $this->assertCount(1, $this->api->calls('POST', 'reservations'));
    }

    public function test_a_new_conversation_never_confirms_an_earlier_card(): void
    {
        $this->postJson('/api/tenant/ai/chat', ['message' => 'احجز لأحمد علي باچر الساعة 5']);

        $this->postJson('/api/tenant/ai/chat', ['message' => 'نعم', 'new_conversation' => true])
            ->assertJsonPath('data.reply.text', 'ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟');
        $this->assertSame([], $this->api->writes());
    }

    public function test_an_old_conversation_is_not_continued_after_the_context_time(): void
    {
        $this->postJson('/api/tenant/ai/chat', ['question' => 'احجز لأحمد علي باچر الساعة 5']);
        $this->freeze(Carbon::now()->addMinutes(31));

        $this->postJson('/api/tenant/ai/chat', ['question' => 'نعم'])
            ->assertJsonPath('data.reply.text', 'ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟');
        $this->assertSame([], $this->api->writes());
        $this->assertSame(AiAgentAction::PENDING, AiAgentAction::sole()->status);
    }

    public function test_another_users_recent_conversation_is_never_continued(): void
    {
        $this->postJson('/api/tenant/ai/chat', ['question' => 'احجز لأحمد علي باچر الساعة 5']);

        $this->actingAsUser(8);
        $this->postJson('/api/tenant/ai/chat', ['question' => 'نعم'])
            ->assertJsonPath('data.reply.text', 'ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟');
        $this->assertSame([], $this->api->writes());
    }

    public function test_answer_only_questions_skip_actions(): void
    {
        // The patient analysis drawer: a one-shot prompt, answered like before
        $this->postJson('/api/tenant/ai/chat', [
            'question' => 'احجز لأحمد علي باچر الساعة 5',
            'answer_only' => true,
        ])->assertOk()
            ->assertJsonPath('data.answer', 'general answer')
            ->assertJsonMissingPath('data.reply')
            ->assertJsonMissingPath('data.conversation_id');

        $this->assertSame([], $this->nlu->calls);
        $this->assertSame(0, AiAgentAction::count());
        $this->assertSame([], $this->api->requests);
    }

    public function test_new_clients_get_the_structured_reply_and_the_legacy_text(): void
    {
        $this->say('احجز لأحمد علي باچر الساعة 5')
            ->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.action.confirm_label', '✅ تأكيد الحجز')
            ->assertJsonPath('data.answer', $this->lastReply['plain_text']);
    }

    public function test_there_is_no_separate_agent_endpoint(): void
    {
        $this->postJson('/api/tenant/ai/agent/messages', ['message' => 'احجز لأحمد علي باچر الساعة 5'])->assertNotFound();
        $this->assertSame([], $this->api->writes());
    }

    public function test_a_message_is_required(): void
    {
        $this->postJson('/api/tenant/ai/chat', [])->assertUnprocessable();
    }
}
