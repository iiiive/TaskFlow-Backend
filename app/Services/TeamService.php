<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Mail\TeamMemberAddedMail;
use Illuminate\Support\Facades\Mail;

class TeamService
{
    public function createTeam(array $data, int $userId): Team
    {
        $team = Team::create([
            'organization_id' => $data['organization_id'] ?? null,
            'project_id'      => $data['project_id'] ?? null,
            'created_by'      => $userId,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'color'           => $data['color'] ?? '#547A95',
        ]);

        TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $userId,
            'role'      => 'team_lead',
            'joined_at' => now(),
        ]);

        return $team->load(['teamMembers.user', 'creator']);
    }

    public function addMember(Team $team, int $userId, string $role = 'member'): TeamMember
    {
        $member = TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $userId,
            'role'      => $role,
            'joined_at' => now(),
        ]);

        $user = User::find($userId);

        if ($user) {
            Mail::to($user->email)->queue(new TeamMemberAddedMail($team, $user, $role));
        }

        return $member->load('user');
    }

    public function updateMemberRole(TeamMember $member, string $role): TeamMember
    {
        $member->update(['role' => $role]);
        return $member->load('user');
    }

    public function removeMember(TeamMember $member): void
    {
        $member->delete();
    }

    public function updateTeam(Team $team, array $data): Team
    {
        $team->update(array_filter([
            'name'        => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? null,
            'project_id'  => $data['project_id'] ?? null,
        ], fn($v) => $v !== null));

        return $team->load(['teamMembers.user', 'creator']);
    }
}
