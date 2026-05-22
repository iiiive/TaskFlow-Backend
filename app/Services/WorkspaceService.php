<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceService
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getUserWorkspaces(User $user): Collection
    {
        return Workspace::whereHas('workspaceMembers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'owner:id,name,email',
                'workspaceMembers.user:id,name,email',
                'kanbanColumns',
            ])
            ->latest()
            ->get();
    }

    public function createWorkspace(User $user, array $data): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create default Kanban workflow
        |--------------------------------------------------------------------------
        | New workspaces now automatically get:
        | Backlog → Ready for Development → Dev In Progress → Ready for Testing
        | → Ready for UAT → Done
        */
        $workspace->createDefaultKanbanColumns();

        $this->activityLogService->create(
            $workspace->id,
            null,
            $user->id,
            'workspace_created',
            'Workspace was created.'
        );

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);
    }

    public function updateWorkspace(Workspace $workspace, array $data, int $userId): Workspace
    {
        $workspace->update([
            'name' => $data['name'] ?? $workspace->name,
            'description' => array_key_exists('description', $data)
                ? $data['description']
                : $workspace->description,
        ]);

        $this->activityLogService->create(
            $workspace->id,
            null,
            $userId,
            'workspace_updated',
            'Workspace details were updated.'
        );

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);
    }

    public function deleteWorkspace(Workspace $workspace, int $userId): void
    {
        $this->activityLogService->create(
            $workspace->id,
            null,
            $userId,
            'workspace_deleted',
            'Workspace was deleted.'
        );

        $workspace->delete();
    }
}