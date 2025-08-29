<?php

namespace App\Providers;

use App\Models\Chat\Channel;
use App\Policies\Chat\ChannelPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Channel::class => ChannelPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}