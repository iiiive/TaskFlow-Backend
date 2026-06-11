<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlanChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public SubscriptionPlan $newPlan,
        public ?SubscriptionPlan $oldPlan = null
    ) {}

    public function build()
    {
        return $this
            ->subject('Your Planora subscription plan has been updated')
            ->view('emails.plan-changed')
            ->with([
                'organization' => $this->organization,
                'newPlan' => $this->newPlan,
                'oldPlan' => $this->oldPlan,
                'loginUrl' => rtrim((string) env('FRONTEND_URL', ''), '/') . '/login',
            ]);
    }
}
