<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabelResource;
use App\Models\Label;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LabelController extends Controller
{
    public function __construct(
        protected WorkspacePermissionService $permissionService
    ) {}

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

        $labels = Label::where('project_id', $project->id)->orderBy('name')->get();

        return response()->json([
            'message' => 'Labels retrieved successfully.',
            'data' => LabelResource::collection($labels),
        ]);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();
        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to create labels.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $exists = Label::where('project_id', $project->id)
            ->whereRaw('name ILIKE ?', [$validated['name']])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'A label with this name already exists.'], 422);
        }

        $label = Label::create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return response()->json([
            'message' => 'Label created successfully.',
            'data' => new LabelResource($label),
        ], 201);
    }

    public function update(Request $request, $labelId)
    {
        $user = Auth::user();
        $label = Label::find($labelId);

        if (!$label) {
            return response()->json(['message' => 'Label not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($label->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to update this label.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $label->update($validated);

        return response()->json([
            'message' => 'Label updated successfully.',
            'data' => new LabelResource($label),
        ]);
    }

    public function destroy($labelId)
    {
        $user = Auth::user();
        $label = Label::find($labelId);

        if (!$label) {
            return response()->json(['message' => 'Label not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($label->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to delete this label.'], 403);
        }

        $label->delete();

        return response()->json(['message' => 'Label deleted successfully.']);
    }
}
