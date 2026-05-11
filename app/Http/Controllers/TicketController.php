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

    public function __construct(
        TicketService $ticketService,
        WorkspacePermissionService $permissionService
    ) {
        $this->ticketService = $ticketService;
        $this->permissionService = $permissionService;
    }

    /**
     * Display all tickets inside a workspace.
     * Owner, editor, and viewer can view.
     */
    public function index($workspaceId)
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

        $tickets = $this->ticketService->getWorkspaceTickets($workspace);

        return response()->json([
            'message' => 'Tickets retrieved successfully.',
            'data' => TicketResource::collection($tickets),
        ], 200);
    }

    /**
     * Create a new ticket.
     * Only owner and editor can create.
     */
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

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:todo,in_progress,in_review,done',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $ticket = $this->ticketService->createTicket(
            $workspace,
            $user->id,
            $request->only([
                'title',
                'description',
                'status',
                'priority',
                'assigned_to',
                'due_date',
            ])
        );

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Display one ticket.
     * Any workspace member can view.
     */
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

    /**
     * Update a ticket.
     * Owner and editor can update.
     */
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

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:todo,in_progress,in_review,done',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $ticket = $this->ticketService->updateTicket(
            $ticket,
            $request->only([
                'title',
                'description',
                'status',
                'priority',
                'assigned_to',
                'due_date',
            ])
        );

        return response()->json([
            'message' => 'Ticket updated successfully.',
            'data' => new TicketResource($ticket),
        ], 200);
    }

    /**
     * Delete a ticket.
     * Only owner can delete.
     */
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
}