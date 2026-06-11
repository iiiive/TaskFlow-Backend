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
            'workspace_id' => $this->project_id,
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
                'owner' => 'Owner',
                'admin' => 'Admin',
                'project_manager' => 'Project Manager',
                'team_lead' => 'Team Lead',
                'developer' => 'Developer',
                'tester' => 'Tester',
                'viewer' => 'Viewer / Read Only',
                'client' => 'Client',
                // Legacy value retained for any pre-migration rows.
                'editor' => 'User',
                default => ucfirst(str_replace('_', ' ', (string) $this->role)),
            },

            'user' => new UserResource($this->whenLoaded('user')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}