<?php

namespace App\Policies;

use App\Models\File;
use App\Models\FilePermission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy
{
    /**
     * Determine whether the user can view any files.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('files:read');
    }

    /**
     * Determine whether the user can view the file.
     */
    public function view(User $user, File $file): bool
    {
        // Check if user is the owner
        if ($file->isOwnedBy($user)) {
            return true;
        }

        // Check if file is public
        if ($file->isPublic()) {
            return $user->can('files:read');
        }

        // Check specific file permissions
        if ($this->hasFilePermission($user, $file, 'read')) {
            return true;
        }

        // Check folder permissions (inherited)
        if ($file->folder && $this->hasFolderPermission($user, $file->folder, 'read')) {
            return true;
        }

        // Check if user has global view permission and file is internal
        if ($file->visibility === 'internal' && $user->can('files:read')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create files.
     */
    public function create(User $user): bool
    {
        return $user->can('files:write');
    }

    /**
     * Determine whether the user can update the file.
     */
    public function update(User $user, File $file): bool
    {
        // Check if user is the owner
        if ($file->isOwnedBy($user)) {
            return $user->can('files:write');
        }

        // Check specific file permissions
        if ($this->hasFilePermission($user, $file, 'write')) {
            return true;
        }

        // Check folder permissions (inherited)
        if ($file->folder && $this->hasFolderPermission($user, $file->folder, 'write')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, File $file): bool
    {
        // Check if user is the owner
        if ($file->isOwnedBy($user)) {
            return $user->can('files:delete');
        }

        // Check specific file permissions
        if ($this->hasFilePermission($user, $file, 'delete')) {
            return true;
        }

        // Check folder permissions (inherited)
        if ($file->folder && $this->hasFolderPermission($user, $file->folder, 'delete')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the file.
     */
    public function restore(User $user, File $file): bool
    {
        return $user->can('files.restore') && 
               ($file->isOwnedBy($user) || $this->hasFilePermission($user, $file, 'write'));
    }

    /**
     * Determine whether the user can permanently delete the file.
     */
    public function forceDelete(User $user, File $file): bool
    {
        return $user->can('files.admin') || 
               ($file->isOwnedBy($user) && $user->can('files:delete'));
    }

    /**
     * Determine whether the user can download the file.
     */
    public function download(User $user, File $file): bool
    {
        // Check if user can view the file and has download permission
        return $this->view($user, $file) && $user->can('files.download');
    }

    /**
     * Determine whether the user can share the file.
     */
    public function share(User $user, File $file): bool
    {
        // Check if user is the owner
        if ($file->isOwnedBy($user)) {
            return $user->can('files.share');
        }

        // Check specific file permissions
        if ($this->hasFilePermission($user, $file, 'share')) {
            return true;
        }

        // Check folder permissions (inherited)
        if ($file->folder && $this->hasFolderPermission($user, $file->folder, 'share')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can comment on the file.
     */
    public function comment(User $user, File $file): bool
    {
        // Must be able to view the file to comment
        if (!$this->view($user, $file)) {
            return false;
        }

        // Check if user is the owner
        if ($file->isOwnedBy($user)) {
            return $user->can('files.comment');
        }

        // Check specific file permissions
        if ($this->hasFilePermission($user, $file, 'comment')) {
            return true;
        }

        // Check folder permissions (inherited)
        if ($file->folder && $this->hasFolderPermission($user, $file->folder, 'comment')) {
            return true;
        }

        return $user->can('files.comment');
    }

    /**
     * Determine whether the user can copy the file.
     */
    public function copy(User $user, File $file): bool
    {
        return $this->view($user, $file) && $user->can('files.copy');
    }

    /**
     * Determine whether the user can move the file.
     */
    public function move(User $user, File $file): bool
    {
        // Must have write permission to move
        return $this->update($user, $file) && $user->can('files.move');
    }

    /**
     * Determine whether the user can manage file permissions.
     */
    public function managePermissions(User $user, File $file): bool
    {
        // Only owner or users with permission management rights
        return $file->isOwnedBy($user) && $user->can('files.permissions.manage');
    }

    /**
     * Determine whether the user can view file versions.
     */
    public function viewVersions(User $user, File $file): bool
    {
        return $this->view($user, $file) && $user->can('files.versions.view');
    }

    /**
     * Determine whether the user can manage file versions.
     */
    public function manageVersions(User $user, File $file): bool
    {
        return $this->update($user, $file) && $user->can('files.versions.manage');
    }

    /**
     * Check if user has specific permission for a file.
     */
    protected function hasFilePermission(User $user, File $file, string $permission): bool
    {
        // Direct user permission
        $userPermission = FilePermission::forFile($file->id)
            ->forSubject(User::class, $user->id)
            ->where('permissions', 'like', '%"' . $permission . '"%')
            ->first();

        if ($userPermission) {
            return true;
        }

        // Role-based permissions
        foreach ($user->roles as $role) {
            $rolePermission = FilePermission::forFile($file->id)
                ->forSubject(get_class($role), $role->id)
                ->where('permissions', 'like', '%"' . $permission . '"%')
                ->first();

            if ($rolePermission) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has specific permission for a folder (for inheritance).
     */
    protected function hasFolderPermission(User $user, $folder, string $permission): bool
    {
        // Direct user permission
        $userPermission = FilePermission::forFolder($folder->id)
            ->forSubject(User::class, $user->id)
            ->where('permissions', 'like', '%"' . $permission . '"%')
            ->first();

        if ($userPermission) {
            return true;
        }

        // Role-based permissions
        foreach ($user->roles as $role) {
            $rolePermission = FilePermission::forFolder($folder->id)
                ->forSubject(get_class($role), $role->id)
                ->where('permissions', 'like', '%"' . $permission . '"%')
                ->first();

            if ($rolePermission) {
                return true;
            }
        }

        // Check parent folder permissions recursively
        if ($folder->parent) {
            return $this->hasFolderPermission($user, $folder->parent, $permission);
        }

        return false;
    }
}