<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Http\Requests\StorePasskeyRequest;
use Spatie\LaravelPasskeys\Models\Passkey;

class WebAuthnController extends Controller
{
    public function registerOptions(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $options = $user->passkeyRegistrationOptions();

        return response()->json($options);
    }

    public function register(StorePasskeyRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            $passkey = $user->storePasskey(
                $request->safe()->merge(['name' => $request->input('name', 'Security Key')])
            );

            return response()->json([
                'success' => true,
                'passkey' => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to register passkey'], 400);
        }
    }

    public function authenticateOptions(Request $request): JsonResponse
    {
        $options = Passkey::passkeyAuthenticationOptions();

        return response()->json($options);
    }

    public function authenticate(Request $request): JsonResponse
    {
        try {
            $user = User::passkeyAuthentication($request->all());

            if (! $user) {
                throw ValidationException::withMessages([
                    'webauthn' => ['WebAuthn authentication failed.'],
                ]);
            }

            Auth::login($user, $request->boolean('remember'));

            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed'], 400);
        }
    }

    public function delete(Request $request, Passkey $passkey): JsonResponse
    {
        $user = Auth::user();

        if (! $user || $passkey->passable_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $passkey->delete();

        return response()->json(['success' => true]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $passkeys = $user->passkeys()->select(['id', 'name', 'created_at'])->get();

        return response()->json(['passkeys' => $passkeys]);
    }
}
