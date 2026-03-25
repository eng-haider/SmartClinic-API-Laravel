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
                            'text' => 'For educational purposes, please analyze this dental X-ray image and provide your findings in Arabic using the structured format specified.',
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
