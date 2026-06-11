<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Label;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class LabelEpicApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    // --- Labels ---

    public function test_can_create_and_list_labels(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/labels", ['name' => 'Urgent', 'color' => '#ff0000'])
            ->assertCreated();

        $this->getJson("/api/v1/projects/{$project->id}/labels")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Urgent']);
    }

    public function test_can_update_and_delete_label(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $label = Label::create(['project_id' => $project->id, 'name' => 'Old', 'color' => '#000']);

        $this->putJson("/api/v1/labels/{$label->id}", ['name' => 'New', 'color' => '#111'])
            ->assertOk();
        $this->assertDatabaseHas('labels', ['id' => $label->id, 'name' => 'New']);

        $this->deleteJson("/api/v1/labels/{$label->id}")->assertOk();
        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
    }

    public function test_label_creation_requires_name(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/labels", ['name' => ''])
            ->assertStatus(422);
    }

    // --- Epics ---

    public function test_can_create_and_list_epics(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/epics", ['name' => 'Epic A', 'color' => '#123456'])
            ->assertCreated();

        $this->getJson("/api/v1/projects/{$project->id}/epics")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Epic A']);
    }

    public function test_can_update_and_delete_epic(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $epic = Epic::create(['project_id' => $project->id, 'created_by' => $user->id, 'name' => 'E1']);

        $this->putJson("/api/v1/epics/{$epic->id}", ['name' => 'E1-renamed'])->assertOk();
        $this->deleteJson("/api/v1/epics/{$epic->id}")->assertOk();
        $this->assertDatabaseMissing('epics', ['id' => $epic->id]);
    }
}
