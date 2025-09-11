<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        // Add a global Gate hook to handle scope-based checks
        Gate::before(function ($user, $ability, $arguments) {
            // If no arguments, default to global permission
            $scope = $arguments[0] ?? null;

            // Check super-admin global bypass
            if ($user->hasRole('super-admin')) {
                return true;
            }

            // Scoped permission check
            if ($scope && $user->hasPermissionTo($ability, $scope)) {
                return true;
            }

            // Global permission check
            if ($user->hasPermissionTo($ability)) {
                return true;
            }

            return null; // let policies handle it if not matched
        });
    }
}
