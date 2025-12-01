<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageFile extends Model
{
    use HasUlids;

    protected $table = 'message_files';

    protected $fillable = [
        'message_id',
        'original_filename',
        'encrypted_filename',
        'mime_type',
        'file_size',
        'encrypted_size',
        'file_hash',
        'encryption_key_encrypted',
        'thumbnail_path',
        'thumbnail_encrypted',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'encrypted_size' => 'integer',
        'thumbnail_encrypted' => 'boolean',
        'metadata' => 'array',
        'encryption_key_encrypted' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
    }

    public function getReadableFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        } else {
            return $bytes.' bytes';
        }
    }

    public function getFileExtension(): string
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    public function hasThumbnail(): bool
    {
        return ! is_null($this->thumbnail_path);
    }
}
