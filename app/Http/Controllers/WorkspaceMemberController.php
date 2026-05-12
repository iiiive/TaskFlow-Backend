<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceMemberResource;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceMemberService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceMemberController extends Controller
{
    protected WorkspaceMemberService $memberService;
    protected WorkspacePermissionService $permissionService;

    public function __construct(
        WorkspaceMemberService $memberService,
        WorkspacePermissionService $permissionService
    ) {
        $this->memberService = $memberService;
        $this->permissionService = $permissionService;
    }

    /**
     * Show all members inside a workspace.
     * Any workspace member can view the member list.
     */
    public function index($workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this workspace.',
            ], 403);
        }

        $members = $this->memberService->getMembers($workspace);

        return response()->json([
            'message' => 'Workspace members retrieved successfully.',
            'data' => WorkspaceMemberResource::collection($members),
        ], 200);
    }

    /**
     * Add a new member.
     * Only owner can add members.
     */
    public function store(Request $request, $workspaceId)
    {
        $authUser = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canManageWorkspace($workspace->owner_id, $authUser->id)) {
            return response()->json([
                'message' => 'Only the workspace owner can add members.',
            ], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:editor,viewer',
        ]);

        $member = $this->memberService->addMember(
            $workspace,
            $authUser,
            $request->only(['email', 'role'])
        );

        return response()->json([
            'message' => 'Workspace member added successfully.',
            'data' => new WorkspaceMemberResource($member),
        ], 201);
    }

    /**
     * Update member role.
     * Only owner can update roles.
     */
    public function update(Request $request, $workspaceId, $memberId)
    {
        $authUser = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canManageWorkspace($workspace->owner_id, $authUser->id)) {
            return response()->json([
                'message' => 'Only the workspace owner can update member roles.',
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:editor,viewer',
        ]);

        $member = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Workspace member not found.',
            ], 404);
        }

        $member = $this->memberService->updateRole(
        $member,
        $request->role,
        $authUser->id
        );

        return response()->json([
            'message' => 'Workspace member role updated successfully.',
            'data' => new WorkspaceMemberResource($member),
        ], 200);
    }

    /**
     * Remove member from workspace.
     * Only owner can remove members.
     */
    public function destroy($workspaceId, $memberId)
    {
        $authUser = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canManageWorkspace($workspace->owner_id, $authUser->id)) {
            return response()->json([
                'message' => 'Only the workspace owner can remove members.',
            ], 403);
        }

        $member = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Workspace member not found.',
            ], 404);
        }

        $this->memberService->removeMember($member, $authUser->id);

        return response()->json([
            'message' => 'Workspace member removed successfully.',
        ], 200);
    }
}