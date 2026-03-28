<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabasePool extends Model
{
    protected $table = 'database_pool';

    protected $fillable = [
        'db_name',
        'db_username',
        'db_password',
        'status',
        'tenant_id',
        'claimed_at',
    ];

    protected $hidden = ['db_password'];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    /**
     * Atomically claim one available database from the pool.
     * Uses a DB-level lock so concurrent signups never get the same slot.
     *
     * @throws \RuntimeException when the pool is empty
     */
    public static function claim(string $tenantId): self
    {
        return \DB::transaction(function () use ($tenantId) {
            $slot = static::where('status', 'available')
                ->lockForUpdate()
                ->first();

            if (!$slot) {
                throw new \RuntimeException(
                    'No available databases in the pool. ' .
                    'Please add more databases via Hostinger hPanel and run the DatabasePoolSeeder.'
                );
            }

            $slot->update([
                'status'      => 'used',
                'tenant_id'   => $tenantId,
                'claimed_at'  => now(),
            ]);

            return $slot->fresh();
        });
    }

    /** Number of available slots remaining. */
    public static function availableCount(): int
    {
        return static::where('status', 'available')->count();
    }
}
