<?php

namespace App\Policies;

use App\Models\KanbanColumn;
use App\Models\User;
use App\Models\WorkspaceMember;

class KanbanColumnPolicy
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

    public function update(User $user, KanbanColumn $column): bool
    {
        return $this->manage($user, $column->project_id);
    }

    public function delete(User $user, KanbanColumn $column): bool
    {
        return $this->manage($user, $column->project_id);
    }
}
