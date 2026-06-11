<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\KanbanColumn;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class DashboardService
{
    public function getUserDashboardData(int $userId): array
    {
        $workspaceIds = WorkspaceMember::where('user_id', $userId)
            ->pluck('project_id');

        /*
        |--------------------------------------------------------------------------
        | New dynamic Kanban summary
        |--------------------------------------------------------------------------
        | This lets the dashboard know how many tickets are inside each custom
        | Kanban column, including Backlog, Blockers, Done, or any user-made column.
        */
        $kanbanColumns = KanbanColumn::whereIn('project_id', $workspaceIds)
            ->withCount('tickets')
            ->orderBy('project_id')
            ->orderBy('position')
            ->get()
            ->map(function ($column) {
                return [
                    'id' => $column->id,
                    'workspace_id' => $column->project_id,
                    'name' => $column->name,
                    'slug' => $column->slug,
                    'position' => $column->position,
                    'status_key' => $column->status_key,
                    'is_backlog_column' => $column->is_backlog_column,
                    'is_done_column' => $column->is_done_column,
                    'tickets_count' => $column->tickets_count,
                ];
            })
            ->values();

        return [
            'summary' => [
                'total_workspaces' => Workspace::whereIn('id', $workspaceIds)->count(),

                'total_tickets' => Ticket::whereIn('project_id', $workspaceIds)->count(),

                /*
                |--------------------------------------------------------------------------
                | Old summary keys kept for frontend compatibility
                |--------------------------------------------------------------------------
                | Your Angular dashboard may still expect these exact names.
                */
                'todo_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'todo')
                    ->count(),

                'ready_for_development_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'ready_for_development')
                    ->count(),

                'dev_in_progress_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'dev_in_progress')
                    ->count(),

                'ready_for_testing_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'ready_for_testing')
                    ->count(),

                'ready_for_uat_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'ready_for_uat')
                    ->count(),

                'done_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'done')
                    ->count(),

                'completed_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('status', 'completed')
                    ->count(),

                /*
                |--------------------------------------------------------------------------
                | Extra compatibility for old dashboard labels
                |--------------------------------------------------------------------------
                | If your frontend still reads in_progress_tickets or in_review_tickets,
                | these will still return useful numbers.
                */
                'in_progress_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->whereIn('status', ['in_progress', 'dev_in_progress'])
                    ->count(),

                'in_review_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->whereIn('status', ['in_review', 'ready_for_testing', 'ready_for_uat'])
                    ->count(),

                'urgent_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->where('priority', 'urgent')
                    ->count(),

                'overdue_tickets' => Ticket::whereIn('project_id', $workspaceIds)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->whereNotIn('status', ['done', 'completed'])
                    ->count(),

                /*
                |--------------------------------------------------------------------------
                | New frontend-ready dynamic data
                |--------------------------------------------------------------------------
                | Later, Angular can use this instead of hardcoded status cards/charts.
                */
                'kanban_columns' => $kanbanColumns,
            ],

            'recent_activity' => ActivityLog::whereIn('project_id', $workspaceIds)
                ->with('user:id,name,email')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}