<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;    

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

    $assignedTickets = \App\Models\Ticket::query()
        ->with([
            'workspace',
            'assignee',
            'creator',
            'comments.user',
            'activityLogs.user',
        ])
        ->where('assigned_to', $user->id)
        ->latest()
        ->get();

    $notifications = [];

    foreach ($assignedTickets as $ticket) {
        $workspaceName = $ticket->workspace?->name ?? 'Unknown Workspace';

        /*
         * Show assigned ticket notification only if another user created it.
         * Do not notify the user about a ticket they created for themselves.
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
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => optional($ticket->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        /*
         * Show comments only if another user commented.
         * Do not notify the user about their own comments.
         */
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
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => optional($comment->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        /*
         * Show activity logs only if another user performed the action.
         * Do not notify the user about their own activity.
         */
        foreach ($ticket->activityLogs as $log) {
            if ((int) $log->user_id === (int) $user->id) {
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
    ]);
}
}