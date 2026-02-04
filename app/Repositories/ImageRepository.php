<?php

namespace App\Repositories;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageRepository extends BaseRepository
{
    /**
     * Create a new ImageRepository instance.
     */
    public function __construct(Image $image)
    {
        parent::__construct($image);
    }

    /**
     * Upload an image file and create a record.
     *
     * @param UploadedFile $file
     * @param string|null $imageableType
     * @param int|null $imageableId
     * @param string|null $type
     * @param array $additionalData
     * @return Image
     */
    public function uploadImage(
        UploadedFile $file,
        ?string $imageableType = null,
        ?int $imageableId = null,
        ?string $type = null,
        array $additionalData = []
    ): Image {
        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Determine storage path based on type
        $path = $type ? "images/{$type}/{$filename}" : "images/{$filename}";
        
        // Store the file (always use 'public' disk for images)
        $disk = 'public';
        $storedPath = $file->storeAs(dirname($path), basename($path), $disk);
        
        // Get image dimensions if it's an image
        $dimensions = @getimagesize($file->getRealPath());
        
        // Prepare image data
        $imageData = array_merge([
            'path' => $storedPath,
            'disk' => $disk,
            'type' => $type,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null,
            'imageable_type' => $imageableType,
            'imageable_id' => $imageableId,
        ], $additionalData);
        
        return $this->create($imageData);
    }

    /**
     * Get images by imageable type and ID.
     *
     * @param string $imageableType
     * @param int $imageableId
     * @param string|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByImageable(string $imageableType, int $imageableId, ?string $type = null)
    {
        $query = $this->query()
            ->where('imageable_type', $imageableType)
            ->where('imageable_id', $imageableId)
            ->ordered();

        if ($type) {
            $query->ofType($type);
        }

        return $query->get();
    }

    /**
     * Get images by type.
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByType(string $type)
    {
        return $this->query()
            ->ofType($type)
            ->ordered()
            ->get();
    }

    /**
     * Get all images with optional filters.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters = [], bool $paginate = false)
    {
        $query = $this->query();

        // Filter by type
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        // Filter by imageable_type
        if (!empty($filters['imageable_type'])) {
            $query->where('imageable_type', $filters['imageable_type']);
        }

        // Filter by imageable_id
        if (!empty($filters['imageable_id'])) {
            $query->where('imageable_id', $filters['imageable_id']);
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Order by
        $query->orderBy('created_at', 'desc');

        return $paginate ? $query->paginate(15) : $query->get();
    }

    /**
     * Update image order.
     *
     * @param int $id
     * @param int $order
     * @return Image
     */
    public function updateOrder(int $id, int $order): Image
    {
        return $this->update($id, ['order' => $order]);
    }

    /**
     * Delete an image and its file.
     *
     * @param int $id
     * @return bool
     */
    public function deleteImage(int $id): bool
    {
        $image = $this->findById($id);

        if (!$image) {
            throw new \Exception("Image with ID {$id} not found");
        }

        // Delete the file from storage
        if (Storage::disk($image->disk)->exists($image->path)) {
            Storage::disk($image->disk)->delete($image->path);
        }

        return $image->delete();
    }

    /**
     * Attach multiple images to an imageable model.
     *
     * @param array $files
     * @param string $imageableType
     * @param int $imageableId
     * @param string|null $type
     * @return \Illuminate\Support\Collection
     */
    public function uploadMultiple(
        array $files,
        string $imageableType,
        int $imageableId,
        ?string $type = null
    ) {
        $images = collect();

        foreach ($files as $index => $file) {
            $image = $this->uploadImage(
                $file,
                $imageableType,
                $imageableId,
                $type,
                ['order' => $index]
            );
            $images->push($image);
        }

        return $images;
    }

    /**
     * Get statistics about images.
     *
     * @param int|null $clinicId
     * @return array
     */
    public function getStatistics(): array
    {
        $query = $this->query();

        // If clinic_id is provided, filter by imageable models that belong to the clinic
        // This would require joining with the imageable tables
        // For simplicity, we'll provide overall stats

        $totalImages = $query->count();
        $totalSize = $query->sum('size');

        // Group by type
        $byType = $this->query()
            ->selectRaw('type, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return [
            'total_images' => $totalImages,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1048576, 2),
            'by_type' => $byType,
        ];
    }
}
