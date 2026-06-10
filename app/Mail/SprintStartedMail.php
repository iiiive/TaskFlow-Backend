<?php

namespace App\Mail;

use App\Models\Sprint;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SprintStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Sprint $sprint,
        public readonly Workspace $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Sprint started: {$this->sprint->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sprint-started');
    }
}
