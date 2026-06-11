<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $assignee,
        public Ticket $ticket
    ) {
        $this->ticket->loadMissing(['workspace', 'reporter']);
    }

    public function build()
    {
        $ticketUrl = rtrim((string) env('FRONTEND_URL', ''), '/')
            . '/projects/' . $this->ticket->project_id . '/board';

        return $this
            ->subject("[{$this->ticket->issue_number}] Ticket assigned to you: {$this->ticket->title}")
            ->view('emails.ticket-assigned')
            ->with([
                'assignee' => $this->assignee,
                'ticket' => $this->ticket,
                'project' => $this->ticket->workspace,
                'reporter' => $this->ticket->reporter,
                'ticketUrl' => $ticketUrl,
            ]);
    }
}
