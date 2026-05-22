<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    protected WorkspaceService $workspaceService;
    protected WorkspacePermissionService $permissionService;

    public function __construct(
        WorkspaceService $workspaceService,
        WorkspacePermissionService $permissionService
    ) {
        $this->workspaceService = $workspaceService;
        $this->permissionService = $permissionService;
    }

    public function index()
    {
        $user = Auth::user();

        $workspaces = $this->workspaceService->getUserWorkspaces($user);

        return response()->json([
            'message' => 'Workspaces retrieved successfully.',
            'data' => WorkspaceResource::collection($workspaces),
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workspace = $this->workspaceService->createWorkspace(
            Auth::user(),
            $request->only(['name', 'description'])
        );

        /*
        |--------------------------------------------------------------------------
        | Create default Kanban columns
        |--------------------------------------------------------------------------
        | Every new workspace starts with:
        | Backlog → Ready for Development → Dev In Progress → Ready for Testing
        | → Ready for UAT → Done
        */
        $workspace->createDefaultKanbanColumns();

        $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);

        return response()->json([
            'message' => 'Workspace created successfully.',
            'data' => new WorkspaceResource($workspace),
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();

        $workspace = Workspace::with([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ])->find($id);

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

        return response()->json([
            'message' => 'Workspace retrieved successfully.',
            'data' => new WorkspaceResource($workspace),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $workspace = Workspace::find($id);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canManageWorkspace($workspace->owner_id, $user->id)) {
            return response()->json([
                'message' => 'Only the workspace owner can update this workspace.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workspace = $this->workspaceService->updateWorkspace(
            $workspace,
            $request->only(['name', 'description']),
            $user->id
        );

        $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);

        return response()->json([
            'message' => 'Workspace updated successfully.',
            'data' => new WorkspaceResource($workspace),
        ], 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        $workspace = Workspace::find($id);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canManageWorkspace($workspace->owner_id, $user->id)) {
            return response()->json([
                'message' => 'Only the workspace owner can delete this workspace.',
            ], 403);
        }

        $this->workspaceService->deleteWorkspace($workspace, $user->id);

        return response()->json([
            'message' => 'Workspace deleted successfully.',
        ], 200);
    }
}