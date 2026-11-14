<?php

namespace App\Modules\Orthodontics\Services;

use App\Models\CaseModel;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keeps original uploads on the tenant's private disk. Files are never
 * overwritten: a new upload is a new file, and deleting a record only
 * soft-deletes it.
 */
class OrthodonticFileStorage
{
    public function __construct(private OrthodonticsAudit $audit)
    {
    }

    public function disk(): string
    {
        return (string) config('orthodontics.disk', 'local');
    }

    public function store(CaseModel $case, UploadedFile $upload, string $kind, array $meta = []): OrthodonticFile
    {
        $ext = strtolower($upload->getClientOriginalExtension() ?: $upload->extension() ?: '');
        $isModel = in_array($kind, OrthodonticFile::MODEL_KINDS, true);
        $allowed = $isModel ? OrthodonticFile::MODEL_EXTENSIONS : OrthodonticFile::IMAGE_EXTENSIONS;

        if (!in_array($ext, $allowed, true)) {
            throw new OrthodonticsException('Unsupported file type for ' . $kind . '. Allowed: ' . implode(', ', $allowed), 422, 'unsupported_file');
        }

        $realPath = $upload->getRealPath();
        $width = $height = null;
        if ($isModel) {
            $meta = array_merge($this->sniffModel($realPath, $ext), $meta);
            // Units are never assumed: the doctor confirms them before any
            // millimetre value is shown.
            $meta['units_confirmed'] = false;
            $meta['scale_to_mm'] = null;
        } else {
            $info = @getimagesize($realPath);
            if (!$info) {
                throw new OrthodonticsException('The file is not a readable image.', 422, 'unsupported_file');
            }
            [$width, $height] = $info;
        }

        $dir = 'orthodontics/case-' . $case->id . '/' . $kind;
        $name = Str::uuid()->toString() . '.' . $ext;
        $path = $upload->storeAs($dir, $name, $this->disk());
        if (!$path) {
            throw new OrthodonticsException('The file could not be stored.', 500, 'storage_failed');
        }

        $file = OrthodonticFile::create([
            'case_id' => $case->id,
            'kind' => $kind,
            'disk' => $this->disk(),
            'path' => $path,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 250),
            'mime_type' => $upload->getMimeType(),
            'extension' => $ext,
            'size' => (int) $upload->getSize(),
            'sha256' => hash_file('sha256', $realPath) ?: null,
            'width' => $width,
            'height' => $height,
            'meta' => $meta ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit->log($case->id, 'file.uploaded', $file, ['kind' => $kind, 'size' => $file->size]);

        return $file;
    }

    /**
     * Stream the original file. Responses are private and never cached.
     */
    public function stream(OrthodonticFile $file): StreamedResponse
    {
        $disk = Storage::disk($file->disk);
        if (!$disk->exists($file->path)) {
            throw OrthodonticsException::notFound('File');
        }

        $this->audit->log($file->case_id, 'file.viewed', $file);

        $mime = $file->isModel() ? 'application/octet-stream' : ($file->mime_type ?: 'application/octet-stream');

        return $disk->response($file->path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Light format check + basic facts about a mesh, without loading it all.
     */
    private function sniffModel(string $path, string $ext): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new OrthodonticsException('The model file could not be read.', 422, 'unsupported_file');
        }
        $head = fread($handle, 512) ?: '';
        $size = filesize($path) ?: 0;
        fclose($handle);

        $meta = ['format' => $ext];
        if ($ext === 'stl') {
            // Binary STL: 80-byte header, uint32 triangle count, 50 bytes per triangle.
            if (strlen($head) >= 84) {
                $count = unpack('V', substr($head, 80, 4))[1];
                if ($size === 84 + $count * 50) {
                    return $meta + ['encoding' => 'binary', 'triangles' => $count];
                }
            }
            if (str_starts_with(ltrim($head), 'solid')) {
                return $meta + ['encoding' => 'ascii'];
            }
            throw new OrthodonticsException('The file is not a valid STL model.', 422, 'unsupported_file');
        }
        if ($ext === 'ply') {
            if (!str_starts_with($head, 'ply')) {
                throw new OrthodonticsException('The file is not a valid PLY model.', 422, 'unsupported_file');
            }
            if (preg_match('/format\s+(\S+)/', $head, $m)) {
                $meta['encoding'] = $m[1];
            }
            if (preg_match('/element\s+vertex\s+(\d+)/', $head, $m)) {
                $meta['vertices'] = (int) $m[1];
            }
            if (preg_match('/element\s+face\s+(\d+)/', $head, $m)) {
                $meta['faces'] = (int) $m[1];
            }

            return $meta;
        }
        // OBJ is plain text with "v x y z" vertex lines.
        if (!preg_match('/^\s*(v|vn|vt|f|o|g|mtllib|#)\s/m', $head)) {
            throw new OrthodonticsException('The file is not a valid OBJ model.', 422, 'unsupported_file');
        }

        return $meta + ['encoding' => 'ascii'];
    }
}
