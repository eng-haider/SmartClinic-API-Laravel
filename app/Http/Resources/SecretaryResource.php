<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecretaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'role' => 'secretary',
            'all_permissions' => $this->getAllPermissions()->pluck('name'),
            'direct_permissions' => $this->getDirectPermissions()->pluck('name'),
            'role_permissions' => $this->when(
                $request->routeIs('secretaries.show') || $request->routeIs('secretaries.index'),
                fn() => $this->getPermissionsViaRoles()->pluck('name')
            ),
            'permissions_count' => $this->getAllPermissions()->count(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
