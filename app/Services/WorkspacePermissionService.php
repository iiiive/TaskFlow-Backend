<?php

namespace App\Services;

use App\Models\WorkspaceMember;

class WorkspacePermissionService
{
    public function getUserRole(int $workspaceId, int $userId): ?string
    {
        $member = WorkspaceMember::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->first();

        return $member?->role;
    }

    public function isMember(int $workspaceId, int $userId): bool
    {
        return WorkspaceMember::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function canView(int $workspaceId, int $userId): bool
    {
        return $this->isMember($workspaceId, $userId);
    }

    public function canCreateOrUpdateTicket(int $workspaceId, int $userId): bool
    {
        $role = $this->getUserRole($workspaceId, $userId);

        return in_array($role, ['owner', 'editor']);
    }

    public function canManageWorkspace(int $workspaceOwnerId, int $userId): bool
    {
        return $workspaceOwnerId === $userId;
    }

    public function canDeleteTicket(int $workspaceId, int $userId): bool
    {
        $role = $this->getUserRole($workspaceId, $userId);

        return $role === 'owner';
    }
}