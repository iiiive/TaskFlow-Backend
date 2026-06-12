<?php

namespace App\Services;

use App\Models\TeamMember;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

/**
 * Keeps a project's single assigned team in sync with its project members:
 * one-team-per-project, where every project member is also a team member.
 */
class ProjectTeamSyncService
{
    /**
     * Add one user to the project's team (if the project has one). Idempotent.
     */
    public function syncUser(Workspace $workspace, int $userId): void
    {
        $team = $workspace->team()->first();

        if (!$team) {
            return;
        }

        TeamMember::firstOrCreate(
            ['team_id' => $team->id, 'user_id' => $userId],
            ['role' => 'developer', 'joined_at' => now()]
        );
    }

    /**
     * Pull every current project member into the project's team. Used right after
     * a team is (re)assigned to a project. Idempotent.
     */
    public function backfill(Workspace $workspace): void
    {
        $team = $workspace->team()->first();

        if (!$team) {
            return;
        }

        $memberIds = WorkspaceMember::where('project_id', $workspace->id)->pluck('user_id');

        foreach ($memberIds as $userId) {
            TeamMember::firstOrCreate(
                ['team_id' => $team->id, 'user_id' => $userId],
                ['role' => 'developer', 'joined_at' => now()]
            );
        }
    }

    /**
     * Push every team member into the project as a project member. Used when a
     * team is assigned to a project so existing team members gain project access.
     * Idempotent — won't overwrite a role that was already set.
     */
    public function backfillTeamToProject(\App\Models\Team $team, Workspace $workspace): void
    {
        $userIds = TeamMember::where('team_id', $team->id)->pluck('user_id');

        foreach ($userIds as $userId) {
            WorkspaceMember::firstOrCreate(
                ['project_id' => $workspace->id, 'user_id' => $userId],
                ['role' => 'developer']
            );
        }
    }
}
