<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_member_can_list_project_tickets(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $this->makeTicket($project, ['title' => 'Ticket One']);

        $this->getJson("/api/v1/projects/{$project->id}/tickets")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Ticket One']);
    }

    public function test_editor_can_create_ticket_with_type_and_labels(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $label = \App\Models\Label::create(['project_id' => $project->id, 'name' => 'Bug', 'color' => '#f00']);

        $this->postJson("/api/v1/projects/{$project->id}/tickets", [
            'title'      => 'New ticket',
            'issue_type' => 'feature_request',
            'priority'   => 'urgent',
            'label_ids'  => [$label->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New ticket')
            ->assertJsonPath('data.issue_type', 'feature_request');

        $this->assertDatabaseHas('tickets', ['title' => 'New ticket', 'issue_type' => 'feature_request']);
    }

    public function test_create_ticket_requires_title(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/tickets", ['title' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_viewer_cannot_create_ticket(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);

        $viewer = $this->actingAsUser();
        $this->addMember($project, $viewer, 'viewer');

        $this->postJson("/api/v1/projects/{$project->id}/tickets", ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_non_member_cannot_view_tickets(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);

        $this->actingAsUser(); // a different, non-member user

        $this->getJson("/api/v1/projects/{$project->id}/tickets")->assertForbidden();
    }

    public function test_editor_can_update_and_delete_ticket(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $this->putJson("/api/v1/tickets/{$ticket->id}", ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');

        $this->deleteJson("/api/v1/tickets/{$ticket->id}")->assertOk();
        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_show_returns_404_for_missing_ticket(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/v1/tickets/999999')->assertNotFound();
    }
}
