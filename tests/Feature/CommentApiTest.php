<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_editor_can_add_and_list_comments(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        // Regression: this used to break on the stale `workspace_id` column.
        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", ['comment' => 'First comment'])
            ->assertCreated()
            ->assertJsonFragment(['comment' => 'First comment']);

        $this->getJson("/api/v1/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonFragment(['comment' => 'First comment']);

        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'comment' => 'First comment']);
    }

    public function test_comment_requires_body(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", ['comment' => ''])
            ->assertStatus(422);
    }

    public function test_viewer_cannot_comment(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);
        $ticket = $this->makeTicket($project);

        $viewer = $this->actingAsUser();
        $this->addMember($project, $viewer, 'viewer');

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", ['comment' => 'no'])
            ->assertForbidden();
    }
}
