<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuditLog;
use App\Models\OAuthScope;
use App\Models\Organization;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class OAuthController extends Controller
{
    public function authorize(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'response_type' => 'required|in:code,token',
            'scope' => 'sometimes|string',
            'state' => 'sometimes|string',
            'code_challenge' => 'sometimes|string|min:43|max:128',
            'code_challenge_method' => 'sometimes|string|in:plain,S256',
        ]);

        if (! Auth::check()) {
            return redirect()->route('login')->with('oauth_request', $request->all());
        }

        $client = Client::with('organization')->where('id', $request->client_id)->first();

        if (! $client || ! in_array($request->redirect_uri, json_decode($client->redirect))) {
            OAuthAuditLog::logError('authorize', 'invalid_client', 'Client authentication failed', [
                'client_id' => $request->client_id,
                'redirect_uri' => $request->redirect_uri,
            ]);

            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 400);
        }

        // Require organization association - no legacy clients allowed
        if (! $client->organization_id) {
            OAuthAuditLog::logError('authorize', 'invalid_client', 'Client must be associated with an organization', [
                'client_id' => $client->id,
            ]);

            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client must be associated with an organization',
            ], 400);
        }

        $organization = $client->organization;
        $userOrganizations = Auth::user()->memberships()->with('organization')->get()->pluck('organization');

        $requestedScopes = explode(' ', $request->scope ?? '');
        $availableScopes = $this->getAvailableScopes($organization);
        $validScopes = $this->validateScopesForUser($requestedScopes, $availableScopes, $organization, $userOrganizations);

        // Ensure user has access to the organization
        $hasOrganizationAccess = $userOrganizations->contains('id', $organization->id);
        if (! $hasOrganizationAccess) {
            OAuthAuditLog::logError('authorize', 'access_denied', 'User does not have access to the organization', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'organization_id' => $organization->id,
            ]);

            return response()->json([
                'error' => 'access_denied',
                'error_description' => 'You do not have access to this organization',
            ], 403);
        }

        $existingConsent = UserConsent::where([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
        ])->first();

        if ($existingConsent && $this->scopesMatch($existingConsent->scopes, $validScopes)) {
            OAuthAuditLog::logEvent('authorize', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'scopes' => $validScopes,
                'metadata' => ['auto_approved' => true],
            ]);

            return $this->generateAuthorizationCode($client, $validScopes, $request);
        }

        return Inertia::render('OAuth/Authorize', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'redirect' => $request->redirect_uri,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->organization_code,
                ],
            ],
            'scopes' => array_map(function ($scope) use ($availableScopes) {
                return [
                    'id' => $scope,
                    'name' => $availableScopes[$scope]['name'],
                    'description' => $availableScopes[$scope]['description'],
                ];
            }, $validScopes),
            'user' => Auth::user()->only(['name', 'email']),
            'state' => $request->state,
            'response_type' => $request->response_type,
            'code_challenge' => $request->code_challenge,
            'code_challenge_method' => $request->code_challenge_method,
        ]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'scopes' => 'required|array',
            'state' => 'sometimes|string',
            'response_type' => 'required|in:code,token',
            'code_challenge' => 'sometimes|string|min:43|max:128',
            'code_challenge_method' => 'sometimes|string|in:plain,S256',
        ]);

        $client = Client::with('organization')->findOrFail($request->client_id);

        // Validate organization context
        if (! $client->organization_id) {
            OAuthAuditLog::logError('approve', 'invalid_client', 'Client must be associated with an organization', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client must be associated with an organization',
            ], 400);
        }

        // Ensure user has access to the organization
        $userOrganizations = Auth::user()->memberships()->active()->pluck('organization_id');
        if (! $userOrganizations->contains($client->organization_id)) {
            OAuthAuditLog::logError('approve', 'access_denied', 'User does not have access to the organization', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'organization_id' => $client->organization_id,
            ]);

            return response()->json([
                'error' => 'access_denied',
                'error_description' => 'You do not have access to this organization',
            ], 403);
        }

        UserConsent::updateOrCreate([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
        ], [
            'scopes' => $request->scopes,
        ]);

        OAuthAuditLog::logEvent('authorize', [
            'client_id' => $client->id,
            'user_id' => Auth::id(),
            'scopes' => $request->scopes,
            'metadata' => ['user_approved' => true],
        ]);

        return $this->generateAuthorizationCode($client, $request->scopes, $request);
    }

    public function deny(Request $request)
    {
        OAuthAuditLog::logError('authorize', 'access_denied', 'The resource owner denied the request', [
            'client_id' => $request->client_id,
            'user_id' => Auth::id(),
            'metadata' => ['user_denied' => true],
        ]);

        $redirectUri = $request->redirect_uri;
        $state = $request->state;

        $params = [
            'error' => 'access_denied',
            'error_description' => 'The resource owner denied the request',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return redirect($redirectUri.'?'.http_build_query($params));
    }

    public function userinfo(Request $request)
    {
        $user = $request->user();
        $token = $request->bearerToken();

        $tokenModel = DB::table('oauth_access_tokens')
            ->where('id', $token)
            ->first();

        if (! $tokenModel) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $scopes = json_decode($tokenModel->scopes, true);

        $userinfo = ['sub' => $user->id];

        if (in_array('profile', $scopes)) {
            $userinfo = array_merge($userinfo, [
                'name' => $user->name,
                'given_name' => $user->first_name,
                'family_name' => $user->last_name,
                'picture' => $user->avatar_url,
                'preferred_username' => $user->username,
            ]);
        }

        if (in_array('email', $scopes)) {
            $userinfo = array_merge($userinfo, [
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
            ]);
        }

        if (in_array('organization:read', $scopes)) {
            $activeMemberships = $user->memberships()->active()->with(['organization', 'organizationUnit', 'organizationPosition'])->get();
            $userinfo['organizations'] = $activeMemberships->map(function ($membership) {
                return [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                    'code' => $membership->organization->organization_code,
                    'type' => $membership->organization->organization_type,
                    'level' => $membership->organization->level,
                    'membership_type' => $membership->membership_type,
                    'position' => $membership->organizationPosition?->name,
                    'unit' => $membership->organizationUnit?->name,
                    'start_date' => $membership->start_date->toISOString(),
                ];
            })->toArray();
        }

        if (in_array('tenant:read', $scopes)) {
            $tenantData = [];
            $activeMemberships = $user->memberships()->active()->with('organization.tenant')->get();
            foreach ($activeMemberships as $membership) {
                if ($membership->organization->tenant) {
                    $tenantData[] = [
                        'id' => $membership->organization->tenant->id,
                        'name' => $membership->organization->tenant->name,
                        'domain' => $membership->organization->tenant->domain,
                        'organization_id' => $membership->organization->id,
                    ];
                }
            }
            $userinfo['tenants'] = $tenantData;
        }

        return response()->json($userinfo);
    }

    public function discovery()
    {
        $baseUrl = config('app.url');

        return response()->json([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl.'/oauth/authorize',
            'token_endpoint' => $baseUrl.'/oauth/token',
            'userinfo_endpoint' => $baseUrl.'/oauth/userinfo',
            'jwks_uri' => $baseUrl.'/oauth/jwks',
            'registration_endpoint' => $baseUrl.'/oauth/clients',
            'introspection_endpoint' => $baseUrl.'/oauth/introspect',
            'revocation_endpoint' => $baseUrl.'/oauth/revoke',
            'scopes_supported' => array_keys($this->getAvailableScopes(new Organization)),
            'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'code id_token', 'token id_token', 'code token id_token'],
            'response_modes_supported' => ['query', 'fragment'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'name', 'given_name', 'family_name', 'email', 'email_verified', 'picture', 'preferred_username'],
        ]);
    }

    public function introspect(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = DB::table('oauth_access_tokens')
            ->where('id', $request->token)
            ->where('revoked', false)
            ->first();

        if (! $token || now()->isAfter($token->expires_at)) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'client_id' => $token->client_id,
            'username' => $token->user_id,
            'scope' => implode(' ', json_decode($token->scopes, true)),
            'exp' => strtotime($token->expires_at),
            'iat' => strtotime($token->created_at),
        ]);
    }

    public function jwks()
    {
        $publicKey = config('passport.public_key') ?: file_get_contents(Passport::keyPath('oauth-public.key'));

        $keyData = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));

        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'kid' => 'passport-key',
                    'n' => rtrim(strtr(base64_encode($keyData['rsa']['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($keyData['rsa']['e']), '+/', '-_'), '='),
                    'alg' => 'RS256',
                ],
            ],
        ]);
    }

    private function generateAuthorizationCode($client, $scopes, $request)
    {
        $code = Str::random(40);

        $authCodeData = [
            'id' => $code,
            'user_id' => Auth::id(),
            'client_id' => $client->id,
            'scopes' => json_encode($scopes),
            'revoked' => false,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($request->code_challenge) {
            $authCodeData['code_challenge'] = $request->code_challenge;
            $authCodeData['code_challenge_method'] = $request->code_challenge_method ?? 'plain';
        }

        DB::table('oauth_auth_codes')->insert($authCodeData);

        $params = [
            'code' => $code,
        ];

        if ($request->state) {
            $params['state'] = $request->state;
        }

        return redirect($request->redirect_uri.'?'.http_build_query($params));
    }

    private function getAvailableScopes($organization = null)
    {
        $scopes = OAuthScope::all()->keyBy('identifier')->map(function ($scope) {
            return [
                'name' => $scope->name,
                'description' => $scope->description,
            ];
        })->toArray();

        // All scopes are now available - filtering happens at validation level
        // No legacy non-organization clients supported

        return $scopes;
    }

    private function scopesMatch($existingScopes, $requestedScopes)
    {
        return empty(array_diff($requestedScopes, $existingScopes));
    }

    private function validateScopesForUser($requestedScopes, $availableScopes, $organization, $userOrganizations)
    {
        $validScopes = array_intersect($requestedScopes, array_keys($availableScopes));

        if ($organization) {
            $hasOrganizationAccess = $userOrganizations->contains('id', $organization->id);

            if (! $hasOrganizationAccess) {
                $organizationScopes = ['organization:read', 'organization:write', 'organization:members', 'organization:admin', 'organization:hierarchy'];
                $validScopes = array_diff($validScopes, $organizationScopes);
            } else {
                $userMembership = Auth::user()->memberships()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->first();

                if (! $userMembership || ! $userMembership->isManagementMembership()) {
                    $adminScopes = ['organization:admin', 'organization:hierarchy'];
                    $validScopes = array_diff($validScopes, $adminScopes);
                }
            }

            if ($organization->tenant) {
                $tenantScopes = ['tenant:read', 'tenant:admin'];
                if (! $hasOrganizationAccess) {
                    $validScopes = array_diff($validScopes, $tenantScopes);
                }
            }
        }

        return $validScopes;
    }
}
