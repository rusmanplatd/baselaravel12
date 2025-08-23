<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthScope extends Model
{
    protected $table = 'oauth_scopes';

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public static function getAvailableScopes()
    {
        return static::all()->keyBy('identifier');
    }

    public static function getDefaultScopes()
    {
        return static::where('is_default', true)->pluck('identifier')->toArray();
    }
}
