<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'disk' => $this->disk,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->formatFileSize($this->size),
            'width' => $this->width,
            'height' => $this->height,
            'dimensions' => $this->width && $this->height ? "{$this->width}x{$this->height}" : null,
            'alt_text' => $this->alt_text,
            'order' => $this->order,
            'imageable_type' => $this->imageable_type,
            'imageable_id' => $this->imageable_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Format file size to human-readable format.
     *
     * @param int|null $bytes
     * @return string|null
     */
    private function formatFileSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
