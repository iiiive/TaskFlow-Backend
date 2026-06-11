<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

/**
 * Exercises the report endpoints — important because they use Postgres-only SQL
 * (FILTER, EXTRACT(EPOCH ...), ILIKE), so this verifies they actually run.
 */
class ReportApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    private function seededProject()
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        // A couple of tickets so aggregates have data.
        $this->makeTicket($project, ['title' => 'A', 'priority' => 'high', 'due_date' => now()->subDay()->toDateString()]);
        $this->makeTicket($project, ['title' => 'B', 'status' => 'done']);

        return [$user, $project];
    }

    public function test_all_report_endpoints_respond(): void
    {
        [$user, $project] = $this->seededProject();
        $base = "/api/v1/reports/projects/{$project->id}";

        foreach ([
            '/distribution', '/workload', '/sla', '/resolution-time', '/overdue',
            '/velocity', '/progress', '/response-time', '/agent-performance', '/summary',
        ] as $path) {
            $this->getJson($base . $path)->assertOk();
        }
    }

    public function test_summary_returns_executive_metrics(): void
    {
        [$user, $project] = $this->seededProject();

        $this->getJson("/api/v1/reports/projects/{$project->id}/summary")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'open_tickets', 'closed_tickets', 'backlog_count',
                'team_productivity_hours_30d', 'average_resolution', 'sla',
            ]]);
    }

    public function test_agent_performance_runs_postgres_filter_sql(): void
    {
        [$user, $project] = $this->seededProject();

        $this->getJson("/api/v1/reports/projects/{$project->id}/agent-performance")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_non_member_cannot_access_reports(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);
        $this->actingAsUser(); // non-member

        $this->getJson("/api/v1/reports/projects/{$project->id}/summary")->assertForbidden();
    }
}
