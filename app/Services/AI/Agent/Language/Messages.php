<?php

namespace App\Services\AI\Agent\Language;

use Carbon\CarbonImmutable;

/**
 * Every sentence the agent says in an action flow, in friendly Iraqi Arabic and
 * English. Replies are built from these templates and API data, so amounts,
 * dates and names are never written by the LLM.
 */
class Messages
{
    private const TEXT = [
        // General
        'greeting' => ['هلا بيك! أكدر أساعدك بهذي الأمور:', 'Hi! Here is what I can do for you:'],
        'llm_unavailable' => ['المساعد مو متاح هسه، جرب بعد شوية.', 'The assistant is unavailable right now, please try again shortly.'],
        'not_understood' => ['ما فهمت طلبك بالضبط. تگدر توضحه أكثر؟', "I didn't quite get that. Could you rephrase it?"],
        'unsupported' => ['هذي العملية مو مدعومة بالنظام حالياً.', "That operation isn't supported by the system."],
        'pay_now_unsupported' => [
            'النظام ما بيه بوابة دفع إلكتروني، فما أكدر أستلم أو أحصّل فلوس. أكدر بس أسجل دفعة استلمتها العيادة فعلاً. تريد أسجل دفعة؟',
            "The system has no payment gateway, so I can't collect money. I can only record a payment the clinic has already received. Want me to record one?",
        ],
        'no_permission' => ['ما عندك صلاحية {operation}.', "You don't have permission to {operation}."],
        'no_pending' => ['ما كو عملية بانتظار تأكيدك هسه. شنو تحب أسوي؟', "There's nothing waiting for your confirmation right now. What would you like to do?"],
        'ambiguous_confirmation' => [
            'ما تأكدت من جوابك. اضغط ✅ للتأكيد أو ❌ للإلغاء، أو اكتب «نعم» أو «لا» بس.',
            "I'm not sure what you meant. Press ✅ to confirm or ❌ to cancel, or reply just “yes” or “no”.",
        ],
        'confirm_hint' => ['(اكتب «نعم» للتأكيد أو «لا» للإلغاء)', '(Reply “yes” to confirm or “no” to cancel)'],
        'action_cancelled' => ['تمام، لغيت العملية وما تغير شي.', 'OK, I cancelled it. Nothing was changed.'],
        'action_superseded' => ['لغيت العملية السابقة اللي كانت بانتظار التأكيد.', 'I cancelled the previous action that was awaiting confirmation.'],
        'action_expired' => ['انتهت مدة التأكيد وما تنفذت العملية. تريد أجهزها من جديد؟', 'This confirmation expired and nothing was done. Want me to prepare it again?'],
        'action_already_executed' => ['هذي العملية تنفذت قبل، وما راح أكررها.', "This was already done, and I won't repeat it."],
        'action_in_progress' => ['العملية قيد التنفيذ هسه، لحظة وترجع النتيجة.', 'That action is being processed right now.'],
        'action_already_closed' => ['هذي العملية مو بانتظار التأكيد ({status}).', 'This action is no longer awaiting confirmation ({status}).'],
        'action_not_found' => ['ما لقيت هذي العملية.', "I couldn't find that action."],
        'action_unknown' => [
            'ما وصلني رد واضح من النظام. فحصت وما لقيت العملية تنفذت، فما تكررت. تگدر تطلبها مرة ثانية.',
            "I didn't get a clear answer from the system. I checked and it was not carried out, so nothing was duplicated. You can ask again.",
        ],
        'action_unknown_unverified' => [
            'ما وصلني رد من النظام وما گدرت أتأكد إذا تنفذت. تأكد من الشاشة قبل لا تعيد الطلب حتى ما يتكرر.',
            "I got no answer from the system and couldn't verify whether it went through. Please check the screen before asking again so it isn't duplicated.",
        ],
        'api_unavailable' => ['النظام ما يستجيب هسه، وما تنفذ شي. جرب بعد شوية.', "The system isn't responding right now, and nothing was done. Please try again shortly."],
        'api_error' => ['النظام رجع خطأ: {message}', 'The system returned an error: {message}'],
        'validation_error' => ['النظام رفض الطلب: {message}', 'The system rejected the request: {message}'],
        'forbidden' => ['النظام رفض الطلب لأن ما عندك صلاحية.', 'The system refused because you lack permission.'],
        'refresh_option' => ['🔄 جهز التأكيد من جديد', '🔄 Prepare it again'],
        'not_ready' => [
            'المساعد ما گدر يجهز نفسه لهذي العيادة، فما أكدر أنفذ حجوزات أو دفعات هسه. تواصل مع الدعم الفني حتى يشغلون تحديث قاعدة البيانات (tenants:migrate).',
            "The assistant couldn't set itself up for this clinic, so it can't book or record payments yet. Please contact support to run the database update (tenants:migrate).",
        ],
        'permissions_unavailable' => ['ما گدرت أتحقق من صلاحياتك هسه، فما راح أنفذ شي.', "I couldn't verify your permissions, so I won't do anything."],
        'injection_refused' => [
            'ما أكدر أتجاوز خطوة التأكيد أو أغير الصلاحيات. كل عملية تحتاج تأكيدك بالزر أو بكلمة «نعم».',
            "I can't skip confirmation or change permissions. Every action needs your confirmation with the button or a plain “yes”.",
        ],

        // Patients
        'ask_patient' => ['باسم أي مراجع؟', 'Which patient?'],
        'patient_not_found' => ['ما لقيت مراجع باسم «{name}». تأكد من الاسم أو اكتب رقم الهاتف.', "I couldn't find a patient named “{name}”. Check the name or send their phone number."],
        'patient_not_found_can_add' => [
            'ما لقيت مراجع باسم «{name}». تريد أضيفه كمراجع جديد؟ اكتب «نعم» أو اضغط الزر، أو اكتب الاسم الصحيح أو رقم الهاتف.',
            "I couldn't find a patient named “{name}”. Add them as a new patient? Reply “yes” or tap the button, or send the correct name or phone number.",
        ],
        'add_phone_hint' => [
            'تگدر تضيف رقم الهاتف قبل التأكيد، مثلاً: «رقمه 07701234567».',
            'You can add a phone number before confirming, e.g. “phone 07701234567”.',
        ],
        'patient_create_option' => ['➕ أضف «{name}» كمراجع جديد', '➕ Add “{name}” as a new patient'],
        'choose_patient' => ['لقيت أكثر من مراجع باسم «{name}». أي واحد تقصد؟', 'I found more than one patient matching “{name}”. Which one?'],
        'patients_found' => ['لقيت {count} مراجع:', 'Found {count} patient(s):'],
        'ask_patient_name' => ['شنو اسم المراجع الجديد؟', "What is the new patient's name?"],
        'confirm_create_patient_title' => ['راح أضيف مراجع جديد بهذي التفاصيل:', "I'll add a new patient with these details:"],
        'confirm_create_patient_question' => ['تأكدلي، أضيفه؟', 'Shall I add them?'],
        'confirm_create_patient_btn' => ['✅ تأكيد الإضافة', '✅ Confirm'],
        'patient_created' => ['✅ تمت إضافة المراجع.', '✅ The patient was added.'],
        'duplicate_patient_warning' => ['تنبيه: موجود مراجع بنفس الاسم ({names}).', 'Note: a patient with this name already exists ({names}).'],

        // Booking
        'ask_date' => ['أي يوم تحب الموعد؟', 'Which day would you like?'],
        'ask_time' => ['شنو الوقت المناسب؟', 'What time works?'],
        'ask_time_free' => ['شنو الوقت المناسب؟ هاي الأوقات الفارغة يوم {date}:', 'What time works? Free times on {date}:'],
        'ask_meridiem' => ['تقصد {a} لو {b}؟', 'Do you mean {a} or {b}?'],
        'choose_same_weekday' => ['تقصد اليوم ({a}) لو الأسبوع الجاي ({b})؟', 'Do you mean today ({a}) or next week ({b})?'],
        'ask_day_of_week' => ['أي يوم بالضبط؟', 'Which day exactly?'],
        'date_past' => ['هذا التاريخ ({date}) فات. اختار يوم من اليوم وطالع.', 'That date ({date}) has passed. Please pick today or later.'],
        'date_unclear' => ['ما گدرت أحدد التاريخ من «{text}». اكتب اليوم أو التاريخ (مثلاً باچر أو 15/11).', "I couldn't work out the date from “{text}”. Please give a day or date (e.g. tomorrow or 15/11)."],
        'time_unclear' => ['ما گدرت أحدد الوقت من «{text}». اكتب الساعة (مثلاً 5 العصر أو 17:00).', "I couldn't work out the time from “{text}”. Please give a time (e.g. 5 PM or 17:00)."],
        'clinic_closed' => ['العيادة مسدودة يوم {date}.', 'The clinic is closed on {date}.'],
        'no_free_times' => ['ما كو أوقات فارغة يوم {date}.', 'There are no free times on {date}.'],
        'slot_taken' => ['هذا الموعد ({time}) صار محجوز أو مو متاح، تحب أشوفلك وقت ثاني؟', 'That time ({time}) is taken or unavailable. Want another time?'],
        'nearest_times' => ['أقرب الأوقات الفارغة:', 'Nearest free times:'],
        'choose_doctor' => ['أي دكتور؟', 'Which doctor?'],
        'doctor_not_found' => ['ما لقيت دكتور باسم «{name}».', "I couldn't find a doctor named “{name}”."],
        'no_doctor' => ['بدون تحديد', 'Not specified'],
        'confirm_booking_title' => ['تمام، راح أحجز الموعد بالتفاصيل التالية:', "Alright, I'll book the appointment with these details:"],
        'confirm_booking_question' => ['تأكدلي، أنفذ الحجز؟', 'Shall I go ahead with the booking?'],
        'confirm_booking_btn' => ['✅ تأكيد الحجز', '✅ Confirm booking'],
        'cancel_btn' => ['❌ إلغاء', '❌ Cancel'],
        'booked' => ['✅ تم حجز الموعد بنجاح.', '✅ The appointment was booked.'],

        // Payments
        'ask_amount' => ['شكد المبلغ اللي تريد تسجله لـ{name}؟', 'How much would you like to record for {name}?'],
        'ask_amount_unit' => ['تقصد {a} لو {b}؟', 'Do you mean {a} or {b}?'],
        'amount_unclear' => ['ما گدرت أفهم المبلغ من «{text}». اكتبه رقم (مثلاً 50000 أو 50 ألف).', "I couldn't read the amount from “{text}”. Please write it as a number (e.g. 50000 or 50k)."],
        'currency_mismatch' => [
            'عملة العيادة {clinic}، والمبالغ تتسجل بيها بس. اكتب المبلغ بعملة العيادة.',
            "The clinic's currency is {clinic}, and amounts are recorded in it only. Please give the amount in {clinic}.",
        ],
        'no_unpaid_cases' => ['{name} ما عليه أي مبلغ متبقي، فما كو فاتورة أسجل عليها الدفعة.', '{name} has no outstanding balance, so there is no invoice to record a payment against.'],
        'choose_case' => ['{name} عنده أكثر من فاتورة غير مسددة. على أي وحدة أسجل الدفعة؟', '{name} has more than one unpaid invoice. Which one should the payment go to?'],
        'overpay' => ['المبلغ ({amount}) أكثر من المتبقي على الفاتورة ({remaining}). تريد أسجل المتبقي بس؟', 'The amount ({amount}) is more than what is left on the invoice ({remaining}). Record just the remaining amount?'],
        'confirm_payment_title' => ['لقيت حساب {name}.', "Found {name}'s account."],
        'confirm_payment_question' => ['تأكدلي، أسجل الدفعة؟', 'Shall I record the payment?'],
        'confirm_payment_btn' => ['✅ تأكيد التسديد', '✅ Confirm payment'],
        'payment_recorded' => ['✅ تم تسجيل التسديد بنجاح.', '✅ The payment was recorded.'],
        'payment_note' => ['ملاحظة: هذا تسجيل لمبلغ استلمته العيادة، مو تحصيل دفع إلكتروني.', 'Note: this records money the clinic already received; no money was collected.'],
        'balance_changed' => ['الحساب تغير من وقت ما جهزت التأكيد، فما سجلت شي. هاي التفاصيل المحدثة:', 'The balance changed since I prepared this, so nothing was recorded. Here are the updated details:'],
        'invoice_not_found' => ['ما لقيت الفاتورة رقم {ref} لهذا المراجع.', "I couldn't find invoice #{ref} for this patient."],

        // Cancel / reschedule
        'ask_which_appointment' => ['أي موعد تقصد؟', 'Which appointment do you mean?'],
        'no_upcoming_appointments' => ['ما لقيت مواعيد قادمة تطابق طلبك.', "I couldn't find an upcoming appointment matching that."],
        'confirm_cancel_title' => ['راح ألغي هذا الموعد:', "I'll cancel this appointment:"],
        'confirm_cancel_question' => ['تأكدلي، ألغي الموعد؟', 'Shall I cancel it?'],
        'confirm_cancel_btn' => ['✅ تأكيد الإلغاء', '✅ Confirm cancellation'],
        'appointment_cancelled' => ['✅ تم إلغاء الموعد.', '✅ The appointment was cancelled.'],
        'appointment_gone' => ['هذا الموعد تغير أو انلغى من وقت ما جهزت التأكيد، فما سويت شي.', 'That appointment changed or was cancelled since I prepared this, so I did nothing.'],
        'ask_new_datetime' => ['لأي يوم أو وقت تريد تنقل الموعد؟', 'What day or time should I move it to?'],
        'same_slot' => ['هذا نفس وقت الموعد الحالي.', 'That is the same time as the current appointment.'],
        'confirm_reschedule_title' => ['راح أغير الموعد:', "I'll move the appointment:"],
        'confirm_reschedule_question' => ['تأكدلي، أغير الموعد؟', 'Shall I move it?'],
        'confirm_reschedule_btn' => ['✅ تأكيد التعديل', '✅ Confirm change'],
        'appointment_rescheduled' => ['✅ تم تغيير الموعد.', '✅ The appointment was moved.'],

        // Read-only answers
        'balance_title' => ['حساب {name}:', "{name}'s account:"],
        'unpaid_title' => ['المراجعين اللي عليهم مبالغ:', 'Patients with outstanding balances:'],
        'unpaid_cases_title' => ['الفواتير غير المسددة لـ{name}:', "{name}'s unpaid invoices:"],
        'no_unpaid' => ['ما كو مبالغ متبقية.', 'There are no outstanding balances.'],
        'payments_title' => ['آخر الدفعات المسجلة:', 'Recent recorded payments:'],
        'payments_title_patient' => ['دفعات {name}:', "{name}'s payments:"],
        'no_payments' => ['ما كو دفعات مسجلة.', 'No payments recorded.'],
        'appointments_title' => ['المواعيد ({range}):', 'Appointments ({range}):'],
        'no_appointments' => ['ما كو مواعيد ({range}).', 'No appointments ({range}).'],
        'free_times_title' => ['الأوقات الفارغة يوم {date}:', 'Free times on {date}:'],
        'available_days_title' => ['أقرب الأيام اللي بيها أوقات فارغة:', 'Next days with free times:'],
        'cases_title' => ['حالات {name}:', "{name}'s cases:"],
        'no_cases' => ['ما كو حالات مسجلة.', 'No cases recorded.'],
        'report_title' => ['ملخص اليوم:', "Today's summary:"],
        'total_outstanding' => ['مجموع المبالغ المتبقية: {amount}', 'Total outstanding: {amount}'],

        // Field labels
        'f.patient' => ['المراجع', 'Patient'],
        'f.phone' => ['الهاتف', 'Phone'],
        'f.date' => ['التاريخ', 'Date'],
        'f.time' => ['الوقت', 'Time'],
        'f.doctor' => ['الطبيب', 'Doctor'],
        'f.reference' => ['رقم الحجز', 'Booking ref'],
        'f.amount' => ['المبلغ المطلوب تسجيله', 'Amount to record'],
        'f.amount_recorded' => ['المبلغ المسجل', 'Amount recorded'],
        'f.invoice' => ['الفاتورة', 'Invoice'],
        'f.remaining_before' => ['المبلغ المتبقي قبل التسديد', 'Remaining before'],
        'f.remaining_after' => ['المتبقي بعد التسديد', 'Remaining after'],
        'f.remaining' => ['المبلغ المتبقي', 'Remaining'],
        'f.total' => ['إجمالي الحالات', 'Total charges'],
        'f.paid' => ['المدفوع', 'Paid'],
        'f.unpaid_cases' => ['فواتير غير مسددة', 'Unpaid invoices'],
        'f.last_payment' => ['آخر دفعة', 'Last payment'],
        'f.status' => ['الحالة', 'Status'],
        'f.current' => ['الموعد الحالي', 'Current'],
        'f.new' => ['الموعد الجديد', 'New'],
        'f.payment_ref' => ['رقم الدفعة', 'Payment ref'],
        'f.name' => ['الاسم', 'Name'],

        // Operations (for permission messages)
        'op.book' => ['حجز المواعيد', 'book appointments'],
        'op.edit_appointment' => ['تعديل أو إلغاء المواعيد', 'change or cancel appointments'],
        'op.view_appointments' => ['عرض المواعيد', 'view appointments'],
        'op.record_payment' => ['تسجيل الدفعات', 'record payments'],
        'op.view_bills' => ['عرض الحسابات والدفعات', 'view balances and payments'],
        'op.view_patients' => ['البحث عن المراجعين', 'search patients'],
        'op.create_patient' => ['إضافة مراجعين', 'add patients'],
        'op.view_cases' => ['عرض الحالات', 'view cases'],
        'op.reports' => ['عرض التقارير', 'view reports'],

        // Capabilities (greeting)
        'cap.book' => ['حجز موعد، تغييره أو إلغاؤه', 'Book, move or cancel appointments'],
        'cap.slots' => ['الأوقات الفارغة ومواعيد اليوم', 'Free times and the day\'s appointments'],
        'cap.payment' => ['تسجيل دفعة استلمتها العيادة', 'Record a payment the clinic received'],
        'cap.balance' => ['حساب المراجع والفواتير غير المسددة', 'Patient balances and unpaid invoices'],
        'cap.patients' => ['البحث عن مراجع أو إضافة مراجع جديد', 'Find or add a patient'],
        'cap.reports' => ['ملخص اليوم والأسئلة عن بيانات العيادة', "Today's summary and questions about clinic data"],
        'cap.footer' => ['كل عملية تغير بيانات تحتاج تأكيدك قبل التنفيذ.', 'Anything that changes data waits for your confirmation first.'],

        // Statuses
        's.pending' => ['بانتظار التأكيد', 'awaiting confirmation'],
        's.executing' => ['قيد التنفيذ', 'in progress'],
        's.executed' => ['منفذة', 'done'],
        's.failed' => ['فشلت', 'failed'],
        's.unknown' => ['غير معروفة', 'unknown'],
        's.cancelled' => ['ملغية', 'cancelled'],
        's.expired' => ['منتهية', 'expired'],
        'range.today' => ['اليوم', 'today'],
    ];

