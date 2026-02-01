<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'address',
        'rx_img',
        'whatsapp_template_sid',
        'whatsapp_message_count',
        'whatsapp_phone',
        'show_image_case',
        'doctor_mony',
        'teeth_v2',
        'send_msg',
        'show_rx_id',
        'logo',
        'api_whatsapp',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_message_count' => 'integer',
            'doctor_mony' => 'integer',
            'show_image_case' => 'boolean',
            'teeth_v2' => 'boolean',
            'send_msg' => 'boolean',
            'show_rx_id' => 'boolean',
            'api_whatsapp' => 'boolean',
            'data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get custom columns for the tenant.
     * These columns will be stored directly in the tenants table.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'address',
            'rx_img',
            'whatsapp_template_sid',
            'whatsapp_message_count',
            'whatsapp_phone',
            'show_image_case',
            'doctor_mony',
            'teeth_v2',
            'send_msg',
            'show_rx_id',
            'logo',
            'api_whatsapp',
        ];
    }
}
