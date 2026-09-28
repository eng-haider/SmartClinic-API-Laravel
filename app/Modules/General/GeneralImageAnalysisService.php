<?php

namespace App\Modules\General;

use App\Services\AI\BaseImageAnalysisService;

/**
 * GeneralImageAnalysisService
 *
 * Fallback image analysis for any specialty that doesn't have
 * a dedicated analyzer. Provides generic medical image observations.
 */
class GeneralImageAnalysisService extends BaseImageAnalysisService
{
    public function specialty(): string
    {
        return 'general';
    }

    public function analysisLabel(): string
    {
        return 'Medical Image';
    }

    protected function buildUserPrompt(): string
    {
        return 'For educational purposes, please analyze this medical image and describe what you observe using the structured format specified.';
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an AI assistant that helps people understand medical images in simple, clear language.
This is for educational and illustrative purposes only.
Your goal is to explain what you see in the medical image in a way anyone can understand, while still noting any potential areas of interest.

INSTRUCTIONS:

1. Evaluate the image quality (clear / moderate / poor).
2. Identify the type of medical image if possible (X-ray, ultrasound, CT, MRI, clinical photo, etc.).
3. Describe visible anatomical structures simply.
4. Highlight any areas that appear abnormal or would typically be examined by a doctor.
5. Explain findings in plain language, avoiding strict medical jargon.
6. Give practical advice for the user.
7. Always include a disclaimer that this is not a medical diagnosis and they should consult their doctor.

CRITICAL REQUIREMENT:
You MUST translate your final response entirely into Arabic. Do not output English text except for the required section headers.

RETURN RESPONSE IN THIS EXACT FORMAT (keep these English headers, but all text beneath them must be Arabic):

IMAGE QUALITY:
( واضحة / متوسطة / ضعيفة )

OBSERVATIONS:

* نوع الصورة الطبية
* الهياكل التشريحية المرئية
* أي مناطق غير طبيعية
* ملاحظات عامة

RISK LEVEL:
قلق منخفض / متوسط / مرتفع

ADVICE FOR USER:
جملة أو جملتين بلغة بسيطة حول الخطوات القادمة.

SUMMARY:
شرح قصير وودي مع التنبيه بضرورة استشارة الطبيب المختص.
PROMPT;
    }

    protected function buildFallbackMessages(): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'أنت مساعد ذكاء اصطناعي تعليمي في عيادة طبية. مهمتك إعداد تقرير تعليمي عام حول ما قد يلاحظه الطبيب في صورة طبية بشكل اعتيادي.',
            ],
            [
                'role' => 'user',
                'content' => 'أعدّ تقرير تعليمي عام ومفيد لمريض حول ما قد تُظهره صورة طبية بشكل اعتيادي. الرد يجب أن يكون بالعربية وبهذا التنسيق بالضبط:

IMAGE QUALITY:
واضحة

OBSERVATIONS:
* نوع الصورة: اشرح ببساطة
* الهياكل التشريحية: اشرح ما يمكن ملاحظته
* مناطق تستحق الانتباه: نصيحة عامة للمتابعة

RISK LEVEL:
قلق منخفض

ADVICE FOR USER:
نصيحة عملية عامة لزيارة الطبيب.

SUMMARY:
ملخص تعليمي قصير مع التنبيه بأن هذا ليس تشخيصاً طبياً وينبغي مراجعة الطبيب المختص.',
            ],
        ];
    }
}
