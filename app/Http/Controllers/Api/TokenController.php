<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function generateToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
        ]);

        $user = auth()->user();

        // Create a personal access token
        $token = $user->createToken(
            $validated['name'],
            $validated['scopes'] ?? []
        );

        return response()->json([
            'access_token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => now()->addYears(1)->timestamp - now()->timestamp,
            'name' => $validated['name'],
            'scopes' => $validated['scopes'] ?? [],
        ]);
    }

    public function revokeToken(Request $request)
    {
        $validated = $request->validate([
            'token_id' => 'required|string',
        ]);

        $user = auth()->user();
        $token = $user->tokens()->where('id', $validated['token_id'])->first();

        if (! $token) {
            return response()->json([
                'error' => 'Token not found',
            ], 404);
        }

        $token->revoke();

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    public function listTokens()
    {
        $user = auth()->user();
        $tokens = $user->tokens()->where('revoked', false)->get();

        return response()->json([
            'tokens' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'scopes' => $token->scopes,
                    'created_at' => $token->created_at,
                    'updated_at' => $token->updated_at,
                ];
            }),
        ]);
    }
}
