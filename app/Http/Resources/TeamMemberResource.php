<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'team_id'   => $this->team_id,
            'user_id'   => $this->user_id,
            'role'      => $this->role,
            'weekly_capacity_hours' => $this->weekly_capacity_hours,
            'joined_at' => $this->joined_at?->toISOString(),
            'user'      => new UserResource($this->whenLoaded('user')),
        ];
    }
}
