<?php

namespace App\Models;

use Laravel\Passport\Client as PassportClient;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Client extends PassportClient
{
    protected $fillable = [
        'owner_id',
        'owner_type',
        'name',
        'secret',
        'provider',
        'redirect_uris',
        'grant_types',
        'revoked',
        'organization_id',
        'allowed_scopes',
        'client_type',
        'last_used_at',
        'user_access_scope',
        'user_access_rules',
    ];

    protected $casts = [
        'revoked' => 'bool',
        'redirect' => 'array',  // Passport expects this field name
        'allowed_scopes' => 'array',
        'user_access_rules' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get the redirect attribute (compatibility with Passport).
     * Passport expects 'redirect' but our database has 'redirect_uris'.
     */
    public function getRedirectAttribute()
    {
        return is_string($this->redirect_uris) ? json_decode($this->redirect_uris, true) : $this->redirect_uris;
    }

    /**
     * Set the redirect attribute (compatibility with Passport).
     */
    public function setRedirectAttribute($value)
    {
        $this->attributes['redirect_uris'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get the organization that owns this OAuth client.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if a user has access to this OAuth client based on the access scope rules.
     */
    public function userHasAccess(User $user): bool
    {
        switch ($this->user_access_scope) {
            case 'all_users':
                return true;

            case 'organization_members':
                if (!$this->organization_id) {
                    return false;
                }
                
                return $user->memberships()
                    ->active()
                    ->where('organization_id', $this->organization_id)
                    ->exists();

            case 'custom':
                return $this->checkCustomAccessRules($user);

            default:
                // Default to organization_members for backward compatibility
                if (!$this->organization_id) {
                    return false;
                }
                
                return $user->memberships()
                    ->active()
                    ->where('organization_id', $this->organization_id)
                    ->exists();
        }
    }

    /**
     * Check custom access rules for a user.
     */
    protected function checkCustomAccessRules(User $user): bool
    {
        if (!$this->user_access_rules) {
            return false;
        }

        $rules = $this->user_access_rules;

        // Check specific user IDs
        if (isset($rules['user_ids']) && in_array($user->id, $rules['user_ids'])) {
            return true;
        }

        // Check user roles/permissions
        if (isset($rules['roles'])) {
            foreach ($rules['roles'] as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        // Check organization membership with specific roles
        if (isset($rules['organization_roles']) && $this->organization_id) {
            $membership = $user->memberships()
                ->active()
                ->where('organization_id', $this->organization_id)
                ->first();

            if ($membership) {
                foreach ($rules['organization_roles'] as $requiredRole) {
                    if ($membership->membership_type === $requiredRole) {
                        return true;
                    }
                }
            }
        }

        // Check organization position levels
        if (isset($rules['position_levels']) && $this->organization_id) {
            $hasRequiredPosition = $user->memberships()
                ->active()
                ->where('organization_id', $this->organization_id)
                ->whereHas('organizationPosition.organizationPositionLevel', function ($q) use ($rules) {
                    $q->whereIn('code', $rules['position_levels']);
                })
                ->exists();

            if ($hasRequiredPosition) {
                return true;
            }
        }

        // Check email domains
        if (isset($rules['email_domains'])) {
            $userEmailDomain = substr(strrchr($user->email, "@"), 1);
            if (in_array($userEmailDomain, $rules['email_domains'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available user access scopes.
     */
    public static function getUserAccessScopes(): array
    {
        return [
            'all_users' => 'All Users',
            'organization_members' => 'Organization Members Only',
            'custom' => 'Custom Rules',
        ];
    }

    /**
     * Get a human-readable description of the current access scope.
     */
    public function getAccessScopeDescription(): string
    {
        switch ($this->user_access_scope) {
            case 'all_users':
                return 'Any registered user can access this OAuth client';

            case 'organization_members':
                return $this->organization 
                    ? "Only members of {$this->organization->name} can access this OAuth client"
                    : 'Only organization members can access this OAuth client';

            case 'custom':
                $rulesCount = $this->user_access_rules ? count($this->user_access_rules) : 0;
                return "Access is controlled by {$rulesCount} custom rule(s)";

            default:
                return 'Access scope not configured';
        }
    }

    /**
     * Scope query to only include clients accessible by a specific user.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // All users scope
            $q->where('user_access_scope', 'all_users')
              // Or organization members where user is a member
              ->orWhere(function ($subQuery) use ($user) {
                  $subQuery->where('user_access_scope', 'organization_members')
                           ->whereIn('organization_id', $user->memberships()->active()->pluck('organization_id'));
              })
              // Custom rules would need to be handled separately due to complexity
              ->orWhere('user_access_scope', 'custom');
        });
    }
}