<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class OAuthClientController extends Controller
{
    private string $authServerBaseUrl = 'http://localhost:8000';

    private string $clientId = 'a8704536-ee26-4675-b324-741444ffb54e'; // Developer Tools client

    private string $redirectUri = 'http://localhost:8081/oauth/callback';

    /**
     * Show the OAuth test page
     */
    public function index()
    {
        $scopes = [
            'openid' => 'OpenID Connect identity',
            'profile' => 'Basic profile information',
            'email' => 'Email address',
            'https://api.yourcompany.com/auth/organization.readonly' => 'Read organization information',
        ];

        $clientSecret = env('OAUTH_CLIENT_SECRET', '');

        return view('oauth-dashboard', compact('scopes', 'clientSecret'));
    }

    /**
     * Start the OAuth authorization flow
     */
    public function startAuthorization(Request $request)
    {
        $request->validate([
            'scopes' => 'array',
            'scopes.*' => 'string',
        ]);

        // Generate and store state parameter
        $state = Str::random(40);
        Session::put('oauth_state', $state);
        Session::put('oauth_start_time', now());

        // Build scope string
        $selectedScopes = $request->input('scopes', ['openid', 'profile', 'email']);
        $scopeString = implode(' ', $selectedScopes);

        // Build authorization URL
        $authParams = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $scopeString,
            'state' => $state,
            'prompt' => 'consent', // Force consent screen for demo purposes
        ];

        $authUrl = $this->authServerBaseUrl.'/oauth/authorize?'.http_build_query($authParams);

        // Store selected scopes for later display
        Session::put('oauth_requested_scopes', $selectedScopes);

        return redirect($authUrl);
    }

    /**
     * Handle the OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');
        $errorDescription = $request->get('error_description');

        // Check for authorization errors
        if ($error) {
            return view('oauth-result', [
                'success' => false,
                'error' => $error,
                'error_description' => $errorDescription,
                'step' => 'authorization',
            ]);
        }

        // Validate state parameter
        $sessionState = Session::get('oauth_state');
        if (! $state || ! $sessionState || $state !== $sessionState) {
            return view('oauth-result', [
                'success' => false,
                'error' => 'invalid_state',
                'error_description' => 'State parameter mismatch. Possible CSRF attack.',
                'step' => 'state_validation',
            ]);
        }

        if (! $code) {
            return view('oauth-result', [
                'success' => false,
                'error' => 'missing_code',
                'error_description' => 'Authorization code not provided',
                'step' => 'authorization',
            ]);
        }

        try {
            // Exchange code for tokens
            $tokenData = $this->exchangeCodeForTokens($code);

            if (! $tokenData['success']) {
                return view('oauth-result', [
                    'success' => false,
                    'error' => $tokenData['error'] ?? 'token_exchange_failed',
                    'error_description' => $tokenData['error_description'] ?? 'Failed to exchange authorization code for tokens',
                    'step' => 'token_exchange',
                    'token_response' => $tokenData['response'] ?? null,
                ]);
            }

            // Get user information
            $userInfo = null;
            $userInfoError = null;
            if (! empty($tokenData['access_token'])) {
                $userInfoResult = $this->getUserInfo($tokenData['access_token']);
                if ($userInfoResult['success']) {
                    $userInfo = $userInfoResult['data'];
                } else {
                    $userInfoError = $userInfoResult['error'];
                }
            }

            // Decode ID token if present
            $idTokenClaims = null;
            if (! empty($tokenData['id_token'])) {
                $idTokenClaims = $this->parseIdToken($tokenData['id_token']);
            }

            // Calculate flow duration
            $startTime = Session::get('oauth_start_time');
            $duration = $startTime ? now()->diffInMilliseconds($startTime) : null;

            // Clean up session
            Session::forget(['oauth_state', 'oauth_start_time', 'oauth_requested_scopes']);

            return view('oauth-result', [
                'success' => true,
                'tokens' => $tokenData,
                'user_info' => $userInfo,
                'user_info_error' => $userInfoError,
                'id_token_claims' => $idTokenClaims,
                'requested_scopes' => Session::get('oauth_requested_scopes', []),
                'flow_duration' => $duration,
                'step' => 'complete',
            ]);

        } catch (\Exception $e) {
            return view('oauth-result', [
                'success' => false,
                'error' => 'unexpected_error',
                'error_description' => $e->getMessage(),
                'step' => 'processing',
            ]);
        }
    }

    /**
     * Test token refresh functionality
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            $refreshResult = $this->refreshAccessToken($request->input('refresh_token'));

            return response()->json([
                'success' => $refreshResult['success'],
                'tokens' => $refreshResult['success'] ? $refreshResult : null,
                'error' => ! $refreshResult['success'] ? $refreshResult['error'] : null,
                'error_description' => ! $refreshResult['success'] ? $refreshResult['error_description'] : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'unexpected_error',
                'error_description' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Revoke a token
     */
    public function revoke(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'token_type_hint' => 'in:access_token,refresh_token',
        ]);

        try {
            $response = Http::asForm()->post($this->authServerBaseUrl.'/oauth/revoke', [
                'token' => $request->input('token'),
                'token_type_hint' => $request->input('token_type_hint', 'access_token'),
                'client_id' => $this->clientId,
                'client_secret' => env('OAUTH_CLIENT_SECRET', ''),
            ]);

            return response()->json([
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'unexpected_error',
                'error_description' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get OAuth server discovery information
     */
    public function discovery()
    {
        try {
            $oauthDiscovery = Http::get($this->authServerBaseUrl.'/.well-known/oauth-authorization-server')->json();
            $oidcDiscovery = Http::get($this->authServerBaseUrl.'/.well-known/openid_configuration')->json();

            return response()->json([
                'oauth2' => $oauthDiscovery,
                'oidc' => $oidcDiscovery,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'discovery_failed',
                'error_description' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = Http::asForm()->post($this->authServerBaseUrl.'/oidc/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => env('OAUTH_CLIENT_SECRET', ''),
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return array_merge(['success' => true], $data);
            } else {
                $errorData = $response->json();

                return [
                    'success' => false,
                    'error' => $errorData['error'] ?? 'token_request_failed',
                    'error_description' => $errorData['error_description'] ?? 'Token request failed',
                    'response' => $errorData,
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'network_error',
                'error_description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh an access token
     */
    private function refreshAccessToken(string $refreshToken): array
    {
        try {
            $response = Http::asForm()->post($this->authServerBaseUrl.'/oidc/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => env('OAUTH_CLIENT_SECRET', ''),
                'refresh_token' => $refreshToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return array_merge(['success' => true], $data);
            } else {
                $errorData = $response->json();

                return [
                    'success' => false,
                    'error' => $errorData['error'] ?? 'refresh_failed',
                    'error_description' => $errorData['error_description'] ?? 'Token refresh failed',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'network_error',
                'error_description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user information using access token
     */
    private function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get($this->authServerBaseUrl.'/oidc/userinfo');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'userinfo_request_failed',
                    'error_description' => 'Failed to retrieve user information',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'network_error',
                'error_description' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse and decode ID token (basic implementation - not validating signature)
     */
    private function parseIdToken(string $idToken): ?array
    {
        try {
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = base64_decode(strtr($parts[1], '-_', '+/'));

            return json_decode($payload, true);

        } catch (\Exception $e) {
            return null;
        }
    }
}
