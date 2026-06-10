<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'project_id'  => $this->project_id,
            'created_by'  => $this->created_by,
            'name'        => $this->name,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'states'      => WorkflowStateResource::collection($this->whenLoaded('states')),
            'transitions' => WorkflowTransitionResource::collection($this->whenLoaded('transitions')),
            'creator'     => new UserResource($this->whenLoaded('creator')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
