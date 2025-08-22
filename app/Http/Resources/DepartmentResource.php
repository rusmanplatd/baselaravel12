<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'parent_department_id' => $this->parent_department_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'parent_department' => new DepartmentResource($this->whenLoaded('parentDepartment')),
            'child_departments' => DepartmentResource::collection($this->whenLoaded('childDepartments')),
            'job_positions' => JobPositionResource::collection($this->whenLoaded('jobPositions')),
            'job_positions_count' => $this->whenCounted('jobPositions'),
            'child_departments_count' => $this->whenCounted('childDepartments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
