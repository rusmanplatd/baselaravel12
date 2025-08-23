<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'guard_name' => $this->guard_name,
            'team_id' => $this->team_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Permissions (when loaded)
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name,
                    ];
                });
            }),
            
            // Users count (when loaded)
            'users_count' => $this->whenCounted('users'),
            
            // Organization (when team_id is set)
            'organization' => $this->when($this->team_id, function () {
                return [
                    'id' => $this->team_id,
                    'name' => optional($this->whenLoaded('team'))->name,
                ];
            }),
        ];
    }
}
