<?php

namespace App\Services;

use App\Models\WorkspaceMember;

class WorkspacePermissionService
{
    public function getUserRole(int $projectId, int $userId): ?string
    {
        $member = WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        return $member?->role;
    }

    public function isMember(int $projectId, int $userId): bool
    {
        return WorkspaceMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function canView(int $projectId, int $userId): bool
    {
        return $this->isMember($projectId, $userId);
    }

    public function canCreateOrUpdateTicket(int $projectId, int $userId): bool
    {
        $role = $this->getUserRole($projectId, $userId);

        return in_array($role, WorkspaceMember::ROLES_CAN_EDIT, true);
    }

    public function canDeleteTicket(int $projectId, int $userId): bool
    {
        $role = $this->getUserRole($projectId, $userId);

        return in_array($role, WorkspaceMember::ROLES_CAN_DELETE, true);
    }

    public function canManageWorkspace(int $projectOwnerId, int $userId): bool
    {
        return $projectOwnerId === $userId;
    }

    public function canManageMembers(int $projectId, int $userId): bool
    {
        $role = $this->getUserRole($projectId, $userId);

        return in_array($role, WorkspaceMember::ROLES_CAN_MANAGE_MEMBERS, true);
    }
}
