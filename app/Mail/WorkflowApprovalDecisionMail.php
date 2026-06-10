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

class WorkflowApprovalDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly WorkflowState $state,
        public readonly User $recipient,
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $verb = $this->approved ? 'Approved' : 'Rejected';
        return new Envelope(subject: "Workflow {$verb}: {$this->ticket->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.workflow-approval-decision');
    }
}