    private const WEEKDAYS_AR = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public static function t(string $key, string $lang, array $vars = []): string
    {
        $pair = self::TEXT[$key] ?? [$key, $key];
        $text = $lang === 'en' ? $pair[1] : $pair[0];

        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /** 11/11/2026 (الأربعاء) */
    public static function date(?string $date, string $lang): string
    {
        if (!$date) {
            return '';
        }
        $d = CarbonImmutable::parse($date);
        $day = $lang === 'en' ? $d->englishDayOfWeek : self::WEEKDAYS_AR[$d->dayOfWeek];

        return $d->format('d/m/Y') . " ({$day})";
    }

    /** 5:00 مساءً / 5:00 PM */
    public static function time(?string $time, string $lang): string
    {
        if (!$time) {
            return '';
        }
        $t = CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5));
        $suffix = $lang === 'en' ? $t->format('A') : ($t->hour < 12 ? 'صباحاً' : 'مساءً');

        return $t->format('g:i') . ' ' . $suffix;
    }

    public static function money(int|float|null $amount, ?string $currency, string $lang, bool $long = false): string
    {
        $value = number_format((int) $amount);
        $label = match (strtoupper((string) $currency)) {
            'IQD' => $lang === 'en' ? 'IQD' : ($long ? 'دينار عراقي' : 'دينار'),
            'USD' => $lang === 'en' ? 'USD' : 'دولار',
            '' => '',
            default => strtoupper((string) $currency),
        };

        return trim("{$value} {$label}");
    }

    public static function status(string $status, string $lang): string
    {
        return self::t('s.' . $status, $lang);
    }
}
