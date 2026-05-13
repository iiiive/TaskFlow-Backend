<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $currentUserRole = null;

        if ($user) {
            if ((int) $this->owner_id === (int) $user->id) {
                $currentUserRole = 'owner';
            } else {
                $member = $this->workspaceMembers
                    ? $this->workspaceMembers->firstWhere('user_id', $user->id)
                    : null;

                $currentUserRole = $member?->role;
            }
        }

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'description' => $this->description,

            // This is what Angular needs.
            'role' => $currentUserRole,

            // Optional but useful for frontend permission checks.
            'can_edit' => in_array($currentUserRole, ['owner', 'editor'], true),
            'can_manage_members' => $currentUserRole === 'owner',

            'owner' => new UserResource($this->whenLoaded('owner')),

            'members' => WorkspaceMemberResource::collection(
                $this->whenLoaded('workspaceMembers')
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}