<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\ActivityLogService;
use App\Services\WorkspacePermissionService;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    protected ActivityLogService $activityLogService;
    protected WorkspacePermissionService $permissionService;

    public function __construct(
        ActivityLogService $activityLogService,
        WorkspacePermissionService $permissionService
    ) {
        $this->activityLogService = $activityLogService;
        $this->permissionService = $permissionService;
    }

    /**
     * Get all activity logs inside a workspace.
     */
    public function workspaceLogs($workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this workspace activity.',
            ], 403);
        }

        $logs = $this->activityLogService->getWorkspaceLogs($workspace->id);

        return response()->json([
            'message' => 'Workspace activity logs retrieved successfully.',
            'data' => ActivityLogResource::collection($logs),
        ], 200);
    }

    /**
     * Get all activity logs for one ticket.
     */
    public function ticketLogs($ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($ticket->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this ticket activity.',
            ], 403);
        }

        $logs = $this->activityLogService->getTicketLogs($ticket->id);

        return response()->json([
            'message' => 'Ticket activity logs retrieved successfully.',
            'data' => ActivityLogResource::collection($logs),
        ], 200);
    }
}