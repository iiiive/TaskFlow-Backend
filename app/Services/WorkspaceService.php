<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceService
{
    public function getUserWorkspaces(User $user): Collection
    {
        return Workspace::whereHas('workspaceMembers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'owner:id,name,email',
                'workspaceMembers.user:id,name,email',
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

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
        ]);
    }

    public function updateWorkspace(Workspace $workspace, array $data): Workspace
    {
        $workspace->update([
            'name' => $data['name'] ?? $workspace->name,
            'description' => array_key_exists('description', $data)
                ? $data['description']
                : $workspace->description,
        ]);

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
        ]);
    }

    public function deleteWorkspace(Workspace $workspace): void
    {
        $workspace->delete();
    }
}