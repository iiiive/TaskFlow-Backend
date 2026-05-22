<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\Ticket;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = $this->dashboardService->getUserDashboardData(Auth::id());

        return response()->json([
            'message' => 'Dashboard data retrieved successfully.',
            'data' => [
                'summary' => $dashboardData['summary'],
                'recent_activity' => ActivityLogResource::collection($dashboardData['recent_activity']),
            ],
        ], 200);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();

        $assignedTickets = Ticket::query()
            ->with([
                'workspace',
                'assignee',
                'creator',
                'kanbanColumn',
                'comments.user',
                'activityLogs.user',
            ])
            ->where('assigned_to', $user->id)
            ->latest()
            ->get();

        $notifications = [];

        foreach ($assignedTickets as $ticket) {
            $workspaceName = $ticket->workspace?->name ?? 'Unknown Workspace';
            $kanbanColumnName = $ticket->kanbanColumn?->name;

            foreach ($ticket->activityLogs as $log) {
                if ((int) $log->user_id === (int) $user->id) {
                    continue;
                }

                $action = strtolower($log->action ?? '');
                $description = strtolower($log->description ?? '');

                $isAssignmentActivity =
                    str_contains($action, 'assign') ||
                    str_contains($description, 'assigned') ||
                    str_contains($description, 'assignee');

                if ($isAssignmentActivity) {
                    $actor = $log->user?->name ?? 'Someone';

                    $notifications[] = [
                        'id' => 'assigned-' . $log->id,
                        'type' => 'assigned_ticket',
                        'title' => 'Ticket assigned to you',
                        'message' => $actor . ' assigned you to "' . $ticket->title . '".',
                        'ticket_id' => $ticket->id,
                        'ticket_title' => $ticket->title,
                        'workspace_id' => $ticket->workspace_id,
                        'workspace_name' => $workspaceName,
                        'kanban_column_id' => $ticket->kanban_column_id,
                        'kanban_column_name' => $kanbanColumnName,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'created_at' => optional($log->created_at)->format('Y-m-d H:i:s'),
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback assigned ticket notification
            |--------------------------------------------------------------------------
            | This is for old tickets that do not have assignment logs yet.
            */
            if ((int) $ticket->created_by !== (int) $user->id) {
                $notifications[] = [
                    'id' => 'ticket-' . $ticket->id,
                    'type' => 'assigned_ticket',
                    'title' => 'Ticket assigned to you',
                    'message' => $ticket->title,
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'workspace_id' => $ticket->workspace_id,
                    'workspace_name' => $workspaceName,
                    'kanban_column_id' => $ticket->kanban_column_id,
                    'kanban_column_name' => $kanbanColumnName,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => optional($ticket->created_at)->format('Y-m-d H:i:s'),
                ];
            }

            foreach ($ticket->comments as $comment) {
                if ((int) $comment->user_id === (int) $user->id) {
                    continue;
                }

                $commentAuthor = $comment->user?->name ?? 'Someone';

                $notifications[] = [
                    'id' => 'comment-' . $comment->id,
                    'type' => 'comment',
                    'title' => $commentAuthor . ' commented on your assigned ticket',
                    'message' => $comment->comment,
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'workspace_id' => $ticket->workspace_id,
                    'workspace_name' => $workspaceName,
                    'kanban_column_id' => $ticket->kanban_column_id,
                    'kanban_column_name' => $kanbanColumnName,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => optional($comment->created_at)->format('Y-m-d H:i:s'),
                ];
            }

            foreach ($ticket->activityLogs as $log) {
                if ((int) $log->user_id === (int) $user->id) {
                    continue;
                }

                $action = strtolower($log->action ?? '');
                $description = strtolower($log->description ?? '');

                $isAssignmentActivity =
                    str_contains($action, 'assign') ||
                    str_contains($description, 'assigned') ||
                    str_contains($description, 'assignee');

                if ($isAssignmentActivity) {
                    continue;
                }

                $actor = $log->user?->name ?? 'Someone';

                $notifications[] = [
                    'id' => 'activity-' . $log->id,
                    'type' => 'activity',
                    'title' => $actor . ' updated your assigned ticket',
                    'message' => $log->description,
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'workspace_id' => $ticket->workspace_id,
                    'workspace_name' => $workspaceName,
                    'kanban_column_id' => $ticket->kanban_column_id,
                    'kanban_column_name' => $kanbanColumnName,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => optional($log->created_at)->format('Y-m-d H:i:s'),
                ];
            }
        }

        usort($notifications, function ($a, $b) {
            return strtotime($b['created_at'] ?? now()) <=> strtotime($a['created_at'] ?? now());
        });

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => array_slice($notifications, 0, 30),
        ], 200);
    }
}