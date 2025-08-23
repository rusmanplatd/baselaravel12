<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Console\Command;

class TenantShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:show {tenant? : Tenant ID or code} {--user= : User ID or email to show tenant access}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detailed information about a tenant organization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantIdentifier = $this->argument('tenant');
        $userIdentifier = $this->option('user');

        // Show user's tenants if user is specified
        if ($userIdentifier) {
            return $this->showUserTenants($userIdentifier);
        }

        // If no tenant specified, show current tenant from context
        if (! $tenantIdentifier) {
            $currentTenant = TenantService::getCurrentTenant();
            if (! $currentTenant) {
                $this->error('No current tenant context and no tenant specified.');

                return 1;
            }
            $tenant = $currentTenant;
        } else {
            // Find tenant by ID or code
            $tenant = Organization::where('id', $tenantIdentifier)
                ->orWhere('organization_code', $tenantIdentifier)
                ->first();

            if (! $tenant) {
                $this->error("Tenant not found: {$tenantIdentifier}");

                return 1;
            }
        }

        $this->showTenantDetails($tenant);

        return 0;
    }

    private function showTenantDetails(Organization $tenant): void
    {
        $this->info('Tenant Organization Details');
        $this->line('========================');

        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $tenant->id],
                ['Name', $tenant->name],
                ['Code', $tenant->organization_code ?? 'N/A'],
                ['Type', ucfirst(str_replace('_', ' ', $tenant->organization_type))],
                ['Level', $tenant->level],
                ['Path', $tenant->path ?? 'N/A'],
                ['Active', $tenant->is_active ? 'Yes' : 'No'],
                ['Created', $tenant->created_at->format('Y-m-d H:i:s')],
                ['Updated', $tenant->updated_at->format('Y-m-d H:i:s')],
            ]
        );

        // Show organizational hierarchy
        $ancestors = $tenant->getAncestors();
        if ($ancestors->isNotEmpty()) {
            $this->line("\nParent Organizations:");
            foreach ($ancestors as $ancestor) {
                $this->line("  • Level {$ancestor->level}: {$ancestor->name} ({$ancestor->organization_code})");
            }
        }

        $descendants = $tenant->getDescendants();
        if ($descendants->isNotEmpty()) {
            $this->line("\nChild Organizations:");
            foreach ($descendants as $descendant) {
                $this->line("  • Level {$descendant->level}: {$descendant->name} ({$descendant->organization_code})");
            }
        }

        // Show members
        $activeMembers = $tenant->activeUsers();
        $memberCount = $activeMembers->count();

        $this->line("\nActive Members: {$memberCount}");

        if ($memberCount > 0 && $memberCount <= 10) {
            $members = $activeMembers->get();
            foreach ($members as $member) {
                $membership = $member->pivot;
                $this->line("  • {$member->name} ({$member->email}) - {$membership->membership_type}");
            }
        } elseif ($memberCount > 10) {
            $this->line('  (Too many members to display - use tenant:members command for full list)');
        }

        // Show units
        $unitCount = $tenant->organizationUnits()->count();
        $this->line("\nOrganization Units: {$unitCount}");
    }

    private function showUserTenants(string $userIdentifier): int
    {
        $user = User::where('id', $userIdentifier)
            ->orWhere('email', $userIdentifier)
            ->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return 1;
        }

        $this->info("Tenant Access for User: {$user->name} ({$user->email})");
        $this->line('========================================');

        $tenants = TenantService::getUserTenants($user);

        if ($tenants->isEmpty()) {
            $this->warn('User has no tenant access.');

            return 0;
        }

        $headers = ['ID', 'Name', 'Code', 'Type', 'Level', 'Role'];
        $rows = [];

        foreach ($tenants as $tenant) {
            $membership = $user->organizations()
                ->where('organization_id', $tenant->id)
                ->first()?->pivot;

            $rows[] = [
                substr($tenant->id, 0, 8).'...',
                $tenant->name,
                $tenant->organization_code ?? 'N/A',
                ucfirst(str_replace('_', ' ', $tenant->organization_type)),
                $tenant->level,
                $membership?->membership_type ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);

        $defaultTenant = TenantService::getDefaultTenant($user);
        if ($defaultTenant) {
            $this->info("Default Tenant: {$defaultTenant->name}");
        }

        return 0;
    }
}
