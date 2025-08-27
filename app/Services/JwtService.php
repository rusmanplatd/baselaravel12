<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

class JwtService
{
    private function getPrivateKey()
    {
        return config('passport.private_key') ?: file_get_contents(Passport::keyPath('oauth-private.key'));
    }

    private function getPublicKey()
    {
        return config('passport.public_key') ?: file_get_contents(Passport::keyPath('oauth-public.key'));
    }

    public function generateIdToken($user, $client, $scopes, $organization = null, $nonce = null)
    {
        $now = time();
        $exp = $now + (60 * 60); // 1 hour

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'aud' => $client->id,
            'iat' => $now,
            'exp' => $exp,
            'auth_time' => $now,
        ];

        if ($nonce) {
            $payload['nonce'] = $nonce;
        }

        if (in_array('profile', $scopes)) {
            $payload['name'] = $user->name;
            $payload['given_name'] = $user->first_name;
            $payload['family_name'] = $user->last_name;
            $payload['preferred_username'] = $user->username ?? $user->email;
            $payload['picture'] = $user->avatar_url;
        }

        if (in_array('email', $scopes)) {
            $payload['email'] = $user->email;
            $payload['email_verified'] = $user->email_verified_at !== null;
        }

        // Add organization context if available
        if ($organization) {
            $payload['org_id'] = $organization->id;
            $payload['org_name'] = $organization->name;
            $payload['org_code'] = $organization->organization_code;

            if (in_array('https://api.yourcompany.com/auth/organization.readonly', $scopes) || in_array('https://api.yourcompany.com/auth/organization', $scopes) || in_array('https://api.yourcompany.com/auth/organization.admin', $scopes)) {
                $userMembership = $user->memberships()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->with(['organizationPosition', 'organizationUnit'])
                    ->first();

                if ($userMembership) {
                    $payload['org_membership'] = [
                        'type' => $userMembership->membership_type,
                        'position' => $userMembership->organizationPosition?->name,
                        'unit' => $userMembership->organizationUnit?->name,
                        'start_date' => $userMembership->start_date->toISOString(),
                    ];
                }
            }

            if ((in_array('https://api.yourcompany.com/auth/organization.readonly', $scopes) || in_array('https://api.yourcompany.com/auth/organization.admin', $scopes)) && $organization->tenant) {
                $payload['tenant_id'] = $organization->tenant->id;
                $payload['tenant_domain'] = $organization->tenant->domain;
            }
        }

        return JWT::encode($payload, $this->getPrivateKey(), 'RS256');
    }

    public function verifyToken($token)
    {
        try {
            return JWT::decode($token, new Key($this->getPublicKey(), 'RS256'));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getJwks()
    {
        return Cache::remember('oauth.jwks', 3600, function () {
            $publicKey = $this->getPublicKey();
            $keyData = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));

            return [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'use' => 'sig',
                        'kid' => 'oauth-key-1',
                        'n' => rtrim(strtr(base64_encode($keyData['rsa']['n']), '+/', '-_'), '='),
                        'e' => rtrim(strtr(base64_encode($keyData['rsa']['e']), '+/', '-_'), '='),
                        'alg' => 'RS256',
                    ],
                ],
            ];
        });
    }
}
