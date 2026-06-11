<?php

namespace Tests\Feature;

use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class SprintApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_can_create_list_and_run_sprint_lifecycle(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $created = $this->postJson("/api/v1/projects/{$project->id}/sprints", [
            'name' => 'Sprint 1',
            'goal' => 'Ship it',
        ])->assertCreated()->json('data');

        $sprintId = $created['id'];

        $this->getJson("/api/v1/projects/{$project->id}/sprints")->assertOk();

        $this->postJson("/api/v1/sprints/{$sprintId}/start")->assertOk();
        $this->assertEquals('active', Sprint::find($sprintId)->status);

        $this->postJson("/api/v1/sprints/{$sprintId}/complete")->assertOk();
        $this->assertEquals('completed', Sprint::find($sprintId)->status);
    }

    public function test_sprint_requires_name(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/sprints", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_only_one_active_sprint_allowed(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $s1 = $this->postJson("/api/v1/projects/{$project->id}/sprints", ['name' => 'S1'])->json('data.id');
        $s2 = $this->postJson("/api/v1/projects/{$project->id}/sprints", ['name' => 'S2'])->json('data.id');

        $this->postJson("/api/v1/sprints/{$s1}/start")->assertOk();
        $this->postJson("/api/v1/sprints/{$s2}/start")->assertStatus(422);
    }
}
