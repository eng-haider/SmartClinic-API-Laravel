<?php

namespace App\Modules\Orthodontics\Services;

use App\Modules\Orthodontics\Analysis\CephalometricCalculator;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Optional automated cephalometric landmark detection through a separately
 * configured provider. No model ships with Smart Clinic: with no provider
 * configured the feature reports itself unavailable, and manual tracing is
 * unaffected. Suggestions are returned to the doctor to accept or move -
 * they are never saved on their own and carry no confidence score.
 */
class LandmarkDetectionService
{
    public function provider(): ?string
    {
        $provider = config('orthodontics.landmark_detection.provider');

        return $provider === 'http' && config('orthodontics.landmark_detection.url') ? 'http' : null;
    }

    public function available(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * @return array<string, array{x: float, y: float}>
     */
    public function detect(OrthodonticFile $file): array
    {
        if (!$this->available()) {
            throw new OrthodonticsException('Automatic landmark detection is not configured for this installation. Place the landmarks manually.', 501, 'detection_unavailable', 'الكشف التلقائي عن النقاط غير مفعل. ضع النقاط يدوياً.');
        }

        $cfg = config('orthodontics.landmark_detection');
        $request = Http::timeout((int) $cfg['timeout']);
        if (!empty($cfg['token'])) {
            $request = $request->withToken($cfg['token']);
        }
        $response = $request
            ->attach('image', Storage::disk($file->disk)->get($file->path), 'ceph.' . ($file->extension ?: 'png'))
            ->post($cfg['url'], ['width' => $file->width, 'height' => $file->height]);

        if (!$response->successful()) {
            throw new OrthodonticsException('The landmark detection service failed.', 502, 'detection_failed');
        }

        $out = [];
        foreach ((array) $response->json('landmarks', []) as $code => $pt) {
            if (isset(CephalometricCalculator::LANDMARKS[$code], $pt['x'], $pt['y']) && is_numeric($pt['x']) && is_numeric($pt['y'])) {
                $x = (float) $pt['x'];
                $y = (float) $pt['y'];
                // Drop points outside the image rather than trusting them.
                if ($x >= 0 && $y >= 0 && (!$file->width || $x <= $file->width) && (!$file->height || $y <= $file->height)) {
                    $out[$code] = ['x' => $x, 'y' => $y];
                }
            }
        }

        return $out;
    }
}
