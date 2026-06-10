<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpicResource;
use App\Models\Epic;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EpicController extends Controller
{
    protected WorkspacePermissionService $permissionService;

    public function __construct(WorkspacePermissionService $permissionService)
    {
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

        $epics = Epic::with('creator:id,name,email')
            ->withCount('tickets')
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Epics retrieved successfully.',
            'data' => EpicResource::collection($epics),
        ], 200);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();

        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to create epics.'], 403);
        }

        $request->merge([
            'name' => is_string($request->name) ? trim($request->name) : $request->name,
            'description' => is_string($request->description) ? trim($request->description) : $request->description,
            'color' => is_string($request->color) ? trim($request->color) : $request->color,
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('epics', 'name')->where(function ($query) use ($project) {
                    return $query->where('project_id', $project->id);
                }),
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
        ]);

        $epic = Epic::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        $epic->load('creator:id,name,email');

        return response()->json([
            'message' => 'Epic created successfully.',
            'data' => new EpicResource($epic),
        ], 201);
    }

    public function show($epicId)
    {
        $user = Auth::user();

        $epic = Epic::with([
            'creator:id,name,email',
            'tickets.creator:id,name,email',
            'tickets.assignee:id,name,email',
            'tickets.kanbanColumn',
        ])->find($epicId);

        if (!$epic) {
            return response()->json(['message' => 'Epic not found.'], 404);
        }

        if (!$this->permissionService->canView($epic->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this epic.'], 403);
        }

        return response()->json([
            'message' => 'Epic retrieved successfully.',
            'data' => new EpicResource($epic),
        ], 200);
    }

    public function update(Request $request, $epicId)
    {
        $user = Auth::user();

        $epic = Epic::find($epicId);

        if (!$epic) {
            return response()->json(['message' => 'Epic not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($epic->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to update epics.'], 403);
        }

        $request->merge([
            'name' => is_string($request->name) ? trim($request->name) : $request->name,
            'description' => is_string($request->description) ? trim($request->description) : $request->description,
            'color' => is_string($request->color) ? trim($request->color) : $request->color,
        ]);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('epics', 'name')
                    ->ignore($epic->id)
                    ->where(function ($query) use ($epic) {
                        return $query->where('project_id', $epic->project_id);
                    }),
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
        ]);

        $epic->update($validated);
        $epic->load('creator:id,name,email');
        $epic->loadCount('tickets');

        return response()->json([
            'message' => 'Epic updated successfully.',
            'data' => new EpicResource($epic),
        ], 200);
    }

    public function destroy($epicId)
    {
        $user = Auth::user();

        $epic = Epic::find($epicId);

        if (!$epic) {
            return response()->json(['message' => 'Epic not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($epic->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to delete epics.'], 403);
        }

        $epic->delete();

        return response()->json(['message' => 'Epic deleted successfully.'], 200);
    }
}
