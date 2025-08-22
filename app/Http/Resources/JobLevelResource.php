<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobLevelResource extends JsonResource
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
            'description' => $this->description,
            'level_order' => $this->level_order,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'is_active' => $this->is_active,
            'job_positions' => JobPositionResource::collection($this->whenLoaded('jobPositions')),
            'job_positions_count' => $this->whenCounted('jobPositions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
