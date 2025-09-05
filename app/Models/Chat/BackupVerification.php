<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupVerification extends Model
{
    use HasUlids;

    protected $table = 'backup_verification';

    protected $fillable = [
        'backup_id',
        'verification_method',
        'verification_data',
        'is_verified',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(ChatBackup::class, 'backup_id');
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function getVerificationMethod(): string
    {
        return $this->verification_method;
    }

    public function isHashVerification(): bool
    {
        return $this->verification_method === 'hash';
    }

    public function isSignatureVerification(): bool
    {
        return $this->verification_method === 'signature';
    }

    public function isChecksumVerification(): bool
    {
        return $this->verification_method === 'checksum';
    }

    public function getVerificationData(): string
    {
        return $this->verification_data;
    }

    public function markAsVerified(?string $notes = null): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    public function markAsFailed(?string $notes = null): void
    {
        $this->update([
            'is_verified' => false,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }
}
