<?php

namespace Tests\Feature\AiAgent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Nlu\Intent;
use Tests\Support\AiAgent\AgentTestCase;

/**
 * A patient the clinic does not have yet: the agent offers to add them (with a
 * confirmation card), then carries on with what the user asked for.
 */
class AgentCreatePatientTest extends AgentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->nlu->on('احجز موعد لحسين علي راضي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'حسين علي راضي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);
    }

    private function notFound(): void
    {
        $this->say('احجز موعد لحسين علي راضي باچر الساعة 5')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'ما لقيت مراجع باسم «حسين علي راضي». تريد أضيفه كمراجع جديد؟ اكتب «نعم» أو اضغط الزر، أو اكتب الاسم الصحيح أو رقم الهاتف.')
            ->assertJsonPath('data.reply.options.0.label', '➕ أضف «حسين علي راضي» كمراجع جديد');
    }

    public function test_typing_yes_adds_the_patient_and_the_booking_continues(): void
    {
        $this->notFound();

        // "نعم" only prepares the add-patient card
        $this->say('نعم')->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.text', 'راح أضيف مراجع جديد بهذي التفاصيل:');
        $this->assertSame('حسين علي راضي', $this->field('الاسم'));
        $this->assertContains('تگدر تضيف رقم الهاتف قبل التأكيد، مثلاً: «رقمه 07701234567».', $this->lastReply['notes']);
        $this->assertSame([], $this->api->writes());

        // The second "نعم" adds the patient, and the booking card follows
        $this->say('نعم')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertStringStartsWith('✅ تمت إضافة المراجع.', $this->lastReply['text']);
        $this->assertSame(['name' => 'حسين علي راضي'], $this->api->calls('POST', 'patients')[0]['body']);
        $this->assertStringStartsWith('حسين علي راضي', $this->field('المراجع'));
        $this->assertSame('5:00 مساءً', $this->field('الوقت'));
        $this->assertCount(0, $this->api->calls('POST', 'reservations'));

        $this->say('نعم')->assertJsonPath('data.reply.kind', 'success')
            ->assertJsonPath('data.reply.text', '✅ تم حجز الموعد بنجاح.');
        $this->assertCount(1, $this->api->calls('POST', 'reservations'));
    }

    public function test_the_option_number_works_for_clients_without_buttons(): void
    {
        $this->notFound();
        $this->assertStringContainsString('1. ➕ أضف «حسين علي راضي» كمراجع جديد', $this->lastReply['plain_text']);

        $this->say('1')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('حسين علي راضي', $this->field('الاسم'));
    }

    public function test_saying_add_him_keeps_the_name(): void
    {
        $this->nlu->on('ضيفه كمراجع جديد', Intent::CREATE_PATIENT);
        $this->notFound();

        $this->say('ضيفه كمراجع جديد')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('حسين علي راضي', $this->field('الاسم'));
    }

    public function test_a_phone_number_can_be_added_before_confirming(): void
    {
        $this->nlu->on('رقمه 07701234567', Intent::PROVIDE_INFO, ['phone' => '07701234567']);
        $this->notFound();
        $this->say('نعم');
        $first = $this->lastReply['action']['id'];

        $this->say('رقمه 07701234567')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('07701234567', $this->field('الهاتف'));
        $this->assertSame(AiAgentAction::CANCELLED, AiAgentAction::find($first)->status);

        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame(['name' => 'حسين علي راضي', 'phone' => '07701234567'], $this->api->calls('POST', 'patients')[0]['body']);
    }

    public function test_adding_a_patient_directly_then_booking_for_them(): void
    {
        $this->nlu->on('ضيف مراجع جديد سجاد كريم رقمه 07801234567', Intent::CREATE_PATIENT, ['name' => 'سجاد كريم', 'phone' => '07801234567'])
            ->on('احجزله موعد باچر الساعة 6', Intent::BOOK_APPOINTMENT, ['patient_ref' => 'previous', 'date_text' => 'باچر', 'time_text' => 'الساعة 6']);

        $this->say('ضيف مراجع جديد سجاد كريم رقمه 07801234567')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('سجاد كريم', $this->field('الاسم'));
        $this->assertSame('07801234567', $this->field('الهاتف'));

        $this->say('نعم')->assertJsonPath('data.reply.kind', 'success')
            ->assertJsonPath('data.reply.text', '✅ تمت إضافة المراجع.');

        $this->say('احجزله موعد باچر الساعة 6')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('سجاد كريم (07801234567)', $this->field('المراجع'));
    }

    public function test_similar_names_can_still_be_added_as_someone_new(): void
    {
        $this->api->addPatient('حسين علي', '07700000001');
        $this->api->addPatient('حسين علي راضي الموسوي', '07700000002');
        $this->nlu->on('احجز موعد لحسين علي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'حسين علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجز موعد لحسين علي باچر الساعة 5')->assertJsonCount(3, 'data.reply.options');
        $this->assertSame('➕ أضف «حسين علي» كمراجع جديد', $this->lastReply['options'][2]['label']);

        // With several choices a bare "نعم" picks nothing
        $this->say('نعم')->assertJsonPath('data.reply.text', 'ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟');
        $this->assertSame([], $this->api->writes());
    }

    public function test_no_add_option_without_permission(): void
    {
        $this->api->user['permissions'] = array_values(array_diff($this->api->user['permissions'], ['create-patient']));

        $this->say('احجز موعد لحسين علي راضي باچر الساعة 5')
            ->assertJsonPath('data.reply.text', 'ما لقيت مراجع باسم «حسين علي راضي». تأكد من الاسم أو اكتب رقم الهاتف.')
            ->assertJsonCount(0, 'data.reply.options');

        $this->say('نعم')->assertJsonPath('data.reply.text', 'ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟');
        $this->assertSame([], $this->api->writes());
    }
}
