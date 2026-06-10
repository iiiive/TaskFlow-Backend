<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'project_id'              => $this->project_id,
            'created_by'              => $this->created_by,
            'name'                    => $this->name,
            'goal'                    => $this->goal,
            'status'                  => $this->status,
            'start_date'              => $this->start_date?->toDateString(),
            'end_date'                => $this->end_date?->toDateString(),
            'completed_at'            => $this->completed_at?->toISOString(),
            'tickets_count'           => $this->whenCounted('tickets'),
            'total_story_points'      => $this->when($this->relationLoaded('tickets'), fn() => $this->totalStoryPoints()),
            'completed_story_points'  => $this->when($this->relationLoaded('tickets'), fn() => $this->completedStoryPoints()),
            'creator'                 => new UserResource($this->whenLoaded('creator')),
            'tickets'                 => TicketResource::collection($this->whenLoaded('tickets')),
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
