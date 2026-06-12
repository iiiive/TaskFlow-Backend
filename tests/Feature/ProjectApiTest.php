<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/projects')->assertUnauthorized();
    }

    public function test_user_can_list_their_projects(): void
    {
        $user = $this->actingAsUser();
        $this->makeProject($user, ['name' => 'Alpha']);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Alpha']);
    }

    public function test_org_admin_can_create_a_project(): void
    {
        $this->actingAsOrgAdmin();

        $this->postJson('/api/v1/projects', [
            'name'         => 'My Project',
            'project_type' => 'it_support',
            'project_mode' => 'scrum',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Project')
            ->assertJsonPath('data.project_type', 'it_support');

        $this->assertDatabaseHas('projects', ['name' => 'My Project', 'project_type' => 'it_support']);
    }

    public function test_workspace_member_cannot_create_a_project(): void
    {
        // Project creation is org-admin-only; regular members cannot create.
        $this->actingAsUser();

        $this->postJson('/api/v1/projects', ['name' => 'Sneaky Project'])
            ->assertForbidden();
    }

    public function test_create_project_validates_input(): void
    {
        $this->actingAsOrgAdmin();

        $this->postJson('/api/v1/projects', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_create_project_rejects_invalid_type(): void
    {
        $this->actingAsOrgAdmin();

        $this->postJson('/api/v1/projects', ['name' => 'X', 'project_type' => 'not_a_type'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_type');
    }

    public function test_owner_can_update_project(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_non_owner_cannot_update_project(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);

        $intruder = $this->actingAsUser();
        $this->addMember($project, $intruder, 'developer');

        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_show_returns_404_for_missing_project(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/v1/projects/999999')->assertNotFound();
    }

    public function test_owner_can_archive_and_unarchive(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/archive")->assertOk();
        $this->assertNotNull($project->fresh()->archived_at);

        $this->postJson("/api/v1/projects/{$project->id}/unarchive")->assertOk();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_owner_can_clone_project(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/clone", ['name' => 'Clone X'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Clone X');

        $this->assertDatabaseHas('projects', ['name' => 'Clone X', 'owner_id' => $user->id]);
    }

    public function test_save_as_template_and_create_from_template(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/save-as-template", ['name' => 'Tmpl'])
            ->assertCreated();

        $template = Workspace::where('is_template', true)->firstOrFail();

        $this->getJson('/api/v1/projects/templates')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tmpl']);

        $this->postJson("/api/v1/projects/from-template/{$template->id}", ['name' => 'From Tmpl'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'From Tmpl');
    }

    public function test_org_admin_can_delete_project(): void
    {
        $admin = $this->actingAsOrgAdmin();
        $project = $this->makeProject($admin);

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_project_manager_cannot_delete_project(): void
    {
        // Deleting a whole project is org-admin-only; even a project manager cannot.
        $user = $this->actingAsUser();
        $project = $this->makeProject($user); // creator is a project_manager

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
