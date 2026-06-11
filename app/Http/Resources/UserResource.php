<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avatarUrl = null;

        if ($this->avatar) {
            if (
                str_starts_with($this->avatar, 'http://') ||
                str_starts_with($this->avatar, 'https://')
            ) {
                $avatarUrl = $this->avatar;
            } else {
                $avatarUrl = url('storage/' . $this->avatar);
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'google_id' => $this->google_id,
            'has_password' => !empty($this->password),
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->format('Y-m-d H:i:s'),

            'avatar' => $this->avatar,
            'avatar_url' => $avatarUrl,
            'timezone' => $this->timezone,
            'preferences' => $this->preferences ?? [],
            'is_super_admin' => (bool) $this->is_super_admin,
            'is_org_admin' => (bool) $this->is_org_admin,
            'organization_id' => $this->organization_id,
            'organization' => $this->when(
                $this->relationLoaded('organization') && $this->organization,
                fn () => [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                    'subscription_ends_at' => $this->organization->subscription_ends_at?->format('Y-m-d H:i:s'),
                    'is_expired' => $this->organization->isSubscriptionExpired(),
                ]
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}