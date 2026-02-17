<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'path',
        'disk',
        'type',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'order',
        'imageable_id',
        'imageable_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the parent imageable model (Patient, Case, User, etc.).
     */
    public function imageable()
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL of the image.
     */
    public function getUrlAttribute(): string
    {
        // Get the actual storage path (which includes tenant folder for tenant context)
        $fullStoragePath = Storage::disk($this->disk)->path($this->path);
        
        // Get the base storage path
        $baseStoragePath = storage_path('');
        
        // Get the relative path from storage root
        $relativePath = str_replace($baseStoragePath, '', $fullStoragePath);
        $relativePath = ltrim($relativePath, '/');
        
        // Build the URL with the full storage path including tenant folder
        return rtrim(config('app.url'), '/') . '/storage/' . $relativePath;
    }

    /**
     * Get the full path of the image.
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Delete the image file when the model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function ($image) {
            if (Storage::disk($image->disk)->exists($image->path)) {
                Storage::disk($image->disk)->delete($image->path);
            }
        });
    }

    /**
     * Scope a query to only include images of a given type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to order images by their order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
