<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Ticket $ticket,
        public int $daysOverdue
    ) {
        $this->ticket->loadMissing(['workspace', 'assignee', 'reporter']);
    }

    public function build()
    {
        $ticketUrl = rtrim((string) env('FRONTEND_URL', ''), '/')
            . '/projects/' . $this->ticket->project_id . '/board';

        return $this
            ->subject("[{$this->ticket->issue_number}] Overdue ({$this->daysOverdue}d): {$this->ticket->title}")
            ->view('emails.ticket-overdue')
            ->with([
                'recipient'   => $this->recipient,
                'ticket'      => $this->ticket,
                'project'     => $this->ticket->workspace,
                'daysOverdue' => $this->daysOverdue,
                'ticketUrl'   => $ticketUrl,
            ]);
    }
}
