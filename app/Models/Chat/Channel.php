<?php

namespace App\Models\Chat;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Channel extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'chat_channels';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'visibility',
        'avatar_url',
        'metadata',
        'status',
        'conversation_id',
        'organization_id',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
                
                $originalSlug = $channel->slug;
                $counter = 1;
                
                while (static::where('slug', $channel->slug)->exists()) {
                    $channel->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(
            Participant::class,
            Conversation::class,
            'id',
            'conversation_id',
            'conversation_id',
            'id'
        );
    }

    public function activeParticipants(): HasManyThrough
    {
        return $this->participants()->whereNull('chat_participants.left_at');
    }

    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(
            Message::class,
            Conversation::class,
            'id',
            'conversation_id',
            'conversation_id',
            'id'
        );
    }

    public function encryptionKeys(): HasManyThrough
    {
        return $this->hasManyThrough(
            EncryptionKey::class,
            Conversation::class,
            'id',
            'conversation_id',
            'conversation_id',
            'id'
        );
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function isEncrypted(): bool
    {
        return $this->conversation->isEncrypted();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('visibility', 'private');
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('left_at');
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description', 'visibility', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Chat channel {$eventName}")
            ->useLogName('chat');
    }

    protected static function newFactory()
    {
        return \Database\Factories\Chat\ChannelFactory::new();
    }
}