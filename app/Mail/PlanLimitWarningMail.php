<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlanLimitWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public int $usagePercent
    ) {
        $this->organization->loadMissing('subscriptionPlan');
    }

    public function build()
    {
        $plan = $this->organization->subscriptionPlan;
        $frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');

        return $this
            ->subject('Planora — Your organization is nearing its plan limit')
            ->view('emails.plan-limit-warning')
            ->with([
                'organization' => $this->organization,
                'plan' => $plan,
                'usagePercent' => $this->usagePercent,
                'contactUrl' => $frontendUrl,
            ]);
    }
}
