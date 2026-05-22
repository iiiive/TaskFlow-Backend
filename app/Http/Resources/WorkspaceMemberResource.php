<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,

            /*
            |--------------------------------------------------------------------------
            | Backend role value
            |--------------------------------------------------------------------------
            | Keep these values for permissions:
            | owner, editor, viewer
            */
            'role' => $this->role,

            /*
            |--------------------------------------------------------------------------
            | Frontend display label
            |--------------------------------------------------------------------------
            | Use this in Angular so we do not need to change the database values.
            */
            'role_label' => match ($this->role) {
                'owner' => 'Project Manager',
                'editor' => 'User',
                'viewer' => 'Viewer / Read Only',
                default => ucfirst($this->role),
            },

            'user' => new UserResource($this->whenLoaded('user')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}