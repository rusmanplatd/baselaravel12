<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'template_name',
        'template_type',
        'template_content',
        'template_settings',
        'is_default',
        'is_shared',
    ];

    protected $casts = [
        'template_settings' => 'array',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('template_type', $type);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeAvailableToUser($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('is_shared', true);
        });
    }

    // Helper methods
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isShared(): bool
    {
        return $this->is_shared;
    }

    public function isJsonTemplate(): bool
    {
        return $this->template_type === 'json';
    }

    public function isXmlTemplate(): bool
    {
        return $this->template_type === 'xml';
    }

    public function isHtmlTemplate(): bool
    {
        return $this->template_type === 'html';
    }

    public function isPdfTemplate(): bool
    {
        return $this->template_type === 'pdf';
    }

    public function getTemplateContent(): string
    {
        return $this->template_content;
    }

    public function getTemplateSettings(): array
    {
        return $this->template_settings ?? [];
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->template_settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->template_settings ?? [];
        $settings[$key] = $value;
        $this->update(['template_settings' => $settings]);
    }

    public function makeDefault(): void
    {
        // Remove default flag from other templates of the same type for this user
        self::where('user_id', $this->user_id)
            ->where('template_type', $this->template_type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this template as default
        $this->update(['is_default' => true]);
    }

    public function makeShared(): void
    {
        $this->update(['is_shared' => true]);
    }

    public function makePrivate(): void
    {
        $this->update(['is_shared' => false]);
    }

    public function canBeUsedBy(User $user): bool
    {
        return $this->user_id === $user->id || $this->is_shared;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function duplicate(User $newOwner, ?string $newName = null): self
    {
        $templateName = $newName ?: $this->template_name.' (Copy)';

        return self::create([
            'user_id' => $newOwner->id,
            'template_name' => $templateName,
            'template_type' => $this->template_type,
            'template_content' => $this->template_content,
            'template_settings' => $this->template_settings,
            'is_default' => false,
            'is_shared' => false,
        ]);
    }

    // Template content helpers
    public function supportsVariable(string $variable): bool
    {
        return str_contains($this->template_content, '{'.$variable.'}');
    }

    public function getRequiredVariables(): array
    {
        preg_match_all('/\{([^}]+)\}/', $this->template_content, $matches);

        return array_unique($matches[1]);
    }

    public function renderWithVariables(array $variables): string
    {
        $content = $this->template_content;

        foreach ($variables as $key => $value) {
            $content = str_replace('{'.$key.'}', $value, $content);
        }

        return $content;
    }
}
