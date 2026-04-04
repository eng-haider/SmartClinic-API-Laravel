<?php

namespace App\Services\AI;

use App\Contracts\ImageAnalysisServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BaseImageAnalysisService
 *
 * Shared logic for all specialty image analysis services.
 * Each specialty overrides:
 *   - specialty()         → e.g. 'dental'
 *   - analysisLabel()     → e.g. 'Dental X-Ray'
 *   - buildSystemPrompt() → specialty-specific prompt
 *   - buildFallbackPrompt() → fallback text prompt
 *   - sectionHeaders()    → response parsing headers
 */
abstract class BaseImageAnalysisService implements ImageAnalysisServiceInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = 'gpt-4o';
    }

    /**
     * The system prompt for the Vision API call.
     */
    abstract protected function buildSystemPrompt(): string;

    /**
     * The user-facing instruction sent alongside the image.
     */
    abstract protected function buildUserPrompt(): string;

    /**
     * Fallback prompt messages when Vision model refuses the image.
     * Return an array of ['role' => ..., 'content' => ...] messages.
     */
    abstract protected function buildFallbackMessages(): array;

    /**
     * Section headers used in the structured response.
     * Order matters — used for parsing boundaries.
     */
    protected function sectionHeaders(): array
    {
        return [
            'IMAGE QUALITY:',
            'OBSERVATIONS',
            'RISK LEVEL:',
            'ADVICE FOR USER:',
            'SUMMARY:',
        ];
    }

    /**
     * Map section headers to parsed result keys.
     */
    protected function sectionKeyMap(): array
    {
        return [
            'IMAGE QUALITY:' => 'image_quality',
            'OBSERVATIONS'   => 'observations',
            'RISK LEVEL:'    => 'risk_level',
            'ADVICE FOR USER:' => 'advice',
            'SUMMARY:'       => 'summary',
        ];
    }

    /**
     * Analyze a medical image.
     */
    public function analyze(?UploadedFile $imageFile = null, ?string $imageBase64 = null, ?int $patientId = null): array
    {
        try {
            $imageDataUri = $this->resolveImageDataUri($imageFile, $imageBase64);

            if (!$imageDataUri) {
                return [
                    'success' => false,
                    'error' => 'No valid image provided. Please upload an image file or provide a base64-encoded image.',
                ];
            }

            $systemPrompt = $this->buildSystemPrompt();
            $analysisText = $this->callVisionAPI($systemPrompt, $imageDataUri);
            $parsed = $this->parseAnalysisResponse($analysisText);

            return [
                'success'     => true,
                'analysis'    => $parsed,
                'raw_response' => $analysisText,
                'patient_id'  => $patientId,
                'specialty'   => $this->specialty(),
                'analyzed_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error($this->analysisLabel() . ' Analysis Error: ' . $e->getMessage(), [
                'specialty'  => $this->specialty(),
                'patient_id' => $patientId,
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to analyze image: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the image into a base64 data URI.
     */
    protected function resolveImageDataUri(?UploadedFile $imageFile, ?string $imageBase64): ?string
    {
        if ($imageFile) {
            $mimeType = $imageFile->getMimeType();
            $base64 = base64_encode(file_get_contents($imageFile->getRealPath()));
            return "data:{$mimeType};base64,{$base64}";
        }

        if ($imageBase64) {
            if (str_starts_with($imageBase64, 'data:image/')) {
                return $imageBase64;
            }
            return "data:image/jpeg;base64,{$imageBase64}";
        }

        return null;
    }

    /**
     * Call OpenAI Vision API with the image.
     */
    protected function callVisionAPI(string $systemPrompt, string $imageDataUri): string
    {
        $body = [
            'model'    => $this->model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $this->buildUserPrompt(),
                        ],
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => $imageDataUri,
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens'  => 1000,
            'temperature' => 0.3,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $body);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI Vision API error');
        }

        $content = trim($data['choices'][0]['message']['content'] ?? '');

        if (empty($content)) {
            throw new \Exception('Empty response from Vision API');
        }

        if ($this->isRefusal($content)) {
            Log::warning($this->analysisLabel() . ': Vision model refused image — using fallback GPT response.');
            $content = $this->callFallbackGPT();
        }

        return $content;
    }

    /**
     * Detect if the model refused to process the image.
     */
    protected function isRefusal(string $text): bool
    {
        $refusalPhrases = [
            "i'm sorry, i can't",
            "i'm sorry, i cannot",
            "i can't assist",
            "i cannot assist",
            "i'm unable to",
            "i am unable to",
            "i'm not able to",
            "i can't help with",
        ];

        $lower = strtolower($text);
        foreach ($refusalPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fallback text-based educational response when Vision refuses.
     */
    protected function callFallbackGPT(): string
    {
        $messages = $this->buildFallbackMessages();

        $body = [
            'model'       => 'gpt-4o-mini',
            'messages'    => $messages,
            'max_tokens'  => 800,
            'temperature' => 0.5,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(20)->post('https://api.openai.com/v1/chat/completions', $body);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI API error');
        }

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Parse the structured text response into a JSON-friendly array.
     */
    protected function parseAnalysisResponse(string $text): array
    {
        $parsed = [];
        foreach ($this->sectionKeyMap() as $header => $key) {
            $parsed[$key] = $this->extractSection($text, $header);
        }
        return $parsed;
    }

    /**
     * Extract a section from the structured response text.
     */
    protected function extractSection(string $text, string $header): string
    {
        $allHeaders = $this->sectionHeaders();

        $headerPos = stripos($text, $header);
        if ($headerPos === false) {
            return '';
        }

        $startPos = $headerPos + strlen($header);
        $newlinePos = strpos($text, "\n", $startPos);
        if ($newlinePos !== false) {
            $startPos = $newlinePos + 1;
        }

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
