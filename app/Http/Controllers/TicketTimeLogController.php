<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTimeLogResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketTimeLog;
use App\Models\Workspace;
use App\Services\WorkspaceEmailNotificationService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketTimeLogController extends Controller
{
    protected WorkspacePermissionService $permissionService;
    protected WorkspaceEmailNotificationService $emailNotificationService;

    public function __construct(
        WorkspacePermissionService $permissionService,
        WorkspaceEmailNotificationService $emailNotificationService
    ) {
        $this->permissionService = $permissionService;
        $this->emailNotificationService = $emailNotificationService;
    }

    public function ticketIndex($ticketId)
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
                'message' => 'You do not have access to this ticket.',
            ], 403);
        }

        $logs = TicketTimeLog::with('user:id,name,email')
            ->where('ticket_id', $ticket->id)
            ->latest('work_date')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Ticket time logs retrieved successfully.',
            'data' => TicketTimeLogResource::collection($logs),
            'total_hours' => (float) $logs->sum('hours'),
        ], 200);
    }

    public function store(Request $request, $ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::with('assignee:id,name,email')->find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->canLogTime($ticket, $user->id)) {
            return response()->json([
                'message' => 'Only owners, editors, or the assigned user can log time on this ticket.',
            ], 403);
        }

        $validated = $request->validate([
            'hours' => 'required|numeric|min:0.25|max:24',
            'description' => 'nullable|string|max:1000',
            'work_date' => 'required|date',
        ]);

        $timeLog = TicketTimeLog::create([
            'workspace_id' => $ticket->workspace_id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'hours' => $validated['hours'],
            'description' => $validated['description'] ?? null,
            'work_date' => $validated['work_date'],
        ]);

        $workDate = date('F d, Y', strtotime($validated['work_date']));

        $description = $user->name . ' logged ' . (float) $timeLog->hours . ' hour(s) on ticket "' . $ticket->title . '" for ' . $workDate . '.';

        if (!empty($validated['description'])) {
            $description .= ' Note: ' . $validated['description'];
        }

        $activityLog = ActivityLog::create([
            'workspace_id' => $ticket->workspace_id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'time_logged',
            'description' => $description,
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);

        $timeLog->load('user:id,name,email');

        return response()->json([
            'message' => 'Time logged successfully.',
            'data' => new TicketTimeLogResource($timeLog),
        ], 201);
    }

    public function workspaceTimesheet(Request $request, $workspaceId)
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
                'message' => 'You do not have access to this workspace.',
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'nullable|date',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $query = TicketTimeLog::with([
                'user:id,name,email',
                'ticket:id,title,status,priority',
            ])
            ->where('workspace_id', $workspace->id);

        if (!empty($validated['date'])) {
            $query->whereDate('work_date', $validated['date']);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('work_date', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('work_date', '<=', $validated['to']);
        }

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $logs = $query
            ->orderByDesc('work_date')
            ->orderByDesc('created_at')
            ->get();

        $dailySummary = $logs
            ->groupBy(fn ($log) => $log->work_date->format('Y-m-d'))
            ->map(function ($dateLogs, $date) {
                return [
                    'date' => $date,
                    'total_hours' => (float) $dateLogs->sum('hours'),
                    'users' => $dateLogs
                        ->groupBy('user_id')
                        ->map(function ($userLogs) {
                            $first = $userLogs->first();

                            return [
                                'user_id' => $first->user_id,
                                'user_name' => $first->user?->name ?? $first->user?->email ?? 'Unknown User',
                                'total_hours' => (float) $userLogs->sum('hours'),
                                'logs' => TicketTimeLogResource::collection($userLogs)->resolve(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Workspace timesheet retrieved successfully.',
            'total_hours' => (float) $logs->sum('hours'),
            'data' => $dailySummary,
        ], 200);
    }

    private function canLogTime(Ticket $ticket, int $userId): bool
    {
        $canManageTicket = $this->permissionService->canCreateOrUpdateTicket(
            $ticket->workspace_id,
            $userId
        );

        $isAssignee = (int) $ticket->assigned_to === (int) $userId;

        return $canManageTicket || $isAssignee;
    }
}