<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles, InteractsWithPasskeys;
    use HasUlids;

    protected $table = 'sys_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
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
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        
        $writer = new \BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);
        
        // Convert SVG to data URL
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
                $query->wherePivotNull('end_date')
                    ->orWherePivot('end_date', '>=', now());
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
}
