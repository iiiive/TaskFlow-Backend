<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizationWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization
    ) {
        $this->organization->loadMissing('subscriptionPlan');
    }

    public function build()
    {
        $plan = $this->organization->subscriptionPlan;
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');
        $loginUrl = $frontendUrl . '/login';

        return $this
            ->subject('Welcome to Planora — Your organization has been set up')
            ->view('emails.organization-welcome')
            ->with([
                'organization' => $this->organization,
                'plan' => $plan,
                'loginUrl' => $loginUrl,
                'maxProjects' => $plan?->max_projects ?? '—',
                'maxMembers' => $plan?->max_members ?? '—',
            ]);
    }
}
