<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectRemovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $removedUser,
        public Workspace $project
    ) {}

    public function build()
    {
        return $this
            ->subject("You've been removed from {$this->project->name} on Planora")
            ->view('emails.project-removed')
            ->with([
                'user' => $this->removedUser,
                'project' => $this->project,
                'loginUrl' => rtrim((string) env('FRONTEND_URL', ''), '/') . '/login',
            ]);
    }
}
