<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->project_id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,

            'action' => $this->action,
            'description' => $this->description,

            'user' => new UserResource($this->whenLoaded('user')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}