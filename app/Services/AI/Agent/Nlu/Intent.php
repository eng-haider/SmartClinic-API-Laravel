<?php

namespace App\Services\AI\Agent\Nlu;

final class Intent
{
    public const BOOK_APPOINTMENT = 'BOOK_APPOINTMENT';
    public const RESCHEDULE_APPOINTMENT = 'RESCHEDULE_APPOINTMENT';
    public const CANCEL_APPOINTMENT = 'CANCEL_APPOINTMENT';
    public const VIEW_APPOINTMENTS = 'VIEW_APPOINTMENTS';
    public const CHECK_AVAILABILITY = 'CHECK_AVAILABILITY';
    public const RECORD_PAYMENT = 'RECORD_PAYMENT';
    public const VIEW_BALANCE = 'VIEW_BALANCE';
    public const VIEW_UNPAID = 'VIEW_UNPAID';
    public const PAYMENT_HISTORY = 'PAYMENT_HISTORY';
    public const PAY_NOW = 'PAY_NOW';
    public const SEARCH_PATIENT = 'SEARCH_PATIENT';
    public const CREATE_PATIENT = 'CREATE_PATIENT';
    public const VIEW_CASES = 'VIEW_CASES';
    public const REPORT = 'REPORT';
    public const PROVIDE_INFO = 'PROVIDE_INFO';
    public const CONFIRM = 'CONFIRM';
    public const DENY = 'DENY';
    public const GREETING = 'GREETING';
    public const GENERAL_QUESTION = 'GENERAL_QUESTION';
    public const UNSUPPORTED = 'UNSUPPORTED';
    public const UNKNOWN = 'UNKNOWN';

    /** Intents that end in an action needing confirmation */
    public const ACTIONS = [
        self::BOOK_APPOINTMENT, self::RESCHEDULE_APPOINTMENT, self::CANCEL_APPOINTMENT,
        self::RECORD_PAYMENT, self::CREATE_PATIENT,
    ];

    public const DESCRIPTIONS = [
        self::BOOK_APPOINTMENT => 'Book / schedule / reserve a new appointment (احجز، سويلي حجز، ثبت موعد، أريد موعد باچر، عندي مريض يريد موعد، book me for tomorrow)',
        self::RESCHEDULE_APPOINTMENT => 'Change the date or time of an EXISTING appointment (غير الموعد، نقل الموعد لباجر، أجل، قدم، بدل وقت الحجز، postpone, move)',
        self::CANCEL_APPOINTMENT => 'Cancel / delete / remove an existing appointment (ألغي الموعد، احذف الحجز، شيل موعد، كنسل، محمد ما راح يجي)',
        self::VIEW_APPOINTMENTS => 'List appointments for a day, period, patient or doctor (مواعيد اليوم، شنو مواعيد باچر)',
        self::CHECK_AVAILABILITY => 'Ask for free / available times without booking yet (شوفلي موعد فارغ، عندك وقت متاح باچر؟، find an available time)',
        self::RECORD_PAYMENT => 'Record money already received from a patient, partial or full, or mark an invoice paid / settle the remaining (سجل دفعة، سدد مبلغ، أحمد دفع 100 ألف، استلمت من علي، قبضت، خلي الفاتورة مدفوعة، سدد باقي الحساب)',
        self::VIEW_BALANCE => "One patient's balance / debt / what they paid (شكد باقي عليه؟، شكد بذمة أحمد؟، شكد مديونية محمد، شكد دافع، how much does Ahmed owe)",
        self::VIEW_UNPAID => 'Unpaid invoices / outstanding amounts, clinic-wide or for a patient (طلعلي الفواتير غير المدفوعة، شنو المبالغ المستحقة)',
        self::PAYMENT_HISTORY => 'Payments recorded, total paid (payment history، شكد إجمالي المدفوعات)',
        self::PAY_NOW => 'Collect money now through a card / online payment gateway (pay now online, ادفع بالبطاقة)',
        self::SEARCH_PATIENT => 'Find a patient record (دورلي على مراجع، search patient)',
        self::CREATE_PATIENT => 'Add / register a new patient record (ضيف مراجع جديد، add a patient)',
        self::VIEW_CASES => "A patient's cases / treatments / case status (حالات أحمد، شنو حالة العلاج)",
        self::REPORT => "Today's summary / dashboard numbers (ملخص اليوم، تقرير اليوم)",
        self::PROVIDE_INFO => 'Only supplies information the assistant asked for (a name, day, time, amount, a choice), without a new request',
        self::CONFIRM => 'Says yes / agrees',
        self::DENY => 'Says no / cancel the proposed action',
        self::GREETING => 'Greeting, thanks, or asking what the assistant can do',
        self::GENERAL_QUESTION => 'Any other question about clinic data, statistics or medical knowledge',
        self::UNSUPPORTED => 'Asks for an operation the clinic system cannot do (delete a patient, send SMS, change permissions...)',
    ];

    public static function all(): array
    {
        return array_keys(self::DESCRIPTIONS);
    }
}
