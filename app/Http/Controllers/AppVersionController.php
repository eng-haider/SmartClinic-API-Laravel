<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $platform = strtolower((string) $request->query('platform', 'android'));

        if ($platform !== 'android') {
            return response()->json([
                'message' => 'The selected platform is invalid.',
                'errors' => ['platform' => ['Only android is currently supported.']],
            ], 422);
        }

        $release = AppVersion::query()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('released_at')
                    ->orWhere('released_at', '<=', now());
            })
            ->orderByDesc('build_number')
            ->first();

        if (! $release) {
            return response()->json([
                'message' => 'No active app version is available.',
            ], 404);
        }

        $apkUrl = $release->apk_url;
        if (! str_starts_with($apkUrl, 'https://') && ! str_starts_with($apkUrl, 'http://')) {
            $apkUrl = url('/'.ltrim($apkUrl, '/'));
        }

        return response()->json([
            'version' => $release->version,
            'build_number' => $release->build_number,
            'force_update' => $release->force_update,
            'apk_url' => $apkUrl,
            'message' => $release->message,
        ]);
    }
}
