<?php

namespace Tests\Feature;

use App\Models\KanbanColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class KanbanColumnApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_member_can_list_columns(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->getJson("/api/v1/projects/{$project->id}/kanban-columns")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Backlog']);
    }

    public function test_editor_can_create_column(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/kanban-columns", ['name' => 'QA'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'QA');
    }

    public function test_create_column_requires_name(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/kanban-columns", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_can_set_wip_limit(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $column = $project->kanbanColumns()->where('is_backlog_column', false)->where('is_done_column', false)->first();

        $this->putJson("/api/v1/kanban-columns/{$column->id}", ['wip_limit' => 3])
            ->assertOk()
            ->assertJsonPath('data.wip_limit', 3);
    }

    public function test_can_reorder_columns(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $columns = $project->kanbanColumns()->orderBy('position')->get();
        $payload = $columns->reverse()->values()->map(fn ($c, $i) => ['id' => $c->id, 'position' => $i + 1])->all();

        $this->putJson("/api/v1/projects/{$project->id}/kanban-columns/reorder", ['columns' => $payload])
            ->assertOk();
    }

    public function test_cannot_delete_backlog_column(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $backlog = $project->kanbanColumns()->where('is_backlog_column', true)->first();

        $this->deleteJson("/api/v1/kanban-columns/{$backlog->id}")->assertStatus(422);
    }

    public function test_viewer_cannot_create_column(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);
        $viewer = $this->actingAsUser();
        $this->addMember($project, $viewer, 'viewer');

        $this->postJson("/api/v1/projects/{$project->id}/kanban-columns", ['name' => 'X'])
            ->assertForbidden();
    }
}
