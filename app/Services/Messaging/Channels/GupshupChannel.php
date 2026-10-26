<?php

namespace App\Services\Messaging\Channels;

use App\Models\MessagingSetting;
use App\Services\Messaging\Contracts\ChannelDriver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp through Gupshup (https://docs.gupshup.io) instead of Meta's Cloud API.
 */
class GupshupChannel implements ChannelDriver
{
    public const API_URL = 'https://api.gupshup.io';

    public function __construct(private MessagingSetting $settings) {}

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(string $to, string $body, array $options = []): array
    {
        try {
            // If a template is specified, send template message
            if (!empty($options['template_name'])) {
                return $this->sendTemplate($to, $options);
            }

            // Otherwise send a plain text message (only delivered inside WhatsApp's 24h window)
            return $this->post('/wa/api/v1/msg', $to, [
                'message' => json_encode(['type' => 'text', 'text' => $body], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::error('Gupshup send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'external_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendTemplate(string $to, array $options): array
    {
        $language = $options['language'] ?? 'ar';
        $templateId = $this->findTemplateId($options['template_name'], $language);

        if (!$templateId) {
            return [
                'success' => false,
                'external_id' => null,
                'error' => "Gupshup has no approved template named '{$options['template_name']}'",
            ];
        }

        return $this->post('/wa/api/v1/template/msg', $to, [
            'template' => json_encode([
                'id' => $templateId,
                'params' => array_map('strval', array_values($options['template_params'] ?? [])),
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function post(string $path, string $to, array $fields): array
    {
        $response = Http::asForm()
            ->withHeaders(['apikey' => $this->settings->whatsapp_access_token])
            ->post(self::API_URL . $path, array_merge([
                'channel' => 'whatsapp',
                'source' => $this->normalizePhone($this->meta('gupshup_source_number')),
                'destination' => $this->normalizePhone($to),
                'src.name' => $this->meta('gupshup_app_name'),
            ], $fields));

        if ($response->successful() && $response->json('status') === 'submitted') {
            return [
                'success' => true,
                'external_id' => $response->json('messageId'),
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'external_id' => null,
            'error' => $response->json('message', 'Unknown Gupshup API error'),
        ];
    }

    /**
     * Automations name templates the way Meta does (by name), but Gupshup sends
     * them by its own template id, so the id is looked up among the app's
     * approved templates. A miss refreshes the list once, for newly approved ones.
     */
    private function findTemplateId(string $name, string $language): ?string
    {
        $cacheKey = 'gupshup_templates:' . $this->meta('gupshup_app_id');

        $id = $this->pickTemplate(Cache::remember($cacheKey, 600, fn () => $this->fetchTemplates()), $name, $language);
        if ($id === null) {
            Cache::forget($cacheKey);
            $id = $this->pickTemplate(Cache::remember($cacheKey, 600, fn () => $this->fetchTemplates()), $name, $language);
        }

        return $id;
    }

    /**
     * @param array<string, array<string, string>> $templates name => [languageCode => id]
     */
    private function pickTemplate(array $templates, string $name, string $language): ?string
    {
        $languages = $templates[$name] ?? [];

        return $languages[$language] ?? (reset($languages) ?: null);
    }

    /**
     * @return array<string, array<string, string>> name => [languageCode => id]
     */
    private function fetchTemplates(): array
    {
        $response = self::templatesRequest(
            $this->settings->whatsapp_access_token,
            $this->meta('gupshup_app_id'),
            ['templateStatus' => 'APPROVED'],
        );

        if (!$response->successful() || $response->json('status') !== 'success') {
            throw new \RuntimeException('Could not load Gupshup templates: ' . $response->json('message', 'Unknown error'));
        }

        $templates = [];
        foreach ($response->json('templates', []) as $template) {
            $templates[$template['elementName']][$template['languageCode']] = $template['id'];
        }

        return $templates;
    }

    /**
     * Lists an app's templates; also used to check a clinic's Gupshup credentials.
     */
    public static function templatesRequest(string $apiKey, string $appId, array $query = []): Response
    {
        return Http::withHeaders(['apikey' => $apiKey])
            ->get(self::API_URL . '/wa/app/' . rawurlencode($appId) . '/template', $query);
    }

    private function meta(string $key): string
    {
        return (string) ($this->settings->meta[$key] ?? '');
    }

    /**
     * Gupshup wants international digits only. Patients' numbers are usually saved
     * the local way ("0770 123 4567"), so a leading 0 becomes the clinic's country code.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return ($this->settings->meta['country_code'] ?? '964') . substr($digits, 1);
        }

        return $digits;
    }
}
