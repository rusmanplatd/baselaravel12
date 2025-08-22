<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPositionResource extends JsonResource
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
            'department_id' => $this->department_id,
            'job_level_id' => $this->job_level_id,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'openings' => $this->openings,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'job_level' => new JobLevelResource($this->whenLoaded('jobLevel')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
