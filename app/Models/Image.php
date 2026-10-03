<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
        'tooth_num',
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
        // Try to get the current tenant ID from the tenancy context
        if (function_exists('tenant') && tenant()) {
            $tenantId = tenant()->id;
            // Clinical files are private. A short-lived signed route lets an
            // authenticated dashboard display an image without making the
            // tenant file tree guessable or publicly cacheable.
            return URL::temporarySignedRoute('file.tenant.private', now()->addMinutes(10), [
                'tenant' => $tenantId,
                'path' => $this->path,
            ]);
        }
        
        // Fallback for non-tenant context (central database)
        $storageUrl = Storage::disk($this->disk)->url($this->path);
        
        if (!str_starts_with($storageUrl, 'http')) {
            return rtrim(config('app.url'), '/') . '/' . ltrim($storageUrl, '/');
        }
        
        return $storageUrl;
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
