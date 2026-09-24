<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Default WhatsApp message templates for a clinic (tenant database).
 *
 * These mirror the built-in suggestions of the patient WhatsApp dialog so every
 * clinic starts with an editable copy under Settings → WhatsApp Templates.
 * Existing keys are never overwritten, so a clinic's edits survive re-seeding.
 *
 * Run for one tenant (inside tenant context) or for all tenants with:
 *   php artisan tenants:seed --class=MessageTemplatesSeeder
 */
class MessageTemplatesSeeder extends Seeder
{
    public const TEMPLATES = [
        [
            'key'  => 'whatsapp_reminder',
            'name' => 'تذكير بالموعد',
            'body' => "مرحباً {{patient_name}}،\n\nهذا تذكير بموعدك القادم في {{clinic_name}}.\n\nيرجى تأكيد حضورك أو إعلامنا إذا كنت بحاجة إلى إعادة الجدولة.\n\nشكراً لك!",
        ],
        [
            'key'  => 'whatsapp_followup',
            'name' => 'متابعة',
            'body' => "عزيزي {{patient_name}}،\n\nنتمنى أن تكون بخير. هذه رسالة متابعة بخصوص زيارتك الأخيرة.\n\nإذا كان لديك أي أسئلة أو استفسارات، لا تتردد في الاتصال بنا.\n\nمع تحياتنا،\n{{clinic_name}}",
        ],
        [
            'key'  => 'whatsapp_checkup',
            'name' => 'تذكير بالفحص',
            'body' => "مرحباً {{patient_name}}،\n\nحان وقت فحصك الدوري!\n\nيرجى حجز موعد في أقرب وقت ممكن.\n\nنتطلع لرؤيتك!\n{{clinic_name}}",
        ],
        [
            'key'  => 'whatsapp_results',
            'name' => 'النتائج جاهزة',
            'body' => "عزيزي {{patient_name}}،\n\nنتائج الفحوصات الخاصة بك أصبحت جاهزة.\n\nيرجى التواصل معنا لحجز موعد لمناقشة النتائج.\n\nشكراً لك!\n{{clinic_name}}",
        ],
        [
            'key'  => 'whatsapp_profile_link',
            'name' => 'مشاركة الملف الطبي',
            'body' => "مرحباً {{patient_name}}،\n\nيمكنك عرض ملفك الطبي الكامل من خلال النقر على الرابط أدناه:\n\n{{profile_url}}\n\nيتضمن ذلك تاريخك الطبي وسجلات العلاج والمواعيد القادمة.\n\nمع تحياتنا،\n{{clinic_name}}",
        ],
        [
            'key'  => 'whatsapp_greeting',
            'name' => 'تحية عامة',
            'body' => "مرحباً {{patient_name}}،\n\nنتمنى أن تكون هذه الرسالة تجدك بخير.\n\nإذا كنت بحاجة إلى أي مساعدة أو ترغب في حجز موعد، يرجى إعلامنا.\n\nمع تحياتنا،\n{{clinic_name}}",
        ],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::TEMPLATES as $template) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $template['body'], $matches);

            $record = MessageTemplate::firstOrCreate(
                ['key' => $template['key']],
                [
                    'name'      => $template['name'],
                    'body'      => $template['body'],
                    'channel'   => 'whatsapp',
                    'language'  => 'ar',
                    'is_active' => true,
                    'variables' => array_values(array_unique($matches[1])),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        $existing = count(self::TEMPLATES) - $created;
        $this->command?->info("  Message templates: {$created} created, {$existing} already existed");
    }
}
