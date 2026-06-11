<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommentMentionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $mentionedUser,
        public TicketComment $comment,
        public Ticket $ticket
    ) {
        $this->ticket->loadMissing('workspace');
        $this->comment->loadMissing('user');
    }

    public function build()
    {
        $ticketUrl = rtrim((string) env('FRONTEND_URL', ''), '/')
            . '/projects/' . $this->ticket->project_id . '/board';

        return $this
            ->subject("[{$this->ticket->issue_number}] You were mentioned in a comment")
            ->view('emails.comment-mention')
            ->with([
                'mentionedUser' => $this->mentionedUser,
                'comment' => $this->comment,
                'commenter' => $this->comment->user,
                'ticket' => $this->ticket,
                'project' => $this->ticket->workspace,
                'ticketUrl' => $ticketUrl,
            ]);
    }
}
