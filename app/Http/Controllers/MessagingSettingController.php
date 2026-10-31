<?php

namespace App\Http\Controllers;

use App\Models\MessagingSetting;
use App\Services\Messaging\Channels\GupshupChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessagingSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
    }

    /**
     * Get all messaging settings.
     */
    public function index(): JsonResponse
    {
        $settings = MessagingSetting::all();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Create or update messaging settings for a provider.
     */
    public function upsert(Request $request): JsonResponse
    {
        // Gupshup's details and the reminder template only matter while the clinic sends by API
        $sendsByApi = fn () => $request->boolean('is_active');
        $gupshupRequired = Rule::requiredIf(fn () => $sendsByApi() && $request->input('meta.driver') === 'gupshup');

        $validated = $request->validate([
            'provider' => 'required|string|in:whatsapp,email,push',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_access_token' => 'nullable|string|max:1000',
            'whatsapp_business_account_id' => 'nullable|string|max:255',
            'whatsapp_webhook_verify_token' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'meta' => 'nullable|array',
            // WhatsApp through Gupshup instead of Meta; the Gupshup API key goes in whatsapp_access_token
            'meta.driver' => 'nullable|string|in:meta,gupshup',
            'meta.gupshup_app_name' => [$gupshupRequired, 'nullable', 'string', 'max:255'],
            'meta.gupshup_app_id' => [$gupshupRequired, 'nullable', 'string', 'max:255'],
            'meta.gupshup_source_number' => [$gupshupRequired, 'nullable', 'string', 'max:20'],
            // Country code put in front of patients' local numbers (0770...) for Gupshup, default 964
            'meta.country_code' => 'nullable|digits_between:1,4',
            // Simple setup (see BookingReminderService): send by API automatically, or by hand from the device's WhatsApp
            'meta.send_mode' => 'nullable|string|in:api,device',
            'meta.booking_reminder_enabled' => 'nullable|boolean',
            'meta.booking_reminder_minutes' => 'nullable|integer|min:5|max:10080',
            'meta.booking_reminder_template' => [
                Rule::requiredIf(fn () => $sendsByApi() && $request->boolean('meta.booking_reminder_enabled')),
                'nullable', 'string', 'max:255',
            ],
            'meta.booking_reminder_text' => 'nullable|string|max:1000',
            // Patients book from WhatsApp (see WhatsAppBookingBot) and hear back there on approval
            'meta.booking_bot_enabled' => 'nullable|boolean',
            'meta.booking_approved_template' => 'nullable|string|max:255',
            'meta.booking_approved_text' => 'nullable|string|max:1000',
        ]);

        // Gupshup's callback URL carries this secret (see webhookInfo), so make one if missing
        if (($validated['meta']['driver'] ?? null) === 'gupshup' && empty($validated['whatsapp_webhook_verify_token'])) {
            $validated['whatsapp_webhook_verify_token'] = MessagingSetting::where('provider', $validated['provider'])
                ->value('whatsapp_webhook_verify_token') ?: Str::random(40);
        }

        $setting = MessagingSetting::updateOrCreate(
            ['provider' => $validated['provider']],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Messaging settings saved',
            'data' => $setting,
        ]);
    }

    /**
     * Test WhatsApp credentials by calling Meta API.
     * Clinics can use this to verify their setup without developer intervention.
     */
    public function testConnection(Request $request): JsonResponse
    {
        if ($request->input('meta.driver') === 'gupshup') {
            return $this->testGupshupConnection($request);
        }

        $validated = $request->validate([
            'whatsapp_phone_number_id' => 'required|string',
            'whatsapp_access_token' => 'required|string',
        ]);

        $response = Http::withToken($validated['whatsapp_access_token'])
            ->get("https://graph.facebook.com/v21.0/{$validated['whatsapp_phone_number_id']}", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);

        if ($response->successful()) {
            $data = $response->json();

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'phone_number' => $data['display_phone_number'] ?? null,
                'verified_name' => $data['verified_name'] ?? null,
                'quality_rating' => $data['quality_rating'] ?? null,
            ]);
        }

        $error = $response->json('error.message') ?? 'Connection failed';

        return response()->json([
            'success' => false,
            'message' => $error,
        ], 422);
    }

    /**
     * Test Gupshup credentials by listing the app's approved templates, which
     * also shows the clinic which template names its automations can use.
     */
    private function testGupshupConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_access_token' => 'required|string',
            'meta.gupshup_app_id' => 'required|string',
        ]);

        $response = GupshupChannel::templatesRequest(
            $validated['whatsapp_access_token'],
            $validated['meta']['gupshup_app_id'],
            ['templateStatus' => 'APPROVED'],
        );

        if ($response->successful() && $response->json('status') === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'approved_templates' => collect($response->json('templates', []))->pluck('elementName')->unique()->values(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response->json('message') ?? 'Connection failed',
        ], 422);
    }

    /**
     * Return webhook setup info for the clinic to configure in Meta Dashboard.
     * This makes the setup fully self-service — no developer needed.
     */
    public function webhookInfo(): JsonResponse
    {
        $setting = MessagingSetting::where('provider', 'whatsapp')->first();

        if ($setting?->usesGupshup()) {
            $token = $setting->whatsapp_webhook_verify_token;

            return response()->json([
                'success' => true,
                'data' => [
                    'callback_url' => $token
                        ? url('/api/webhooks/gupshup/' . rawurlencode(tenant('id')) . '/' . rawurlencode($token))
                        : 'Not configured yet',
                    'instructions' => [
                        'step1' => 'Go to apps.gupshup.io → your app → Webhooks tab',
                        'step2' => 'Add a webhook with the callback_url above, in Gupshup (V2) format',
                        'step3' => 'Enable the events: Message, Sent, Delivered, Read, Failed',
                        'step4' => 'Save',
                    ],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'callback_url' => url('/api/webhooks/whatsapp'),
                'verify_token' => $setting?->whatsapp_webhook_verify_token ?? 'Not configured yet',
                'subscribed_fields' => ['messages', 'message_status_updates'],
                'instructions' => [
                    'step1' => 'Go to developers.facebook.com → Your App → WhatsApp → Configuration',
                    'step2' => 'Set Callback URL to the callback_url above',
                    'step3' => 'Set Verify Token to the verify_token above',
                    'step4' => 'Subscribe to: messages, message_status_updates',
                    'step5' => 'Click Verify and Save',
                ],
            ],
        ]);
    }
}
