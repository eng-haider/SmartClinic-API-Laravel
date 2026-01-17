<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recipe_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'doctors_id',
        'clinics_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'doctors_id' => 'integer',
            'clinics_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the doctor that owns the recipe item.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctors_id');
    }

    /**
     * Get the clinic that owns the recipe item.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinics_id');
    }

    /**
     * Scope a query to filter by doctor.
     */
    public function scopeByDoctor($query, int $doctorId)
    {
        return $query->where('doctors_id', $doctorId);
    }

    /**
     * Scope a query to filter by clinic.
     */
    public function scopeByClinic($query, int $clinicId)
    {
        return $query->where('clinics_id', $clinicId);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearchByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    /**
     * Scope a query to order by name.
     */
    public function scopeOrderedByName($query)
    {
        return $query->orderBy('name');
    }
}
