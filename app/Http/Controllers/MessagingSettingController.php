<?php

namespace App\Http\Controllers;

use App\Models\MessagingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
