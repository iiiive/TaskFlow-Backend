<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\WorkspaceMember;
use App\Services\TicketCommentService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketCommentController extends Controller
{
    protected TicketCommentService $commentService;
    protected WorkspacePermissionService $permissionService;

    public function __construct(
        TicketCommentService $commentService,
        WorkspacePermissionService $permissionService
    ) {
        $this->commentService = $commentService;
        $this->permissionService = $permissionService;
    }

    public function index($ticketId)
    {
        $user = Auth::user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($ticket->project_id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this ticket.',
            ], 403);
        }

        $comments = $this->commentService->getTicketComments($ticket);

        return response()->json([
            'message' => 'Ticket comments retrieved successfully.',
            'data' => TicketCommentResource::collection($comments),
        ], 200);
    }

    public function store(Request $request, $ticketId)
    {
        $user = Auth::user();
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($ticket->project_id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this ticket.',
            ], 403);
        }

        $member = WorkspaceMember::where('project_id', $ticket->project_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member || !in_array($member->role, WorkspaceMember::ROLES_CAN_EDIT, true)) {
            return response()->json([
                'message' => 'You do not have permission to comment on this ticket.',
            ], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
        ]);

        $comment = $this->commentService->createComment(
            $ticket,
            $user->id,
            $validated['comment']
        );

        return response()->json([
            'message' => 'Comment added successfully.',
            'data' => new TicketCommentResource($comment),
        ], 201);
    }
}
