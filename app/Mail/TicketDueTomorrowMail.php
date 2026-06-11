<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketDueTomorrowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Ticket $ticket
    ) {
        $this->ticket->loadMissing(['workspace', 'assignee', 'reporter']);
    }

    public function build()
    {
        $ticketUrl = rtrim((string) env('FRONTEND_URL', ''), '/')
            . '/projects/' . $this->ticket->project_id . '/board';

        return $this
            ->subject("[{$this->ticket->issue_number}] Due tomorrow: {$this->ticket->title}")
            ->view('emails.ticket-due-tomorrow')
            ->with([
                'recipient' => $this->recipient,
                'ticket'    => $this->ticket,
                'project'   => $this->ticket->workspace,
                'ticketUrl' => $ticketUrl,
            ]);
    }
}
