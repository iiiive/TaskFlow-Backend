<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamMemberAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Team $team,
        public readonly User $user,
        public readonly string $role,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You've been added to team: {$this->team->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-member-added');
    }
}
