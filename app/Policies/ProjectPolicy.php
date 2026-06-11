<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $project): bool
    {
        return WorkspaceMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $project): bool
    {
        $role = $this->getRole($user, $project);

        return in_array($role, WorkspaceMember::ROLES_CAN_MANAGE_MEMBERS, true);
    }

    public function delete(User $user, Workspace $project): bool
    {
        $role = $this->getRole($user, $project);

        return in_array($role, WorkspaceMember::ROLES_CAN_MANAGE_PROJECT, true);
    }

    public function archive(User $user, Workspace $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageMembers(User $user, Workspace $project): bool
    {
        $role = $this->getRole($user, $project);

        return in_array($role, WorkspaceMember::ROLES_CAN_MANAGE_MEMBERS, true);
    }

    private function getRole(User $user, Workspace $project): ?string
    {
        return WorkspaceMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->value('role');
    }
}
