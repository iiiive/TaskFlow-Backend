<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberService
{
    public function getMembers(Workspace $workspace): Collection
    {
        return WorkspaceMember::where('workspace_id', $workspace->id)
            ->with('user:id,name,email')
            ->get();
    }

    public function addMember(Workspace $workspace, User $authUser, array $data): WorkspaceMember
    {
        $userToAdd = User::where('email', $data['email'])->first();

        if ($userToAdd->id === $authUser->id) {
            throw ValidationException::withMessages([
                'email' => ['You are already the owner of this workspace.']
            ]);
        }

        $existingMember = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $userToAdd->id)
            ->first();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => ['User is already a member of this workspace.']
            ]);
        }

        $member = WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $userToAdd->id,
            'role' => $data['role'],
        ]);

        return $member->load('user:id,name,email');
    }

    public function updateRole(WorkspaceMember $member, string $role): WorkspaceMember
    {
        if ($member->role === 'owner') {
            throw ValidationException::withMessages([
                'role' => ['Owner role cannot be changed here.']
            ]);
        }

        $member->update([
            'role' => $role,
        ]);

        return $member->load('user:id,name,email');
    }

    public function removeMember(WorkspaceMember $member): void
    {
        if ($member->role === 'owner') {
            throw ValidationException::withMessages([
                'member' => ['Workspace owner cannot be removed.']
            ]);
        }

        $member->delete();
    }
}