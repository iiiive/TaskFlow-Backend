<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'project_id'      => $this->project_id,
            'created_by'      => $this->created_by,
            'name'            => $this->name,
            'description'     => $this->description,
            'color'           => $this->color,
            'capacity_hours'  => $this->capacity_hours,
            'project'         => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name]),
            'members_count'   => $this->whenCounted('teamMembers'),
            'members'         => TeamMemberResource::collection($this->whenLoaded('teamMembers')),
            'creator'         => new UserResource($this->whenLoaded('creator')),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
