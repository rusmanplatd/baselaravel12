<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
