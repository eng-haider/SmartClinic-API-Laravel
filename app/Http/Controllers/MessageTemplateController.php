<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Services\Messaging\TemplateEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt');
    }

    /**
     * List all message templates.
     */
    public function index(): JsonResponse
    {
        $templates = MessageTemplate::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create a new message template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:message_templates,key',
            'name' => 'required|string|max:255',
            'channel' => 'string|in:whatsapp,email,push',
            'body' => 'required|string',
            'language' => 'string|max:10',
            'is_active' => 'boolean',
            'variables' => 'nullable|array',
        ]);

        $template = MessageTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template created',
            'data' => $template,
        ], 201);
    }

    /**
     * Show a single template.
     */
    public function show(int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        $validated = $request->validate([
            'key' => 'string|max:255|unique:message_templates,key,' . $id,
            'name' => 'string|max:255',
            'channel' => 'string|in:whatsapp,email,push',
            'body' => 'string',
            'language' => 'string|max:10',
            'is_active' => 'boolean',
            'variables' => 'nullable|array',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated',
            'data' => $template->fresh(),
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted',
        ]);
    }

    /**
     * Preview a template with sample data.
     */
    public function preview(int $id, TemplateEngine $engine): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);
        $rendered = $engine->preview($template->key);

        return response()->json([
            'success' => true,
            'data' => [
                'template' => $template,
                'preview' => $rendered,
            ],
        ]);
    }
}
