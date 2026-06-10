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

    public function workspaceLogs($projectId)
    {
        $user = Auth::user();

        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canView($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this project activity.'], 403);
        }

        $logs = $this->activityLogService->getWorkspaceLogs($project->id);

        return response()->json([
            'message' => 'Project activity logs retrieved successfully.',
            'data' => ActivityLogResource::collection($logs),
        ], 200);
    }

    public function ticketLogs($ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (!$this->permissionService->canView($ticket->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this ticket activity.'], 403);
        }

        $logs = $this->activityLogService->getTicketLogs($ticket->id);

        return response()->json([
            'message' => 'Ticket activity logs retrieved successfully.',
            'data' => ActivityLogResource::collection($logs),
        ], 200);
    }
}
