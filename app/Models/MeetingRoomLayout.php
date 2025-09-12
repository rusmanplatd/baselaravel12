<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MeetingRoomLayout extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'created_by',
        'layout_name',
        'description',
        'layout_type',
        'is_default',
        'is_active',
        'layout_config',
        'responsive_breakpoints',
        'participant_positioning',
        'background_type',
        'background_value',
        'theme_settings',
        'branding_elements',
        'auto_arrange_participants',
        'max_visible_participants',
        'highlight_active_speaker',
        'show_participant_names',
        'show_participant_status',
        'grid_columns',
        'grid_rows',
        'grid_aspect_ratio',
        'fill_grid_dynamically',
        'speaker_position',
        'speaker_size',
        'show_speaker_thumbnails',
        'thumbnail_count',
        'content_position',
        'content_size',
        'participants_position',
        'participants_size',
        'custom_regions',
        'region_rules',
        'animation_settings',
        'enable_layout_switching',
        'allow_participant_pinning',
        'enable_spotlight_mode',
        'show_layout_controls',
        'high_contrast_mode',
        'reduce_animations',
        'accessibility_settings',
        'video_quality',
        'adaptive_quality',
        'frame_rate',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'layout_config' => 'array',
        'responsive_breakpoints' => 'array',
        'participant_positioning' => 'array',
        'theme_settings' => 'array',
        'branding_elements' => 'array',
        'auto_arrange_participants' => 'boolean',
        'max_visible_participants' => 'integer',
        'highlight_active_speaker' => 'boolean',
        'show_participant_names' => 'boolean',
        'show_participant_status' => 'boolean',
        'grid_columns' => 'integer',
        'grid_rows' => 'integer',
        'fill_grid_dynamically' => 'boolean',
        'show_speaker_thumbnails' => 'boolean',
        'thumbnail_count' => 'integer',
        'custom_regions' => 'array',
        'region_rules' => 'array',
        'animation_settings' => 'array',
        'enable_layout_switching' => 'boolean',
        'allow_participant_pinning' => 'boolean',
        'enable_spotlight_mode' => 'boolean',
        'show_layout_controls' => 'boolean',
        'high_contrast_mode' => 'boolean',
        'reduce_animations' => 'boolean',
        'accessibility_settings' => 'array',
        'adaptive_quality' => 'boolean',
        'frame_rate' => 'integer',
    ];

    // Relationships
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingCalendarIntegration::class, 'meeting_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(MeetingLayoutElement::class, 'layout_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(MeetingLayoutHistory::class, 'layout_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('layout_type', $type);
    }

    public function scopeByMeeting($query, string $meetingId)
    {
        return $query->where('meeting_id', $meetingId);
    }

    // Layout Management Methods
    public function makeDefault(): void
    {
        // Remove default flag from other layouts in this meeting
        self::where('meeting_id', $this->meeting_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this layout as default
        $this->update(['is_default' => true]);
    }

    public function applyToMeeting(): array
    {
        // Record layout application in history
        $this->recordLayoutChange('applied', null, $this->getLayoutConfiguration());

        // Return configuration for frontend
        return $this->getLayoutConfiguration();
    }

    public function getLayoutConfiguration(): array
    {
        $baseConfig = [
            'id' => $this->id,
            'name' => $this->layout_name,
            'type' => $this->layout_type,
            'config' => $this->layout_config ?? [],
        ];

        // Add type-specific configurations
        switch ($this->layout_type) {
            case 'grid':
                $baseConfig['grid'] = $this->getGridConfiguration();
                break;
            case 'speaker':
                $baseConfig['speaker'] = $this->getSpeakerConfiguration();
                break;
            case 'presentation':
                $baseConfig['presentation'] = $this->getPresentationConfiguration();
                break;
            case 'custom':
                $baseConfig['custom'] = $this->getCustomConfiguration();
                break;
        }

        // Add common settings
        $baseConfig['behavior'] = $this->getBehaviorConfiguration();
        $baseConfig['theme'] = $this->getThemeConfiguration();
        $baseConfig['accessibility'] = $this->getAccessibilityConfiguration();
        $baseConfig['performance'] = $this->getPerformanceConfiguration();

        return $baseConfig;
    }

    protected function getGridConfiguration(): array
    {
        return [
            'columns' => $this->grid_columns,
            'rows' => $this->grid_rows,
            'aspect_ratio' => $this->grid_aspect_ratio,
            'fill_dynamically' => $this->fill_grid_dynamically,
            'max_visible' => $this->max_visible_participants,
        ];
    }

    protected function getSpeakerConfiguration(): array
    {
        return [
            'speaker_position' => $this->speaker_position,
            'speaker_size' => $this->speaker_size,
            'show_thumbnails' => $this->show_speaker_thumbnails,
            'thumbnail_count' => $this->thumbnail_count,
            'highlight_active' => $this->highlight_active_speaker,
        ];
    }

    protected function getPresentationConfiguration(): array
    {
        return [
            'content_position' => $this->content_position,
            'content_size' => $this->content_size,
            'participants_position' => $this->participants_position,
            'participants_size' => $this->participants_size,
        ];
    }

    protected function getCustomConfiguration(): array
    {
        return [
            'regions' => $this->custom_regions ?? [],
            'region_rules' => $this->region_rules ?? [],
            'elements' => $this->elements()->get()->map(function ($element) {
                return $element->getElementConfiguration();
            })->toArray(),
        ];
    }

    protected function getBehaviorConfiguration(): array
    {
        return [
            'auto_arrange' => $this->auto_arrange_participants,
            'allow_pinning' => $this->allow_participant_pinning,
            'spotlight_mode' => $this->enable_spotlight_mode,
            'show_controls' => $this->show_layout_controls,
            'layout_switching' => $this->enable_layout_switching,
            'show_names' => $this->show_participant_names,
            'show_status' => $this->show_participant_status,
        ];
    }

    protected function getThemeConfiguration(): array
    {
        return [
            'background_type' => $this->background_type,
            'background_value' => $this->background_value,
            'theme_settings' => $this->theme_settings ?? [],
            'branding' => $this->branding_elements ?? [],
            'animations' => $this->animation_settings ?? [],
        ];
    }

    protected function getAccessibilityConfiguration(): array
    {
        return [
            'high_contrast' => $this->high_contrast_mode,
            'reduce_animations' => $this->reduce_animations,
            'settings' => $this->accessibility_settings ?? [],
        ];
    }

    protected function getPerformanceConfiguration(): array
    {
        return [
            'video_quality' => $this->video_quality,
            'adaptive_quality' => $this->adaptive_quality,
            'frame_rate' => $this->frame_rate,
        ];
    }

    // Layout Switching Methods
    public function switchToLayout(MeetingRoomLayout $newLayout, User $changedBy = null): bool
    {
        if ($newLayout->meeting_id !== $this->meeting_id) {
            return false;
        }

        $previousConfig = $this->getLayoutConfiguration();
        $newConfig = $newLayout->getLayoutConfiguration();

        // Record the layout switch
        $newLayout->recordLayoutChange(
            'switched',
            $previousConfig,
            $newConfig,
            $changedBy
        );

        // Apply the new layout
        $newLayout->applyToMeeting();

        return true;
    }

    public function recordLayoutChange(
        string $changeType,
        ?array $previousConfig = null,
        ?array $newConfig = null,
        ?User $changedBy = null
    ): void {
        $this->history()->create([
            'meeting_id' => $this->meeting_id,
            'changed_by' => $changedBy?->id ?? auth()->id(),
            'change_type' => $changeType,
            'previous_config' => $previousConfig,
            'new_config' => $newConfig ?? $this->getLayoutConfiguration(),
            'participant_count' => $this->meeting->getActiveAttendeeCount(),
            'was_automatic' => !$changedBy && !auth()->id(),
        ]);
    }

    // Responsive Layout Methods
    public function getResponsiveConfiguration(string $breakpoint = 'desktop'): array
    {
        $baseConfig = $this->getLayoutConfiguration();
        $responsiveSettings = $this->responsive_breakpoints[$breakpoint] ?? [];

        return array_merge_recursive($baseConfig, $responsiveSettings);
    }

    public function updateResponsiveBreakpoint(string $breakpoint, array $config): void
    {
        $breakpoints = $this->responsive_breakpoints ?? [];
        $breakpoints[$breakpoint] = $config;
        
        $this->update(['responsive_breakpoints' => $breakpoints]);
    }

    // Participant Positioning Methods
    public function arrangeParticipants(array $participantIds, ?array $customArrangement = null): array
    {
        if (!$this->auto_arrange_participants && !$customArrangement) {
            return $this->participant_positioning ?? [];
        }

        $arrangement = match ($this->layout_type) {
            'grid' => $this->arrangeInGrid($participantIds),
            'speaker' => $this->arrangeForSpeaker($participantIds),
            'presentation' => $this->arrangeForPresentation($participantIds),
            'custom' => $customArrangement ?? $this->arrangeCustom($participantIds),
            default => $this->arrangeInGrid($participantIds),
        };

        $this->update(['participant_positioning' => $arrangement]);

        return $arrangement;
    }

    protected function arrangeInGrid(array $participantIds): array
    {
        $totalParticipants = min(count($participantIds), $this->max_visible_participants);
        $columns = $this->grid_columns ?? $this->calculateOptimalGridColumns($totalParticipants);
        $rows = $this->grid_rows ?? ceil($totalParticipants / $columns);

        $arrangement = [];
        foreach (array_slice($participantIds, 0, $totalParticipants) as $index => $participantId) {
            $row = intval($index / $columns);
            $col = $index % $columns;
            
            $arrangement[] = [
                'participant_id' => $participantId,
                'position' => [
                    'row' => $row,
                    'column' => $col,
                    'width' => 100 / $columns,
                    'height' => 100 / $rows,
                ],
                'z_index' => 1,
            ];
        }

        return $arrangement;
    }

    protected function arrangeForSpeaker(array $participantIds): array
    {
        $arrangement = [];
        
        // Main speaker (first participant or active speaker)
        if (!empty($participantIds)) {
            $speakerId = $participantIds[0];
            $arrangement[] = [
                'participant_id' => $speakerId,
                'position' => $this->getSpeakerMainPosition(),
                'z_index' => 10,
                'role' => 'main_speaker',
            ];

            // Thumbnails for other participants
            $thumbnailParticipants = array_slice($participantIds, 1, $this->thumbnail_count);
            foreach ($thumbnailParticipants as $index => $participantId) {
                $arrangement[] = [
                    'participant_id' => $participantId,
                    'position' => $this->getThumbnailPosition($index),
                    'z_index' => 5,
                    'role' => 'thumbnail',
                ];
            }
        }

        return $arrangement;
    }

    protected function arrangeForPresentation(array $participantIds): array
    {
        $arrangement = [];
        
        // Arrange participants in sidebar/strip
        foreach (array_slice($participantIds, 0, $this->max_visible_participants) as $index => $participantId) {
            $arrangement[] = [
                'participant_id' => $participantId,
                'position' => $this->getParticipantStripPosition($index),
                'z_index' => 1,
                'role' => 'participant',
            ];
        }

        return $arrangement;
    }

    protected function arrangeCustom(array $participantIds): array
    {
        $arrangement = [];
        $regions = $this->custom_regions ?? [];
        
        foreach ($regions as $regionId => $region) {
            $regionRules = $this->region_rules[$regionId] ?? [];
            $maxParticipants = $regionRules['max_participants'] ?? 1;
            
            // Assign participants to this region based on rules
            $assignedParticipants = array_slice(
                $participantIds, 
                count($arrangement), 
                $maxParticipants
            );

            foreach ($assignedParticipants as $index => $participantId) {
                $arrangement[] = [
                    'participant_id' => $participantId,
                    'position' => $this->calculateCustomPosition($region, $index),
                    'z_index' => $region['z_index'] ?? 1,
                    'region' => $regionId,
                ];
            }
        }

        return $arrangement;
    }

    // Helper Methods
    protected function calculateOptimalGridColumns(int $participantCount): int
    {
        if ($participantCount <= 1) return 1;
        if ($participantCount <= 4) return 2;
        if ($participantCount <= 9) return 3;
        if ($participantCount <= 16) return 4;
        return ceil(sqrt($participantCount));
    }

    protected function getSpeakerMainPosition(): array
    {
        $sizeConfig = match ($this->speaker_size) {
            'small' => ['width' => 40, 'height' => 40],
            'medium' => ['width' => 60, 'height' => 60],
            'large' => ['width' => 80, 'height' => 80],
            'full' => ['width' => 100, 'height' => 100],
            default => ['width' => 80, 'height' => 80],
        };

        $positionConfig = match ($this->speaker_position) {
            'center' => ['x' => 50 - $sizeConfig['width'] / 2, 'y' => 50 - $sizeConfig['height'] / 2],
            'left' => ['x' => 5, 'y' => 50 - $sizeConfig['height'] / 2],
            'right' => ['x' => 95 - $sizeConfig['width'], 'y' => 50 - $sizeConfig['height'] / 2],
            'top' => ['x' => 50 - $sizeConfig['width'] / 2, 'y' => 5],
            'bottom' => ['x' => 50 - $sizeConfig['width'] / 2, 'y' => 95 - $sizeConfig['height']],
            default => ['x' => 10, 'y' => 10],
        };

        return array_merge($positionConfig, $sizeConfig);
    }

    protected function getThumbnailPosition(int $index): array
    {
        $thumbnailSize = 15; // 15% of container
        $spacing = 2;
        
        return [
            'x' => 5,
            'y' => 5 + ($index * ($thumbnailSize + $spacing)),
            'width' => $thumbnailSize,
            'height' => $thumbnailSize,
        ];
    }

    protected function getParticipantStripPosition(int $index): array
    {
        $participantSize = match ($this->participants_size) {
            'small' => 15,
            'medium' => 25,
            'large' => 35,
            default => 20,
        };

        $isVertical = in_array($this->participants_position, ['left', 'right']);
        
        if ($isVertical) {
            return [
                'x' => $this->participants_position === 'left' ? 5 : 95 - $participantSize,
                'y' => 5 + ($index * ($participantSize + 2)),
                'width' => $participantSize,
                'height' => $participantSize,
            ];
        } else {
            return [
                'x' => 5 + ($index * ($participantSize + 2)),
                'y' => $this->participants_position === 'top' ? 5 : 95 - $participantSize,
                'width' => $participantSize,
                'height' => $participantSize,
            ];
        }
    }

    protected function calculateCustomPosition(array $region, int $participantIndex): array
    {
        // Calculate position within the custom region
        $regionWidth = $region['width'] ?? 100;
        $regionHeight = $region['height'] ?? 100;
        
        // Simple grid within region for multiple participants
        $participantsInRegion = $region['max_participants'] ?? 1;
        $columns = ceil(sqrt($participantsInRegion));
        $rows = ceil($participantsInRegion / $columns);
        
        $col = $participantIndex % $columns;
        $row = intval($participantIndex / $columns);
        
        return [
            'x' => ($region['x'] ?? 0) + ($col * $regionWidth / $columns),
            'y' => ($region['y'] ?? 0) + ($row * $regionHeight / $rows),
            'width' => $regionWidth / $columns,
            'height' => $regionHeight / $rows,
        ];
    }

    // Layout Analytics
    public function getUsageAnalytics(): array
    {
        $historyData = $this->history()
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        return [
            'total_uses' => $historyData->where('change_type', 'applied')->count(),
            'avg_duration' => $historyData->avg('duration_active_seconds'),
            'avg_participants' => $historyData->avg('participant_count'),
            'most_used_breakpoint' => $this->getMostUsedBreakpoint(),
            'performance_score' => $this->calculatePerformanceScore(),
            'user_satisfaction' => $this->calculateUserSatisfaction(),
        ];
    }

    protected function getMostUsedBreakpoint(): ?string
    {
        // This would require tracking breakpoint usage in history
        return 'desktop'; // Placeholder
    }

    protected function calculatePerformanceScore(): float
    {
        // Calculate based on technical performance metrics
        return 0.85; // Placeholder
    }

    protected function calculateUserSatisfaction(): float
    {
        // Calculate based on user feedback and usage patterns
        return 0.90; // Placeholder
    }

    public static function createFromPreset(MeetingLayoutPreset $preset, string $meetingId): self
    {
        $presetConfig = $preset->preset_config;
        
        return self::create([
            'meeting_id' => $meetingId,
            'created_by' => auth()->id(),
            'layout_name' => $preset->preset_name,
            'description' => $preset->description,
            'layout_type' => $preset->layout_type,
            'layout_config' => $presetConfig,
            'is_default' => false,
            'is_active' => true,
        ]);
    }
}