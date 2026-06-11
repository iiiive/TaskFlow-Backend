<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->project_id,
            'created_by' => $this->created_by,

            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,

            'creator' => new UserResource($this->whenLoaded('creator')),

            'tickets_count' => $this->whenCounted('tickets'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}