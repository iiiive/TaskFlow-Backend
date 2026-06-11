<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_email' => $this->owner_email,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? url('storage/' . $this->logo_path) : null,
            'primary_color' => $this->primary_color,
            'custom_domain' => $this->custom_domain,
            'is_active' => $this->is_active,
            'onboarded_at' => $this->onboarded_at?->format('Y-m-d H:i:s'),

            'subscription_plan' => new SubscriptionPlanResource(
                $this->whenLoaded('subscriptionPlan')
            ),

            'users_count' => $this->when(
                isset($this->users_count),
                $this->users_count
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
