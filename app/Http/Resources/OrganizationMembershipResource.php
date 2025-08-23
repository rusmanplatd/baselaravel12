<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMembershipResource extends JsonResource
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
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'organization_unit_id' => $this->organization_unit_id,
            'organization_position_id' => $this->organization_position_id,
            'membership_type' => $this->membership_type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'additional_roles' => $this->additional_roles,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed attributes
            'duration' => $this->duration,
            'duration_in_years' => $this->duration_in_years,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_upcoming' => $this->isUpcoming(),
            'is_board_membership' => $this->isBoardMembership(),
            'is_executive_membership' => $this->isExecutiveMembership(),
            'is_management_membership' => $this->isManagementMembership(),
            'full_position_title' => $this->full_position_title,

            // Related models (when loaded)
            'user' => new UserResource($this->whenLoaded('user')),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'organization_unit' => $this->whenLoaded('organizationUnit', function () {
                return [
                    'id' => $this->organizationUnit->id,
                    'name' => $this->organizationUnit->name,
                    'unit_type' => $this->organizationUnit->unit_type,
                ];
            }),
            'organization_position' => $this->whenLoaded('organizationPosition', function () {
                return [
                    'id' => $this->organizationPosition->id,
                    'title' => $this->organizationPosition->title,
                    'position_level' => $this->organizationPosition->position_level,
                    'full_title' => $this->organizationPosition->full_title,
                ];
            }),
        ];
    }
}
