<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Ticket;
use App\Models\TicketTimeLog;
use App\Models\User;
use App\Mail\TeamMemberAddedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TeamService
{
    public function createTeam(array $data, int $userId, bool $addCreator = true): Team
    {
        $team = Team::create([
            'organization_id' => $data['organization_id'] ?? null,
            'project_id'      => $data['project_id'] ?? null,
            'created_by'      => $userId,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'color'           => $data['color'] ?? '#547A95',
            'capacity_hours'  => $data['capacity_hours'] ?? null,
        ]);

        if ($addCreator) {
            TeamMember::create([
                'team_id'   => $team->id,
                'user_id'   => $userId,
                'role'      => 'project_manager',
                'joined_at' => now(),
            ]);
        }

        return $team->load(['teamMembers.user', 'creator']);
    }

    public function addMember(Team $team, int $userId, string $role = 'developer', ?int $weeklyCapacityHours = null): TeamMember
    {
        $member = TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $userId,
            'role'      => $role,
            'weekly_capacity_hours' => $weeklyCapacityHours,
            'joined_at' => now(),
        ]);

        $user = User::find($userId);

        if ($user) {
            Mail::to($user->email)->queue(new TeamMemberAddedMail($team, $user, $role));
        }

        return $member->load('user');
    }

    public function updateMemberRole(TeamMember $member, string $role, ?int $weeklyCapacityHours = null): TeamMember
    {
        $member->update(array_merge(
            ['role' => $role],
            $weeklyCapacityHours !== null ? ['weekly_capacity_hours' => $weeklyCapacityHours] : []
        ));
        return $member->load('user');
    }

    public function removeMember(TeamMember $member): void
    {
        $member->delete();
    }

    public function updateTeam(Team $team, array $data): Team
    {
        $team->update([
            'name'           => $data['name'] ?? $team->name,
            'description'    => array_key_exists('description', $data) ? $data['description'] : $team->description,
            'color'          => $data['color'] ?? $team->color,
            'project_id'     => array_key_exists('project_id', $data) ? $data['project_id'] : $team->project_id,
            'capacity_hours' => array_key_exists('capacity_hours', $data) ? $data['capacity_hours'] : $team->capacity_hours,
        ]);

        return $team->load(['teamMembers.user', 'creator']);
    }

    /**
     * Compute per-member workload for a team: hours logged in the window and
     * the count of open (not done) tickets currently assigned, against each
     * member's weekly capacity. Scoped to the team's project when one is set.
     *
     * @return array{members: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function workload(Team $team, ?string $from = null, ?string $to = null): array
    {
        $team->loadMissing('teamMembers.user');

        $from = $from ?: now()->startOfWeek()->toDateString();
        $to   = $to ?: now()->endOfWeek()->toDateString();
        $projectId = $team->project_id;

        $userIds = $team->teamMembers->pluck('user_id')->all();

        // Logged hours per user within the window.
        $hoursByUser = TicketTimeLog::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$from, $to])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(hours) as total_hours'))
            ->pluck('total_hours', 'user_id');

        // Open ticket count per user (status not done/completed).
        $openByUser = Ticket::query()
            ->whereIn('assigned_to', $userIds)
            ->whereNotIn('status', ['done', 'completed'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->groupBy('assigned_to')
            ->select('assigned_to', DB::raw('COUNT(*) as open_count'))
            ->pluck('open_count', 'assigned_to');

        $members = [];
        $totalLogged = 0.0;
        $totalCapacity = 0;

        foreach ($team->teamMembers as $member) {
            $logged = (float) ($hoursByUser[$member->user_id] ?? 0);
            $capacity = (int) ($member->weekly_capacity_hours ?? 0);
            $totalLogged += $logged;
            $totalCapacity += $capacity;

            $members[] = [
                'user_id'        => $member->user_id,
                'name'           => $member->user?->name,
                'email'          => $member->user?->email,
                'role'           => $member->role,
                'capacity_hours' => $capacity,
                'logged_hours'   => round($logged, 2),
                'open_tickets'   => (int) ($openByUser[$member->user_id] ?? 0),
                'utilization'    => $capacity > 0 ? (int) round(($logged / $capacity) * 100) : null,
            ];
        }

        return [
            'members' => $members,
            'totals'  => [
                'from'           => $from,
                'to'             => $to,
                'logged_hours'   => round($totalLogged, 2),
                'capacity_hours' => $team->capacity_hours ?? $totalCapacity,
                'open_tickets'   => array_sum(array_map(fn ($m) => $m['open_tickets'], $members)),
            ],
        ];
    }
}
