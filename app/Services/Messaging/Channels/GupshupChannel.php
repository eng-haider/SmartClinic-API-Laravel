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

            // A list of choices. If Gupshup refuses it, the text below goes instead;
            // senders put the same choices in it as a numbered menu.
            if (!empty($options['list'])) {
                $result = $this->post('/wa/api/v1/msg', $to, [
                    'message' => json_encode($this->listMessage($options['list']), JSON_UNESCAPED_UNICODE),
                ]);

                if ($result['success']) {
                    return $result;
                }

                Log::warning('Gupshup refused a list message, sending it as text', ['error' => $result['error']]);
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

    /**
     * Gupshup's list message, cut to WhatsApp's limits. A tapped row's id comes back
     * as its postbackText.
     *
     * @param array{title: string, body: string, button: string, rows: list<array{id: string, title: string, description: string}>} $list
     */
    private function listMessage(array $list): array
    {
        return [
            'type' => 'list',
            'title' => mb_substr($list['title'], 0, 60),
            'body' => mb_substr($list['body'], 0, 1024),
            'msgid' => 'list',
            'globalButtons' => [['type' => 'text', 'title' => mb_substr($list['button'], 0, 20)]],
            'items' => [[
                'title' => mb_substr($list['button'], 0, 24),
                'subtitle' => mb_substr($list['button'], 0, 24),
                'options' => array_map(fn (array $row) => [
                    'type' => 'text',
                    'title' => mb_substr($row['title'], 0, 24),
                    'description' => mb_substr($row['description'], 0, 72),
                    'postbackText' => $row['id'],
                ], array_slice($list['rows'], 0, 10)),
            ]],
        ];
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

        // Gupshup's docs show both "submitted" and "success" for an accepted message
        if ($response->successful() && in_array($response->json('status'), ['submitted', 'success'], true)) {
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
     *
     * Cache::store() rather than Cache::remember(): inside a tenant, stancl's cache
     * manager tags every facade call, and the database store can't tag ("This cache
     * store does not support tagging"), so every template send failed. The store is
     * already on the tenant's connection and the key is per Gupshup app.
     */
    private function findTemplateId(string $name, string $language): ?string
    {
        $cache = Cache::store();
        $cacheKey = 'gupshup_templates:' . $this->meta('gupshup_app_id');

        $id = $this->pickTemplate($cache->remember($cacheKey, 600, fn () => $this->fetchTemplates()), $name, $language);
        if ($id === null) {
            $cache->forget($cacheKey);
            $id = $this->pickTemplate($cache->remember($cacheKey, 600, fn () => $this->fetchTemplates()), $name, $language);
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
