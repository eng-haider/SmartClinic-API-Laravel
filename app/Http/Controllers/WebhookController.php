<?php

namespace App\Http\Controllers;

use App\Services\Messaging\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookService $webhookService,
    ) {}

    /**
     * WhatsApp webhook verification (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if (!$mode || !$token || !$challenge) {
            return response('Missing parameters', 400);
        }

        $result = $this->webhookService->verifyWebhook($mode, $token, $challenge);

        if ($result !== null) {
            return response($result, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * WhatsApp webhook handler (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $this->webhookService->handleWhatsApp($payload);

        return response()->json(['success' => true]);
    }
}
