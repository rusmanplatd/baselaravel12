<?php

namespace App\Models\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'type',
        'scope_type',
        'scope_id',
        'is_global',
        'scope_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'name' => 'string',
        'guard_name' => 'string',
        'type' => 'int',
        'scope_type' => 'string',
        'scope_id' => 'string',
        'is_global' => 'boolean',
        'scope_path' => 'array',
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
            
            // Set is_global flag based on scope
            $model->is_global = empty($model->scope_type) && empty($model->scope_id);
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
            
            // Update is_global flag
            $model->is_global = empty($model->scope_type) && empty($model->scope_id);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'team_id');
    }

    public function scopedResource(): MorphTo
    {
        return $this->morphTo('scope', 'scope_type', 'scope_id');
    }

    public function permissionScope(): BelongsTo
    {
        return $this->belongsTo(PermissionScope::class, 'scope_id', 'scope_id')
            ->where('scope_type', $this->scope_type);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    public function scopeScoped(Builder $query): Builder
    {
        return $query->where('is_global', false);
    }

    public function scopeForScope(Builder $query, string $scopeType, string $scopeId): Builder
    {
        return $query->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId);
    }

    public function scopeForScopeType(Builder $query, string $scopeType): Builder
    {
        return $query->where('scope_type', $scopeType);
    }

    public function scopeInheritable(Builder $query, string $scopeType, string $scopeId): Builder
    {
        return $query->where(function ($q) use ($scopeType, $scopeId) {
            // Include global roles
            $q->where('is_global', true)
              // Include roles directly scoped to this resource
              ->orWhere(function ($q2) use ($scopeType, $scopeId) {
                  $q2->where('scope_type', $scopeType)
                     ->where('scope_id', $scopeId);
              })
              // Include roles from parent scopes if inheritance is enabled
              ->orWhereExists(function ($q3) use ($scopeType, $scopeId) {
                  $q3->select('id')
                     ->from('sys_permission_scopes as ps')
                     ->where('ps.scope_type', $scopeType)
                     ->where('ps.scope_id', $scopeId)
                     ->where('ps.inherits_permissions', true)
                     ->whereRaw('JSON_CONTAINS(ps.scope_path, JSON_OBJECT("type", sys_roles.scope_type, "id", sys_roles.scope_id))');
              });
        });
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return $this->is_global;
    }

    public function isScoped(): bool
    {
        return !$this->is_global;
    }

    public function getScopeIdentifier(): ?string
    {
        return $this->scope_type && $this->scope_id 
            ? "{$this->scope_type}:{$this->scope_id}" 
            : null;
    }

    public function appliesToScope(string $scopeType, string $scopeId): bool
    {
        // Global roles apply everywhere
        if ($this->is_global) {
            return true;
        }

        // Direct scope match
        if ($this->scope_type === $scopeType && $this->scope_id === $scopeId) {
            return true;
        }

        // Check if this role applies through inheritance
        $permissionScope = PermissionScope::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('inherits_permissions', true)
            ->first();

        if ($permissionScope && !empty($permissionScope->scope_path)) {
            foreach ($permissionScope->scope_path as $pathItem) {
                if ($pathItem['type'] === $this->scope_type && $pathItem['id'] === $this->scope_id) {
                    return true;
                }
            }
        }

        return false;
    }
}
