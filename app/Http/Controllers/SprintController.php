<?php

namespace App\Http\Controllers;

use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use App\Models\Workspace;
use App\Services\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function __construct(private SprintService $sprintService) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $sprints = Sprint::where('project_id', $projectId)
            ->withCount('tickets')
            ->with('creator')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => SprintResource::collection($sprints)]);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Workspace::findOrFail($projectId);

        $validated = $request->validate([
            'name'       => 'required|string|max:150',
            'goal'       => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $sprint = $this->sprintService->createSprint($project, $validated, $request->user()->id);

        return response()->json(['data' => new SprintResource($sprint)], 201);
    }

    public function show(Sprint $sprint): JsonResponse
    {
        $sprint->load(['tickets', 'creator']);

        return response()->json(['data' => new SprintResource($sprint)]);
    }

    public function update(Request $request, Sprint $sprint): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|required|string|max:150',
            'goal'       => 'nullable|string|max:500',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $sprint->update($validated);

        return response()->json(['data' => new SprintResource($sprint)]);
    }

    public function destroy(Sprint $sprint): JsonResponse
    {
        $sprint->tickets()->update(['sprint_id' => null]);
        $sprint->delete();

        return response()->json(['message' => 'Sprint deleted.']);
    }

    public function start(Sprint $sprint): JsonResponse
    {
        if ($sprint->isActive()) {
            return response()->json(['message' => 'Sprint is already active.'], 422);
        }

        if ($sprint->isCompleted()) {
            return response()->json(['message' => 'Completed sprints cannot be restarted.'], 422);
        }

        $activeExists = Sprint::where('project_id', $sprint->project_id)
            ->where('status', 'active')
            ->exists();

        if ($activeExists) {
            return response()->json(['message' => 'Another sprint is already active. Complete it first.'], 422);
        }

        $sprint = $this->sprintService->startSprint($sprint);

        return response()->json(['data' => new SprintResource($sprint)]);
    }

    public function complete(Sprint $sprint): JsonResponse
    {
        if (!$sprint->isActive()) {
            return response()->json(['message' => 'Only active sprints can be completed.'], 422);
        }

        $sprint = $this->sprintService->completeSprint($sprint);

        return response()->json(['data' => new SprintResource($sprint)]);
    }
}
