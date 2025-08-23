<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaDisableRequest;
use App\Http\Requests\Auth\MfaSetupRequest;
use App\Http\Requests\Auth\MfaVerifyRequest;
use App\Http\Requests\Auth\RegenerateBackupCodesRequest;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaController extends Controller
{
    public function show(Request $request): Response
    {
        $user = Auth::user();
        $mfaSettings = $user->mfaSettings;

        return Inertia::render('Auth/MfaSetup', [
            'mfaEnabled' => $mfaSettings?->hasMfaEnabled() ?? false,
            'hasBackupCodes' => $mfaSettings?->hasBackupCodes() ?? false,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->hasMfaEnabled()) {
            return response()->json(['error' => 'MFA is already enabled'], 400);
        }

        $secret = $user->generateTotpSecret();
        $qrCodeUrl = $user->getTotpQrCodeUrl($secret);
        
        // Generate QR code as base64 data URL
        $qrCodeImage = $user->getTotpQrCodeImage($secret);

        $mfaSettings = $user->mfaSettings()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $mfaSettings->update([
            'totp_secret' => $secret,
            'totp_enabled' => false,
        ]);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_image' => $qrCodeImage,
        ]);
    }

    public function confirm(MfaSetupRequest $request): JsonResponse
    {

        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->mfaSettings || ! $user->mfaSettings->totp_secret) {
            return response()->json(['error' => 'No MFA setup found'], 400);
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

        // Log MFA enable
        ActivityLogService::logAuth('mfa_enabled', 'User enabled multi-factor authentication', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $user);

        return response()->json([
            'success' => true,
            'backup_codes' => $backupCodes,
        ]);
    }

    public function disable(MfaDisableRequest $request): JsonResponse
    {

        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->hasMfaEnabled()) {
            return response()->json(['error' => 'MFA is not enabled'], 400);
        }

        $user->mfaSettings->update([
            'totp_enabled' => false,
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'backup_codes' => null,
            'backup_codes_used' => 0,
        ]);

        // Log MFA disable
        ActivityLogService::logAuth('mfa_disabled', 'User disabled multi-factor authentication', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $user);

        return response()->json(['success' => true]);
    }

    public function verify(MfaVerifyRequest $request): JsonResponse
    {

        $user = Auth::user();

        if (! $user->hasMfaEnabled()) {
            return response()->json(['error' => 'MFA is not enabled'], 400);
        }

        $code = $request->code;

        if (strlen($code) === 6 && $user->verifyTotpCode($code)) {
            $request->session()->put('mfa_verified', true);

            // Log successful MFA verification
            ActivityLogService::logAuth('mfa_verified', 'User successfully verified MFA using TOTP', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => 'totp',
            ], $user);

            return response()->json(['success' => true]);
        }

        if (strlen($code) === 8 && $this->verifyBackupCode($user, $code)) {
            $request->session()->put('mfa_verified', true);

            // Log successful MFA verification with backup code
            ActivityLogService::logAuth('mfa_verified', 'User successfully verified MFA using backup code', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => 'backup_code',
            ], $user);

            return response()->json(['success' => true]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid.'],
        ]);
    }

    public function regenerateBackupCodes(RegenerateBackupCodesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->hasMfaEnabled()) {
            return response()->json(['error' => 'MFA is not enabled'], 400);
        }

        $backupCodes = $this->generateBackupCodes();

        $user->mfaSettings->update([
            'backup_codes' => $backupCodes,
            'backup_codes_used' => 0,
        ]);

        return response()->json([
            'success' => true,
            'backup_codes' => $backupCodes,
        ]);
    }

    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    private function verifyBackupCode($user, string $code): bool
    {
        $mfaSettings = $user->mfaSettings;

        if (! $mfaSettings || ! $mfaSettings->backup_codes) {
            return false;
        }

        // The backup codes are stored as a JSON string, not hashed for this implementation
        // In a production environment, you might want to hash these as well
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
