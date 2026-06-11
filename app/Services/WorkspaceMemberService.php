<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected ProjectTeamSyncService $teamSync
    ) {}

    public function getMembers(Workspace $workspace): Collection
    {
        return WorkspaceMember::where('project_id', $workspace->id)
            ->with('user:id,name,email')
            ->get();
    }

    public function addMember(Workspace $workspace, User $authUser, array $data): WorkspaceMember
    {
        $userToAdd = User::where('email', $data['email'])->first();

        if ($userToAdd->id === $authUser->id) {
            throw ValidationException::withMessages([
                'email' => ['You are already a member of this project.']
            ]);
        }

        $existingMember = WorkspaceMember::where('project_id', $workspace->id)
            ->where('user_id', $userToAdd->id)
            ->first();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => ['User is already a member of this project.']
            ]);
        }

        $member = WorkspaceMember::create([
            'project_id' => $workspace->id,
            'user_id' => $userToAdd->id,
            'role' => $data['role'],
        ]);

        // One-team-per-project: a new project member auto-joins the project's team.
        $this->teamSync->syncUser($workspace, $userToAdd->id);

        $this->activityLogService->create(
            $workspace->id,
            null,
            $authUser->id,
            'member_added',
            $userToAdd->name . ' was added as ' . $data['role'] . '.'
        );

        return $member->load('user:id,name,email');
    }

    public function updateRole(WorkspaceMember $member, string $role, int $authUserId): WorkspaceMember
    {
        if ($member->role === 'owner') {
            throw ValidationException::withMessages([
                'role' => ['Owner role cannot be changed.']
            ]);
        }

        $oldRole = $member->role;

        $member->update(['role' => $role]);
        $member->load('user:id,name,email');

        $this->activityLogService->create(
            $member->project_id,
            null,
            $authUserId,
            'member_role_updated',
            $member->user->name . ' role was changed from ' . $oldRole . ' to ' . $role . '.'
        );

        return $member;
    }

    public function removeMember(WorkspaceMember $member, int $authUserId): void
    {
        if ($member->role === 'owner') {
            throw ValidationException::withMessages([
                'member' => ['Project owner cannot be removed.']
            ]);
        }

        $member->load('user:id,name,email');
        $removedUserName = $member->user?->name ?? 'A member';
        $projectId = $member->project_id;

        $this->activityLogService->create(
            $projectId,
            null,
            $authUserId,
            'member_removed',
            $removedUserName . ' was removed from the project.'
        );

        $member->delete();
    }
}
