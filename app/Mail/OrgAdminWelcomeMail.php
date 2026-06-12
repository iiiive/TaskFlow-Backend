<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrgAdminWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public User $admin,
        public string $temporaryPassword
    ) {
        $this->organization->loadMissing('subscriptionPlan');
    }

    public function build()
    {
        $plan = $this->organization->subscriptionPlan;
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');

        return $this
            ->subject('Your Planora organization is ready — admin access inside')
            ->view('emails.org-admin-welcome')
            ->with([
                'organization' => $this->organization,
                'admin' => $this->admin,
                'email' => $this->admin->email,
                'password' => $this->temporaryPassword,
                'loginUrl' => $frontendUrl . '/login',
                'plan' => $plan,
                'maxProjects' => $plan?->max_projects ?? '—',
                'maxMembers' => $plan?->max_members ?? '—',
                'expiresAt' => $this->organization->subscription_ends_at?->format('F j, Y'),
            ]);
    }
}
