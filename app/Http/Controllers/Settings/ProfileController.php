<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDestroyRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Log profile update activity
        ActivityLogService::logAuth('profile_updated', 'User updated profile', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'changed_fields' => array_keys($user->getChanges()),
        ], $user);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(ProfileDestroyRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Log account deletion before deleting
        ActivityLogService::logAuth('account_deleted', 'User deleted account', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user->id,
            'user_email' => $user->email,
        ], $user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Upload a new avatar for the user.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', File::image()->max(5 * 1024)], // 5MB max
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        // Update user avatar path
        $user->update(['avatar' => $path]);

        // Log avatar upload activity
        ActivityLogService::logAuth('avatar_uploaded', 'User uploaded avatar', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'file_path' => $path,
        ], $user);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => asset('storage/'.$path),
        ]);
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            // Delete file from storage
            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Clear avatar from database
            $user->update(['avatar' => null]);
            
            // Log avatar deletion activity
            ActivityLogService::logAuth('avatar_deleted', 'User deleted avatar', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $user);
        }

        return response()->json([
            'message' => 'Avatar deleted successfully',
        ]);
    }
}
