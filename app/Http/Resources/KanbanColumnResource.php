<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KanbanColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'project_id'        => $this->project_id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'position'          => $this->position,
            'wip_limit'         => $this->wip_limit,
            'status_key'        => $this->status_key,
            'is_backlog_column' => $this->is_backlog_column,
            'is_done_column'    => $this->is_done_column,

            'tickets' => TicketResource::collection(
                $this->whenLoaded('tickets')
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}