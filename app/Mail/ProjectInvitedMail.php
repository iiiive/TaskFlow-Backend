<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectInvitedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitedUser,
        public Workspace $project,
        public string $role
    ) {}

    public function build()
    {
        $projectUrl = rtrim((string) env('FRONTEND_URL', ''), '/') . '/projects/' . $this->project->id . '/board';

        return $this
            ->subject("You've been added to {$this->project->name} on Planora")
            ->view('emails.project-invited')
            ->with([
                'user' => $this->invitedUser,
                'project' => $this->project,
                'role' => $this->role,
                'projectUrl' => $projectUrl,
            ]);
    }
}
