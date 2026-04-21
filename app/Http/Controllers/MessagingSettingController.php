<?php

namespace App\Http\Controllers;

use App\Models\MessagingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        $validated = $request->validate([
            'provider' => 'required|string|in:whatsapp,email,push',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_access_token' => 'nullable|string|max:1000',
            'whatsapp_business_account_id' => 'nullable|string|max:255',
            'whatsapp_webhook_verify_token' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'meta' => 'nullable|array',
        ]);

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
     * Return webhook setup info for the clinic to configure in Meta Dashboard.
     * This makes the setup fully self-service — no developer needed.
     */
    public function webhookInfo(): JsonResponse
    {
        $setting = MessagingSetting::where('provider', 'whatsapp')->first();

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
