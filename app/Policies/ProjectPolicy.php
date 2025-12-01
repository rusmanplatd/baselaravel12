<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.view') && $project->isVisible($user)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.edit') && $project->canEdit($user)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.delete') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->can('projects.admin');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->can('projects.admin');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.members.manage') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }

    public function addMembers(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.members.add') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }

    public function removeMembers(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.members.remove') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }

    public function manageItems(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.items.create') && $project->canEdit($user)) {
            return true;
        }

        return false;
    }

    public function manageViews(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.views.create') && $project->canEdit($user)) {
            return true;
        }

        return false;
    }

    public function manageFields(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.fields.create') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }

    public function manageWorkflows(User $user, Project $project): bool
    {
        if ($user->can('projects.admin')) {
            return true;
        }

        if ($user->can('projects.workflows.create') && $project->canAdmin($user)) {
            return true;
        }

        return false;
    }
}