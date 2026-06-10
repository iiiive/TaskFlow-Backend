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

    public function index(Request $request, $projectId)
    {
        $user = Auth::user();

        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canView($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        $request->validate([
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'issue_type' => 'nullable|in:' . implode(',', Ticket::ISSUE_TYPES),
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:255',
            'due_before' => 'nullable|date',
            'due_after' => 'nullable|date',
        ]);

        $query = Ticket::with([
            'creator:id,name,email',
            'assignee:id,name,email',
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
            'parent:id,title,issue_number',
        ])->where('project_id', $project->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('kanban_column_id')) {
            $columnBelongsToProject = KanbanColumn::where('id', $request->kanban_column_id)
                ->where('project_id', $project->id)
                ->exists();

            if (!$columnBelongsToProject) {
                return response()->json(['message' => 'The selected Kanban column does not belong to this project.'], 422);
            }

            $query->where('kanban_column_id', $request->kanban_column_id);
        }

        if ($request->filled('epic_id')) {
            $epicBelongsToProject = Epic::where('id', $request->epic_id)
                ->where('project_id', $project->id)
                ->exists();

            if (!$epicBelongsToProject) {
                return response()->json(['message' => 'The selected epic does not belong to this project.'], 422);
            }

            $query->where('epic_id', $request->epic_id);
        }

        if ($request->filled('issue_type')) {
            $query->where('issue_type', $request->issue_type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('title ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('description ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('issue_number ILIKE ?', ["%{$search}%"]);
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

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();

        $project = Workspace::with('kanbanColumns')->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to create tickets.'], 403);
        }

        if (!$project->kanbanColumns()->exists()) {
            $project->createDefaultKanbanColumns();
            $project->load('kanbanColumns');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'issue_type' => 'nullable|in:' . implode(',', Ticket::ISSUE_TYPES),
            'parent_ticket_id' => 'nullable|integer|exists:tickets,id',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'reporter_id' => 'nullable|exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            'story_points' => 'nullable|integer|min:0|max:100',
            'category' => 'nullable|string|max:100',
            'due_date' => 'nullable|date',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:labels,id',
        ]);

        if (!empty($validated['epic_id'])) {
            $epic = Epic::where('id', $validated['epic_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$epic) {
                return response()->json(['message' => 'The selected epic does not belong to this project.'], 422);
            }
        }

        $column = null;

        if (!empty($validated['kanban_column_id'])) {
            $column = KanbanColumn::where('id', $validated['kanban_column_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$column) {
                return response()->json(['message' => 'The selected Kanban column does not belong to this project.'], 422);
            }
        } else {
            $column = $project->kanbanColumns()
                ->where('is_backlog_column', true)
                ->first()
                ?? $project->kanbanColumns()->orderBy('position')->first();

            $validated['kanban_column_id'] = $column?->id;
        }

        if ($column && $column->status_key) {
            $validated['status'] = $column->status_key;
        } else {
            $validated['status'] = $validated['status'] ?? 'todo';
        }

        $validated['priority'] = $validated['priority'] ?? 'medium';
        $validated['issue_type'] = $validated['issue_type'] ?? 'task';

        $ticket = $this->ticketService->createTicket($project, $user->id, $validated);

        $ticket->epic_id = $validated['epic_id'] ?? null;
        $ticket->save();

        if (!empty($validated['label_ids'])) {
            $ticket->labels()->sync($validated['label_ids']);
        }

        $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
            'parent:id,title,issue_number',
        ]);

        $columnName = $this->resolveColumnName($ticket->project_id, $ticket->kanban_column_id, $ticket->status);

        $this->createAndSendActivity(
            $ticket->project_id,
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
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
            'parent:id,title,issue_number',
            'children:id,title,issue_number,status,priority,issue_type',
        ])->find($ticketId);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (!$this->permissionService->canView($ticket->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this ticket.'], 403);
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
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($ticket->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to update tickets.'], 403);
        }

        $oldTitle = $ticket->title;
        $oldDescription = $ticket->description;
        $oldPriority = $ticket->priority;
        $oldDueDate = $ticket->due_date;
        $oldEpicId = $ticket->epic_id;
        $oldAssignedTo = $ticket->assigned_to;
        $oldAssigneeName = $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Unassigned';
        $oldKanbanColumnId = $ticket->kanban_column_id;
        $oldStatus = $ticket->status;
        $oldColumnName = $this->resolveColumnName($ticket->project_id, $ticket->kanban_column_id, $ticket->status);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'issue_type' => 'nullable|in:' . implode(',', Ticket::ISSUE_TYPES),
            'parent_ticket_id' => 'nullable|integer|exists:tickets,id',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'kanban_column_id' => 'nullable|integer|exists:kanban_columns,id',
            'epic_id' => 'nullable|integer|exists:epics,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'reporter_id' => 'nullable|exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            'story_points' => 'nullable|integer|min:0|max:100',
            'category' => 'nullable|string|max:100',
            'due_date' => 'nullable|date',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:labels,id',
        ]);

        if (array_key_exists('epic_id', $validated) && !empty($validated['epic_id'])) {
            $epic = Epic::where('id', $validated['epic_id'])
                ->where('project_id', $ticket->project_id)
                ->first();

            if (!$epic) {
                return response()->json(['message' => 'The selected epic does not belong to this project.'], 422);
            }
        }

        if (array_key_exists('kanban_column_id', $validated) && !empty($validated['kanban_column_id'])) {
            $column = KanbanColumn::where('id', $validated['kanban_column_id'])
                ->where('project_id', $ticket->project_id)
                ->first();

            if (!$column) {
                return response()->json(['message' => 'The selected Kanban column does not belong to this project.'], 422);
            }

            if ($column->status_key) {
                $validated['status'] = $column->status_key;
            } else {
                unset($validated['status']);
            }
        }

        if (array_key_exists('status', $validated) && !array_key_exists('kanban_column_id', $validated)) {
            $matchingColumn = KanbanColumn::where('project_id', $ticket->project_id)
                ->where('status_key', $validated['status'])
                ->first();

            if ($matchingColumn) {
                $validated['kanban_column_id'] = $matchingColumn->id;
            }
        }

        $ticket = $this->ticketService->updateTicket($ticket, $validated);

        if (array_key_exists('epic_id', $validated)) {
            $ticket->epic_id = $validated['epic_id'] ?? null;
            $ticket->save();
        }

        if (array_key_exists('label_ids', $validated)) {
            $ticket->labels()->sync($validated['label_ids'] ?? []);
        }

        $ticket->load([
            'creator:id,name,email',
            'assignee:id,name,email',
            'reporter:id,name,email',
            'kanbanColumn',
            'epic',
            'labels',
        ]);

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
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (!$this->permissionService->canDeleteTicket($ticket->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to delete tickets.'], 403);
        }

        $ticketTitle = $ticket->title;
        $projectId = $ticket->project_id;
        $ticketIdForLog = $ticket->id;

        $this->ticketService->deleteTicket($ticket);

        $this->createAndSendActivity(
            $projectId,
            $ticketIdForLog,
            $user->id,
            'ticket_deleted',
            $user->name . ' deleted ticket "' . $ticketTitle . '".'
        );

        return response()->json(['message' => 'Ticket deleted successfully.'], 200);
    }

    public function insights($ticketId)
    {
        $user = Auth::user();

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (!$this->permissionService->canView($ticket->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this ticket.'], 403);
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
        $newColumnName = $this->resolveColumnName($ticket->project_id, $ticket->kanban_column_id, $ticket->status);
        $newAssigneeName = $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Unassigned';

        if (
            array_key_exists('kanban_column_id', $requestData)
            && (int) ($oldKanbanColumnId ?? 0) !== (int) ($ticket->kanban_column_id ?? 0)
        ) {
            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_moved',
                $user->name . ' moved ticket "' . $ticket->title . '" from "' . $oldColumnName . '" to "' . $newColumnName . '".'
            );
        } elseif (array_key_exists('status', $requestData) && $oldStatus !== $ticket->status) {
            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_moved',
                $user->name . ' moved ticket "' . $ticket->title . '" from "' .
                $this->resolveColumnName($ticket->project_id, $oldKanbanColumnId, $oldStatus) .
                '" to "' . $this->resolveColumnName($ticket->project_id, $ticket->kanban_column_id, $ticket->status) . '".'
            );
        }

        if (
            array_key_exists('assigned_to', $requestData)
            && (int) ($oldAssignedTo ?? 0) !== (int) ($ticket->assigned_to ?? 0)
        ) {
            $description = $ticket->assigned_to
                ? $user->name . ' assigned "' . $ticket->title . '" from "' . $oldAssigneeName . '" to "' . $newAssigneeName . '".'
                : $user->name . ' removed the assignee from "' . $ticket->title . '".';

            $this->createAndSendActivity($ticket->project_id, $ticket->id, $user->id, 'ticket_assignee_updated', $description);
        }

        if (array_key_exists('priority', $requestData) && $oldPriority !== $ticket->priority) {
            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_priority_updated',
                $user->name . ' changed the priority of "' . $ticket->title . '" from "' .
                $this->formatStatusLabel($oldPriority) . '" to "' . $this->formatStatusLabel($ticket->priority) . '".'
            );
        }

        if (array_key_exists('title', $requestData) && $oldTitle !== $ticket->title) {
            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_title_updated',
                $user->name . ' renamed "' . $oldTitle . '" to "' . $ticket->title . '".'
            );
        }

        if (array_key_exists('description', $requestData) && $oldDescription !== $ticket->description) {
            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_description_updated',
                $user->name . ' updated the description of "' . $ticket->title . '".'
            );
        }

        if (
            array_key_exists('due_date', $requestData)
            && (string) $oldDueDate !== (string) $ticket->due_date
        ) {
            $oldDate = $oldDueDate ? date('F d, Y', strtotime((string) $oldDueDate)) : 'No due date';
            $newDate = $ticket->due_date ? date('F d, Y', strtotime((string) $ticket->due_date)) : 'No due date';

            $this->createAndSendActivity(
                $ticket->project_id, $ticket->id, $user->id,
                'ticket_due_date_updated',
                $user->name . ' changed the due date of "' . $ticket->title . '" from "' . $oldDate . '" to "' . $newDate . '".'
            );
        }

        if (
            array_key_exists('epic_id', $requestData)
            && (int) ($oldEpicId ?? 0) !== (int) ($ticket->epic_id ?? 0)
        ) {
            $epicName = $ticket->epic?->title ?? $ticket->epic?->name ?? 'No Epic';
            $description = $ticket->epic_id
                ? $user->name . ' assigned "' . $ticket->title . '" to epic "' . $epicName . '".'
                : $user->name . ' removed "' . $ticket->title . '" from its epic.';

            $this->createAndSendActivity($ticket->project_id, $ticket->id, $user->id, 'ticket_epic_updated', $description);
        }
    }

    private function createAndSendActivity(
        int $projectId,
        ?int $ticketId,
        int $userId,
        string $action,
        string $description
    ): void {
        $activityLog = ActivityLog::create([
            'project_id' => $projectId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);

        $this->emailNotificationService->sendActivityNotification($activityLog);
    }

    private function resolveColumnName(int $projectId, mixed $kanbanColumnId = null, ?string $status = null): string
    {
        if ($kanbanColumnId) {
            $name = KanbanColumn::where('project_id', $projectId)
                ->where('id', $kanbanColumnId)
                ->value('name');

            if ($name) {
                return $name;
            }
        }

        if ($status) {
            $name = KanbanColumn::where('project_id', $projectId)
                ->where('status_key', $status)
                ->value('name');

            if ($name) {
                return $name;
            }
        }

        return $this->formatStatusLabel($status);
    }

    private function formatStatusLabel(?string $value): string
    {
        if (!$value) {
            return 'None';
        }

        return str($value)->replace(['_', '-'], ' ')->title()->toString();
    }
}
