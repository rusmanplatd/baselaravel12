<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own documents and shared documents
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // Owner can always view
        if ($document->isOwnedBy($user)) {
            return true;
        }

        // Public documents can be viewed by anyone
        if ($document->isPublic()) {
            return true;
        }

        // Check if user is a collaborator
        if ($document->collaborators()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check explicit file permissions
        $permission = $document->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canRead()) {
            return true;
        }

        // Check if user has permission through their role in the organization
        if ($document->owner_type === 'App\\Models\\Organization') {
            $organization = $document->owner;
            if ($organization && $user->hasRole(['admin', 'manager'], $organization)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create documents
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        // Owner can always update
        if ($document->isOwnedBy($user)) {
            return true;
        }

        // Check if user is an editor collaborator
        $collaborator = $document->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['editor', 'owner'])
            ->first();

        if ($collaborator) {
            return true;
        }

        // Check explicit file permissions
        $permission = $document->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canWrite()) {
            return true;
        }

        // Check organization permissions
        if ($document->owner_type === 'App\\Models\\Organization') {
            $organization = $document->owner;
            if ($organization && $user->hasRole(['admin', 'manager'], $organization)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        // Only owner can delete
        if ($document->isOwnedBy($user)) {
            return true;
        }

        // Check if user is an owner collaborator
        $collaborator = $document->collaborators()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();

        if ($collaborator) {
            return true;
        }

        // Check organization admin permissions
        if ($document->owner_type === 'App\\Models\\Organization') {
            $organization = $document->owner;
            if ($organization && $user->hasRole('admin', $organization)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Document $document): bool
    {
        return $this->delete($user, $document);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return $this->delete($user, $document);
    }

    /**
     * Determine whether the user can manage collaborators.
     */
    public function manageCollaborators(User $user, Document $document): bool
    {
        // Owner can always manage collaborators
        if ($document->isOwnedBy($user)) {
            return true;
        }

        // Check if user is an owner collaborator
        $collaborator = $document->collaborators()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();

        return $collaborator !== null;
    }

    /**
     * Determine whether the user can comment on the document.
     */
    public function comment(User $user, Document $document): bool
    {
        // If user can update, they can comment
        if ($this->update($user, $document)) {
            return true;
        }

        // Check if user is a commenter collaborator
        $collaborator = $document->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['commenter', 'editor', 'owner'])
            ->first();

        if ($collaborator) {
            return true;
        }

        // Check explicit comment permissions
        $permission = $document->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canComment()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can share the document.
     */
    public function share(User $user, Document $document): bool
    {
        // Owner can always share
        if ($document->isOwnedBy($user)) {
            return true;
        }

        // Check if user is an owner collaborator
        $collaborator = $document->collaborators()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();

        if ($collaborator) {
            return true;
        }

        // Check explicit share permissions
        $permission = $document->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canShare()) {
            return true;
        }

        return false;
    }
}