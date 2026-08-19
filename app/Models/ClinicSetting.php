<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ClinicSetting extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the setting definition.
     */
    public function definition()
    {
        return $this->belongsTo(SettingDefinition::class, 'setting_key', 'setting_key');
    }

    /**
     * Get the casted value of the setting.
     */
    public function getValue()
    {
        return match ($this->setting_type) {
            'boolean' => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->setting_value,
            'json' => json_decode($this->setting_value, true),
            default => $this->setting_value,
        };
    }

    /**
     * Build a publicly reachable URL for a file stored on the tenant's public disk.
     *
     * Inside tenancy the public disk root is storage/tenant{id}/app/public, which the
     * public/storage symlink does not cover, so files are served through the
     * /file/tenant/{tenant}/{path} route (same approach as the Image model).
     */
    public static function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Value already stored as an absolute URL (older records) - leave it alone.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (function_exists('tenant') && tenant()) {
            return rtrim(config('app.url'), '/') . '/file/tenant/' . tenant()->id . '/' . $path;
        }

        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http')
            ? $url
            : rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    /**
     * Reduce a stored value back to a disk-relative path so the old file can be deleted,
     * whether it was saved as a bare path or as a full URL.
     */
    public static function fileStoragePath(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $path = $value;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
        }

        $path = ltrim(rawurldecode($path), '/');

        // Strip the serving prefixes: file/tenant/{tenant}/... or storage/...
        if (preg_match('#^file/tenant/[^/]+/(.+)$#', $path, $m)) {
            return $m[1];
        }

        if (str_starts_with($path, 'storage/')) {
            return substr($path, strlen('storage/'));
        }

        return $path;
    }

    /**
     * Scope a query to only include active settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by setting key.
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('setting_key', $key);
    }
}
