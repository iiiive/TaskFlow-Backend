<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowState;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly WorkflowState $toState,
        public readonly User $approver,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Approval required: {$this->ticket->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.workflow-approval-request');
    }
}
