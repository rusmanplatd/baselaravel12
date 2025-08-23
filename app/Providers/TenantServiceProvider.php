<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\TenantMiddleware;
use App\Services\TenantService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantService::class);
    }

    public function boot(): void
    {
        $this->bootTenantService();
        $this->registerMiddleware();
        $this->addTenantContextToActivityLog();
    }

    protected function bootTenantService(): void
    {
        TenantService::boot();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('tenant', TenantMiddleware::class);
        $router->aliasMiddleware('tenant.access', EnsureTenantAccess::class);

        $router->middlewareGroup('tenant', [
            'tenant',
            'tenant.access',
        ]);
    }

    protected function addTenantContextToActivityLog(): void
    {
        if (class_exists(\Spatie\Activitylog\ActivityLogger::class)) {
            \Spatie\Activitylog\ActivityLogger::macro('withTenantContext', function () {
                $tenantId = TenantService::getTenantId();

                if ($tenantId) {
                    $this->properties = $this->properties->put('tenant_context', [
                        'organization_id' => $tenantId,
                        'tenant_path' => TenantService::getCurrentTenant()?->path,
                        'tenant_name' => TenantService::getCurrentTenant()?->name,
                    ]);
                }

                return $this;
            });
        }
    }
}
