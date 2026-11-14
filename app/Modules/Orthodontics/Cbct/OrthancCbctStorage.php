<?php

namespace App\Modules\Orthodontics\Cbct;

use App\Modules\Orthodontics\Models\OrthodonticCbctInstance;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forwards instances to an Orthanc server (GPLv3, run as a separate service
 * on a private network - see docs). Orthanc must not be reachable from the
 * internet: Laravel authenticates, authorises and proxies every read.
 *
 * Orthanc is shared by the clinics on one deployment, so each study is also
 * labelled with its tenant (Orthanc >= 1.12) for operators; access control
 * itself is done by Laravel, which only ever asks Orthanc for instance ids
 * recorded in the requesting clinic's own database.
 */
class OrthancCbctStorage implements CbctStorageDriver
{
    public function name(): string
    {
        return 'orthanc';
    }

    private function client(): PendingRequest
    {
        $cfg = config('orthodontics.cbct.orthanc');
        if (empty($cfg['url'])) {
            throw new OrthodonticsException('Orthanc is not configured (ORTHANC_URL).', 503, 'orthanc_not_configured');
        }
        $client = Http::baseUrl(rtrim($cfg['url'], '/'))->timeout((int) ($cfg['timeout'] ?? 120));
        if (!empty($cfg['username'])) {
            $client = $client->withBasicAuth($cfg['username'], (string) $cfg['password']);
        }

        return $client;
    }

    public function store(OrthodonticCbctStudy $study, string $localPath, array $header): array
    {
        $response = $this->client()
            ->withBody(file_get_contents($localPath), 'application/dicom')
            ->post('/instances');

        if (!$response->successful() || !$response->json('ID')) {
            Log::warning('Orthanc rejected an instance', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new OrthodonticsException('The imaging server rejected the DICOM file.', 502, 'orthanc_rejected');
        }

        $studyId = $response->json('ParentStudy');
        if ($studyId && function_exists('tenant') && tenant()) {
            try {
                $this->client()->put('/studies/' . $studyId . '/labels/tenant-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) tenant('id')));
            } catch (\Throwable) {
                // Labels need Orthanc >= 1.12; they are informational only.
            }
        }

        return ['orthanc_instance_id' => $response->json('ID'), 'orthanc_study_id' => $studyId];
    }

    public function stream(OrthodonticCbctInstance $instance): Response
    {
        if (!$instance->orthanc_instance_id) {
            throw OrthodonticsException::notFound('DICOM instance');
        }
        $response = $this->client()->get('/instances/' . rawurlencode($instance->orthanc_instance_id) . '/file');
        if (!$response->successful()) {
            throw OrthodonticsException::notFound('DICOM instance');
        }

        return response($response->body(), 200, [
            'Content-Type' => 'application/dicom',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function discard(OrthodonticCbctStudy $study): void
    {
        // Instances of a study that never completed are removed from Orthanc.
        foreach ($study->instances()->whereNotNull('orthanc_instance_id')->pluck('orthanc_instance_id') as $id) {
            try {
                $this->client()->delete('/instances/' . rawurlencode($id));
            } catch (\Throwable $e) {
                Log::warning('Orthanc cleanup failed', ['instance' => $id, 'error' => $e->getMessage()]);
            }
        }
    }
}
