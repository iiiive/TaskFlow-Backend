<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceMember;

class AssignmentEngineService
{
    /**
     * Returns a user_id to auto-assign to, or null if no eligible member found.
     */
    public function resolveAssignee(Workspace $project): ?int
    {
        if (!$project->auto_assign_enabled) {
            return null;
        }

        $eligibleRoles = WorkspaceMember::ROLES_CAN_EDIT;

        $members = $project->workspaceMembers()
            ->whereIn('role', $eligibleRoles)
            ->where('role', '!=', 'owner')
            ->with('user')
            ->get();

        if ($members->isEmpty()) {
            return null;
        }

        return match ($project->auto_assign_strategy) {
            'least_loaded' => $this->leastLoaded($project->id, $members),
            default        => $this->roundRobin($project->id, $members),
        };
    }

    private function roundRobin(int $projectId, $members): int
    {
        // Find the member whose user_id appears least recently as assigned_to
        $userIds = $members->pluck('user_id')->toArray();

        $lastAssigned = \App\Models\Ticket::where('project_id', $projectId)
            ->whereIn('assigned_to', $userIds)
            ->whereNotNull('assigned_to')
            ->orderByDesc('created_at')
            ->value('assigned_to');

        if (!$lastAssigned) {
            return $userIds[0];
        }

        $lastIndex = array_search($lastAssigned, $userIds);
        $nextIndex = ($lastIndex + 1) % count($userIds);

        return $userIds[$nextIndex];
    }

    private function leastLoaded(int $projectId, $members): int
    {
        $userIds = $members->pluck('user_id')->toArray();

        $counts = \App\Models\Ticket::where('project_id', $projectId)
            ->whereIn('assigned_to', $userIds)
            ->whereNotIn('status', ['done', 'completed'])
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, count(*) as ticket_count')
            ->pluck('ticket_count', 'assigned_to')
            ->toArray();

        $minCount = PHP_INT_MAX;
        $pick = $userIds[0];

        foreach ($userIds as $userId) {
            $count = $counts[$userId] ?? 0;

            if ($count < $minCount) {
                $minCount = $count;
                $pick = $userId;
            }
        }

        return $pick;
    }
}
