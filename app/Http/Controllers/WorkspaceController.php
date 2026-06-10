<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        $projects = $this->workspaceService->getUserWorkspaces($user);

        return response()->json([
            'message' => 'Projects retrieved successfully.',
            'data' => WorkspaceResource::collection($projects),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_key' => 'nullable|string|max:10|alpha_num|uppercase',
            'project_type' => 'nullable|in:software,it_support,marketing,hr,construction,general',
            'project_mode' => 'nullable|in:kanban,scrum',
        ]);

        $user = Auth::user();

        if (empty($validated['project_key'])) {
            $validated['project_key'] = $this->generateProjectKey($validated['name']);
        }

        $project = $this->workspaceService->createWorkspace($user, $validated);

        $project->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => new WorkspaceResource($project),
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();

        $project = Workspace::with([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ])->find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canView($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        return response()->json([
            'message' => 'Project retrieved successfully.',
            'data' => new WorkspaceResource($project),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $project = Workspace::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageWorkspace($project->owner_id, $user->id)) {
            return response()->json(['message' => 'Only the project owner can update this project.'], 403);
        }

        $validated = $request->validate([
            'name'                 => 'sometimes|required|string|max:255',
            'description'          => 'nullable|string',
            'project_key'          => 'sometimes|nullable|string|max:10|alpha_num|uppercase',
            'project_type'         => 'nullable|in:software,it_support,marketing,hr,construction,general',
            'project_mode'         => 'nullable|in:kanban,scrum',
            'auto_assign_enabled'  => 'nullable|boolean',
            'auto_assign_strategy' => 'nullable|in:round_robin,least_loaded',
        ]);

        $project = $this->workspaceService->updateWorkspace($project, $validated, $user->id);

        $project->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => new WorkspaceResource($project),
        ], 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        $project = Workspace::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageWorkspace($project->owner_id, $user->id)) {
            return response()->json(['message' => 'Only the project owner can delete this project.'], 403);
        }

        $this->workspaceService->deleteWorkspace($project, $user->id);

        return response()->json(['message' => 'Project deleted successfully.'], 200);
    }

    public function archive($id)
    {
        $user = Auth::user();
        $project = Workspace::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageWorkspace($project->owner_id, $user->id)) {
            return response()->json(['message' => 'Only the project owner can archive this project.'], 403);
        }

        $project->update(['archived_at' => now()]);

        return response()->json(['message' => 'Project archived successfully.']);
    }

    public function unarchive($id)
    {
        $user = Auth::user();
        $project = Workspace::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canManageWorkspace($project->owner_id, $user->id)) {
            return response()->json(['message' => 'Only the project owner can unarchive this project.'], 403);
        }

        $project->update(['archived_at' => null]);

        return response()->json(['message' => 'Project unarchived successfully.']);
    }

    private function generateProjectKey(string $name): string
    {
        $words = preg_split('/[\s\-_]+/', strtoupper(trim($name)));
        $key = '';

        if (count($words) === 1) {
            $key = substr($words[0], 0, 5);
        } else {
            foreach ($words as $word) {
                $key .= substr($word, 0, 1);
                if (strlen($key) >= 5) {
                    break;
                }
            }
        }

        $key = preg_replace('/[^A-Z0-9]/', '', $key);
        $key = $key ?: 'PROJ';

        return substr($key, 0, 5);
    }
}
