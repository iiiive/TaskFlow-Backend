<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTimeLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->project_id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'hours' => (float) $this->hours,
            'description' => $this->description,
            'work_date' => $this->work_date?->format('Y-m-d'),

            'user' => new UserResource($this->whenLoaded('user')),
            'ticket' => new TicketResource($this->whenLoaded('ticket')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}