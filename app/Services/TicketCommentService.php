<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;

class TicketCommentService
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getTicketComments(Ticket $ticket)
    {
        return TicketComment::where('ticket_id', $ticket->id)
            ->with('user:id,name,email')
            ->oldest()
            ->get();
    }

    public function createComment(Ticket $ticket, int $userId, string $comment): TicketComment
    {
        $ticketComment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'comment' => $comment,
        ]);

        $this->activityLogService->create(
            $ticket->project_id,
            $ticket->id,
            $userId,
            'comment_added',
            'A comment was added to the ticket.'
        );

        return $ticketComment->load('user:id,name,email');
    }
}