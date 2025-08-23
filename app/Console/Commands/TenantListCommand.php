<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class TenantListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:list {--active : Only show active tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available tenant organizations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Organization::query();

        if ($this->option('active')) {
            $query->where('is_active', true);
        }

        $organizations = $query->orderBy('level')
            ->orderBy('name')
            ->get();

        if ($organizations->isEmpty()) {
            $this->info('No tenant organizations found.');

            return 0;
        }

        $headers = ['ID', 'Name', 'Code', 'Type', 'Level', 'Active', 'Members'];
        $rows = [];

        foreach ($organizations as $org) {
            $memberCount = $org->activeUsers()->count();

            $rows[] = [
                substr($org->id, 0, 8).'...',
                $org->name,
                $org->organization_code ?? 'N/A',
                ucfirst(str_replace('_', ' ', $org->organization_type)),
                $org->level,
                $org->is_active ? '✓' : '✗',
                $memberCount,
            ];
        }

        $this->table($headers, $rows);

        $this->info("Total: {$organizations->count()} tenant organizations");

        return 0;
    }
}
