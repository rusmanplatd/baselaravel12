<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserMfaSetting extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'user_id',
        'totp_enabled',
        'totp_secret',
        'totp_confirmed_at',
        'backup_codes',
        'backup_codes_used',
        'mfa_required',
    ];

    protected $casts = [
        'totp_enabled' => 'boolean',
        'totp_confirmed_at' => 'datetime',
        'backup_codes' => 'array',
        'backup_codes_used' => 'integer',
        'mfa_required' => 'boolean',
    ];

    protected $hidden = [
        'totp_secret',
        'backup_codes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->totp_enabled && $this->totp_confirmed_at !== null;
    }

    public function hasBackupCodes(): bool
    {
        return ! empty($this->backup_codes) && count($this->backup_codes) > $this->backup_codes_used;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'totp_enabled',
                'totp_confirmed_at',
                'backup_codes_used',
                'mfa_required'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "MFA settings {$eventName}")
            ->useLogName('security')
            ->dontLogIfAttributesChangedOnly(['backup_codes', 'totp_secret']);
    }
}
