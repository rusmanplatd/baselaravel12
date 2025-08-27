<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OidcTokenRequest;
use App\Models\OAuthAuditLog;
use App\Models\Organization;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

class OidcController extends Controller
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function token(OidcTokenRequest $request)
    {

        if ($request->grant_type === 'authorization_code') {
            return $this->handleAuthorizationCodeGrant($request);
        }

        return response()->json(['error' => 'unsupported_grant_type'], 400);
    }

    private function handleAuthorizationCodeGrant(Request $request)
    {
        $authCode = DB::table('oauth_auth_codes')
            ->where('id', $request->code)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $authCode) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        $client = Client::with('organization')->find($authCode->client_id);
        if (! $client || $client->id !== $request->client_id) {
            OAuthAuditLog::logError('token', 'invalid_client', 'Client not found or mismatch', [
                'client_id' => $request->client_id,
                'auth_code_client_id' => $authCode->client_id ?? null,
            ]);

            return response()->json(['error' => 'invalid_client'], 400);
        }

        // Ensure client has organization context (no legacy clients allowed)
        if (! $client->organization_id) {
            OAuthAuditLog::logError('token', 'invalid_client', 'Client must be associated with an organization', [
                'client_id' => $client->id,
            ]);

            return response()->json(['error' => 'invalid_client'], 400);
        }

        if ($authCode->code_challenge) {
            if (! $request->code_verifier) {
                return response()->json(['error' => 'code_verifier_required'], 400);
            }

            $method = $authCode->code_challenge_method ?: 'plain';

            if ($method === 'S256') {
                $challenge = rtrim(strtr(base64_encode(hash('sha256', $request->code_verifier, true)), '+/', '-_'), '=');
            } else {
                $challenge = $request->code_verifier;
            }

            if (! hash_equals($authCode->code_challenge, $challenge)) {
                return response()->json(['error' => 'invalid_grant'], 400);
            }
        }

        $user = \App\Models\User::find($authCode->user_id);
        $scopes = json_decode($authCode->scopes, true);

        $accessTokenExpiry = now()->addDays(15);
        $refreshTokenExpiry = now()->addDays(30);

        $accessToken = Str::random(80);
        $refreshToken = Str::random(80);

        DB::table('oauth_access_tokens')->insert([
            'id' => $accessToken,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'OIDC Token',
            'scopes' => json_encode($scopes),
            'revoked' => false,
            'expires_at' => $accessTokenExpiry,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('oauth_refresh_tokens')->insert([
            'id' => $refreshToken,
            'access_token_id' => $accessToken,
            'revoked' => false,
            'expires_at' => $refreshTokenExpiry,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('oauth_auth_codes')->where('id', $request->code)->update(['revoked' => true]);

        // Update client last_used_at
        $client->update(['last_used_at' => now()]);

        // Log successful token issuance with organization context
        OAuthAuditLog::logEvent('token', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'scopes' => $scopes,
            'grant_type' => 'authorization_code',
            'metadata' => ['expires_at' => $accessTokenExpiry->toISOString()],
        ]);

        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenExpiry->diffInSeconds(now()),
            'refresh_token' => $refreshToken,
            'scope' => implode(' ', $scopes),
        ];

        if (in_array('openid', $scopes)) {
            $idToken = $this->jwtService->generateIdToken($user, $client, $scopes, $client->organization);
            $response['id_token'] = $idToken;
        }

        return response()->json($response);
    }

    public function userinfo(Request $request)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $tokenRecord = DB::table('oauth_access_tokens')
            ->where('id', $token)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $tokenRecord) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $user = \App\Models\User::find($tokenRecord->user_id);
        $client = Client::with('organization')->find($tokenRecord->client_id);
        $scopes = json_decode($tokenRecord->scopes, true);

        $userinfo = ['sub' => $user->id];

        if (in_array('profile', $scopes)) {
            $userinfo = array_merge($userinfo, [
                'name' => $user->name,
                'given_name' => $user->first_name,
                'family_name' => $user->last_name,
                'preferred_username' => $user->username ?? $user->email,
                'picture' => $user->avatar_url,
            ]);
        }

        if (in_array('email', $scopes)) {
            $userinfo = array_merge($userinfo, [
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
            ]);
        }

        // Add organization and tenant context based on scopes
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

        if ((in_array('https://api.yourcompany.com/auth/organization.readonly', $scopes) || in_array('https://api.yourcompany.com/auth/organization.admin', $scopes)) && $client->organization) {
            $tenantData = [];
            if ($client->organization->tenant) {
                $tenantData[] = [
                    'id' => $client->organization->tenant->id,
                    'name' => $client->organization->tenant->name,
                    'domain' => $client->organization->tenant->domain,
                    'organization_id' => $client->organization->id,
                ];
            }
            $userinfo['tenants'] = $tenantData;
        }

        return response()->json($userinfo);
    }

    public function jwks()
    {
        return response()->json($this->jwtService->getJwks());
    }

    public function discovery()
    {
        $baseUrl = config('app.url');

        return response()->json([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl.'/oauth/authorize',
            'token_endpoint' => $baseUrl.'/oidc/token',
            'userinfo_endpoint' => $baseUrl.'/oidc/userinfo',
            'jwks_uri' => $baseUrl.'/oidc/jwks',
            'registration_endpoint' => $baseUrl.'/oauth/clients',
            'scopes_supported' => [
                'openid', 'profile', 'email',
                'https://api.yourcompany.com/auth/organization.readonly',
                'https://api.yourcompany.com/auth/organization',
                'https://api.yourcompany.com/auth/organization.members',
                'https://api.yourcompany.com/auth/organization.admin',
                'https://api.yourcompany.com/auth/userinfo.profile',
                'https://api.yourcompany.com/auth/user.modify',
                'offline_access',
            ],
            'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'code id_token', 'token id_token', 'code token id_token'],
            'response_modes_supported' => ['query', 'fragment'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic', 'none'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'name', 'given_name', 'family_name', 'email', 'email_verified', 'picture', 'preferred_username'],
            'code_challenge_methods_supported' => ['plain', 'S256'],
        ]);
    }
}
