<?php

namespace App\Modules\Orthodontics\Cbct;

use App\Modules\Orthodontics\Models\OrthodonticCbctInstance;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where DICOM instances live. Laravel stays the only way in: the browser
 * never talks to the storage directly, and every request is authorised by
 * the orthodontics access checks first.
 */
interface CbctStorageDriver
{
    public function name(): string;

    /**
     * Store one instance; returns the fields to save on the instance row
     * (path or orthanc_instance_id) and, for Orthanc, the parent study id.
     */
    public function store(OrthodonticCbctStudy $study, string $localPath, array $header): array;

    public function stream(OrthodonticCbctInstance $instance): Response;

    /** Remove partial uploads of an abandoned study. */
    public function discard(OrthodonticCbctStudy $study): void;
}
