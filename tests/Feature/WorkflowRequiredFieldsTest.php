<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowState;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProjectWithWorkflow(): array
    {
        $user = User::factory()->create();

        $project = Workspace::create([
            'owner_id' => $user->id,
            'name'     => 'WF Project',
        ]);

        WorkspaceMember::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'role'       => 'owner',
        ]);

        $workflow = WorkflowTemplate::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name'       => 'Default',
            'is_active'  => true,
        ]);

        $open = WorkflowState::create([
            'workflow_template_id' => $workflow->id,
            'name'                 => 'Open',
            'position'             => 1,
            'is_initial'           => true,
        ]);

        $inProgress = WorkflowState::create([
            'workflow_template_id' => $workflow->id,
            'name'                 => 'In Progress',
            'position'             => 2,
            'required_fields'      => ['assigned_to'],
        ]);

        WorkflowTransition::create([
            'workflow_template_id' => $workflow->id,
            'from_state_id'        => $open->id,
            'to_state_id'          => $inProgress->id,
        ]);

        return compact('user', 'project', 'workflow', 'open', 'inProgress');
    }

    public function test_transition_is_blocked_when_required_field_missing(): void
    {
        ['user' => $user, 'project' => $project, 'open' => $open, 'inProgress' => $inProgress] = $this->makeProjectWithWorkflow();

        $ticket = Ticket::create([
            'project_id'        => $project->id,
            'created_by'        => $user->id,
            'title'             => 'Needs assignee',
            'status'            => 'todo',
            'priority'          => 'medium',
            'workflow_state_id' => $open->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(WorkflowService::class)->transitionTicket($ticket, $inProgress->id);
    }

    public function test_transition_succeeds_when_required_field_present(): void
    {
        ['user' => $user, 'project' => $project, 'open' => $open, 'inProgress' => $inProgress] = $this->makeProjectWithWorkflow();

        $ticket = Ticket::create([
            'project_id'        => $project->id,
            'created_by'        => $user->id,
            'assigned_to'       => $user->id,
            'title'             => 'Has assignee',
            'status'            => 'todo',
            'priority'          => 'medium',
            'workflow_state_id' => $open->id,
        ]);

        $updated = app(WorkflowService::class)->transitionTicket($ticket, $inProgress->id);

        $this->assertEquals($inProgress->id, $updated->workflow_state_id);
    }
}
