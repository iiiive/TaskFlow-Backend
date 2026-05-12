<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class DashboardService
{
    public function getUserDashboardData(int $userId): array
    {
        $workspaceIds = WorkspaceMember::where('user_id', $userId)
            ->pluck('workspace_id');

        return [
            'summary' => [
                'total_workspaces' => Workspace::whereIn('id', $workspaceIds)->count(),

                'total_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)->count(),

                'todo_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->where('status', 'todo')
                    ->count(),

                'in_progress_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->where('status', 'in_progress')
                    ->count(),

                'in_review_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->where('status', 'in_review')
                    ->count(),

                'done_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->where('status', 'done')
                    ->count(),

                'urgent_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->where('priority', 'urgent')
                    ->count(),

                'overdue_tickets' => Ticket::whereIn('workspace_id', $workspaceIds)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->where('status', '!=', 'done')
                    ->count(),
            ],

            'recent_activity' => ActivityLog::whereIn('workspace_id', $workspaceIds)
                ->with('user:id,name,email')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}