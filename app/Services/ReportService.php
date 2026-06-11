<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketTimeLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getBurndown(int $projectId, int $sprintId): array
    {
        $sprint = Sprint::where('id', $sprintId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        $start = Carbon::parse($sprint->start_date);
        $end   = Carbon::parse($sprint->end_date ?? now());
        $today = now()->startOfDay();

        $days = [];
        $current = $start->copy();

        while ($current->lte($end) && $current->lte($today)) {
            $remaining = Ticket::where('sprint_id', $sprintId)
                ->where(function ($q) use ($current) {
                    $q->whereNull('updated_at')
                        ->orWhereDate('updated_at', '>', $current);
                })
                ->whereNotIn('status', ['done', 'completed'])
                ->sum('story_points');

            $days[] = [
                'date'            => $current->toDateString(),
                'remaining_points' => (int) $remaining,
            ];

            $current->addDay();
        }

        $totalPoints = Ticket::where('sprint_id', $sprintId)->sum('story_points');
        $daysTotal   = max(1, $start->diffInDays($end));

        $ideal = [];
        for ($i = 0; $i <= $daysTotal; $i++) {
            $ideal[] = [
                'date'   => $start->copy()->addDays($i)->toDateString(),
                'points' => round($totalPoints - ($totalPoints / $daysTotal) * $i, 1),
            ];
        }

        return [
            'sprint'       => $sprint->only(['id', 'name', 'start_date', 'end_date', 'status']),
            'total_points' => (int) $totalPoints,
            'actual'       => $days,
            'ideal'        => $ideal,
        ];
    }

    public function getVelocity(int $projectId): array
    {
        $sprints = Sprint::where('project_id', $projectId)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->take(10)
            ->get();

        $data = $sprints->map(function (Sprint $sprint) {
            $committed = Ticket::where('sprint_id', $sprint->id)->sum('story_points');
            $completed = Ticket::where('sprint_id', $sprint->id)
                ->whereIn('status', ['done', 'completed'])
                ->sum('story_points');

            return [
                'sprint_id'       => $sprint->id,
                'sprint_name'     => $sprint->name,
                'committed_points' => (int) $committed,
                'completed_points' => (int) $completed,
                'completion_rate'  => $committed > 0
                    ? round(($completed / $committed) * 100, 1)
                    : 0,
            ];
        });

        $avgVelocity = $data->avg('completed_points') ?? 0;

        return [
            'sprints'         => $data->values()->all(),
            'average_velocity' => round($avgVelocity, 1),
        ];
    }

    public function getSlaCompliance(int $projectId, ?string $from = null, ?string $to = null): array
    {
        $query = Ticket::where('project_id', $projectId)
            ->whereNotNull('due_date');

        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        $total    = (clone $query)->count();
        $resolved = (clone $query)->whereIn('status', ['done', 'completed'])->count();
        $onTime   = (clone $query)
            ->whereIn('status', ['done', 'completed'])
            ->whereColumn('updated_at', '<=', DB::raw('due_date'))
            ->count();

        return [
            'total_with_due_date' => $total,
            'resolved'            => $resolved,
            'resolved_on_time'    => $onTime,
            'compliance_rate'     => $resolved > 0
                ? round(($onTime / $resolved) * 100, 1)
                : null,
        ];
    }

    public function getAverageResolutionTime(int $projectId): array
    {
        $avg = Ticket::where('project_id', $projectId)
            ->whereIn('status', ['done', 'completed'])
            ->whereNotNull('created_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        return [
            'average_hours' => $avg ? round((float) $avg, 1) : null,
            'average_days'  => $avg ? round((float) $avg / 24, 1) : null,
        ];
    }

    public function getWorkload(int $projectId): array
    {
        $logs = TicketTimeLog::where('project_id', $projectId)
            ->with('user:id,name,email')
            ->select('user_id', DB::raw('SUM(hours) as total_hours'), DB::raw('COUNT(*) as log_count'))
            ->groupBy('user_id')
            ->orderByDesc('total_hours')
            ->get();

        $openByAssignee = Ticket::where('project_id', $projectId)
            ->whereNotIn('status', ['done', 'completed'])
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('COUNT(*) as open_count'))
            ->groupBy('assigned_to')
            ->pluck('open_count', 'assigned_to');

        return $logs->map(function ($log) use ($openByAssignee) {
            return [
                'user'         => $log->user ? $log->user->only(['id', 'name', 'email']) : null,
                'total_hours'  => (float) $log->total_hours,
                'log_count'    => (int) $log->log_count,
                'open_tickets' => (int) ($openByAssignee[$log->user_id] ?? 0),
            ];
        })->values()->all();
    }

    public function getIssueDistribution(int $projectId): array
    {
        $byType = Ticket::where('project_id', $projectId)
            ->select('issue_type', DB::raw('COUNT(*) as count'))
            ->groupBy('issue_type')
            ->pluck('count', 'issue_type');

        $byPriority = Ticket::where('project_id', $projectId)
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $byStatus = Ticket::where('project_id', $projectId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'by_type'     => $byType,
            'by_priority' => $byPriority,
            'by_status'   => $byStatus,
            'total'       => Ticket::where('project_id', $projectId)->count(),
        ];
    }

    public function getProjectProgress(int $projectId): array
    {
        $byStatus = Ticket::where('project_id', $projectId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $byStatus->sum();
        $done = (int) Ticket::where('project_id', $projectId)
            ->whereIn('status', ['done', 'completed'])
            ->count();

        return [
            'total'            => $total,
            'completed'        => $done,
            'in_progress'      => $total - $done,
            'percent_complete' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
            'by_status'        => $byStatus,
        ];
    }

    public function getResponseTime(int $projectId, ?string $from = null, ?string $to = null): array
    {
        // First response = earliest comment on the ticket.
        $firstComments = DB::table('ticket_comments')
            ->select('ticket_id', DB::raw('MIN(created_at) as first_at'))
            ->groupBy('ticket_id');

        $query = DB::table('tickets as t')
            ->joinSub($firstComments, 'fc', 'fc.ticket_id', '=', 't.id')
            ->where('t.project_id', $projectId);

        if ($from) $query->whereDate('t.created_at', '>=', $from);
        if ($to)   $query->whereDate('t.created_at', '<=', $to);

        $avgHours = (clone $query)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (fc.first_at - t.created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        $responded = (clone $query)->count();

        $totalQuery = Ticket::where('project_id', $projectId);
        if ($from) $totalQuery->whereDate('created_at', '>=', $from);
        if ($to)   $totalQuery->whereDate('created_at', '<=', $to);
        $total = $totalQuery->count();

        return [
            'average_hours'    => $avgHours !== null ? round((float) $avgHours, 1) : null,
            'responded'        => $responded,
            'total'            => $total,
            'response_rate'    => $total > 0 ? round(($responded / $total) * 100, 1) : null,
        ];
    }

    public function getAgentPerformance(int $projectId, ?string $from = null, ?string $to = null): array
    {
        $query = Ticket::where('project_id', $projectId)
            ->whereNotNull('assigned_to');

        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        $rows = $query->select('assigned_to')
            ->selectRaw('COUNT(*) as total_assigned')
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('done','completed')) as resolved")
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 3600) FILTER (WHERE status IN ('done','completed')) as avg_resolution_hours")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('done','completed') AND due_date IS NOT NULL AND updated_at::date <= due_date) as sla_met")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('done','completed') AND due_date IS NOT NULL) as sla_eligible")
            ->groupBy('assigned_to')
            ->orderByDesc('resolved')
            ->get();

        $users = \App\Models\User::whereIn('id', $rows->pluck('assigned_to'))
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $users->get($row->assigned_to);
            return [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
                'total_assigned'        => (int) $row->total_assigned,
                'resolved'              => (int) $row->resolved,
                'avg_resolution_hours'  => $row->avg_resolution_hours !== null ? round((float) $row->avg_resolution_hours, 1) : null,
                'sla_compliance'        => $row->sla_eligible > 0 ? round(($row->sla_met / $row->sla_eligible) * 100, 1) : null,
            ];
        })->values()->all();
    }

    public function getExecutiveSummary(int $projectId): array
    {
        $open = Ticket::where('project_id', $projectId)
            ->whereNotIn('status', ['done', 'completed'])
            ->count();

        $closed = Ticket::where('project_id', $projectId)
            ->whereIn('status', ['done', 'completed'])
            ->count();

        // Backlog = tickets sitting in a backlog column or 'todo' status.
        $backlog = Ticket::where('project_id', $projectId)
            ->where(function ($q) {
                $q->where('status', 'todo')
                    ->orWhereHas('kanbanColumn', fn ($c) => $c->where('is_backlog_column', true));
            })
            ->whereNotIn('status', ['done', 'completed'])
            ->count();

        // Team productivity = hours logged in the last 30 days.
        $productivity = (float) TicketTimeLog::where('project_id', $projectId)
            ->whereDate('work_date', '>=', now()->subDays(30)->toDateString())
            ->sum('hours');

        return [
            'open_tickets'      => $open,
            'closed_tickets'    => $closed,
            'backlog_count'     => $backlog,
            'team_productivity_hours_30d' => round($productivity, 1),
            'average_resolution' => $this->getAverageResolutionTime($projectId),
            'sla'               => $this->getSlaCompliance($projectId),
        ];
    }

    public function getOverdueTickets(int $projectId): array
    {
        $tickets = Ticket::where('project_id', $projectId)
            ->whereNotIn('status', ['done', 'completed'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->with([
                'assignee:id,name,email',
                'reporter:id,name,email',
            ])
            ->orderBy('due_date')
            ->paginate(50);

        return [
            'data'  => $tickets->items(),
            'total' => $tickets->total(),
        ];
    }
}
