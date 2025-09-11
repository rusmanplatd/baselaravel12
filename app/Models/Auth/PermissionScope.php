<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class PermissionScope extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'sys_permission_scopes';

    protected $fillable = [
        'scope_type',
        'scope_id', 
        'parent_scope_type',
        'parent_scope_id',
        'scope_path',
        'inherits_permissions',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'scope_id' => 'string',
        'parent_scope_id' => 'string',
        'scope_path' => 'array',
        'inherits_permissions' => 'boolean',
        'metadata' => 'array',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
            
            // Auto-generate scope path if not set
            if (empty($model->scope_path)) {
                $model->scope_path = static::generateScopePath($model->scope_type, $model->scope_id, $model->parent_scope_type, $model->parent_scope_id);
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function scopedResource(): MorphTo
    {
        return $this->morphTo('scope', 'scope_type', 'scope_id');
    }

    public function parentScope(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_scope_id');
    }

    public function childScopes()
    {
        return $this->hasMany(self::class, 'parent_scope_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateScopePath(string $scopeType, string $scopeId, ?string $parentScopeType = null, ?string $parentScopeId = null): array
    {
        $path = [];
        
        // Build path from parent if provided
        if ($parentScopeType && $parentScopeId) {
            $parentScope = static::where('scope_type', $parentScopeType)
                ->where('scope_id', $parentScopeId)
                ->first();
                
            if ($parentScope && $parentScope->scope_path) {
                $path = $parentScope->scope_path;
            }
        }
        
        // Add current scope to path
        $path[] = [
            'type' => $scopeType,
            'id' => $scopeId
        ];
        
        return $path;
    }

    public function getFullScopePathAttribute(): array
    {
        return $this->scope_path ?? [];
    }

    public function isChildOf(string $scopeType, string $scopeId): bool
    {
        if (empty($this->scope_path)) {
            return false;
        }

        foreach ($this->scope_path as $pathItem) {
            if ($pathItem['type'] === $scopeType && $pathItem['id'] === $scopeId) {
                return true;
            }
        }

        return false;
    }

    public function getAncestorScopes(): array
    {
        return collect($this->scope_path)
            ->slice(0, -1) // Remove current scope
            ->toArray();
    }
}