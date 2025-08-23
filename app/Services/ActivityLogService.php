<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public static function logAuth(string $event, string $description, array $properties = [], ?Model $subject = null): Activity
    {
        return self::log('auth', $event, $description, $properties, $subject);
    }

    public static function logOrganization(string $event, string $description, array $properties = [], ?Model $subject = null): Activity
    {
        return self::log('organization', $event, $description, $properties, $subject);
    }

    public static function logOAuth(string $event, string $description, array $properties = [], ?Model $subject = null): Activity
    {
        return self::log('oauth', $event, $description, $properties, $subject);
    }

    public static function logSystem(string $event, string $description, array $properties = [], ?Model $subject = null): Activity
    {
        return self::log('system', $event, $description, $properties, $subject);
    }

    private static function log(string $logName, string $event, string $description, array $properties = [], ?Model $subject = null): Activity
    {
        $activity = activity($logName)
            ->event($event)
            ->performedOn($subject)
            ->withProperties($properties);

        // Add organization context if available
        $organizationId = self::getCurrentOrganizationId();
        if ($organizationId) {
            $activity->withProperties(array_merge($properties, [
                'organization_id' => $organizationId,
            ]));
        }

        // Add tenant context if available
        $tenantId = self::getCurrentTenantId();
        if ($tenantId) {
            $activity->withProperties(array_merge($properties, [
                'tenant_id' => $tenantId,
            ]));
        }

        $activityModel = $activity->log($description);

        // Update the activity model with organization and tenant context
        if ($organizationId || $tenantId) {
            $activityModel->update(array_filter([
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
            ]));
        }

        return $activityModel;
    }

    private static function getCurrentOrganizationId(): ?string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        // Get the current organization from session or user's primary organization
        if (session('current_organization_id')) {
            return session('current_organization_id');
        }

        // Get user's first active organization membership
        $membership = $user->activeOrganizationMemberships()->first();

        return $membership?->organization_id;
    }

    private static function getCurrentTenantId(): ?string
    {
        // Get tenant from multitenancy package if available
        if (class_exists(\Spatie\Multitenancy\Models\Tenant::class)) {
            return app('current-tenant')?->getKey();
        }

        return null;
    }

    public static function getActivitiesForUser(User $user, array $filters = [])
    {
        $query = Activity::forUser($user->id);

        if (isset($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (isset($filters['organization_id'])) {
            $query->forOrganization($filters['organization_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public static function getActivitiesForOrganization(Organization $organization, array $filters = [])
    {
        $query = Activity::forOrganization($organization->id);

        if (isset($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (isset($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
