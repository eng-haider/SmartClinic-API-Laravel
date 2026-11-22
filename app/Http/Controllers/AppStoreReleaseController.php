<?php

namespace App\Http\Controllers;

use App\Models\AppStoreRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppStoreReleaseController extends Controller
{
    /**
     * The version live on the platform's store. The app compares
     * build_number with its own build to offer the update, and must update
     * when its build is below min_build_number.
     */
    public function latest(Request $request): JsonResponse
    {
        $platform = strtolower((string) $request->query('platform', ''));

        if (! in_array($platform, ['android', 'ios'], true)) {
            return response()->json([
                'message' => 'The selected platform is invalid.',
                'errors' => ['platform' => ['The platform must be android or ios.']],
            ], 422);
        }

        $release = AppStoreRelease::query()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        if (! $release || ! str_starts_with($release->store_url, 'https://')) {
            return response()->json([
                'message' => 'No active store release is available.',
            ], 404);
        }

        return response()->json([
            'platform' => $release->platform,
            'version' => $release->version,
            'build_number' => $release->build_number,
            'min_build_number' => $release->min_build_number,
            'store_url' => $release->store_url,
            'message_ar' => $release->message_ar,
            'message_en' => $release->message_en,
        ]);
    }
}
