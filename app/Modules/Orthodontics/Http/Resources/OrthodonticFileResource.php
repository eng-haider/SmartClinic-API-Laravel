<?php

namespace App\Modules\Orthodontics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthodonticFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'kind' => $this->kind,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'width' => $this->width,
            'height' => $this->height,
            'meta' => $this->meta ?? (object) [],
            'uploaded_by' => $this->uploaded_by,
            // Authenticated download through the API - there is no public URL.
            'content_path' => 'cases/' . $this->case_id . '/orthodontics/files/' . $this->id . '/content',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
