<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles, InteractsWithPasskeys;
    use HasUlids, LogsActivity;

    protected $table = 'sys_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'nickname',
        'email',
        'password',
        'avatar',
        'public_key',
        'profile_url',
        'website',
        'gender',
        'birthdate',
        'zoneinfo',
        'locale',
        'street_address',
        'locality',
        'region',
        'postal_code',
        'country',
        'formatted_address',
        'phone_number',
        'phone_verified_at',
        'profile_updated_at',
        'external_id',
        'social_links',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'phone_verified_at' => 'datetime',
            'profile_updated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'social_links' => 'array',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return asset('storage/'.$this->avatar);
    }

    public function getFormattedAddressAttribute(): ?string
    {
        // Check the raw attribute to avoid circular reference
        if ($this->getRawOriginal('formatted_address')) {
            return $this->getRawOriginal('formatted_address');
        }

        $addressParts = array_filter([
            $this->street_address,
            $this->locality,
            $this->region,
            $this->postal_code,
            $this->country,
        ]);

        return ! empty($addressParts) ? implode(', ', $addressParts) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'first_name', 'middle_name', 'last_name', 'username', 'nickname', 'email',
                'avatar', 'profile_url', 'website', 'gender', 'birthdate', 'zoneinfo', 'locale',
                'street_address', 'locality', 'region', 'postal_code', 'country', 'phone_number',
                'external_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "User {$eventName}")
            ->useLogName('user');
    }

    public function mfaSettings(): HasOne
    {
        return $this->hasOne(UserMfaSetting::class);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfaSettings?->hasMfaEnabled() ?? false;
    }

    public function requiresMfa(): bool
    {
        return $this->mfaSettings?->mfa_required ?? false;
    }

    public function generateTotpSecret(): string
    {
        return app(Google2FA::class)->generateSecretKey();
    }

    public function getTotpQrCodeUrl(string $secret): string
    {
        $google2fa = app(Google2FA::class);
        $companyName = config('app.name');
        $companyEmail = $this->email;

        return $google2fa->getQRCodeUrl(
            $companyName,
            $companyEmail,
            $secret
        );
    }

    public function getTotpQrCodeImage(string $secret): string
    {
        $companyName = config('app.name');
        $companyEmail = $this->email;

        // Generate QR code URL first
        $google2fa = app(Google2FA::class);
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $companyName,
            $companyEmail,
            $secret
        );

        // Generate QR code image as SVG using BaconQrCode
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd
        );

        $writer = new \BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);

        // Convert SVG to data URL
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function verifyTotpCode(string $code): bool
    {
        if (! $this->mfaSettings || ! $this->mfaSettings->totp_secret) {
            return false;
        }

        $google2fa = app(Google2FA::class);

        return $google2fa->verifyKey($this->mfaSettings->totp_secret, $code);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function activeOrganizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class)->active();
    }

    /**
     * Alias for organizationMemberships for backwards compatibility
     */
    public function memberships(): HasMany
    {
        return $this->organizationMemberships();
    }

    public function getCurrentOrganizations()
    {
        return $this->activeOrganizationMemberships()
            ->with('organization')
            ->get()
            ->pluck('organization')
            ->unique('id');
    }

    public function getCurrentPositions()
    {
        return $this->activeOrganizationMemberships()
            ->with('organizationPosition')
            ->whereNotNull('organization_position_id')
            ->get()
            ->pluck('organizationPosition')
            ->filter();
    }

    public function isBoardMember(): bool
    {
        return $this->activeOrganizationMemberships()
            ->board()
            ->exists();
    }

    public function isExecutive(): bool
    {
        return $this->activeOrganizationMemberships()
            ->executive()
            ->exists();
    }

    public function isManagement(): bool
    {
        return $this->activeOrganizationMemberships()
            ->management()
            ->exists();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot([
                'organization_unit_id',
                'organization_position_id',
                'membership_type',
                'start_date',
                'end_date',
                'status',
                'additional_roles',
            ])
            ->withTimestamps();
    }

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot('status', 'active')
            ->wherePivot('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('organization_memberships.end_date')
                    ->orWhere('organization_memberships.end_date', '>=', now());
            });
    }

    public function assignRoleInOrganization(string $roleName, Organization $organization): void
    {
        // Use setPermissionsTeamId for team context in assignments
        setPermissionsTeamId($organization->id);
        $this->assignRole($roleName);
        setPermissionsTeamId(null);
    }

    public function removeRoleFromOrganization(string $roleName, Organization $organization): void
    {
        setPermissionsTeamId($organization->id);
        $this->removeRole($roleName);
        setPermissionsTeamId(null);
    }

    public function hasRoleInOrganization(string $roleName, Organization $organization): bool
    {
        setPermissionsTeamId($organization->id);
        $result = $this->hasRole($roleName);
        setPermissionsTeamId(null);

        return $result;
    }

    public function getRolesInOrganization(Organization $organization)
    {
        return $this->roles()->where('sys_roles.team_id', $organization->id);
    }

    public function getPermissionsInOrganization(Organization $organization)
    {
        setPermissionsTeamId($organization->id);
        $permissions = $this->getAllPermissions();
        setPermissionsTeamId(null);

        return $permissions;
    }

    public function canInOrganization(string $permission, Organization $organization): bool
    {
        setPermissionsTeamId($organization->id);
        $result = $this->can($permission);
        setPermissionsTeamId(null);

        return $result;
    }

    public function givePermissionToInOrganization(string $permission, Organization $organization): void
    {
        setPermissionsTeamId($organization->id);
        $this->givePermissionTo($permission);
        setPermissionsTeamId(null);
    }

    public function revokePermissionFromOrganization(string $permission, Organization $organization): void
    {
        setPermissionsTeamId($organization->id);
        $this->revokePermissionTo($permission);
        setPermissionsTeamId(null);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(Session::class)->active();
    }

    public function getCurrentSession(string $sessionId): ?Session
    {
        return $this->sessions()
            ->where('id', $sessionId)
            ->active()
            ->first();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Signal Protocol relationships
     */
    public function signalIdentityKeys(): HasMany
    {
        return $this->hasMany(\App\Models\Signal\IdentityKey::class);
    }

    public function userDevices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Override the can method to automatically grant basic chat permissions to all users
     */
    public function can($abilities, $arguments = []): bool
    {
        // Define basic chat permissions that all authenticated users should have
        $basicChatPermissions = [
            'chat:read',
            'chat:write',
            'chat:files',
            'chat:calls',
        ];

        // If checking for a basic chat permission, automatically grant it
        if (is_string($abilities) && in_array($abilities, $basicChatPermissions)) {
            return true;
        }

        // For arrays of abilities, check if all are basic chat permissions
        if (is_array($abilities)) {
            $allBasicChat = array_diff($abilities, $basicChatPermissions) === [];
            if ($allBasicChat) {
                return true;
            }
        }

        // For all other permissions, use the default behavior
        return parent::can($abilities, $arguments);
    }
}
