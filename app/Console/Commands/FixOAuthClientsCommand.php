<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Console\Command;

class FixOAuthClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:fix-clients
                          {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Fix OAuth clients with missing required fields (user_access_scope, organization_id)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $this->info('Checking OAuth clients for missing required fields...');

        // Get all clients with missing fields
        $clientsNeedingFix = Client::where(function ($query) {
            $query->whereNull('user_access_scope')
                ->orWhereNull('organization_id')
                ->orWhere('user_access_scope', '');
        })->get();

        if ($clientsNeedingFix->isEmpty()) {
            $this->info('✅ All OAuth clients have required fields properly set.');

            return 0;
        }

        $this->warn("Found {$clientsNeedingFix->count()} clients that need to be fixed:");
        $this->line('');

        $defaultOrganization = Organization::first();
        if (! $defaultOrganization) {
            $this->error('❌ No organizations found. Please create an organization first.');

            return 1;
        }

        $fixedCount = 0;

        foreach ($clientsNeedingFix as $client) {
            $changes = [];

            // Fix user_access_scope
            if (empty($client->user_access_scope)) {
                $changes[] = "user_access_scope: null → 'all_users'";
                if (! $dryRun) {
                    $client->user_access_scope = 'all_users';
                }
            }

            // Fix organization_id
            if (empty($client->organization_id)) {
                $changes[] = "organization_id: null → '{$defaultOrganization->id}'";
                if (! $dryRun) {
                    $client->organization_id = $defaultOrganization->id;
                }
            }

            // Fix client_type if missing
            if (empty($client->client_type)) {
                $clientType = $client->secret ? 'confidential' : 'public';
                $changes[] = "client_type: null → '{$clientType}'";
                if (! $dryRun) {
                    $client->client_type = $clientType;
                }
            }

            if (! empty($changes)) {
                $this->line("🔧 Client: <info>{$client->name}</info> (ID: {$client->id})");
                foreach ($changes as $change) {
                    $this->line("   - {$change}");
                }

                if (! $dryRun) {
                    try {
                        $client->save();
                        $fixedCount++;
                        $this->line('   ✅ <comment>Fixed successfully</comment>');
                    } catch (\Exception $e) {
                        $this->line("   ❌ <error>Failed to fix: {$e->getMessage()}</error>");
                    }
                } else {
                    $this->line('   📋 <comment>Would be fixed</comment>');
                }

                $this->line('');
            }
        }

        if ($dryRun) {
            $this->info("📋 DRY RUN COMPLETE: {$clientsNeedingFix->count()} clients would be fixed.");
            $this->info('Run without --dry-run to apply these changes.');
        } else {
            $this->info("✅ COMPLETE: Fixed {$fixedCount} out of {$clientsNeedingFix->count()} clients.");

            if ($fixedCount < $clientsNeedingFix->count()) {
                $failedCount = $clientsNeedingFix->count() - $fixedCount;
                $this->warn("⚠️  {$failedCount} clients could not be fixed. Check the error messages above.");
            }
        }

        return 0;
    }
}
