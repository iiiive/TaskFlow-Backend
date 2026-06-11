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
            $member = $this->workspaceMembers
                ? $this->workspaceMembers->firstWhere('user_id', $user->id)
                : null;

            $currentUserRole = $member?->role;
        }

        $editRoles = \App\Models\WorkspaceMember::ROLES_CAN_EDIT;
        $manageRoles = \App\Models\WorkspaceMember::ROLES_CAN_MANAGE_MEMBERS;
        $manageProjectRoles = \App\Models\WorkspaceMember::ROLES_CAN_MANAGE_PROJECT;

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'project_key' => $this->project_key,
            'project_type' => $this->project_type,
            'project_mode' => $this->project_mode,
            'is_template' => (bool) $this->is_template,
            'archived_at' => $this->archived_at?->format('Y-m-d H:i:s'),
            'is_archived' => $this->isArchived(),

            'role' => $currentUserRole,
            'can_edit' => in_array($currentUserRole, $editRoles, true),
            'can_manage_members' => in_array($currentUserRole, $manageRoles, true),
            'can_manage_project' => in_array($currentUserRole, $manageProjectRoles, true),

            'owner' => new UserResource($this->whenLoaded('owner')),

            'members' => WorkspaceMemberResource::collection(
                $this->whenLoaded('workspaceMembers')
            ),

            'kanban_columns' => KanbanColumnResource::collection(
                $this->whenLoaded('kanbanColumns')
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
