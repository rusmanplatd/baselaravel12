<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    protected $table = 'user_consents';

    protected $fillable = [
        'user_id',
        'client_id',
        'scopes',
    ];

    protected $casts = [
        'scopes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\Laravel\Passport\Client::class);
    }
}
