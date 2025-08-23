<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\StoreClientRequest;
use App\Http\Requests\OAuth\UpdateClientRequest;
use App\Models\Organization;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class ClientController extends Controller
{
    protected $clients;

    public function __construct(ClientRepository $clients)
    {
        $this->clients = $clients;

        $this->middleware('permission:oauth.client.view')->only(['index', 'show']);
        $this->middleware('permission:oauth.client.create')->only(['store']);
        $this->middleware('permission:oauth.client.edit')->only(['update']);
        $this->middleware('permission:oauth.client.delete')->only(['destroy']);
        $this->middleware('permission:oauth.client.regenerate')->only(['regenerateSecret']);
    }

    public function index()
    {
        $userOrganizations = Auth::user()->memberships()->active()->pluck('organization_id');

        // Only show organization-associated clients (no legacy clients)
        $clients = Client::whereNotNull('organization_id')
            ->whereIn('organization_id', $userOrganizations)
            ->with('organization')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'secret' => $client->secret,
                    'redirect' => json_decode($client->redirect),
                    'revoked' => $client->revoked,
                    'organization' => $client->organization ? [
                        'id' => $client->organization->id,
                        'name' => $client->organization->name,
                        'code' => $client->organization->organization_code,
                    ] : null,
                    'client_type' => $client->client_type ?? 'public',
                    'last_used_at' => $client->last_used_at?->toDateTimeString(),
                    'created_at' => $client->created_at->toDateTimeString(),
                ];
            });

        $availableOrganizations = Auth::user()->memberships()
            ->active()
            ->whereHas('organizationPosition.organizationPositionLevel', function ($q) {
                $q->whereIn('code', ['c_level', 'vice_president', 'director', 'senior_manager', 'manager']);
            })
            ->with('organization')
            ->get()
            ->pluck('organization')
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'code' => $org->organization_code,
                ];
            });

        return Inertia::render('OAuth/Clients', [
            'clients' => $clients,
            'organizations' => $availableOrganizations,
        ]);
    }

    public function store(StoreClientRequest $request)
    {

        // Validate user has management access to the organization
        $userManagementOrgs = Auth::user()->memberships()
            ->active()
            ->whereHas('organizationPosition.organizationPositionLevel', function ($q) {
                $q->whereIn('code', ['c_level', 'vice_president', 'director', 'senior_manager', 'manager']);
            })
            ->pluck('organization_id');

        if (! $userManagementOrgs->contains($request->organization_id)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'You do not have management access to this organization',
            ], 403);
        }

        $client = $this->clients->create(
            Auth::id(),
            $request->name,
            $request->redirect_uris,
            null,
            false,
            false,
            true
        );

        if ($request->description) {
            $client->update(['description' => $request->description]);
        }

        if ($request->website) {
            $client->update(['website' => $request->website]);
        }

        if ($request->logo_url) {
            $client->update(['logo_url' => $request->logo_url]);
        }

        // Organization ID is now required and already validated
        $client->update([
            'organization_id' => $request->organization_id,
            'client_type' => $request->client_type,
        ]);

        // Handle allowed scopes
        $organization = Organization::find($request->organization_id);
        if ($request->allowed_scopes) {
            $availableScopes = $organization->getAvailableOAuthScopes();
            $validScopes = array_intersect($request->allowed_scopes, $availableScopes);
            $client->update(['allowed_scopes' => json_encode($validScopes)]);
        } else {
            // Default to basic scopes for the organization
            $defaultScopes = ['openid', 'profile', 'email'];
            $client->update(['allowed_scopes' => json_encode($defaultScopes)]);
        }

        // Log OAuth client creation
        ActivityLogService::logOAuth('client_created', 'OAuth client created: '.$client->name, [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'organization_id' => $request->organization_id,
            'client_type' => $request->client_type,
        ]);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'secret' => $client->secret,
            'redirect' => json_decode($client->redirect),
        ], 201);
    }

    public function show($clientId)
    {
        $client = Client::where('id', $clientId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'secret' => $client->secret,
            'redirect' => json_decode($client->redirect),
            'description' => $client->description,
            'website' => $client->website,
            'logo_url' => $client->logo_url,
            'revoked' => $client->revoked,
            'created_at' => $client->created_at->toDateTimeString(),
            'updated_at' => $client->updated_at->toDateTimeString(),
        ]);
    }

    public function update(UpdateClientRequest $request, $clientId)
    {
        $client = Client::where('id', $clientId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $updateData = $request->only(['name', 'description', 'website', 'logo_url']);

        if ($request->has('redirect_uris')) {
            $updateData['redirect'] = json_encode($request->redirect_uris);
        }

        $client->update($updateData);

        // Log OAuth client update
        ActivityLogService::logOAuth('client_updated', 'OAuth client updated: '.$client->name, [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'changes' => array_keys($updateData),
        ]);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'redirect' => json_decode($client->redirect),
            'description' => $client->description,
            'website' => $client->website,
            'logo_url' => $client->logo_url,
        ]);
    }

    public function destroy($clientId)
    {
        $userManagementOrgs = Auth::user()->memberships()
            ->active()
            ->whereHas('organizationPosition.organizationPositionLevel', function ($q) {
                $q->whereIn('code', ['c_level', 'vice_president', 'director', 'senior_manager', 'manager']);
            })
            ->pluck('organization_id');

        $client = Client::whereNotNull('organization_id')
            ->whereIn('organization_id', $userManagementOrgs)
            ->where('id', $clientId)
            ->firstOrFail();

        // Log OAuth client revocation
        ActivityLogService::logOAuth('client_revoked', 'OAuth client revoked: '.$client->name, [
            'client_id' => $client->id,
            'client_name' => $client->name,
        ]);

        $client->update(['revoked' => true]);

        return response()->json(['message' => 'Client revoked successfully']);
    }

    public function regenerateSecret($clientId)
    {
        $userManagementOrgs = Auth::user()->memberships()
            ->active()
            ->whereHas('organizationPosition.organizationPositionLevel', function ($q) {
                $q->whereIn('code', ['c_level', 'vice_president', 'director', 'senior_manager', 'manager']);
            })
            ->pluck('organization_id');

        $client = Client::whereNotNull('organization_id')
            ->whereIn('organization_id', $userManagementOrgs)
            ->where('id', $clientId)
            ->firstOrFail();

        // Log OAuth client secret regeneration
        ActivityLogService::logOAuth('client_secret_regenerated', 'OAuth client secret regenerated: '.$client->name, [
            'client_id' => $client->id,
            'client_name' => $client->name,
        ]);

        $client->update(['secret' => Str::random(40)]);

        return response()->json([
            'secret' => $client->secret,
        ]);
    }

    // Public registration removed - all clients must be organization-associated
}
