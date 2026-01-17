<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'noteable_id',
        'noteable_type',
        'content',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noteable_id' => 'integer',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the parent noteable model (Patient, Case, etc.).
     */
    public function noteable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that created the note.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
