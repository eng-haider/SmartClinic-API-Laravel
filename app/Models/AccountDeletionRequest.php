<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A user's request to delete their account. Lives in the central database so
 * the purge job can find it without knowing which clinic to look in.
 */
class AccountDeletionRequest extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'central_user_id',
        'tenant_user_id',
        'phone',
        'deletes_clinic',
        'purge_after',
        'purged_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'deletes_clinic' => 'boolean',
            'purge_after' => 'datetime',
            'purged_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNull('purged_at')
            ->whereNull('cancelled_at')
            ->where('purge_after', '<=', now());
    }
}
