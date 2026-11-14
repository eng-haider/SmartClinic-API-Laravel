<?php

namespace App\Modules\Orthodontics\Cbct;

use App\Modules\Orthodontics\Models\OrthodonticCbctInstance;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * DICOM files on the tenant's private disk (storage/tenant{id}/app/...).
 * Works on any hosting; no extra service needed.
 */
class LocalCbctStorage implements CbctStorageDriver
{
    public function name(): string
    {
        return 'local';
    }

    private function disk()
    {
        return Storage::disk((string) config('orthodontics.disk', 'local'));
    }

    private function dir(OrthodonticCbctStudy $study): string
    {
        return 'orthodontics/case-' . $study->case_id . '/cbct/study-' . $study->id;
    }

    public function store(OrthodonticCbctStudy $study, string $localPath, array $header): array
    {
        $name = Str::uuid()->toString() . '.dcm';
        $stream = fopen($localPath, 'rb');
        $ok = $this->disk()->writeStream($this->dir($study) . '/' . $name, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (!$ok) {
            throw new OrthodonticsException('The DICOM file could not be stored.', 500, 'storage_failed');
        }

        return ['path' => $this->dir($study) . '/' . $name];
    }

    public function stream(OrthodonticCbctInstance $instance): Response
    {
        if (!$instance->path || !$this->disk()->exists($instance->path)) {
            throw OrthodonticsException::notFound('DICOM instance');
        }

        return $this->disk()->response($instance->path, null, [
            'Content-Type' => 'application/dicom',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function discard(OrthodonticCbctStudy $study): void
    {
        $this->disk()->deleteDirectory($this->dir($study));
    }
}
