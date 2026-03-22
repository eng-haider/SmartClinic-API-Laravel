<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseCategory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category_type',
        'order',
        'item_cost',
        'without_detect_tooth',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'item_cost' => 'integer',
            'without_detect_tooth' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the cases in this category.
     */
    public function cases()
    {
        return $this->hasMany(CaseModel::class, 'case_categores_id');
    }

    /**
     * Check if this is a dental category.
     */
    public function isDental(): bool
    {
        return $this->category_type === 'dental';
    }

    /**
     * Check if this is a beauty category.
     */
    public function isBeauty(): bool
    {
        return $this->category_type === 'beauty';
    }

    /**
     * Check if this category requires tooth detection.
     * Only dental categories can require tooth detection.
     */
    public function requiresToothDetection(): bool
    {
        return $this->isDental() && !$this->without_detect_tooth;
    }

    /**
     * Scope a query to only include dental categories.
     */
    public function scopeDental($query)
    {
        return $query->where('category_type', 'dental');
    }

    /**
     * Scope a query to only include beauty categories.
     */
    public function scopeBeauty($query)
    {
        return $query->where('category_type', 'beauty');
    }
}
