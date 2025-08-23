<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Organization-specific data (when loaded via pivot)
            'membership' => $this->whenPivotLoaded('organization_memberships', function () {
                return [
                    'id' => $this->pivot->id,
                    'organization_id' => $this->pivot->organization_id,
                    'organization_unit_id' => $this->pivot->organization_unit_id,
                    'organization_position_id' => $this->pivot->organization_position_id,
                    'membership_type' => $this->pivot->membership_type,
                    'start_date' => $this->pivot->start_date,
                    'end_date' => $this->pivot->end_date,
                    'status' => $this->pivot->status,
                    'additional_roles' => $this->pivot->additional_roles,
                    'created_at' => $this->pivot->created_at,
                    'updated_at' => $this->pivot->updated_at,
                ];
            }),
            
            // Roles (when loaded)
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            
            // Organizations (when loaded)
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
        ];
    }
}
