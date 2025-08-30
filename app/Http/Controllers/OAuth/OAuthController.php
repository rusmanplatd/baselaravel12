<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\ApproveRequest;
use App\Http\Requests\OAuth\AuthorizeRequest;
use App\Http\Requests\OAuth\IntrospectRequest;
use App\Models\Client;
use App\Models\DeviceCode;
use App\Models\OAuthAuditLog;
use App\Models\OAuthScope;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Laravel\Passport\Passport;

#[Group('OAuth 2.0 & OpenID Connect')]
class OAuthController extends Controller
{
    #[Endpoint(
        title: 'OAuth 2.0 Authorization',
        description: 'Initiate OAuth 2.0 authorization flow with organization-scoped clients'
    )]
    #[Authenticated]
    #[QueryParam('client_id', 'string', 'OAuth client ID', true, '9a5d7f8e-1234-5678-9abc-def012345678')]
    #[QueryParam('redirect_uri', 'string', 'Redirect URI registered with the client', true, 'https://example.com/callback')]
    #[QueryParam('response_type', 'string', 'OAuth response type', true, 'code')]
    #[QueryParam('scope', 'string', 'Requested scopes (space-separated)', false, 'openid profile https://api.yourcompany.com/auth/organization.readonly')]
    #[QueryParam('state', 'string', 'CSRF protection state parameter', false, 'random-state-string')]
    #[ScribeResponse(null, 302, headers: ['Location' => 'https://example.com/callback?code=auth_code&state=random-state-string'])]
    public function handleAuthorize(AuthorizeRequest $request)
    {

        if (! Auth::check()) {
            return redirect()->route('login')->with('oauth_request', $request->all());
        }

        $client = Client::with('organization')->where('id', $request->client_id)->first();

        $redirectUris = is_string($client->redirect_uris)
            ? json_decode($client->redirect_uris, true)
            : $client->redirect_uris;

        if (! $client || ! in_array($request->redirect_uri, $redirectUris)) {
            return $this->errorResponse($request->redirect_uri, 'invalid_client',
                'The client credentials are invalid.', $request->state);
        }

        // Check if user has access to this client based on access scope configuration
        if (! $client->userHasAccess(Auth::user())) {
            OAuthAuditLog::logError('authorize', 'access_denied', 'User does not have access to this OAuth client', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'organization_id' => $client->organization_id,
                'access_scope' => $client->user_access_scope,
            ]);

            return $this->errorResponse($request->redirect_uri, 'access_denied',
                'You do not have access to this application.', $request->state);
        }

        $organization = $client->organization;
        $userOrganizations = Auth::user()->memberships()->with('organization')->get()->pluck('organization');

        $requestedScopes = explode(' ', $request->scope ?? '');
        $availableScopes = $this->getAvailableScopes($organization);
        $validScopes = $this->validateScopesForUser($requestedScopes, $availableScopes, $organization, $userOrganizations);

        $existingConsent = UserConsent::where([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
        ])->active()->first();

        // Google-style incremental authorization (strictly enforced)
        $includeGrantedScopes = $request->include_granted_scopes === 'true';
        $hasNewScopes = ! $existingConsent || ! empty(array_diff($validScopes, $existingConsent->scopes ?? []));

        // Google always requires include_granted_scopes for incremental auth
        if ($existingConsent && ! $request->has('include_granted_scopes')) {
            return $this->errorResponse($request->redirect_uri, 'invalid_request',
                'Missing required parameter: include_granted_scopes', $request->state);
        }

        // Combine scopes according to Google's strict rules
        $finalScopes = $validScopes;
        if ($existingConsent && $includeGrantedScopes) {
            $finalScopes = array_unique(array_merge($existingConsent->scopes, $validScopes));
        }

        // Auto-approve only if no new scopes AND not forced re-consent
        if (! $hasNewScopes && $request->approval_prompt !== 'force' && $request->prompt !== 'consent') {
            OAuthAuditLog::logEvent('authorize', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'scopes' => $finalScopes,
                'metadata' => ['auto_approved' => true, 'include_granted_scopes' => $includeGrantedScopes],
            ]);

            return $this->generateAuthorizationCode($client, $finalScopes, $request);
        }

        // Categorize scopes for better UX
        $categorizedScopes = $this->categorizeScopes($validScopes, $availableScopes);
        $existingScopes = $existingConsent ? $existingConsent->scopes : [];

        return Inertia::render('OAuth/Authorize', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'redirect' => $request->redirect_uri,
                'description' => $client->description,
                'website' => $client->website,
                'logo_url' => $client->logo_url,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->organization_code,
                ],
            ],
            'scopes' => $categorizedScopes,
            'existingScopes' => $existingScopes,
            'includeGrantedScopes' => $includeGrantedScopes,
            'isIncremental' => $existingConsent && count($existingScopes) > 0,
            'user' => Auth::user()->only(['name', 'first_name', 'middle_name', 'last_name', 'nickname', 'email', 'avatar_url', 'profile_url']),
            'state' => $request->state,
            'response_type' => $request->response_type,
            'code_challenge' => $request->code_challenge,
            'code_challenge_method' => $request->code_challenge_method,
        ]);
    }

    public function approve(ApproveRequest $request)
    {

        $client = Client::with('organization')->findOrFail($request->client_id);

        // Check if user has access to this client based on access scope configuration
        if (! $client->userHasAccess(Auth::user())) {
            OAuthAuditLog::logError('approve', 'access_denied', 'User does not have access to this OAuth client', [
                'client_id' => $client->id,
                'user_id' => Auth::id(),
                'organization_id' => $client->organization_id,
                'access_scope' => $client->user_access_scope,
            ]);

            return $this->errorResponse($request->redirect_uri, 'access_denied',
                'You do not have access to this application.', $request->state);
        }

        $availableScopes = $this->getAvailableScopes($client->organization);
        $existingConsent = UserConsent::where([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
        ])->active()->first();

        // Google-style incremental authorization - strict enforcement
        $requestedScopes = $request->scopes ?? [];
        $includeGrantedScopes = $request->include_granted_scopes === 'true';

        // Google requires include_granted_scopes for existing consents
        if ($existingConsent && ! $request->has('include_granted_scopes')) {
            return $this->errorResponse($request->redirect_uri, 'invalid_request',
                'include_granted_scopes parameter is required when user has existing permissions', $request->state);
        }

        // Final scopes based on Google's behavior
        $finalScopes = $requestedScopes;
        if ($existingConsent && $includeGrantedScopes) {
            $finalScopes = array_unique(array_merge($existingConsent->scopes, $requestedScopes));
        } elseif ($existingConsent && ! $includeGrantedScopes) {
            // Google behavior: only grant new scopes, revoke existing ones not in current request
            $finalScopes = $requestedScopes;
        }

        // Track scope changes for audit
        $newScopes = array_diff($finalScopes, $existingConsent->scopes ?? []);
        $revokedScopes = $existingConsent ? array_diff($existingConsent->scopes, $finalScopes) : [];

        // Prepare scope details with Google-style metadata
        $scopeDetails = [];
        foreach ($finalScopes as $scope) {
            $isNew = in_array($scope, $newScopes);
            $existingDetail = null;

            if ($existingConsent && ! $isNew && isset($existingConsent->scope_details)) {
                foreach ($existingConsent->scope_details as $detail) {
                    if ($detail['scope'] === $scope) {
                        $existingDetail = $detail;
                        break;
                    }
                }
            }

            $scopeDetails[] = $existingDetail ?? [
                'scope' => $scope,
                'name' => $availableScopes[$scope]['name'] ?? $scope,
                'description' => $availableScopes[$scope]['description'] ?? '',
                'granted_at' => $isNew ? now()->toISOString() : ($existingDetail['granted_at'] ?? now()->toISOString()),
                'is_new' => $isNew,
            ];
        }

        UserConsent::updateOrCreate([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
        ], [
            'scopes' => $finalScopes,
            'scope_details' => $scopeDetails,
            'status' => 'active',
            'granted_by_ip' => $request->ip(),
            'granted_user_agent' => $request->userAgent(),
            'expires_at' => now()->addYear(),
            'usage_stats' => array_merge(
                $existingConsent->usage_stats ?? ['access_count' => 0],
                [
                    'last_updated' => now()->toISOString(),
                    'incremental_grants' => ($existingConsent->usage_stats['incremental_grants'] ?? 0) + ($existingConsent ? 1 : 0),
                ]
            ),
        ]);

        OAuthAuditLog::logEvent('authorize', [
            'client_id' => $client->id,
            'user_id' => Auth::id(),
            'scopes' => $finalScopes,
            'new_scopes' => $newScopes,
            'revoked_scopes' => $revokedScopes,
            'metadata' => [
                'user_approved' => true,
                'incremental' => $existingConsent !== null,
                'include_granted_scopes' => $includeGrantedScopes,
                'scope_changes' => count($newScopes) + count($revokedScopes),
                'flow_type' => $existingConsent ? 'incremental' : 'initial',
            ],
        ]);

        return $this->generateAuthorizationCode($client, $finalScopes, $request);
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

    public function deviceAuthorization(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'scope' => 'sometimes|string',
        ]);

        $client = Client::with('organization')->find($request->client_id);
        if (! $client) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'The client credentials are invalid',
            ], 400);
        }

        // Check if client supports device flow
        $grantTypes = is_string($client->grant_types)
            ? json_decode($client->grant_types, true)
            : $client->grant_types;

        if (! in_array('urn:ietf:params:oauth:grant-type:device_code', $grantTypes ?? [])) {
            return response()->json([
                'error' => 'unsupported_grant_type',
                'error_description' => 'Client does not support device authorization grant',
            ], 400);
        }

        $requestedScopes = explode(' ', $request->scope ?? 'openid');
        $organization = $client->organization;
        $availableScopes = $this->getAvailableScopes($organization);
        $validScopes = array_intersect($requestedScopes, array_keys($availableScopes));

        $deviceCode = DeviceCode::generateDeviceCode();
        $userCode = DeviceCode::generateUserCode();
        $baseUrl = config('app.url');

        $verificationUri = $baseUrl.'/oauth/device';
        $verificationUriComplete = $verificationUri.'?user_code='.$userCode;

        DeviceCode::create([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'client_id' => $client->id,
            'scopes' => $validScopes,
            'expires_at' => now()->addMinutes(10),
            'verification_uri' => $verificationUri,
            'verification_uri_complete' => $verificationUriComplete,
            'interval' => 5,
        ]);

        OAuthAuditLog::logEvent('device_authorization', [
            'client_id' => $client->id,
            'user_code' => $userCode,
            'scopes' => $validScopes,
        ]);

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'verification_uri' => $verificationUri,
            'verification_uri_complete' => $verificationUriComplete,
            'expires_in' => 600,
            'interval' => 5,
        ]);
    }

    public function deviceVerification(Request $request)
    {
        $userCode = $request->input('user_code') ?? $request->query('user_code');

        if (! Auth::check()) {
            return redirect()->route('login')->with('device_user_code', $userCode);
        }

        if (! $userCode) {
            return Inertia::render('OAuth/DeviceCodeEntry');
        }

        $deviceCode = DeviceCode::where('user_code', $userCode)->active()->first();

        if (! $deviceCode) {
            return Inertia::render('OAuth/DeviceCodeEntry', [
                'error' => 'Invalid or expired code',
            ]);
        }

        $client = $deviceCode->client;
        $organization = $client->organization;
        $availableScopes = $this->getAvailableScopes($organization);

        return Inertia::render('OAuth/DeviceAuthorize', [
            'device_code' => $deviceCode,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->organization_code,
                ],
            ],
            'scopes' => array_map(function ($scope) use ($availableScopes) {
                return [
                    'id' => $scope,
                    'name' => $availableScopes[$scope]['name'] ?? $scope,
                    'description' => $availableScopes[$scope]['description'] ?? '',
                ];
            }, $deviceCode->scopes ?? []),
            'user' => Auth::user()->only(['name', 'first_name', 'middle_name', 'last_name', 'nickname', 'email', 'avatar_url', 'profile_url']),
        ]);
    }

    public function deviceApprove(Request $request)
    {
        $request->validate([
            'user_code' => 'required|string',
            'action' => 'required|in:approve,deny',
        ]);

        $deviceCode = DeviceCode::where('user_code', $request->user_code)->active()->first();

        if (! $deviceCode) {
            return response()->json([
                'error' => 'Invalid or expired code',
            ], 400);
        }

        if ($request->action === 'approve') {
            $deviceCode->markAsAuthorized(Auth::user());

            OAuthAuditLog::logEvent('device_approved', [
                'client_id' => $deviceCode->client_id,
                'user_id' => Auth::id(),
                'user_code' => $deviceCode->user_code,
                'scopes' => $deviceCode->scopes,
            ]);

            return response()->json(['message' => 'Device authorized successfully']);
        } else {
            $deviceCode->markAsDenied();

            OAuthAuditLog::logError('device_denied', 'access_denied', 'User denied device authorization', [
                'client_id' => $deviceCode->client_id,
                'user_id' => Auth::id(),
                'user_code' => $deviceCode->user_code,
            ]);

            return response()->json(['message' => 'Device authorization denied']);
        }
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
            $profileData = [
                'name' => $user->name,
                'given_name' => $user->first_name,
                'family_name' => $user->last_name,
                'picture' => $user->avatar_url,
                'preferred_username' => $user->username,
            ];

            // Add optional OIDC standard profile claims
            if ($user->middle_name) {
                $profileData['middle_name'] = $user->middle_name;
            }
            if ($user->nickname) {
                $profileData['nickname'] = $user->nickname;
            }
            if ($user->profile_url) {
                $profileData['profile'] = $user->profile_url;
            }
            if ($user->website) {
                $profileData['website'] = $user->website;
            }
            if ($user->gender) {
                $profileData['gender'] = $user->gender;
            }
            if ($user->birthdate) {
                $profileData['birthdate'] = $user->birthdate->format('Y-m-d');
            }
            if ($user->zoneinfo) {
                $profileData['zoneinfo'] = $user->zoneinfo;
            }
            if ($user->locale) {
                $profileData['locale'] = $user->locale;
            }
            if ($user->profile_updated_at) {
                $profileData['updated_at'] = $user->profile_updated_at->timestamp;
            }

            $userinfo = array_merge($userinfo, $profileData);
        }

        if (in_array('email', $scopes)) {
            $userinfo = array_merge($userinfo, [
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
            ]);
        }

        if (in_array('address', $scopes)) {
            $address = [];
            if ($user->street_address) {
                $address['street_address'] = $user->street_address;
            }
            if ($user->locality) {
                $address['locality'] = $user->locality;
            }
            if ($user->region) {
                $address['region'] = $user->region;
            }
            if ($user->postal_code) {
                $address['postal_code'] = $user->postal_code;
            }
            if ($user->country) {
                $address['country'] = $user->country;
            }
            if ($user->formatted_address) {
                $address['formatted'] = $user->formatted_address;
            }

            if (! empty($address)) {
                $userinfo['address'] = $address;
            }
        }

        if (in_array('phone', $scopes)) {
            if ($user->phone_number) {
                $userinfo['phone_number'] = $user->phone_number;
                $userinfo['phone_number_verified'] = $user->phone_verified_at !== null;
            }
        }

        // Add custom claims for extended profile information
        if (in_array('https://api.yourcompany.com/auth/userinfo.profile', $scopes)) {
            if ($user->external_id) {
                $userinfo['external_id'] = $user->external_id;
            }

            if ($user->social_links) {
                $userinfo['social_links'] = $user->social_links;
            }
        }

        if (in_array('https://api.yourcompany.com/auth/organization.readonly', $scopes) || in_array('https://api.yourcompany.com/auth/organization', $scopes) || in_array('https://api.yourcompany.com/auth/organization.admin', $scopes)) {
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

        if (in_array('https://api.yourcompany.com/auth/organization.readonly', $scopes) || in_array('https://api.yourcompany.com/auth/organization.admin', $scopes)) {
            $tenantData = [];
            $activeMemberships = $user->memberships()->active()->with('organization')->get();
            foreach ($activeMemberships as $membership) {
                // Organization acts as the tenant in this system
                $tenantData[] = [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                    'domain' => strtolower($membership->organization->organization_code).'.example.com',
                    'organization_id' => $membership->organization->id,
                ];
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
            'device_authorization_endpoint' => $baseUrl.'/oauth/device_authorization',
            'scopes_supported' => array_keys($this->getAvailableScopes(new Organization)),
            'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'code id_token', 'token id_token', 'code token id_token'],
            'response_modes_supported' => ['query', 'fragment'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials', 'urn:ietf:params:oauth:grant-type:device_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['plain', 'S256'],
            'claims_supported' => [
                // Standard OIDC claims
                'sub', 'iss', 'aud', 'exp', 'iat', 'name', 'given_name', 'middle_name', 'family_name',
                'nickname', 'preferred_username', 'profile', 'picture', 'website', 'email', 'email_verified',
                'gender', 'birthdate', 'zoneinfo', 'locale', 'phone_number', 'phone_number_verified',
                'address', 'updated_at',
                // Custom claims
                'external_id', 'social_links', 'organizations', 'tenants',
            ],
        ]);
    }

    public function introspect(IntrospectRequest $request)
    {
        $validated = $request->validated();

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

    public function revoke(Request $request)
    {
        $token = $request->input('token');
        if (! $token) {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        // Revoke access token
        DB::table('oauth_access_tokens')
            ->where('id', $token)
            ->update(['revoked' => true]);

        // Revoke refresh token
        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $token)
            ->update(['revoked' => true]);

        return response()->json(['revoked' => true]);
    }

    public function userConsents(Request $request)
    {
        $consents = UserConsent::where('user_id', Auth::id())
            ->active()
            ->with('client.organization')
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($consent) {
                return [
                    'id' => $consent->id,
                    'client' => [
                        'id' => $consent->client->id,
                        'name' => $consent->client->name,
                        'description' => $consent->client->description,
                        'website' => $consent->client->website,
                        'logo_url' => $consent->client->logo_url,
                        'organization' => [
                            'name' => $consent->client->organization->name,
                            'code' => $consent->client->organization->organization_code,
                        ],
                    ],
                    'scopes' => $consent->scope_details ?? [],
                    'granted_at' => $consent->created_at,
                    'last_used_at' => $consent->last_used_at,
                    'expires_at' => $consent->expires_at,
                    'usage_stats' => $consent->usage_stats ?? [],
                    'granted_by_ip' => $consent->granted_by_ip,
                ];
            });

        return response()->json(['consents' => $consents]);
    }

    public function revokeConsent(Request $request, $consentId)
    {
        $consent = UserConsent::where('user_id', Auth::id())
            ->where('id', $consentId)
            ->active()
            ->first();

        if (! $consent) {
            return response()->json([
                'error' => 'consent_not_found',
                'error_description' => 'Consent not found or already revoked',
            ], 404);
        }

        $consent->revoke();

        // Revoke all active tokens for this client-user combination
        DB::table('oauth_access_tokens')
            ->where('user_id', Auth::id())
            ->where('client_id', $consent->client_id)
            ->update(['revoked' => true]);

        DB::table('oauth_refresh_tokens')
            ->whereIn('access_token_id', function ($query) use ($consent) {
                $query->select('id')
                    ->from('oauth_access_tokens')
                    ->where('user_id', Auth::id())
                    ->where('client_id', $consent->client_id);
            })
            ->update(['revoked' => true]);

        OAuthAuditLog::logEvent('consent_revoked', [
            'client_id' => $consent->client_id,
            'user_id' => Auth::id(),
            'consent_id' => $consent->id,
            'revoked_scopes' => $consent->scopes,
        ]);

        return response()->json(['message' => 'Consent revoked successfully']);
    }

    public function tokenInfo(Request $request)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'missing_token'], 400);
        }

        $tokenModel = DB::table('oauth_access_tokens')
            ->select([
                'id', 'client_id', 'user_id', 'scopes', 'created_at',
                'updated_at', 'expires_at', 'revoked',
            ])
            ->where('id', $token)
            ->first();

        if (! $tokenModel || $tokenModel->revoked) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        if (now()->isAfter($tokenModel->expires_at)) {
            return response()->json(['error' => 'token_expired'], 401);
        }

        $client = Client::with('organization')->find($tokenModel->client_id);
        $user = User::find($tokenModel->user_id);

        return response()->json([
            'active' => true,
            'client_id' => $tokenModel->client_id,
            'client_name' => $client->name,
            'organization' => [
                'name' => $client->organization->name,
                'code' => $client->organization->organization_code,
            ],
            'user_id' => $tokenModel->user_id,
            'username' => $user->email,
            'scope' => implode(' ', json_decode($tokenModel->scopes, true)),
            'exp' => strtotime($tokenModel->expires_at),
            'iat' => strtotime($tokenModel->created_at),
            'issued_at' => $tokenModel->created_at,
            'expires_at' => $tokenModel->expires_at,
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
        return OAuthScope::all()->keyBy('identifier')->map(function ($scope) {
            return [
                'name' => $scope->name,
                'description' => $scope->description,
            ];
        })->toArray();
    }

    private function scopesMatch($existingScopes, $requestedScopes)
    {
        return empty(array_diff($requestedScopes, $existingScopes ?? []));
    }

    private function validateScopesForUser($requestedScopes, $availableScopes, $organization, $userOrganizations)
    {
        $validScopes = array_intersect($requestedScopes, array_keys($availableScopes));

        if ($organization) {
            $hasOrganizationAccess = $userOrganizations->contains('id', $organization->id);

            if (! $hasOrganizationAccess) {
                $organizationScopes = ['https://api.yourcompany.com/auth/organization.readonly', 'https://api.yourcompany.com/auth/organization', 'https://api.yourcompany.com/auth/organization.members', 'https://api.yourcompany.com/auth/organization.admin'];
                $validScopes = array_diff($validScopes, $organizationScopes);
            } else {
                $userMembership = Auth::user()->memberships()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->first();

                if (! $userMembership || ! $userMembership->isManagementMembership()) {
                    $adminScopes = ['https://api.yourcompany.com/auth/organization.admin'];
                    $validScopes = array_diff($validScopes, $adminScopes);
                }
            }

            // Organization acts as the tenant in this system
            $tenantScopes = ['https://api.yourcompany.com/auth/organization.readonly', 'https://api.yourcompany.com/auth/organization.admin'];
            if (! $hasOrganizationAccess) {
                $validScopes = array_diff($validScopes, $tenantScopes);
            }
        }

        return $validScopes;
    }

    private function errorResponse($redirectUri, $error, $errorDescription, $state = null, $errorUri = null)
    {
        OAuthAuditLog::logError('authorize', $error, $errorDescription);

        // Google-style error mapping
        $googleStyleErrors = [
            'invalid_client' => [
                'error' => 'unauthorized_client',
                'description' => 'The client is not authorized to request an authorization code using this method.',
            ],
            'access_denied' => [
                'error' => 'access_denied',
                'description' => 'The resource owner or authorization server denied the request.',
            ],
            'unsupported_response_type' => [
                'error' => 'unsupported_response_type',
                'description' => 'The authorization server does not support obtaining an authorization code using this method.',
            ],
            'invalid_scope' => [
                'error' => 'invalid_scope',
                'description' => 'The requested scope is invalid, unknown, or malformed.',
            ],
            'server_error' => [
                'error' => 'server_error',
                'description' => 'The authorization server encountered an unexpected condition that prevented it from fulfilling the request.',
            ],
        ];

        $mappedError = $googleStyleErrors[$error] ?? [
            'error' => $error,
            'description' => $errorDescription,
        ];

        if (! $redirectUri) {
            $response = [
                'error' => $mappedError['error'],
                'error_description' => $mappedError['description'],
            ];

            if ($errorUri) {
                $response['error_uri'] = $errorUri;
            }

            return response()->json($response, $this->getHttpStatusForError($mappedError['error']));
        }

        $params = [
            'error' => $mappedError['error'],
            'error_description' => $mappedError['description'],
        ];

        if ($state) {
            $params['state'] = $state;
        }

        if ($errorUri) {
            $params['error_uri'] = $errorUri;
        }

        return redirect($redirectUri.'?'.http_build_query($params));
    }

    private function getHttpStatusForError($error)
    {
        $statusMap = [
            'invalid_request' => 400,
            'unauthorized_client' => 401,
            'access_denied' => 403,
            'unsupported_response_type' => 400,
            'invalid_scope' => 400,
            'server_error' => 500,
            'temporarily_unavailable' => 503,
        ];

        return $statusMap[$error] ?? 400;
    }

    private function categorizeScopes($scopes, $availableScopes)
    {
        $categories = [
            'identity' => [
                'title' => 'Basic info',
                'description' => 'Access your basic profile information',
                'scopes' => [],
                'required' => true,
            ],
            'profile' => [
                'title' => 'Profile',
                'description' => 'View your personal profile information',
                'scopes' => [],
                'required' => false,
            ],
            'organization' => [
                'title' => 'Organization access',
                'description' => 'Access your organization information and memberships',
                'scopes' => [],
                'required' => false,
            ],
            'custom' => [
                'title' => 'Additional permissions',
                'description' => 'Other application-specific permissions',
                'scopes' => [],
                'required' => false,
            ],
        ];

        foreach ($scopes as $scope) {
            $scopeInfo = [
                'id' => $scope,
                'name' => $availableScopes[$scope]['name'] ?? $scope,
                'description' => $availableScopes[$scope]['description'] ?? '',
                'sensitive' => $this->isSensitiveScope($scope),
            ];

            if (in_array($scope, ['openid', 'offline_access'])) {
                $categories['identity']['scopes'][] = $scopeInfo;
            } elseif (in_array($scope, ['profile', 'email', 'phone', 'address'])) {
                $categories['profile']['scopes'][] = $scopeInfo;
            } elseif (strpos($scope, 'organization') !== false) {
                $categories['organization']['scopes'][] = $scopeInfo;
            } else {
                $categories['custom']['scopes'][] = $scopeInfo;
            }
        }

        // Remove empty categories
        return array_filter($categories, function ($category) {
            return ! empty($category['scopes']);
        });
    }

    private function isSensitiveScope($scope)
    {
        $sensitiveScopes = [
            'https://api.yourcompany.com/auth/organization.admin',
            'https://api.yourcompany.com/auth/organization.members',
            'offline_access',
        ];

        return in_array($scope, $sensitiveScopes) || strpos($scope, 'write') !== false || strpos($scope, 'admin') !== false;
    }
}
