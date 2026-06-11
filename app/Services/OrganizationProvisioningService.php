<?php

namespace App\Services;

use App\Mail\OrgAdminWelcomeMail;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrganizationProvisioningService
{
    /**
     * Create an organization together with its admin account and a timed
     * subscription window, then email the admin their credentials.
     *
     * @param array{name: string, owner_email: string, owner_name: string, subscription_plan_id: int} $data
     */
    public function provision(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

            $startsAt = now();
            $endsAt = $plan->duration_days
                ? $startsAt->copy()->addDays($plan->duration_days)
                : null;

            $organization = Organization::create([
                'name' => $data['name'],
                'owner_email' => $data['owner_email'],
                'subscription_plan_id' => $plan->id,
                'is_active' => true,
                'onboarded_at' => $startsAt,
                'subscription_starts_at' => $startsAt,
                'subscription_ends_at' => $endsAt,
            ]);

            $temporaryPassword = Str::password(12);

            $admin = User::create([
                'organization_id' => $organization->id,
                'is_org_admin' => true,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($temporaryPassword),
                'email_verified_at' => now(),
            ]);

            $organization->update(['owner_id' => $admin->id]);
            $organization->load('subscriptionPlan', 'owner');

            Mail::to($admin->email)->queue(
                new OrgAdminWelcomeMail($organization, $admin, $temporaryPassword)
            );

            return $organization;
        });
    }

    /**
     * Extend an organization's subscription by its plan duration (or an explicit
     * end date) and reactivate it.
     */
    public function renew(Organization $organization, ?string $endsAt = null): Organization
    {
        $organization->loadMissing('subscriptionPlan');

        if ($endsAt) {
            $newEnd = \Carbon\Carbon::parse($endsAt);
        } else {
            $days = $organization->subscriptionPlan?->duration_days;
            // Extend from the later of "now" or the existing end date so an
            // early renewal doesn't shorten the remaining window.
            $base = $organization->subscription_ends_at && $organization->subscription_ends_at->isFuture()
                ? $organization->subscription_ends_at
                : now();
            $newEnd = $days ? $base->copy()->addDays($days) : null;
        }

        $organization->update([
            'is_active' => true,
            'subscription_starts_at' => $organization->subscription_starts_at ?? now(),
            'subscription_ends_at' => $newEnd,
        ]);

        return $organization->load('subscriptionPlan', 'owner');
    }
}
