<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkflowTemplateResource;
use App\Http\Resources\WorkflowStateResource;
use App\Http\Resources\WorkflowTransitionResource;
use App\Models\Workspace;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    public function index(int $projectId): JsonResponse
    {
        $workflows = WorkflowTemplate::where('project_id', $projectId)
            ->with(['states', 'creator'])
            ->withCount(['states', 'transitions'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => WorkflowTemplateResource::collection($workflows)]);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Workspace::findOrFail($projectId);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $workflow = $this->workflowService->createWorkflow($project, $validated, $request->user()->id);

        return response()->json(['data' => new WorkflowTemplateResource($workflow)], 201);
    }

    public function show(WorkflowTemplate $workflow): JsonResponse
    {
        $workflow->load(['states', 'transitions.fromState', 'transitions.toState', 'creator']);

        return response()->json(['data' => new WorkflowTemplateResource($workflow)]);
    }

    public function update(Request $request, WorkflowTemplate $workflow): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $workflow->update($validated);

        return response()->json(['data' => new WorkflowTemplateResource($workflow->load('states'))]);
    }

    public function destroy(WorkflowTemplate $workflow): JsonResponse
    {
        if ($workflow->is_active) {
            return response()->json(['message' => 'Deactivate the workflow before deleting it.'], 422);
        }

        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted.']);
    }

    public function addState(Request $request, WorkflowTemplate $workflow): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:80',
            'color'             => 'nullable|string|max:20',
            'position'          => 'nullable|integer|min:1',
            'is_initial'        => 'nullable|boolean',
            'is_final'          => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'required_fields'   => 'nullable|array',
            'required_fields.*' => 'string|in:' . implode(',', array_keys(config('workflow.required_field_options'))),
        ]);

        if (!empty($validated['is_initial'])) {
            $workflow->states()->where('is_initial', true)->update(['is_initial' => false]);
        }

        $state = $this->workflowService->addState($workflow, $validated);

        return response()->json(['data' => new WorkflowStateResource($state)], 201);
    }

    public function updateState(Request $request, WorkflowTemplate $workflow, WorkflowState $state): JsonResponse
    {
        abort_if($state->workflow_template_id !== $workflow->id, 404);

        $validated = $request->validate([
            'name'              => 'sometimes|required|string|max:80',
            'color'             => 'nullable|string|max:20',
            'position'          => 'nullable|integer|min:1',
            'is_initial'        => 'nullable|boolean',
            'is_final'          => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'required_fields'   => 'nullable|array',
            'required_fields.*' => 'string|in:' . implode(',', array_keys(config('workflow.required_field_options'))),
        ]);

        if (!empty($validated['is_initial'])) {
            $workflow->states()->where('id', '!=', $state->id)->update(['is_initial' => false]);
        }

        $state->update($validated);

        return response()->json(['data' => new WorkflowStateResource($state)]);
    }

    public function removeState(WorkflowTemplate $workflow, WorkflowState $state): JsonResponse
    {
        abort_if($state->workflow_template_id !== $workflow->id, 404);

        if ($state->tickets()->exists()) {
            return response()->json(['message' => 'Cannot delete a state that has tickets assigned to it.'], 422);
        }

        $state->delete();

        return response()->json(['message' => 'State removed.']);
    }

    public function addTransition(Request $request, WorkflowTemplate $workflow): JsonResponse
    {
        $validated = $request->validate([
            'from_state_id' => 'required|integer|exists:workflow_states,id',
            'to_state_id'   => 'required|integer|exists:workflow_states,id|different:from_state_id',
            'name'          => 'nullable|string|max:80',
        ]);

        if (WorkflowTransition::where('workflow_template_id', $workflow->id)
            ->where('from_state_id', $validated['from_state_id'])
            ->where('to_state_id', $validated['to_state_id'])
            ->exists()) {
            return response()->json(['message' => 'This transition already exists.'], 422);
        }

        $transition = $this->workflowService->addTransition(
            $workflow,
            $validated['from_state_id'],
            $validated['to_state_id'],
            $validated['name'] ?? null
        );

        $transition->load(['fromState', 'toState']);

        return response()->json(['data' => new WorkflowTransitionResource($transition)], 201);
    }

    public function removeTransition(WorkflowTemplate $workflow, WorkflowTransition $transition): JsonResponse
    {
        abort_if($transition->workflow_template_id !== $workflow->id, 404);

        $transition->delete();

        return response()->json(['message' => 'Transition removed.']);
    }

    public function activate(WorkflowTemplate $workflow): JsonResponse
    {
        if (!$workflow->states()->where('is_initial', true)->exists()) {
            return response()->json(['message' => 'Workflow must have at least one initial state before activation.'], 422);
        }

        $workflow = $this->workflowService->activateWorkflow($workflow);

        return response()->json(['data' => new WorkflowTemplateResource($workflow)]);
    }
}
