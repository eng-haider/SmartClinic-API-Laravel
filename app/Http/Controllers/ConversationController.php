<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
    }

    /**
     * List conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::with('patient');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $conversations = $query->orderBy('last_message_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $conversations->items(),
            'pagination' => [
                'total' => $conversations->total(),
                'per_page' => $conversations->perPage(),
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
            ],
        ]);
    }

    /**
     * Show conversation with messages.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::with(['patient', 'messages' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    /**
     * Send a direct message in a conversation.
     */
    public function sendMessage(Request $request, int $id, MessageService $messageService): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string|max:4096',
        ]);

        $message = $messageService->sendDirect(
            $conversation->phone_number,
            $validated['body'],
            $conversation->channel,
            $conversation->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Message sent',
            'data' => $message,
        ], 201);
    }
}
