<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\FilePermission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    /**
     * Determine whether the user can view any folders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('files:read');
    }

    /**
     * Determine whether the user can view the folder.
     */
    public function view(User $user, Folder $folder): bool
    {
        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return true;
        }

        // Check if folder is public
        if ($folder->isPublic()) {
            return $user->can('files:read');
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'read')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'read')) {
            return true;
        }

        // Check if user has global view permission and folder is internal
        if ($folder->visibility === 'internal' && $user->can('files:read')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create folders.
     */
    public function create(User $user): bool
    {
        return $user->can('folders.create');
    }

    /**
     * Determine whether the user can update the folder.
     */
    public function update(User $user, Folder $folder): bool
    {
        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return $user->can('folders.manage');
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'write')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'write')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, Folder $folder): bool
    {
        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return $user->can('files:delete');
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'delete')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'delete')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the folder.
     */
    public function restore(User $user, Folder $folder): bool
    {
        return $user->can('files.restore') && 
               ($folder->isOwnedBy($user) || $this->hasFolderPermission($user, $folder, 'write'));
    }

    /**
     * Determine whether the user can permanently delete the folder.
     */
    public function forceDelete(User $user, Folder $folder): bool
    {
        return $user->can('files.admin') || 
               ($folder->isOwnedBy($user) && $user->can('files:delete'));
    }

    /**
     * Determine whether the user can share the folder.
     */
    public function share(User $user, Folder $folder): bool
    {
        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return $user->can('files.share');
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'share')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'share')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can comment on the folder.
     */
    public function comment(User $user, Folder $folder): bool
    {
        // Must be able to view the folder to comment
        if (!$this->view($user, $folder)) {
            return false;
        }

        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return $user->can('files.comment');
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'comment')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'comment')) {
            return true;
        }

        return $user->can('files.comment');
    }

    /**
     * Determine whether the user can copy the folder.
     */
    public function copy(User $user, Folder $folder): bool
    {
        return $this->view($user, $folder) && $user->can('files.copy');
    }

    /**
     * Determine whether the user can move the folder.
     */
    public function move(User $user, Folder $folder): bool
    {
        // Must have write permission to move
        return $this->update($user, $folder) && $user->can('files.move');
    }

    /**
     * Determine whether the user can upload files to the folder.
     */
    public function uploadFiles(User $user, Folder $folder): bool
    {
        // Must be able to view folder and create files
        if (!$this->view($user, $folder) || !$user->can('files:write')) {
            return false;
        }

        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return true;
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'write')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'write')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create subfolders.
     */
    public function createSubfolders(User $user, Folder $folder): bool
    {
        // Must be able to view folder and create folders
        if (!$this->view($user, $folder) || !$user->can('folders.create')) {
            return false;
        }

        // Check if user is the owner
        if ($folder->isOwnedBy($user)) {
            return true;
        }

        // Check specific folder permissions
        if ($this->hasFolderPermission($user, $folder, 'write')) {
            return true;
        }

        // Check parent folder permissions (inherited)
        if ($folder->parent && $this->hasFolderPermission($user, $folder->parent, 'write')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage folder permissions.
     */
    public function managePermissions(User $user, Folder $folder): bool
    {
        // Only owner or users with permission management rights
        return $folder->isOwnedBy($user) && $user->can('files.permissions.manage');
    }

    /**
     * Determine whether the user can manage the folder structure.
     */
    public function manage(User $user, Folder $folder): bool
    {
        return $folder->isOwnedBy($user) && $user->can('folders.manage');
    }

    /**
     * Check if user has specific permission for a folder.
     */
    protected function hasFolderPermission(User $user, Folder $folder, string $permission): bool
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

        // Check inherited permissions from parent folders
        if ($folder->parent) {
            $inheritedPermission = FilePermission::forFolder($folder->parent->id)
                ->where('inherited', true)
                ->where('permissions', 'like', '%"' . $permission . '"%')
                ->where(function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->where('subject_type', User::class)
                          ->where('subject_id', $user->id);
                    });

                    foreach ($user->roles as $role) {
                        $query->orWhere(function ($q) use ($role) {
                            $q->where('subject_type', get_class($role))
                              ->where('subject_id', $role->id);
                        });
                    }
                })
                ->first();

            if ($inheritedPermission) {
                return true;
            }

            // Recursively check parent folders
            return $this->hasFolderPermission($user, $folder->parent, $permission);
        }

        return false;
    }
}