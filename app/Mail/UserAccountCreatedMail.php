<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{name: string, role: string}> $projects
     */
    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public Organization $organization,
        public array $projects = []
    ) {
    }

    public function build()
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');

        return $this
            ->subject("You've been added to {$this->organization->name} on Planora")
            ->view('emails.user-account-created')
            ->with([
                'user' => $this->user,
                'email' => $this->user->email,
                'password' => $this->temporaryPassword,
                'organizationName' => $this->organization->name,
                'projects' => $this->projects,
                'loginUrl' => $frontendUrl . '/login',
            ]);
    }
}
