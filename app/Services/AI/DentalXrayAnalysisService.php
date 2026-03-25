<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DentalXrayAnalysisService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = 'gpt-4o'; // Vision-capable model for medical image analysis
    }

    /**
     * Analyze a dental X-ray image.
     *
     * @param UploadedFile|null $imageFile  Uploaded image file
     * @param string|null       $imageBase64 Base64-encoded image string
     * @param int|null          $patientId   Optional patient ID for context
     * @return array Structured analysis result
     */
    public function analyze(?UploadedFile $imageFile = null, ?string $imageBase64 = null, ?int $patientId = null): array
    {
        try {
            // Convert image to base64 data URI
            $imageDataUri = $this->resolveImageDataUri($imageFile, $imageBase64);

            if (!$imageDataUri) {
                return [
                    'success' => false,
                    'error' => 'No valid image provided. Please upload an image file or provide a base64-encoded image.',
                ];
            }

            // Build the system prompt for dental X-ray analysis
            $systemPrompt = $this->buildSystemPrompt();

            // Call OpenAI Vision API
            $analysisText = $this->callVisionAPI($systemPrompt, $imageDataUri);

            // Parse the structured response
            $parsed = $this->parseAnalysisResponse($analysisText);

            return [
                'success' => true,
                'analysis' => $parsed,
                'raw_response' => $analysisText,
                'patient_id' => $patientId,
                'analyzed_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Dental X-Ray Analysis Error: ' . $e->getMessage(), [
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to analyze X-ray image: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the image into a base64 data URI.
     */
    private function resolveImageDataUri(?UploadedFile $imageFile, ?string $imageBase64): ?string
    {
        if ($imageFile) {
            $mimeType = $imageFile->getMimeType();
            $base64 = base64_encode(file_get_contents($imageFile->getRealPath()));
            return "data:{$mimeType};base64,{$base64}";
        }

        if ($imageBase64) {
            // If already a data URI, return as-is
            if (str_starts_with($imageBase64, 'data:image/')) {
                return $imageBase64;
            }
            // Otherwise, assume JPEG and wrap it
            return "data:image/jpeg;base64,{$imageBase64}";
        }

        return null;
    }

    /**
     * Build the system prompt for dental X-ray analysis.
     */
    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
أنت مساعد ذكاء اصطناعي تعليمي. دورك هو محاكاة تحليل صور الأشعة السينية للأسنان لأغراض تعليمية وتوضيحية فقط.
هذا ليس تشخيصاً طبياً، ويجب دائماً استشارة طبيب أسنان مختص.

التعليمات:

1. قيّم جودة الصورة (واضحة / متوسطة / ضعيفة).
2. صِف الأسنان وهياكل الفك ببساطة بناءً على ما يظهر بوضوح.
3. أشر إلى المناطق التي عادة ما يبحث فيها أطباء الأسنان عن التسوس أو مشاكل العظام.
4. اشرح النتائج المحتملة بلغة عامية بسيطة.
5. قدم نصيحة توجيهية (مثل: "يُنصح بمراجعة الطبيب لفحص هذه المنطقة").
6. أضف ملاحظة صريحة في الملخص بأن هذا التحليل غير طبي.

يجب أن يكون الرد بالكامل باللغة العربية.

أرجع الرد بهذا التنسيق بالضبط:

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
جملة أو جملتين بلغة بسيطة.

SUMMARY:
شرح قصير وودي مناسب لمراجع العيادة مع التنبيه بضرورة استشارة الطبيب.
PROMPT;
    }

    /**
     * Call OpenAI Vision API with the X-ray image.
     */
    private function callVisionAPI(string $systemPrompt, string $imageDataUri): string
    {
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'لأغراض تعليمية وتوضيحية، يرجى وصف ما يظهر في صورة أشعة الأسنان هذه باستخدام التنسيق المطلوب.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageDataUri,
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.3,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $body);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI Vision API error');
        }

        $content = trim($data['choices'][0]['message']['content'] ?? '');

        if (empty($content)) {
            throw new \Exception('Empty response from Vision API');
        }

        return $content;
    }

    /**
     * Parse the structured text response into a JSON-friendly array.
     */
    private function parseAnalysisResponse(string $text): array
    {
        $parsed = [
            'image_quality' => $this->extractSection($text, 'IMAGE QUALITY:'),
            'observations' => $this->extractSection($text, 'OBSERVATIONS'),
            'risk_level' => $this->extractSection($text, 'RISK LEVEL:'),
            'advice' => $this->extractSection($text, 'ADVICE FOR USER:'),
            'summary' => $this->extractSection($text, 'SUMMARY:'),
        ];

        return $parsed;
    }

    /**
     * Extract a section from the structured response text.
     */
    private function extractSection(string $text, string $header): string
    {
        // Define all known headers to find boundaries
        $allHeaders = [
            'IMAGE QUALITY:',
            'OBSERVATIONS',
            'RISK LEVEL:',
            'ADVICE FOR USER:',
            'SUMMARY:',
        ];

        // Find the start of this section
        $headerPos = stripos($text, $header);
        if ($headerPos === false) {
            return '';
        }

        // Move past the header line
        $startPos = $headerPos + strlen($header);
        // Skip past any trailing characters on header line (e.g., "(simple language):")
        $newlinePos = strpos($text, "\n", $startPos);
        if ($newlinePos !== false) {
            $startPos = $newlinePos + 1;
        }

        // Find the start of the next section
        $endPos = strlen($text);
        foreach ($allHeaders as $otherHeader) {
            if (strcasecmp($otherHeader, $header) === 0) {
                continue;
            }
            $otherPos = stripos($text, $otherHeader, $headerPos + 1);
            if ($otherPos !== false && $otherPos < $endPos && $otherPos > $startPos) {
                $endPos = $otherPos;
            }
        }

        $section = substr($text, $startPos, $endPos - $startPos);
        return trim($section);
    }
}
