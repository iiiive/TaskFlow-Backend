<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_can_build_and_activate_a_workflow(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $wf = $this->postJson("/api/v1/projects/{$project->id}/workflows", ['name' => 'Flow'])
            ->assertCreated()->json('data.id');

        $open = $this->postJson("/api/v1/workflows/{$wf}/states", [
            'name' => 'Open', 'is_initial' => true,
        ])->assertCreated()->json('data.id');

        $done = $this->postJson("/api/v1/workflows/{$wf}/states", [
            'name' => 'Done', 'is_final' => true, 'required_fields' => ['assigned_to'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/workflows/{$wf}/transitions", [
            'from_state_id' => $open, 'to_state_id' => $done,
        ])->assertCreated();

        $this->postJson("/api/v1/workflows/{$wf}/activate")->assertOk();
        $this->assertDatabaseHas('workflow_templates', ['id' => $wf, 'is_active' => true]);
    }

    public function test_workflow_requires_name(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/workflows", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_cannot_activate_without_initial_state(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $wf = $this->postJson("/api/v1/projects/{$project->id}/workflows", ['name' => 'Flow'])->json('data.id');
        $this->postJson("/api/v1/workflows/{$wf}/states", ['name' => 'Open'])->assertCreated();

        $this->postJson("/api/v1/workflows/{$wf}/activate")->assertStatus(422);
    }

    public function test_required_fields_must_be_whitelisted(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $wf = $this->postJson("/api/v1/projects/{$project->id}/workflows", ['name' => 'Flow'])->json('data.id');

        $this->postJson("/api/v1/workflows/{$wf}/states", [
            'name' => 'X', 'required_fields' => ['not_a_field'],
        ])->assertStatus(422);
    }
}
