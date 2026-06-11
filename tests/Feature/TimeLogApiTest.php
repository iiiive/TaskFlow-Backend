<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class TimeLogApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_member_can_log_time(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $this->postJson("/api/v1/tickets/{$ticket->id}/time-logs", [
            'hours'     => 2.5,
            'work_date' => now()->toDateString(),
            'description' => 'Worked on it',
        ])->assertCreated();

        $this->assertDatabaseHas('ticket_time_logs', ['ticket_id' => $ticket->id, 'project_id' => $project->id]);
    }

    public function test_log_time_validates_hours(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $this->postJson("/api/v1/tickets/{$ticket->id}/time-logs", [
            'hours'     => 99,
            'work_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_can_view_ticket_time_logs(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $this->postJson("/api/v1/tickets/{$ticket->id}/time-logs", [
            'hours' => 1, 'work_date' => now()->toDateString(),
        ])->assertCreated();

        $this->getJson("/api/v1/tickets/{$ticket->id}/time-logs")
            ->assertOk()
            ->assertJsonStructure(['data', 'total_hours']);
    }

    public function test_can_view_project_timesheet(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);

        $this->getJson("/api/v1/projects/{$project->id}/timesheet")
            ->assertOk()
            ->assertJsonStructure(['total_hours', 'data']);
    }
}
