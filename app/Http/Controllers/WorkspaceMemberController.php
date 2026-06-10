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

    public function index($projectId)
    {
        $user = Auth::user();
        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canView($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        $members = $this->memberService->getMembers($project);

        return response()->json([
            'message' => 'Project members retrieved successfully.',
            'data' => WorkspaceMemberResource::collection($members),
        ], 200);
    }

    public function store(Request $request, $projectId)
    {
        $authUser = Auth::user();
        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageMembers($project->id, $authUser->id)) {
            return response()->json(['message' => 'Only the project owner or admin can add members.'], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:' . implode(',', WorkspaceMember::ROLES),
        ]);

        $member = $this->memberService->addMember(
            $project,
            $authUser,
            $request->only(['email', 'role'])
        );

        return response()->json([
            'message' => 'Project member added successfully.',
            'data' => new WorkspaceMemberResource($member),
        ], 201);
    }

    public function update(Request $request, $projectId, $memberId)
    {
        $authUser = Auth::user();
        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageMembers($project->id, $authUser->id)) {
            return response()->json(['message' => 'Only the project owner or admin can update member roles.'], 403);
        }

        $request->validate([
            'role' => 'required|in:' . implode(',', WorkspaceMember::ROLES),
        ]);

        $member = WorkspaceMember::where('project_id', $project->id)
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Project member not found.'], 404);
        }

        $member = $this->memberService->updateRole($member, $request->role, $authUser->id);

        return response()->json([
            'message' => 'Project member role updated successfully.',
            'data' => new WorkspaceMemberResource($member),
        ], 200);
    }

    public function destroy($projectId, $memberId)
    {
        $authUser = Auth::user();
        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageMembers($project->id, $authUser->id)) {
            return response()->json(['message' => 'Only the project owner or admin can remove members.'], 403);
        }

        $member = WorkspaceMember::where('project_id', $project->id)
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Project member not found.'], 404);
        }

        $this->memberService->removeMember($member, $authUser->id);

        return response()->json(['message' => 'Project member removed successfully.'], 200);
    }
}
