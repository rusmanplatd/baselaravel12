<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MeetingTemplateCategory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'organization_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            MeetingTemplate::class,
            'meeting_template_category_assignments',
            'category_id',
            'template_id'
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeByOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // Helper Methods
    public function getTemplateCount(): int
    {
        return $this->templates()->active()->count();
    }

    public function getCategoryInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'template_count' => $this->getTemplateCount(),
            'sort_order' => $this->sort_order,
        ];
    }
}