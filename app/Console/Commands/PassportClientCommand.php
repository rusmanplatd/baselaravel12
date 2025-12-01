<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class PassportClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'passport:client 
                          {--personal : Create a personal access token client}
                          {--password : Create a password grant client}
                          {--client : Create a client credentials grant client}
                          {--name= : The name of the client}
                          {--provider= : The user provider for the client}
                          {--redirect_uri= : The URI to redirect to after authorization}
                          {--user_id= : The user ID the client should be assigned to}
                          {--public : Create a public client (PKCE)}
                          {--organization= : The organization ID for the client}
                          {--user-access-scope= : User access scope (all_users, organization_members, custom)}';

    /**
     * The console command description.
     */
    protected $description = 'Create a Passport client with proper organization and access scope configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('personal')) {
            $this->createPersonalClient();
        } elseif ($this->option('password')) {
            $this->createPasswordClient();
        } elseif ($this->option('client')) {
            $this->createClientCredentialsClient();
        } else {
            $this->createAuthCodeClient();
        }
    }

    /**
     * Create a personal access token client.
     */
    protected function createPersonalClient()
    {
        $name = $this->option('name') ?: $this->ask('What should we name the personal access client?', config('app.name').' Personal Access Client');

        $organization = $this->getOrganization();
        $userAccessScope = $this->getUserAccessScope();

        $client = Client::create([
            'name' => $name,
            'secret' => null,
            'provider' => $this->option('provider'),
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
            'organization_id' => $organization->id,
            'user_access_scope' => $userAccessScope,
            'client_type' => 'public',
        ]);

        $this->info('Personal access client created successfully.');
        $this->line('<comment>Client ID:</comment> '.$client->id);
        $this->line('<comment>Organization:</comment> '.$organization->name);
        $this->line('<comment>User Access Scope:</comment> '.$userAccessScope);
    }

    /**
     * Create a password grant client.
     */
    protected function createPasswordClient()
    {
        $name = $this->option('name') ?: $this->ask('What should we name the password grant client?', config('app.name').' Password Grant Client');

        $organization = $this->getOrganization();
        $userAccessScope = $this->getUserAccessScope();

        $client = Client::create([
            'name' => $name,
            'secret' => Hash::make($secret = \Illuminate\Support\Str::random(40)),
            'provider' => $this->option('provider') ?: 'users',
            'redirect_uris' => [],
            'grant_types' => ['password'],
            'revoked' => false,
            'organization_id' => $organization->id,
            'user_access_scope' => $userAccessScope,
            'client_type' => 'confidential',
        ]);

        $this->info('Password grant client created successfully.');
        $this->line('<comment>Client ID:</comment> '.$client->id);
        $this->line('<comment>Client secret:</comment> '.$secret);
        $this->line('<comment>Organization:</comment> '.$organization->name);
        $this->line('<comment>User Access Scope:</comment> '.$userAccessScope);
    }

    /**
     * Create a client credentials grant client.
     */
    protected function createClientCredentialsClient()
    {
        $name = $this->option('name') ?: $this->ask('What should we name the client credentials grant client?', config('app.name').' Client Credentials Client');

        $organization = $this->getOrganization();
        $userAccessScope = $this->getUserAccessScope();

        $client = Client::create([
            'name' => $name,
            'secret' => Hash::make($secret = \Illuminate\Support\Str::random(40)),
            'provider' => null,
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
            'revoked' => false,
            'organization_id' => $organization->id,
            'user_access_scope' => $userAccessScope,
            'client_type' => 'confidential',
        ]);

        $this->info('Client credentials grant client created successfully.');
        $this->line('<comment>Client ID:</comment> '.$client->id);
        $this->line('<comment>Client secret:</comment> '.$secret);
        $this->line('<comment>Organization:</comment> '.$organization->name);
        $this->line('<comment>User Access Scope:</comment> '.$userAccessScope);
    }

    /**
     * Create an authorization code grant client.
     */
    protected function createAuthCodeClient()
    {
        $userId = $this->option('user_id') ?: null;
        $name = $this->option('name') ?: $this->ask('What should we name the client?');
        $redirect = $this->option('redirect_uri') ?: $this->ask('Where should we redirect the request after authorization?', url('/auth/callback'));

        $organization = $this->getOrganization();
        $userAccessScope = $this->getUserAccessScope();

        $client = Client::create([
            'owner_id' => $userId,
            'name' => $name,
            'secret' => ($this->option('public')) ? null : Hash::make($secret = \Illuminate\Support\Str::random(40)),
            'provider' => $this->option('provider'),
            'redirect_uris' => [$redirect],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
            'organization_id' => $organization->id,
            'user_access_scope' => $userAccessScope,
            'client_type' => $this->option('public') ? 'public' : 'confidential',
        ]);

        $this->info('Client created successfully.');
        $this->line('<comment>Client ID:</comment> '.$client->id);

        if (! $this->option('public')) {
            $this->line('<comment>Client secret:</comment> '.$secret);
        }

        $this->line('<comment>Organization:</comment> '.$organization->name);
        $this->line('<comment>User Access Scope:</comment> '.$userAccessScope);
    }

    /**
     * Get or select an organization for the client.
     */
    protected function getOrganization(): Organization
    {
        $organizationId = $this->option('organization');

        if ($organizationId) {
            $organization = Organization::find($organizationId);
            if (! $organization) {
                $this->error("Organization with ID '$organizationId' not found.");

                return $this->selectOrganization();
            }

            return $organization;
        }

        return $this->selectOrganization();
    }

    /**
     * Select an organization interactively.
     */
    protected function selectOrganization(): Organization
    {
        $organizations = Organization::orderBy('name')->get();

        if ($organizations->isEmpty()) {
            $this->error('No organizations found. Please create an organization first.');
            exit(1);
        }

        if ($organizations->count() === 1) {
            $org = $organizations->first();
            $this->info("Using organization: {$org->name}");

            return $org;
        }

        $choices = $organizations->pluck('name', 'id')->toArray();
        $selectedId = $this->choice('Select an organization:', $choices);

        return $organizations->find($selectedId);
    }

    /**
     * Get or select user access scope.
     */
    protected function getUserAccessScope(): string
    {
        $scope = $this->option('user-access-scope');

        if ($scope && in_array($scope, ['all_users', 'organization_members', 'custom'])) {
            return $scope;
        }

        $scopes = Client::getUserAccessScopes();

        return $this->choice('Select user access scope:', $scopes, 'all_users');
    }
}
