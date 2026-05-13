<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\TicketService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    protected TicketService $ticketService;
    protected WorkspacePermissionService $permissionService;

    private array $allowedStatuses = [
        'todo',
        'ready_for_development',
        'dev_in_progress',
        'ready_for_testing',
        'ready_for_uat',
        'done',
    ];

    public function __construct(
        TicketService $ticketService,
        WorkspacePermissionService $permissionService
    ) {
        $this->ticketService = $ticketService;
        $this->permissionService = $permissionService;
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
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:255',
            'due_before' => 'nullable|date',
            'due_after' => 'nullable|date',
        ]);

        $tickets = $this->ticketService->getWorkspaceTickets(
            $workspace,
            $request->only([
                'status',
                'priority',
                'assigned_to',
                'search',
                'due_before',
                'due_after',
            ])
        );

        return response()->json([
            'message' => 'Tickets retrieved successfully.',
            'data' => TicketResource::collection($tickets),
        ], 200);
    }

    public function store(Request $request, $workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can create tickets.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $validated['status'] = $validated['status'] ?? 'todo';
        $validated['priority'] = $validated['priority'] ?? 'medium';

        $ticket = $this->ticketService->createTicket(
            $workspace,
            $user->id,
            $validated
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

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($ticket->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can update tickets.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $ticket = $this->ticketService->updateTicket(
            $ticket,
            $validated
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
                'message' => 'Only the workspace owner can delete tickets.',
            ], 403);
        }

        $this->ticketService->deleteTicket($ticket);

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
}