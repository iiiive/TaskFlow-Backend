<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceMember;

class WorkspaceMemberPolicy
{
    public function viewAny(User $user, int $projectId): bool
    {
        return WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function manage(User $user, int $projectId): bool
    {
        $role = WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, WorkspaceMember::ROLES_CAN_MANAGE_MEMBERS, true);
    }

    public function update(User $user, WorkspaceMember $member): bool
    {
        return $this->manage($user, $member->project_id);
    }

    public function delete(User $user, WorkspaceMember $member): bool
    {
        if ($user->id === $member->user_id) {
            return true;
        }

        return $this->manage($user, $member->project_id);
    }
}
