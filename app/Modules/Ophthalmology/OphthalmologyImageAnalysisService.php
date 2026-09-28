<?php

namespace App\Modules\Ophthalmology;

use App\Services\AI\BaseImageAnalysisService;

/**
 * OphthalmologyImageAnalysisService
 *
 * Eye-specific image analysis: fundus photos, OCT scans, slit-lamp images.
 * Focuses on retina, optic disc, macula, IOP indicators, lens clarity.
 */
class OphthalmologyImageAnalysisService extends BaseImageAnalysisService
{
    public function specialty(): string
    {
        return 'ophthalmology';
    }

    public function analysisLabel(): string
    {
        return 'Eye Image';
    }

    protected function buildUserPrompt(): string
    {
        return 'For educational purposes, please analyze this eye/ophthalmology image and describe what you observe using the structured format specified.';
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an AI assistant that helps people understand ophthalmology (eye) images in simple, clear language.
This is for educational and illustrative purposes only.
Your goal is to explain what you see in the eye image in a way anyone can understand, while still noting any potential areas of interest.

The image may be: a fundus photograph, an OCT scan, a slit-lamp image, a visual field test, or another ophthalmology diagnostic image.

INSTRUCTIONS:

1. Evaluate the image quality (clear / moderate / poor).
2. Describe the visible eye structures simply: retina, optic disc, macula, blood vessels, lens, cornea — whichever are visible.
3. Highlight any areas where an ophthalmologist typically looks for abnormalities:
   - Optic disc shape and color (glaucoma signs)
   - Macula health (macular degeneration, edema)
   - Blood vessel patterns (diabetic retinopathy, hypertensive changes)
   - Lens clarity (cataracts)
   - Retinal detachment signs
   - Any lesions, hemorrhages, or exudates
4. Explain findings in plain language, avoiding strict medical jargon.
5. Give practical advice for the user.
6. Always include a disclaimer that this is not a medical diagnosis and they should consult an ophthalmologist.

CRITICAL REQUIREMENT:
You MUST translate your final response entirely into Arabic. Do not output English text except for the required section headers.

RETURN RESPONSE IN THIS EXACT FORMAT (keep these English headers, but all text beneath them must be Arabic):

IMAGE QUALITY:
( واضحة / متوسطة / ضعيفة )

OBSERVATIONS:

* حالة الشبكية
* القرص البصري (العصب البصري)
* البقعة الصفراء (الماكولا)
* الأوعية الدموية
* صحة العدسة والقرنية
* أي مناطق غير طبيعية

RISK LEVEL:
قلق منخفض / متوسط / مرتفع

ADVICE FOR USER:
جملة أو جملتين بلغة بسيطة حول الخطوات القادمة.

SUMMARY:
شرح قصير وودي مع التنبيه بضرورة استشارة طبيب العيون.
PROMPT;
    }

    protected function buildFallbackMessages(): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'أنت مساعد ذكاء اصطناعي تعليمي في عيادة عيون. مهمتك إعداد تقرير تعليمي عام حول ما قد يلاحظه طبيب العيون في صورة فحص العين بشكل اعتيادي.',
            ],
            [
                'role' => 'user',
                'content' => 'أعدّ تقرير تعليمي عام ومفيد لمريض حول ما قد تُظهره صورة فحص العين بشكل اعتيادي. الرد يجب أن يكون بالعربية وبهذا التنسيق بالضبط:

IMAGE QUALITY:
واضحة

OBSERVATIONS:
* حالة الشبكية: اشرح ببساطة مظهر الشبكية الطبيعي
* القرص البصري: اشرح كيف يبدو العصب البصري السليم
* البقعة الصفراء: اشرح مظهر الماكولا الطبيعي
* الأوعية الدموية: اشرح كيف تبدو الأوعية الطبيعية
* مناطق تستحق الانتباه: نصيحة عامة للمتابعة

RISK LEVEL:
قلق منخفض

ADVICE FOR USER:
نصيحة عملية عامة لزيارة طبيب العيون.

SUMMARY:
ملخص تعليمي قصير مع التنبيه بأن هذا ليس تشخيصاً طبياً وينبغي مراجعة طبيب العيون.',
            ],
        ];
    }
}
