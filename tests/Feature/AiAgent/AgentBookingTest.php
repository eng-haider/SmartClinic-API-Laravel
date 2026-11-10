<?php

namespace Tests\Feature\AiAgent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Nlu\Intent;
use Carbon\Carbon;
use Tests\Support\AiAgent\AgentTestCase;

/**
 * Booking, rescheduling and cancelling appointments in Arabic, Iraqi dialect,
 * English and mixed messages. "Today" is Tuesday 10/11/2026, 10:00 Baghdad.
 */
class AgentBookingTest extends AgentTestCase
{
    private int $ahmed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahmed = $this->api->addPatient('أحمد علي', '07701111111');
        $this->api->addPatient('Ahmed Kareem', '07702222222');
    }

    public function test_iraqi_dialect_booking_waits_for_confirmation_then_books_through_the_api(): void
    {
        $this->nlu->on('احجزلي موعد لاحمد علي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'احمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجزلي موعد لاحمد علي باچر الساعة 5')->assertOk()
            ->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.dir', 'rtl')
            ->assertJsonPath('data.reply.text', 'تمام، راح أحجز الموعد بالتفاصيل التالية:')
            ->assertJsonPath('data.reply.question', 'تأكدلي، أنفذ الحجز؟')
            ->assertJsonPath('data.reply.action.confirm_label', '✅ تأكيد الحجز');

        // "احمد" without hamza still finds "أحمد علي"; 5 o'clock means 5 PM within 09:00-21:00
        $this->assertSame('أحمد علي (07701111111)', $this->field('المراجع'));
        $this->assertSame('11/11/2026 (الأربعاء)', $this->field('التاريخ'));
        $this->assertSame('5:00 مساءً', $this->field('الوقت'));
        $this->assertSame('د. محمد حسن', $this->field('الطبيب'));
        $this->assertSame([], $this->api->writes(), 'Nothing may change before confirmation');
        $this->assertStringContainsString('«نعم»', $this->lastReply['plain_text']);

        $this->say('اي')->assertOk()
            ->assertJsonPath('data.reply.kind', 'success')
            ->assertJsonPath('data.reply.text', '✅ تم حجز الموعد بنجاح.');

        $post = $this->api->calls('POST', 'reservations');
        $this->assertCount(1, $post);
        $this->assertSame([
            'patient_id' => $this->ahmed,
            'doctor_id' => 3,
            'reservation_start_date' => '2026-11-11',
            'reservation_end_date' => '2026-11-11',
            'reservation_from_time' => '17:00:00',
            'reservation_to_time' => '17:30:00',
        ], $post[0]['body']);
        $this->assertNotEmpty($post[0]['headers']['Idempotency-Key']);
        $this->assertMatchesRegularExpression('/^#\d+$/', $this->field('رقم الحجز'));
        $this->assertSame(AiAgentAction::EXECUTED, AiAgentAction::sole()->status);
    }

    public function test_modern_standard_arabic_booking_with_hour_in_words(): void
    {
        $this->nlu->on('أريد حجز موعد للمريض أحمد علي يوم الخميس الساعة الخامسة مساءً', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'يوم الخميس', 'time_text' => 'الساعة الخامسة مساءً',
        ]);

        $this->say('أريد حجز موعد للمريض أحمد علي يوم الخميس الساعة الخامسة مساءً')
            ->assertJsonPath('data.reply.kind', 'confirmation');

        $this->assertSame('12/11/2026 (الخميس)', $this->field('التاريخ'));
        $this->assertSame('5:00 مساءً', $this->field('الوقت'));
    }

    public function test_english_booking_confirmed_with_the_button(): void
    {
        $this->nlu->on('Book Ahmed Kareem for tomorrow at 5 PM', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'Ahmed Kareem', 'date_text' => 'tomorrow', 'time_text' => 'at 5 PM', 'time' => '17:00',
        ]);

        $this->say('Book Ahmed Kareem for tomorrow at 5 PM')
            ->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.language', 'en')
            ->assertJsonPath('data.reply.dir', 'ltr')
            ->assertJsonPath('data.reply.action.confirm_label', '✅ Confirm booking');
        $this->assertSame('11/11/2026 (Wednesday)', $this->field('Date'));
        $this->assertSame('5:00 PM', $this->field('Time'));

        $this->pressConfirm()->assertOk()
            ->assertJsonPath('data.reply.kind', 'success')
            ->assertJsonPath('data.reply.text', '✅ The appointment was booked.')
            ->assertJsonPath('data.action.status', 'executed');
        $this->assertCount(1, $this->api->calls('POST', 'reservations'));
    }

    public function test_mixed_arabic_and_english_booking(): void
    {
        $this->nlu->on('احجز موعد لـ Ahmed Kareem tomorrow الساعة 6 العصر', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'Ahmed Kareem', 'date_text' => 'tomorrow', 'time_text' => 'الساعة 6 العصر',
        ]);

        $this->say('احجز موعد لـ Ahmed Kareem tomorrow الساعة 6 العصر')
            ->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('Ahmed Kareem (07702222222)', $this->field('المراجع'));
        $this->assertSame('6:00 مساءً', $this->field('الوقت'));
    }

    public function test_missing_details_are_asked_one_by_one_without_repeating(): void
    {
        $this->nlu->on('أريد أحجز موعد', Intent::BOOK_APPOINTMENT)
            ->on('أحمد علي', Intent::PROVIDE_INFO, ['patient_name' => 'أحمد علي'])
            ->on('باچر', Intent::PROVIDE_INFO, ['date_text' => 'باچر'])
            ->on('الساعة 5', Intent::PROVIDE_INFO, ['time_text' => 'الساعة 5']);

        $this->say('أريد أحجز موعد')->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'باسم أي مراجع؟');
        $this->say('أحمد علي')->assertJsonPath('data.reply.text', 'أي يوم تحب الموعد؟');
        $this->say('باچر')->assertJsonPath('data.reply.kind', 'clarification');
        $this->assertStringStartsWith('شنو الوقت المناسب؟', $this->lastReply['text']);
        $this->assertNotEmpty($this->lastReply['options'], 'Free times are offered');

        $this->say('الساعة 5')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('أحمد علي (07701111111)', $this->field('المراجع'));
        $this->assertSame('11/11/2026 (الأربعاء)', $this->field('التاريخ'));
        $this->assertSame([], $this->api->writes());
    }

    /** The production conversation, with what the real model returned for each message. */
    public function test_booking_for_a_full_name_then_a_typed_date(): void
    {
        $this->api->addPatient('حسين علي راضي', '07709999999');
        $this->nlu->on('حجز موعد جديد للمراجع حسين علي راضي', Intent::BOOK_APPOINTMENT, ['patient_name' => 'حسين علي راضي'])
            ->on('20-11-2026', Intent::PROVIDE_INFO, ['patient_name' => 'حسين علي راضي', 'patient_ref' => 'previous', 'date_text' => '20-11-2026', 'date' => '2026-11-20'])
            ->on('19-11-2026', Intent::PROVIDE_INFO, ['date_text' => '19-11-2026', 'date' => '2026-11-19']);

        $this->say('حجز موعد جديد للمراجع حسين علي راضي')->assertJsonPath('data.reply.text', 'أي يوم تحب الموعد؟');

        // 20/11/2026 is a Friday, when this clinic is closed: say so and offer open days
        $this->say('20-11-2026')->assertJsonPath('data.reply.text', 'العيادة مسدودة يوم 20/11/2026 (الجمعة).');
        $this->assertNotEmpty($this->lastReply['options']);

        $this->say('19-11-2026')->assertJsonPath('data.reply.kind', 'clarification');
        $this->assertStringStartsWith('شنو الوقت المناسب؟ هاي الأوقات الفارغة يوم 19/11/2026 (الخميس):', $this->lastReply['text']);
        $this->assertSame([], $this->api->writes());
    }

    public function test_free_time_offered_as_an_option_can_be_clicked(): void
    {
        $this->nlu->on('احجز لأحمد علي باچر', Intent::BOOK_APPOINTMENT, ['patient_name' => 'أحمد علي', 'date_text' => 'باچر']);

        $this->say('احجز لأحمد علي باچر');
        $this->choose('9:30');
        $this->assertSame('confirmation', $this->lastReply['kind']);
        $this->assertSame('9:30 صباحاً', $this->field('الوقت'));
    }

    public function test_ambiguous_patient_name_lists_the_matches_and_never_picks_one(): void
    {
        $this->api->addPatient('محمد جاسم', '07703333333');
        $second = $this->api->addPatient('محمد كاظم', '07704444444');
        $this->nlu->on('احجز لمحمد باچر الساعة 6 العصر', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'محمد', 'date_text' => 'باچر', 'time_text' => 'الساعة 6 العصر',
        ]);

        $this->say('احجز لمحمد باچر الساعة 6 العصر')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'لقيت أكثر من مراجع باسم «محمد». أي واحد تقصد؟')
            ->assertJsonCount(3, 'data.reply.options')
            ->assertJsonPath('data.reply.options.2.label', '➕ أضف «محمد» كمراجع جديد');

        $this->say('2')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('محمد كاظم (07704444444)', $this->field('المراجع'));
        $this->pressConfirm();
        $this->assertSame($second, $this->api->calls('POST', 'reservations')[0]['body']['patient_id']);
    }

    public function test_unknown_patient_can_be_added_and_the_booking_resumes(): void
    {
        $this->nlu->on('احجز لزينب باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'زينب', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجز لزينب باچر الساعة 5')
            ->assertJsonPath('data.reply.text', 'ما لقيت مراجع باسم «زينب». تريد أضيفه كمراجع جديد؟ اكتب «نعم» أو اضغط الزر، أو اكتب الاسم الصحيح أو رقم الهاتف.');

        $this->choose('زينب')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('زينب', $this->field('الاسم'));
        $this->assertSame([], $this->api->writes());

        // Adding the patient brings back the booking, which needs its own confirmation
        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertStringStartsWith('✅ تمت إضافة المراجع.', $this->lastReply['text']);
        $this->assertCount(1, $this->api->calls('POST', 'patients'));
        $this->assertCount(0, $this->api->calls('POST', 'reservations'));
        $this->assertSame('11/11/2026 (الأربعاء)', $this->field('التاريخ'));

        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'success');
        $this->assertCount(1, $this->api->calls('POST', 'reservations'));
    }

    public function test_taken_time_is_refused_with_alternatives(): void
    {
        $other = $this->api->addPatient('سارة', null);
        $this->api->addReservation($other, '2026-11-11', '17:00');
        $this->nlu->on('احجز لأحمد علي باچر الساعة 5 العصر', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5 العصر',
        ]);

        $this->say('احجز لأحمد علي باچر الساعة 5 العصر')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'هذا الموعد (5:00 مساءً) صار محجوز أو مو متاح، تحب أشوفلك وقت ثاني؟');
        $this->assertContains('4:30 مساءً', array_column($this->lastReply['options'], 'label'));
    }

    public function test_slot_taken_between_confirmation_and_execution_is_not_booked(): void
    {
        $this->nlu->on('احجز لأحمد علي باچر الساعة 5 العصر', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5 العصر',
        ]);
        $this->say('احجز لأحمد علي باچر الساعة 5 العصر')->assertJsonPath('data.reply.kind', 'confirmation');

        $this->api->addReservation($this->api->addPatient('غيره', null), '2026-11-11', '17:00');

        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'error')
            ->assertJsonPath('data.action.status', 'failed');
        $this->assertCount(0, $this->api->calls('POST', 'reservations'));
        $this->assertNotEmpty($this->lastReply['options']);
    }

    public function test_closed_day_offers_open_days(): void
    {
        $this->nlu->on('احجز لأحمد علي الجمعة الجاية الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'الجمعة الجاية', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجز لأحمد علي الجمعة الجاية الساعة 5')
            ->assertJsonPath('data.reply.text', 'العيادة مسدودة يوم 13/11/2026 (الجمعة).');
        $this->assertNotEmpty($this->lastReply['options']);
    }

    public function test_dates_are_resolved_in_the_clinic_timezone(): void
    {
        // 21:30 UTC on the 10th is already 00:30 on the 11th in Baghdad
        $this->freeze(Carbon::create(2026, 11, 10, 21, 30, 0, 'UTC'));
        $this->nlu->on('احجز لأحمد علي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجز لأحمد علي باچر الساعة 5')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('12/11/2026 (الخميس)', $this->field('التاريخ'));
    }

    public function test_past_date_is_refused(): void
    {
        $this->nlu->on('احجز لأحمد علي 1/11/2026 الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => '1/11/2026', 'time_text' => 'الساعة 5',
        ]);

        $this->say('احجز لأحمد علي 1/11/2026 الساعة 5')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'هذا التاريخ (01/11/2026 (الأحد)) فات. اختار يوم من اليوم وطالع.');
    }

    public function test_changing_a_detail_regenerates_the_confirmation(): void
    {
        $this->nlu->on('احجز لأحمد علي باچر الساعة 5', Intent::BOOK_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'date_text' => 'باچر', 'time_text' => 'الساعة 5',
        ])->on('لا خليه الساعة 6', Intent::PROVIDE_INFO, ['time_text' => 'الساعة 6']);

        $this->say('احجز لأحمد علي باچر الساعة 5');
        $first = $this->lastReply['action']['id'];

        $this->say('لا خليه الساعة 6')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('6:00 مساءً', $this->field('الوقت'));
        $this->assertNotSame($first, $this->lastReply['action']['id']);
        $this->assertSame(AiAgentAction::CANCELLED, AiAgentAction::find($first)->status);

        // The old card can no longer be used
        $this->pressConfirm($first)->assertJsonPath('data.reply.kind', 'error');
        $this->assertCount(0, $this->api->calls('POST', 'reservations'));
    }

    public function test_cancel_appointment_after_confirmation(): void
    {
        $id = $this->api->addReservation($this->ahmed, '2026-11-11', '17:00');
        $this->nlu->on('شيل موعد أحمد علي باچر', Intent::CANCEL_APPOINTMENT, ['patient_name' => 'أحمد علي', 'date_text' => 'باچر']);

        $this->say('شيل موعد أحمد علي باچر')
            ->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.text', 'راح ألغي هذا الموعد:');
        $this->assertSame('5:00 مساءً', $this->field('الوقت'));
        $this->assertSame([], $this->api->writes());

        $this->say('نعم')->assertJsonPath('data.reply.text', '✅ تم إلغاء الموعد.');
        $patch = $this->api->calls('PATCH', "reservations/{$id}/status");
        $this->assertCount(1, $patch);
        $this->assertSame(['status_id' => 4], $patch[0]['body']);
    }

    public function test_cancel_by_time_only_lists_the_matching_appointments(): void
    {
        $sara = $this->api->addPatient('سارة', null);
        $this->api->addReservation($this->ahmed, '2026-11-11', '17:00');
        $this->api->addReservation($sara, '2026-11-12', '17:00');
        $this->nlu->on('الغي موعد الساعة 5', Intent::CANCEL_APPOINTMENT, ['time_text' => 'الساعة 5']);

        $this->say('الغي موعد الساعة 5')
            ->assertJsonPath('data.reply.text', 'أي موعد تقصد؟')
            ->assertJsonCount(2, 'data.reply.options');
        $this->choose('سارة')->assertJsonPath('data.reply.kind', 'confirmation');
    }

    public function test_reschedule_keeps_the_status_and_moves_the_time(): void
    {
        $id = $this->api->addReservation($this->ahmed, '2026-11-11', '17:00', 2);
        $this->nlu->on('أجل موعد أحمد علي لعقب باچر', Intent::RESCHEDULE_APPOINTMENT, [
            'patient_name' => 'أحمد علي', 'new_date_text' => 'عقب باچر',
        ]);

        $this->say('أجل موعد أحمد علي لعقب باچر')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('11/11/2026 (الأربعاء) — 5:00 مساءً', $this->field('الموعد الحالي'));
        $this->assertSame('12/11/2026 (الخميس) — 5:00 مساءً', $this->field('الموعد الجديد'));

        $this->pressConfirm()->assertJsonPath('data.reply.text', '✅ تم تغيير الموعد.');
        $put = $this->api->calls('PUT', "reservations/{$id}")[0]['body'];
        $this->assertSame(2, $put['status_id'], 'The current status is sent so PUT does not reset it');
        $this->assertSame('2026-11-12', $put['reservation_start_date']);
        $this->assertSame('17:00:00', $put['reservation_from_time']);
    }

    public function test_reschedule_asks_for_the_new_time_when_missing(): void
    {
        $this->api->addReservation($this->ahmed, '2026-11-11', '17:00');
        $this->nlu->on('غير موعد أحمد علي', Intent::RESCHEDULE_APPOINTMENT, ['patient_name' => 'أحمد علي'])
            ->on('خليه الساعة 7', Intent::PROVIDE_INFO, ['time_text' => 'الساعة 7']);

        $this->say('غير موعد أحمد علي')->assertJsonPath('data.reply.text', 'لأي يوم أو وقت تريد تنقل الموعد؟');
        $this->say('خليه الساعة 7')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('11/11/2026 (الأربعاء) — 7:00 مساءً', $this->field('الموعد الجديد'));
    }

    public function test_free_times_are_listed_and_clicking_one_starts_a_booking(): void
    {
        $this->nlu->on('عندك وقت فارغ باچر العصر؟', Intent::CHECK_AVAILABILITY, ['date_text' => 'باچر', 'time_text' => 'العصر']);

        $this->say('عندك وقت فارغ باچر العصر؟')
            ->assertJsonPath('data.reply.kind', 'info')
            ->assertJsonPath('data.reply.text', 'الأوقات الفارغة يوم 11/11/2026 (الأربعاء):');
        $this->assertStringContainsString('12:00 مساءً', $this->lastReply['items'][0]['title']);
        $this->assertStringNotContainsString('صباحاً', $this->lastReply['items'][0]['title']);

        $this->choose('3:00 مساءً')->assertJsonPath('data.reply.text', 'باسم أي مراجع؟');
        $this->assertSame([], $this->api->writes());
    }

    public function test_view_todays_appointments(): void
    {
        $this->api->addReservation($this->ahmed, '2026-11-10', '18:00');
        $this->nlu->on('شنو مواعيد اليوم', Intent::VIEW_APPOINTMENTS, ['period' => 'today']);

        $this->say('شنو مواعيد اليوم')
            ->assertJsonPath('data.reply.kind', 'info')
            ->assertJsonPath('data.reply.text', 'المواعيد (اليوم):')
            ->assertJsonPath('data.reply.items.0.title', '6:00 مساءً — أحمد علي');
    }
}
