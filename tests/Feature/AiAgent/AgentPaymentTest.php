<?php

namespace Tests\Feature\AiAgent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Nlu\Intent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AiAgent\AgentTestCase;

/**
 * Recording payments, balances and unpaid invoices, including IQD shorthand
 * and follow-ups that refer to the patient already being discussed.
 */
class AgentPaymentTest extends AgentTestCase
{
    private int $ahmed;
    private int $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahmed = $this->api->addPatient('أحمد علي', '07701111111');
        $this->case = $this->api->addCase($this->ahmed, 150_000, 'تقويم');
    }

    public function test_partial_payment_shows_balance_before_and_after_and_records_only_after_yes(): void
    {
        $this->nlu->on('سجل تسديد 50 ألف لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '50 ألف']);

        $this->say('سجل تسديد 50 ألف لأحمد علي')
            ->assertJsonPath('data.reply.kind', 'confirmation')
            ->assertJsonPath('data.reply.text', 'لقيت حساب أحمد علي.')
            ->assertJsonPath('data.reply.question', 'تأكدلي، أسجل الدفعة؟')
            ->assertJsonPath('data.reply.action.confirm_label', '✅ تأكيد التسديد');
        $this->assertSame('50,000 دينار عراقي', $this->field('المبلغ المطلوب تسجيله'));
        $this->assertSame("#{$this->case} — تقويم (2026-11-01)", $this->field('الفاتورة'));
        $this->assertSame('150,000 دينار', $this->field('المبلغ المتبقي قبل التسديد'));
        $this->assertSame('100,000 دينار', $this->field('المتبقي بعد التسديد'));
        $this->assertSame([], $this->api->writes());

        $this->say('تأكيد')->assertJsonPath('data.reply.kind', 'success')
            ->assertJsonPath('data.reply.text', '✅ تم تسجيل التسديد بنجاح.');
        $this->assertSame('50,000 دينار', $this->field('المبلغ المسجل'));
        $this->assertSame('100,000 دينار', $this->field('المبلغ المتبقي'));
        $this->assertContains('ملاحظة: هذا تسجيل لمبلغ استلمته العيادة، مو تحصيل دفع إلكتروني.', $this->lastReply['notes']);

        $post = $this->api->calls('POST', 'bills');
        $this->assertCount(1, $post);
        $this->assertSame(['patient_id' => $this->ahmed, 'billable_id' => $this->case, 'price' => 50_000, 'billable_type' => 'App\\Models\\Case'], $post[0]['body']);
    }

    public function test_full_payment_of_the_remaining_balance(): void
    {
        $this->api->addBill($this->case, 30_000);
        $this->nlu->on('سدد باقي الحساب لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'pay_remaining' => true]);

        $this->say('سدد باقي الحساب لأحمد علي')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('120,000 دينار عراقي', $this->field('المبلغ المطلوب تسجيله'));
        $this->assertSame('0 دينار', $this->field('المتبقي بعد التسديد'));

        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'success');
        $this->assertSame('0 دينار', $this->field('المبلغ المتبقي'));
    }

    public function test_english_payment(): void
    {
        $this->nlu->on('Ahmed Ali paid fifty thousand', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => 'fifty thousand']);

        $this->say('Ahmed Ali paid fifty thousand')->assertJsonPath('data.reply.language', 'en');
        $this->assertSame('50,000 IQD', $this->field('Amount to record'));
        $this->assertSame('100,000 IQD', $this->field('Remaining after'));
    }

    public function test_amount_is_asked_when_missing(): void
    {
        $this->nlu->on('سدد مبلغ لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي'])
            ->on('خمسين ألف', Intent::PROVIDE_INFO, ['amount_text' => 'خمسين ألف']);

        $this->say('سدد مبلغ لأحمد علي')->assertJsonPath('data.reply.text', 'شكد المبلغ اللي تريد تسجله لـأحمد علي؟');
        $this->say('خمسين ألف')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('50,000 دينار عراقي', $this->field('المبلغ المطلوب تسجيله'));
    }

    public function test_bare_shorthand_amount_is_never_guessed(): void
    {
        $this->nlu->on('أحمد علي دفع 50', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '50']);

        $this->say('أحمد علي دفع 50')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'تقصد 50,000 دينار لو 50 دينار؟');

        $this->choose('50,000')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('50,000 دينار عراقي', $this->field('المبلغ المطلوب تسجيله'));
    }

    #[DataProvider('amounts')]
    public function test_iqd_amount_expressions(string $text, string $expected): void
    {
        $this->nlu->on("سجل {$text} لأحمد علي", Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => $text]);

        $this->say("سجل {$text} لأحمد علي")->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame($expected, $this->field('المبلغ المطلوب تسجيله'));
    }

    public static function amounts(): array
    {
        return [
            '50 ألف' => ['50 ألف', '50,000 دينار عراقي'],
            'خمسين ألف' => ['خمسين ألف', '50,000 دينار عراقي'],
            '50k' => ['50k', '50,000 دينار عراقي'],
            '50,000' => ['50,000', '50,000 دينار عراقي'],
            'Arabic-Indic digits' => ['٥٠ ألف', '50,000 دينار عراقي'],
            'مية وخمسين ألف' => ['مية وخمسين ألف', '150,000 دينار عراقي'],
            'خمسة وعشرين ألف' => ['خمسة وعشرين ألف', '25,000 دينار عراقي'],
            'IQD suffix' => ['75,000 IQD', '75,000 دينار عراقي'],
        ];
    }

    public function test_currency_other_than_the_clinics_is_not_converted(): void
    {
        $this->nlu->on('سجل 50 دولار لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '50 دولار', 'currency' => 'USD']);

        $this->say('سجل 50 دولار لأحمد علي')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'عملة العيادة IQD، والمبالغ تتسجل بيها بس. اكتب المبلغ بعملة العيادة.');
    }

    public function test_overpayment_is_not_recorded_without_correction(): void
    {
        $this->nlu->on('سجل 200 ألف لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '200 ألف']);

        $this->say('سجل 200 ألف لأحمد علي')
            ->assertJsonPath('data.reply.kind', 'clarification')
            ->assertJsonPath('data.reply.text', 'المبلغ (200,000 دينار) أكثر من المتبقي على الفاتورة (150,000 دينار). تريد أسجل المتبقي بس؟');
        $this->choose('150,000')->assertJsonPath('data.reply.kind', 'confirmation');
    }

    public function test_several_unpaid_invoices_are_listed_to_choose_from(): void
    {
        $second = $this->api->addCase($this->ahmed, 80_000, 'حشوة');
        $this->nlu->on('سجل 50 ألف لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '50 ألف']);

        $this->say('سجل 50 ألف لأحمد علي')
            ->assertJsonPath('data.reply.text', 'أحمد علي عنده أكثر من فاتورة غير مسددة. على أي وحدة أسجل الدفعة؟')
            ->assertJsonCount(2, 'data.reply.options');
        $this->say('الثاني')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame("#{$second} — حشوة (2026-11-01)", $this->field('الفاتورة'));
    }

    public function test_balance_changed_after_confirmation_shows_fresh_numbers_instead_of_recording(): void
    {
        $this->nlu->on('سجل 50 ألف لأحمد علي', Intent::RECORD_PAYMENT, ['patient_name' => 'أحمد علي', 'amount_text' => '50 ألف']);
        $this->say('سجل 50 ألف لأحمد علي');
        $old = $this->lastReply['action']['id'];

        $this->api->addBill($this->case, 20_000); // recorded by someone else meanwhile

        $this->pressConfirm()->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertStringStartsWith('الحساب تغير', $this->lastReply['text']);
        $this->assertSame('130,000 دينار', $this->field('المبلغ المتبقي قبل التسديد'));
        $this->assertSame(AiAgentAction::FAILED, AiAgentAction::find($old)->status);
        $this->assertCount(0, $this->api->calls('POST', 'bills'));
    }

    public function test_balance_then_payment_for_the_same_patient_without_naming_them_again(): void
    {
        $this->nlu->on('شكد بذمة أحمد علي؟', Intent::VIEW_BALANCE, ['patient_name' => 'أحمد علي'])
            ->on('سجل تسديد 50 ألف', Intent::RECORD_PAYMENT, ['amount_text' => '50 ألف']);

        $this->say('شكد بذمة أحمد علي؟')
            ->assertJsonPath('data.reply.kind', 'info')
            ->assertJsonPath('data.reply.text', 'حساب أحمد علي:');
        $this->assertSame('150,000 دينار', $this->field('المبلغ المتبقي'));

        $this->say('سجل تسديد 50 ألف')->assertJsonPath('data.reply.kind', 'confirmation');
        $this->assertSame('أحمد علي (07701111111)', $this->field('المراجع'));
        $this->assertSame([], $this->api->writes());
    }

    public function test_secretary_balance_falls_back_to_cases(): void
    {
        $this->api->user['roles'] = ['secretary'];
        $this->api->user['permissions'] = ['view-clinic-patients', 'create-bill', 'view-clinic-reservations'];
        $this->nlu->on('شكد باقي على أحمد علي', Intent::VIEW_BALANCE, ['patient_name' => 'أحمد علي']);

        $this->say('شكد باقي على أحمد علي')->assertJsonPath('data.reply.kind', 'info');
        // A secretary cannot read the currency setting, so no currency is named rather than guessed
        $this->assertSame('150,000', $this->field('المبلغ المتبقي'));
        $this->assertSame([], $this->api->calls('GET', "bills/patient-balances/{$this->ahmed}/bills"));
    }

    public function test_unpaid_invoices_across_the_clinic(): void
    {
        $sara = $this->api->addPatient('سارة محمود', '07705555555');
        $this->api->addCase($sara, 300_000);
        $this->nlu->on('طلعلي الفواتير غير المدفوعة', Intent::VIEW_UNPAID);

        $this->say('طلعلي الفواتير غير المدفوعة')
            ->assertJsonPath('data.reply.kind', 'info')
            ->assertJsonPath('data.reply.text', 'المراجعين اللي عليهم مبالغ:')
            ->assertJsonPath('data.reply.items.0.title', 'سارة محمود')
            ->assertJsonPath('data.reply.items.1.title', 'أحمد علي');
        $this->assertContains('مجموع المبالغ المتبقية: 450,000 دينار', $this->lastReply['notes']);
    }

    public function test_payment_history(): void
    {
        $this->api->addBill($this->case, 30_000);
        $this->nlu->on('Payment history for Ahmed Ali', Intent::PAYMENT_HISTORY, ['patient_name' => 'أحمد علي']);

        $this->say('Payment history for Ahmed Ali')
            ->assertJsonPath('data.reply.text', "أحمد علي's payments:")
            ->assertJsonPath('data.reply.items.0.title', '30,000 IQD');
    }

    public function test_pay_now_is_not_offered_as_collection(): void
    {
        $this->nlu->on('ادفع الفاتورة بالبطاقة هسه', Intent::PAY_NOW, ['patient_name' => 'أحمد علي']);

        $this->say('ادفع الفاتورة بالبطاقة هسه')
            ->assertJsonPath('data.reply.kind', 'info')
            ->assertJsonPath('data.reply.options.0.label', '✅ تأكيد التسديد');
        $this->assertStringContainsString('ما بيه بوابة دفع', $this->lastReply['text']);
        $this->assertSame([], $this->api->writes());
    }
}
