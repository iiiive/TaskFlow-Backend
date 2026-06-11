<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_can_create_team_with_capacity(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/teams', [
            'name' => 'Backend', 'color' => '#123456', 'capacity_hours' => 160,
        ])
            ->assertCreated()
            ->assertJsonPath('data.capacity_hours', 160);
    }

    public function test_team_requires_name(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/teams', ['name' => ''])->assertStatus(422);
    }

    public function test_can_add_member_and_view_workload(): void
    {
        $owner = $this->actingAsUser();
        $member = $this->makeUser(['email' => 'teammate@example.com']);

        $teamId = $this->postJson('/api/v1/teams', ['name' => 'Team', 'capacity_hours' => 80])->json('data.id');

        $this->postJson("/api/v1/teams/{$teamId}/members", [
            'email' => 'teammate@example.com', 'role' => 'team_lead', 'weekly_capacity_hours' => 40,
        ])->assertCreated();

        $this->getJson("/api/v1/teams/{$teamId}/workload")
            ->assertOk()
            ->assertJsonStructure(['data' => ['members', 'totals']]);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $this->actingAsUser();
        $this->makeUser(['email' => 'dup@example.com']);
        $teamId = $this->postJson('/api/v1/teams', ['name' => 'Team'])->json('data.id');

        $this->postJson("/api/v1/teams/{$teamId}/members", ['email' => 'dup@example.com'])->assertCreated();
        $this->postJson("/api/v1/teams/{$teamId}/members", ['email' => 'dup@example.com'])->assertStatus(422);
    }
}
