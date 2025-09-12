<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'document_id',
        'content',
        'yjs_state',
        'created_by',
        'version',
        'changes',
        'metadata',
        'is_auto_save',
        'is_milestone',
        'milestone_name',
        'size',
        'word_count',
        'character_count',
    ];

    protected $casts = [
        'yjs_state' => 'binary',
        'changes' => 'array',
        'metadata' => 'array',
        'is_auto_save' => 'boolean',
        'is_milestone' => 'boolean',
        'size' => 'integer',
        'word_count' => 'integer',
        'character_count' => 'integer',
    ];

    protected $appends = [
        'human_size',
        'changes_summary',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeMilestones($query)
    {
        return $query->where('is_milestone', true);
    }

    public function scopeAutoSaves($query)
    {
        return $query->where('is_auto_save', true);
    }

    public function scopeManualSaves($query)
    {
        return $query->where('is_auto_save', false);
    }

    public function scopeByVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Helper methods
    public function isMilestone(): bool
    {
        return $this->is_milestone;
    }

    public function isAutoSave(): bool
    {
        return $this->is_auto_save;
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->formatBytes($this->size ?? strlen($this->content));
    }

    public function getChangesSummaryAttribute(): string
    {
        if (!$this->changes) {
            return 'No changes recorded';
        }

        $summary = [];
        
        if (isset($this->changes['additions'])) {
            $summary[] = "{$this->changes['additions']} additions";
        }
        
        if (isset($this->changes['deletions'])) {
            $summary[] = "{$this->changes['deletions']} deletions";
        }
        
        if (isset($this->changes['modifications'])) {
            $summary[] = "{$this->changes['modifications']} modifications";
        }

        return empty($summary) ? 'No changes recorded' : implode(', ', $summary);
    }

    public function restore(): bool
    {
        return $this->document->update([
            'content' => $this->content,
            'yjs_state' => $this->yjs_state,
            'version' => $this->document->version + 1,
            'last_edited_at' => now(),
            'last_edited_by' => auth()->id(),
        ]);
    }

    public function compare(DocumentRevision $other): array
    {
        // Basic comparison - can be enhanced with proper diff algorithms
        $thisWords = str_word_count($this->content, 1);
        $otherWords = str_word_count($other->content, 1);
        
        $additions = array_diff($thisWords, $otherWords);
        $deletions = array_diff($otherWords, $thisWords);
        
        return [
            'additions' => count($additions),
            'deletions' => count($deletions),
            'word_diff' => $this->word_count - $other->word_count,
            'size_diff' => $this->size - $other->size,
            'version_diff' => $this->version - $other->version,
        ];
    }

    public function createMilestone(string $name): bool
    {
        return $this->update([
            'is_milestone' => true,
            'milestone_name' => $name,
        ]);
    }

    protected function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($revision) {
            if (!isset($revision->size)) {
                $revision->size = strlen($revision->content);
            }
            
            if (!isset($revision->word_count)) {
                $revision->word_count = str_word_count(strip_tags($revision->content));
            }
            
            if (!isset($revision->character_count)) {
                $revision->character_count = strlen(strip_tags($revision->content));
            }
        });
    }
}