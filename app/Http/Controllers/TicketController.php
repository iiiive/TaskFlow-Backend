<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\ActivityLog;
use App\Models\Epic;
use App\Models\KanbanColumn;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TicketService;
use App\Services\WorkspaceEmailNotificationService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    protected TicketService $ticketService;
    protected WorkspacePermissionService $permissionService;
    protected WorkspaceEmailNotificationService $emailNotificationService;

    private array $allowedStatuses = [
        'todo',
        'ready_for_development',
        'dev_in_progress',
        'ready_for_testing',
        'ready_for_uat',
        'done',
        'completed',
    ];

    public function __construct(
        TicketService $ticketService,
        WorkspacePermissionService $permissionService,
        WorkspaceEmailNotificationService $emailNotificationService
    ) {
        $this->ticketService = $ticketService;
        $this->permissionService = $permissionService;
        $this->emailNotificationService = $emailNotificationService;
    }

    public function index(Request $request, $workspaceId)
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

        $request->validate([
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:255',
            'due_before' => 'nullable|date',
            'due_after' => 'nullable|date',
        ]);

        $query = Ticket::with([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ])->where('workspace_id', $workspace->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('kanban_column_id')) {
            $columnBelongsToWorkspace = KanbanColumn::where('id', $request->kanban_column_id)
                ->where('workspace_id', $workspace->id)
                ->exists();

            if (!$columnBelongsToWorkspace) {
                return response()->json([
                    'message' => 'The selected Kanban column does not belong to this workspace.',
                ], 422);
            }

            $query->where('kanban_column_id', $request->kanban_column_id);
        }

        if ($request->filled('epic_id')) {
            $epicBelongsToWorkspace = Epic::where('id', $request->epic_id)
                ->where('workspace_id', $workspace->id)
                ->exists();

            if (!$epicBelongsToWorkspace) {
                return response()->json([
                    'message' => 'The selected epic does not belong to this workspace.',
                ], 422);
            }

            $query->where('epic_id', $request->epic_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('due_before')) {
            $query->whereDate('due_date', '<=', $request->due_before);
        }

        if ($request->filled('due_after')) {
            $query->whereDate('due_date', '>=', $request->due_after);
        }

        $tickets = $query->latest()->get();

        return response()->json([
            'message' => 'Tickets retrieved successfully.',
            'data' => TicketResource::collection($tickets),
        ], 200);
    }

    public function store(Request $request, $workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::with('kanbanColumns')->find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'Only project managers and users can create tickets.',
            ], 403);
        }

        if (!$workspace->kanbanColumns()->exists()) {
            $workspace->createDefaultKanbanColumns();
            $workspace->load('kanbanColumns');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        if (!empty($validated['epic_id'])) {
            $epic = Epic::where('id', $validated['epic_id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if (!$epic) {
                return response()->json([
                    'message' => 'The selected epic does not belong to this workspace.',
                ], 422);
            }
        }

        $column = null;

        if (!empty($validated['kanban_column_id'])) {
            $column = KanbanColumn::where('id', $validated['kanban_column_id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if (!$column) {
                return response()->json([
                    'message' => 'The selected Kanban column does not belong to this workspace.',
                ], 422);
            }
        } else {
            $column = $workspace->kanbanColumns()
                ->where('is_backlog_column', true)
                ->first();

            if (!$column) {
                $column = $workspace->kanbanColumns()
                    ->orderBy('position')
                    ->first();
            }

            $validated['kanban_column_id'] = $column?->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Important Custom Column Fix
        |--------------------------------------------------------------------------
        | If the selected Kanban column has a status_key, use it.
        | If it is a custom column without status_key, do NOT force it to todo.
        | We keep status as a compatibility field only.
        */
        if ($column && $column->status_key) {
            $validated['status'] = $column->status_key;
        } else {
            $validated['status'] = $validated['status'] ?? 'todo';
        }

        $validated['priority'] = $validated['priority'] ?? 'medium';

        $ticket = $this->ticketService->createTicket(
            $workspace,
            $user->id,
            $validated
        );

        $ticket->epic_id = $validated['epic_id'] ?? null;
        $ticket->save();

        $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Accurate Workspace Email Activity
        |--------------------------------------------------------------------------
        | This tells members exactly what was created and where it was placed.
        */
        $columnName = $ticket->kanbanColumn?->name ?? $this->formatStatusLabel($ticket->status);

        $this->createAndSendActivity(
            $ticket->workspace_id,
            $ticket->id,
            $user->id,
            'ticket_created',
            $user->name . ' created ticket "' . $ticket->title . '" in "' . $columnName . '".'
        );

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => new TicketResource($ticket),
        ], 201);
    }

    public function show($ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::with([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ])->find($ticketId);

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

        return response()->json([
            'message' => 'Ticket retrieved successfully.',
            'data' => new TicketResource($ticket),
        ], 200);
    }

    public function update(Request $request, $ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::with([
            'workspace',
            'kanbanColumn',
            'assignee:id,name,email',
            'epic',
        ])->find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($ticket->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'Only project managers and users can update tickets.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Save old values before update
        |--------------------------------------------------------------------------
        | These are used to generate accurate email/activity descriptions.
        */
        $oldTitle = $ticket->title;
        $oldDescription = $ticket->description;
        $oldPriority = $ticket->priority;
        $oldDueDate = $ticket->due_date;
        $oldEpicId = $ticket->epic_id;
        $oldAssignedTo = $ticket->assigned_to;
        $oldKanbanColumnId = $ticket->kanban_column_id;
        $oldStatus = $ticket->status;
        $oldColumnName = $ticket->kanbanColumn?->name ?? $this->formatStatusLabel($ticket->status);
        $oldAssigneeName = $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Unassigned';

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        if (array_key_exists('epic_id', $validated) && !empty($validated['epic_id'])) {
            $epic = Epic::where('id', $validated['epic_id'])
                ->where('workspace_id', $ticket->workspace_id)
                ->first();

            if (!$epic) {
                return response()->json([
                    'message' => 'The selected epic does not belong to this ticket workspace.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Accurate Kanban Column Movement Fix
        |--------------------------------------------------------------------------
        | When kanban_column_id is sent, trust the actual column, not the old
        | fixed workflow status sent by the frontend.
        |
        | If the custom column has no status_key, we keep the old status.
        | This prevents custom columns from being labeled as "To Do" in emails.
        */
        if (array_key_exists('kanban_column_id', $validated) && !empty($validated['kanban_column_id'])) {
            $column = KanbanColumn::where('id', $validated['kanban_column_id'])
                ->where('workspace_id', $ticket->workspace_id)
                ->first();

            if (!$column) {
                return response()->json([
                    'message' => 'The selected Kanban column does not belong to this ticket workspace.',
                ], 422);
            }

            if ($column->status_key) {
                $validated['status'] = $column->status_key;
            } else {
                /*
                | If frontend sends status=todo for custom columns, remove it.
                | This stops wrong emails like "Moved from Dev In Progress to To Do".
                */
                unset($validated['status']);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Direct Status Update Compatibility
        |--------------------------------------------------------------------------
        | If the frontend sends only status, find the matching default column.
        | This keeps old buttons/features working.
        */
        if (
            array_key_exists('status', $validated)
            && !array_key_exists('kanban_column_id', $validated)
        ) {
            $matchingColumn = KanbanColumn::where('workspace_id', $ticket->workspace_id)
                ->where('status_key', $validated['status'])
                ->first();

            if ($matchingColumn) {
                $validated['kanban_column_id'] = $matchingColumn->id;
            }
        }

        $ticket = $this->ticketService->updateTicket(
            $ticket,
            $validated
        );

        if (array_key_exists('epic_id', $validated)) {
            $ticket->epic_id = $validated['epic_id'] ?? null;
            $ticket->save();
        }

        $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'kanbanColumn',
            'epic',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Accurate Activity Logs + Emails
        |--------------------------------------------------------------------------
        | These are intentionally specific. The email notification reads from
        | activity_logs.description, so accuracy starts here.
        */
        $this->logAccurateTicketUpdateActivities(
            ticket: $ticket,
            user: $user,
            oldTitle: $oldTitle,
            oldDescription: $oldDescription,
            oldPriority: $oldPriority,
            oldDueDate: $oldDueDate,
            oldEpicId: $oldEpicId,
            oldAssignedTo: $oldAssignedTo,
            oldAssigneeName: $oldAssigneeName,
            oldKanbanColumnId: $oldKanbanColumnId,
            oldStatus: $oldStatus,
            oldColumnName: $oldColumnName,
            requestData: $validated
        );

        return response()->json([
            'message' => 'Ticket updated successfully.',
            'data' => new TicketResource($ticket),
        ], 200);
    }

    public function destroy($ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canDeleteTicket($ticket->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'Only the project manager can delete tickets.',
            ], 403);
        }

        $ticketTitle = $ticket->title;
        $workspaceId = $ticket->workspace_id;
        $ticketIdForLog = $ticket->id;

        $this->ticketService->deleteTicket($ticket);

        $this->createAndSendActivity(
            $workspaceId,
            $ticketIdForLog,
            $user->id,
            'ticket_deleted',
            $user->name . ' deleted ticket "' . $ticketTitle . '".'
        );

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ], 200);
    }

    public function insights($ticketId)
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

        $insightService = app(\App\Services\TicketInsightService::class);

        return response()->json([
            'message' => 'Ticket insights retrieved successfully.',
            'data' => $insightService->getTicketInsights($ticket),
        ], 200);
    }

    private function logAccurateTicketUpdateActivities(
        Ticket $ticket,
        User $user,
        ?string $oldTitle,
        ?string $oldDescription,
        ?string $oldPriority,
        mixed $oldDueDate,
        mixed $oldEpicId,
        mixed $oldAssignedTo,
        ?string $oldAssigneeName,
        mixed $oldKanbanColumnId,
        ?string $oldStatus,
        ?string $oldColumnName,
        array $requestData
    ): void {
        $newColumnName = $ticket->kanbanColumn?->name ?? $this->formatStatusLabel($ticket->status);
        $newAssigneeName = $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Unassigned';

        if (
            array_key_exists('kanban_column_id', $requestData)
            && (int) $oldKanbanColumnId !== (int) $ticket->kanban_column_id
        ) {
            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_moved',
                $user->name . ' moved ticket "' . $ticket->title . '" from "' . $oldColumnName . '" to "' . $newColumnName . '".'
            );
        } elseif (
            array_key_exists('status', $requestData)
            && $oldStatus !== $ticket->status
        ) {
            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_status_updated',
                $user->name . ' moved ticket "' . $ticket->title . '" from "' . $this->formatStatusLabel($oldStatus) . '" to "' . $this->formatStatusLabel($ticket->status) . '".'
            );
        }

        if (
            array_key_exists('assigned_to', $requestData)
            && (int) ($oldAssignedTo ?? 0) !== (int) ($ticket->assigned_to ?? 0)
        ) {
            $description = $ticket->assigned_to
                ? $user->name . ' assigned ticket "' . $ticket->title . '" from "' . $oldAssigneeName . '" to "' . $newAssigneeName . '".'
                : $user->name . ' removed the assignee from ticket "' . $ticket->title . '".';

            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_assignee_updated',
                $description
            );
        }

        if (
            array_key_exists('priority', $requestData)
            && $oldPriority !== $ticket->priority
        ) {
            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_priority_updated',
                $user->name . ' changed the priority of ticket "' . $ticket->title . '" from "' . $this->formatStatusLabel($oldPriority) . '" to "' . $this->formatStatusLabel($ticket->priority) . '".'
            );
        }

        if (
            array_key_exists('title', $requestData)
            && $oldTitle !== $ticket->title
        ) {
            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_title_updated',
                $user->name . ' renamed ticket "' . $oldTitle . '" to "' . $ticket->title . '".'
            );
        }

        if (
            array_key_exists('description', $requestData)
            && $oldDescription !== $ticket->description
        ) {
            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_description_updated',
                $user->name . ' updated the description of ticket "' . $ticket->title . '".'
            );
        }

        if (
            array_key_exists('due_date', $requestData)
            && (string) $oldDueDate !== (string) $ticket->due_date
        ) {
            $oldDate = $oldDueDate ? date('F d, Y', strtotime((string) $oldDueDate)) : 'No due date';
            $newDate = $ticket->due_date ? date('F d, Y', strtotime((string) $ticket->due_date)) : 'No due date';

            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_due_date_updated',
                $user->name . ' changed the due date of ticket "' . $ticket->title . '" from "' . $oldDate . '" to "' . $newDate . '".'
            );
        }

        if (
            array_key_exists('epic_id', $requestData)
            && (int) ($oldEpicId ?? 0) !== (int) ($ticket->epic_id ?? 0)
        ) {
            $epicName = $ticket->epic?->title ?? $ticket->epic?->name ?? 'No Epic';

            $description = $ticket->epic_id
                ? $user->name . ' assigned ticket "' . $ticket->title . '" to epic "' . $epicName . '".'
                : $user->name . ' removed ticket "' . $ticket->title . '" from its epic.';

            $this->createAndSendActivity(
                $ticket->workspace_id,
                $ticket->id,
                $user->id,
                'ticket_epic_updated',
                $description
            );
        }
    }

    private function createAndSendActivity(
        int $workspaceId,
        ?int $ticketId,
        int $userId,
        string $action,
        string $description
    ): void {
        $activityLog = ActivityLog::create([
            'workspace_id' => $workspaceId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);
    }

    private function formatStatusLabel(?string $value): string
    {
        if (!$value) {
            return 'None';
        }

        return str($value)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }
}