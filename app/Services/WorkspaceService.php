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
            'organization_id' => $user->organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'project_key' => $data['project_key'] ?? null,
            'project_type' => $data['project_type'] ?? 'software',
            'project_mode' => $data['project_mode'] ?? 'kanban',
        ]);

        WorkspaceMember::create([
            'project_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $workspace->createDefaultKanbanColumns();

        $this->activityLogService->create(
            $workspace->id,
            null,
            $user->id,
            'project_created',
            'Project "' . $workspace->name . '" was created.'
        );

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);
    }

    public function updateWorkspace(Workspace $workspace, array $data, int $userId): Workspace
    {
        $workspace->update(array_filter([
            'name' => $data['name'] ?? $workspace->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $workspace->description,
            'project_key' => $data['project_key'] ?? $workspace->project_key,
            'project_type' => $data['project_type'] ?? $workspace->project_type,
            'project_mode' => $data['project_mode'] ?? $workspace->project_mode,
        ], fn ($v) => $v !== null));

        $this->activityLogService->create(
            $workspace->id,
            null,
            $userId,
            'project_updated',
            'Project details were updated.'
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
            'project_deleted',
            'Project "' . $workspace->name . '" was deleted.'
        );

        $workspace->delete();
    }
}
