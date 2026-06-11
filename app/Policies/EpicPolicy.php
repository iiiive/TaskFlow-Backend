<?php

namespace App\Policies;

use App\Models\Epic;
use App\Models\User;
use App\Models\WorkspaceMember;

class EpicPolicy
{
    public function viewAny(User $user, int $projectId): bool
    {
        return WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function view(User $user, Epic $epic): bool
    {
        return WorkspaceMember::where('project_id', $epic->project_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user, int $projectId): bool
    {
        $role = $this->getRole($user, $projectId);

        return in_array($role, WorkspaceMember::ROLES_CAN_EDIT, true);
    }

    public function update(User $user, Epic $epic): bool
    {
        $role = $this->getRole($user, $epic->project_id);

        return in_array($role, WorkspaceMember::ROLES_CAN_EDIT, true);
    }

    public function delete(User $user, Epic $epic): bool
    {
        $role = $this->getRole($user, $epic->project_id);

        return in_array($role, WorkspaceMember::ROLES_CAN_DELETE, true);
    }

    private function getRole(User $user, int $projectId): ?string
    {
        return WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->value('role');
    }
}
