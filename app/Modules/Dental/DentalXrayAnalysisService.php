<?php

namespace App\Modules\Dental;

use App\Services\AI\BaseImageAnalysisService;

/**
 * DentalXrayAnalysisService
 *
 * Dental-specific image analysis: X-rays, panoramic, periapical.
 * Focuses on teeth, jaw, cavities, bone loss, restorations.
 */
class DentalXrayAnalysisService extends BaseImageAnalysisService
{
    public function specialty(): string
    {
        return 'dental';
    }

    public function analysisLabel(): string
    {
        return 'Dental X-Ray';
    }

    protected function buildUserPrompt(): string
    {
        return 'For educational purposes, please analyze this dental image and describe what you observe using the structured format specified.';
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an AI assistant that helps people understand dental X-ray images in simple, clear language.
This is for educational and illustrative purposes only.
Your goal is to explain what you see in the X-ray in a way anyone can understand, while still noting any potential areas of interest.

INSTRUCTIONS:

1. Evaluate the image quality (clear / moderate / poor).
2. Describe teeth and jaw structures simply based on what is visible.
3. Highlight any areas where dentists typically look for cavities, bone changes, or restorations.
4. Explain findings in plain language, avoiding strict medical jargon.
5. Give practical advice for the user (e.g., "See a dentist soon", "Good oral health observed", "Monitor this area").
6. Always include a disclaimer that this is not a medical diagnosis and they should consult a dentist.

CRITICAL REQUIREMENT:
You MUST translate your final response entirely into Arabic. Do not output English text except for the required section headers.

RETURN RESPONSE IN THIS EXACT FORMAT (keep these English headers, but all text beneath them must be Arabic):

IMAGE QUALITY:
( واضحة / متوسطة / ضعيفة )

OBSERVATIONS:

* مظهر الأسنان
* التسوس المحتمل
* صحة الفك/العظام
* أي مناطق غير طبيعية

RISK LEVEL:
قلق منخفض / متوسط / مرتفع

ADVICE FOR USER:
جملة أو جملتين بلغة بسيطة حول الخطوات القادمة.

SUMMARY:
شرح قصير وودي مع التنبيه بضرورة استشارة الطبيب.
PROMPT;
    }

    protected function buildFallbackMessages(): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'أنت مساعد ذكاء اصطناعي تعليمي في عيادة أسنان. مهمتك إعداد تقرير تعليمي عام حول ما قد يلاحظه طبيب الأسنان في صورة أشعة سينية لأسنان بشكل اعتيادي.',
            ],
            [
                'role' => 'user',
                'content' => 'أعدّ تقرير تعليمي عام ومفيد لمريض حول ما قد تُظهره صورة أشعة سينية لأسنان بشكل اعتيادي. الرد يجب أن يكون بالعربية وبهذا التنسيق بالضبط:

IMAGE QUALITY:
واضحة

OBSERVATIONS:
* مظهر الأسنان: اشرح ببساطة مظهر الأسنان الطبيعي في الأشعة
* التسوس المحتمل: اشرح ما قد يبحث عنه الطبيب
* صحة الفك/العظام: اشرح كيف يبدو العظم السليم
* مناطق تستحق الانتباه: نصيحة عامة للمتابعة

RISK LEVEL:
قلق منخفض

ADVICE FOR USER:
نصيحة عملية عامة لزيارة الطبيب.

SUMMARY:
ملخص تعليمي قصير مع التنبيه بأن هذا ليس تشخيصاً طبياً وينبغي مراجعة طبيب الأسنان.',
            ],
        ];
    }
}
