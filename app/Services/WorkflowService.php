<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Mail\WorkflowApprovalRequestMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WorkflowService
{
    public function createWorkflow(Workspace $project, array $data, int $userId): WorkflowTemplate
    {
        return WorkflowTemplate::create([
            'project_id'  => $project->id,
            'created_by'  => $userId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => false,
        ]);
    }

    public function addState(WorkflowTemplate $workflow, array $data): WorkflowState
    {
        $maxPosition = $workflow->states()->max('position') ?? 0;

        return WorkflowState::create([
            'workflow_template_id' => $workflow->id,
            'name'                 => $data['name'],
            'color'                => $data['color'] ?? '#547A95',
            'position'             => $data['position'] ?? ($maxPosition + 1),
            'is_initial'           => $data['is_initial'] ?? false,
            'is_final'             => $data['is_final'] ?? false,
            'requires_approval'    => $data['requires_approval'] ?? false,
        ]);
    }

    public function addTransition(WorkflowTemplate $workflow, int $fromStateId, int $toStateId, ?string $name = null): WorkflowTransition
    {
        return WorkflowTransition::create([
            'workflow_template_id' => $workflow->id,
            'from_state_id'        => $fromStateId,
            'to_state_id'          => $toStateId,
            'name'                 => $name,
        ]);
    }

    public function activateWorkflow(WorkflowTemplate $workflow): WorkflowTemplate
    {
        DB::transaction(function () use ($workflow) {
            // Deactivate all other workflows in the same project
            WorkflowTemplate::where('project_id', $workflow->project_id)
                ->where('id', '!=', $workflow->id)
                ->update(['is_active' => false]);

            $workflow->update(['is_active' => true]);
        });

        return $workflow->fresh(['states', 'transitions']);
    }

    /**
     * Validates and moves a ticket to a new workflow state.
     * Returns the updated ticket, or throws a ValidationException if the transition is blocked.
     */
    public function transitionTicket(Ticket $ticket, int $toStateId): Ticket
    {
        $project = $ticket->workspace;
        $activeWorkflow = WorkflowTemplate::where('project_id', $ticket->project_id)
            ->where('is_active', true)
            ->with(['states', 'transitions'])
            ->first();

        if (!$activeWorkflow) {
            // No active workflow — allow free movement
            $ticket->update(['workflow_state_id' => $toStateId]);
            return $ticket->fresh();
        }

        $fromStateId = $ticket->workflow_state_id;

        // If ticket has no state yet, it must move to the initial state
        if (!$fromStateId) {
            $initial = $activeWorkflow->initialState();
            if ($initial && $initial->id !== $toStateId) {
                throw new \InvalidArgumentException("Ticket must first enter the initial workflow state: {$initial->name}");
            }

            $ticket->update(['workflow_state_id' => $toStateId]);
            return $ticket->fresh(['workflowState']);
        }

        if (!$activeWorkflow->canTransition($fromStateId, $toStateId)) {
            $fromState = WorkflowState::find($fromStateId);
            $toState   = WorkflowState::find($toStateId);
            throw new \InvalidArgumentException(
                "Transition from '{$fromState?->name}' to '{$toState?->name}' is not allowed by the active workflow."
            );
        }

        $toState = WorkflowState::find($toStateId);

        if ($toState?->requires_approval) {
            // Notify project members who can approve
            $project = $ticket->workspace()->with('workspaceMembers.user')->first();
            foreach ($project->workspaceMembers as $member) {
                if (in_array($member->role, ['owner', 'admin', 'project_manager']) && $member->user?->email) {
                    Mail::to($member->user->email)->queue(
                        new WorkflowApprovalRequestMail($ticket, $toState, $member->user)
                    );
                }
            }
        }

        $ticket->update(['workflow_state_id' => $toStateId]);
        return $ticket->fresh(['workflowState']);
    }
}
