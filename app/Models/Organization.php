<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Multitenancy\Models\Tenant;

class Organization extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_code',
        'name',
        'organization_type',
        'parent_organization_id',
        'description',
        'address',
        'phone',
        'email',
        'website',
        'registration_number',
        'tax_number',
        'governance_structure',
        'authorized_capital',
        'paid_capital',
        'establishment_date',
        'legal_status',
        'business_activities',
        'contact_persons',
        'level',
        'path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'governance_structure' => 'array',
        'contact_persons' => 'array',
        'authorized_capital' => 'decimal:2',
        'paid_capital' => 'decimal:2',
        'establishment_date' => 'date',
    ];

    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_organization_id');
    }

    public function childOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_organization_id');
    }

    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'name', 'organization_code');
    }

    public function oauthClients(): HasMany
    {
        return $this->hasMany(\Laravel\Passport\Client::class, 'organization_id');
    }

    public function updatePath(): void
    {
        if ($this->parent_organization_id) {
            $parent = $this->parentOrganization;
            $this->level = $parent->level + 1;
            $this->path = $parent->path.'/'.$this->id;
        } else {
            $this->level = 0;
            $this->path = (string) $this->id;
        }
        $this->save();

        foreach ($this->childOrganizations as $child) {
            $child->updatePath();
        }
    }

    public function getAncestors()
    {
        if (! $this->path) {
            return collect();
        }

        $ids = array_filter(explode('/', $this->path));
        array_pop($ids);

        return static::whereIn('id', $ids)->orderBy('level')->get();
    }

    public function getDescendants()
    {
        return static::where('path', 'like', $this->path.'/%')->orderBy('level')->get();
    }

    public function isAncestorOf(Organization $organization): bool
    {
        return str_starts_with($organization->path, $this->path.'/');
    }

    public function isDescendantOf(Organization $organization): bool
    {
        return str_starts_with($this->path, $organization->path.'/');
    }

    public function isSiblingOf(Organization $organization): bool
    {
        return $this->parent_organization_id === $organization->parent_organization_id
            && $this->id !== $organization->id;
    }

    public function createTenant(): ?Tenant
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        return Tenant::create([
            'name' => $this->organization_code,
            'domain' => strtolower($this->organization_code).'.example.com',
            'database' => 'tenant_'.strtolower($this->organization_code),
        ]);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot([
                'organization_unit_id',
                'organization_position_id', 
                'membership_type',
                'start_date',
                'end_date',
                'status',
                'additional_roles'
            ])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('status', 'active')
            ->wherePivot('start_date', '<=', now())
            ->where(function ($query) {
                $query->wherePivotNull('end_date')
                    ->orWherePivot('end_date', '>=', now());
            });
    }

    public function getAvailableOAuthScopes(): array
    {
        $baseScopes = ['openid', 'profile', 'email'];

        if ($this->organization_type === 'corporate') {
            $baseScopes[] = 'organization:read';
            $baseScopes[] = 'organization:members';
        }

        if ($this->level === 0) {
            $baseScopes[] = 'organization:admin';
            $baseScopes[] = 'organization:hierarchy';
        }

        return $baseScopes;
    }
}
