<?php

namespace App\Policies;

use App\Models\TicketTimeLog;
use App\Models\User;
use App\Models\WorkspaceMember;

class TimeLogPolicy
{
    public function create(User $user, int $projectId): bool
    {
        $role = WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, WorkspaceMember::ROLES_CAN_EDIT, true);
    }

    public function delete(User $user, TicketTimeLog $timeLog): bool
    {
        if ($user->id === $timeLog->user_id) {
            return true;
        }

        $role = WorkspaceMember::where('project_id', $timeLog->project_id)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, WorkspaceMember::ROLES_CAN_DELETE, true);
    }
}
