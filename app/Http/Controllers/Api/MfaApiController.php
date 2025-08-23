<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaDisableRequest;
use App\Http\Requests\Auth\MfaSetupRequest;
use App\Http\Requests\Auth\MfaVerifyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Multi-Factor Authentication')]
class MfaApiController extends Controller
{
    #[Endpoint(
        title: 'Get MFA status',
        description: 'Retrieve the current MFA configuration status for the authenticated user'
    )]
    #[Authenticated]
    #[ScribeResponse([
        'mfa_enabled' => true,
        'totp_enabled' => true,
        'backup_codes_count' => 8,
        'created_at' => '2024-01-15T10:30:00Z',
        'confirmed_at' => '2024-01-15T10:35:00Z',
    ])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mfaSettings = $user->mfaSettings;

        return response()->json([
            'mfa_enabled' => $mfaSettings?->hasMfaEnabled() ?? false,
            'totp_enabled' => $mfaSettings?->totp_enabled ?? false,
            'backup_codes_count' => $mfaSettings ? (count($mfaSettings->backup_codes ?? []) - ($mfaSettings->backup_codes_used ?? 0)) : 0,
            'created_at' => $mfaSettings?->created_at,
            'confirmed_at' => $mfaSettings?->totp_confirmed_at,
        ]);
    }

    #[Endpoint(
        title: 'Initialize MFA setup',
        description: 'Start the MFA setup process by generating a TOTP secret and QR code'
    )]
    #[Authenticated]
    #[ScribeResponse([
        'message' => 'MFA setup initiated',
        'secret' => 'JBSWY3DPEHPK3PXP',
        'qr_code_url' => 'otpauth://totp/Example:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Example',
    ], 201)]
    #[ScribeResponse(['error' => 'MFA is already enabled', 'code' => 'MFA_ALREADY_ENABLED'], 400)]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            return response()->json([
                'error' => 'MFA is already enabled',
                'code' => 'MFA_ALREADY_ENABLED',
            ], 400);
        }

        $secret = $user->generateTotpSecret();
        $qrCodeUrl = $user->getTotpQrCodeUrl($secret);

        $mfaSettings = $user->mfaSettings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $mfaSettings->update([
            'totp_secret' => $secret,
            'totp_enabled' => false,
        ]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $secret,
            'issuer' => config('app.name'),
            'account' => $user->email,
        ], 201);
    }

    /**
     * Confirm and fully enable MFA
     */
    public function update(MfaSetupRequest $request): JsonResponse
    {

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->mfaSettings || ! $user->mfaSettings->totp_secret) {
            return response()->json([
                'error' => 'No MFA setup found',
                'code' => 'NO_MFA_SETUP',
            ], 400);
        }

        if (! $user->verifyTotpCode($request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        $backupCodes = $this->generateBackupCodes();

        $user->mfaSettings->update([
            'totp_enabled' => true,
            'totp_confirmed_at' => now(),
            'backup_codes' => $backupCodes,
            'backup_codes_used' => 0,
        ]);

        return response()->json([
            'message' => 'MFA successfully enabled',
            'backup_codes' => $backupCodes,
            'mfa_enabled' => true,
        ]);
    }

    /**
     * Disable MFA for the authenticated user
     */
    public function destroy(MfaDisableRequest $request): JsonResponse
    {

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->hasMfaEnabled()) {
            return response()->json([
                'error' => 'MFA is not enabled',
                'code' => 'MFA_NOT_ENABLED',
            ], 400);
        }

        $user->mfaSettings->update([
            'totp_enabled' => false,
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'backup_codes' => null,
            'backup_codes_used' => 0,
        ]);

        return response()->json([
            'message' => 'MFA successfully disabled',
            'mfa_enabled' => false,
        ]);
    }

    /**
     * Verify MFA code
     */
    public function verify(MfaVerifyRequest $request): JsonResponse
    {

        $user = $request->user();

        if (! $user->hasMfaEnabled()) {
            return response()->json([
                'error' => 'MFA is not enabled',
                'code' => 'MFA_NOT_ENABLED',
            ], 400);
        }

        $code = $request->code;
        $verified = false;
        $type = null;

        // Check TOTP code (6 digits)
        if (strlen($code) === 6 && $user->verifyTotpCode($code)) {
            $verified = true;
            $type = 'totp';
        }
        // Check backup code (8 digits)
        elseif (strlen($code) === 8 && $this->verifyBackupCode($user, $code)) {
            $verified = true;
            $type = 'backup_code';
        }

        if ($verified) {
            $request->session()->put('mfa_verified', true);

            return response()->json([
                'verified' => true,
                'type' => $type,
                'message' => 'MFA verification successful',
            ]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid.'],
        ]);
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(MfaDisableRequest $request): JsonResponse
    {

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->hasMfaEnabled()) {
            return response()->json([
                'error' => 'MFA is not enabled',
                'code' => 'MFA_NOT_ENABLED',
            ], 400);
        }

        $backupCodes = $this->generateBackupCodes();

        $user->mfaSettings->update([
            'backup_codes' => $backupCodes,
            'backup_codes_used' => 0,
        ]);

        return response()->json([
            'backup_codes' => $backupCodes,
            'message' => 'Backup codes regenerated successfully',
        ]);
    }

    /**
     * Get remaining backup codes count
     */
    public function backupCodesStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $mfaSettings = $user->mfaSettings;

        if (! $mfaSettings || ! $user->hasMfaEnabled()) {
            return response()->json([
                'error' => 'MFA is not enabled',
                'code' => 'MFA_NOT_ENABLED',
            ], 400);
        }

        $totalCodes = count($mfaSettings->backup_codes ?? []);
        $usedCodes = $mfaSettings->backup_codes_used ?? 0;
        $remainingCodes = $totalCodes - $usedCodes;

        return response()->json([
            'total_codes' => $totalCodes,
            'used_codes' => $usedCodes,
            'remaining_codes' => $remainingCodes,
            'backup_codes_available' => $remainingCodes > 0,
        ]);
    }

    /**
     * Generate backup codes
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    /**
     * Verify backup code
     */
    private function verifyBackupCode($user, string $code): bool
    {
        $mfaSettings = $user->mfaSettings;

        if (! $mfaSettings || ! $mfaSettings->backup_codes) {
            return false;
        }

        $backupCodes = $mfaSettings->backup_codes;

        if (! is_array($backupCodes)) {
            return false;
        }

        $usedCodes = $mfaSettings->backup_codes_used;

        if ($usedCodes >= count($backupCodes)) {
            return false;
        }

        // Check if the code is in the unused portion of backup codes
        $unusedCodes = array_slice($backupCodes, $usedCodes);
        $codeIndex = array_search($code, $unusedCodes);

        if ($codeIndex !== false) {
            $mfaSettings->increment('backup_codes_used');

            return true;
        }

        return false;
    }
}
