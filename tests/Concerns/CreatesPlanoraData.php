<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Laravel\Sanctum\Sanctum;

/**
 * Shared builders for Planora feature tests. Uses the current (post-rename)
 * schema: project_id columns + the 8-role membership system.
 */
trait CreatesPlanoraData
{
    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    /** Create a user and authenticate them via Sanctum for the test request. */
    protected function actingAsUser(array $overrides = []): User
    {
        $user = $this->makeUser($overrides);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeSuperAdmin(): User
    {
        return $this->actingAsUser(['is_super_admin' => true]);
    }

    protected function makeProject(User $owner, array $overrides = [], bool $withColumns = true): Workspace
    {
        $project = Workspace::create(array_merge([
            'owner_id'        => $owner->id,
            'organization_id' => $owner->organization_id,
            'name'            => 'Test Project',
            'project_key'     => 'TST',
            'project_type'    => 'software',
            'project_mode'    => 'kanban',
        ], $overrides));

        WorkspaceMember::create([
            'project_id' => $project->id,
            'user_id'    => $owner->id,
            'role'       => 'owner',
        ]);

        if ($withColumns) {
            $project->createDefaultKanbanColumns();
        }

        return $project->refresh();
    }

    protected function addMember(Workspace $project, User $user, string $role = 'developer'): WorkspaceMember
    {
        return WorkspaceMember::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'role'       => $role,
        ]);
    }

    protected function makeTicket(Workspace $project, array $overrides = []): Ticket
    {
        $column = $project->kanbanColumns()->first();

        return Ticket::create(array_merge([
            'project_id'       => $project->id,
            'kanban_column_id' => $column?->id,
            'issue_type'       => 'task',
            'issue_number'     => $project->generateNextIssueNumber(),
            'created_by'       => $project->owner_id,
            'reporter_id'      => $project->owner_id,
            'title'            => 'Test Ticket',
            'status'           => 'todo',
            'priority'         => 'medium',
        ], $overrides));
    }

    protected function makePlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name'         => 'Test Plan',
            'max_projects' => 10,
            'max_members'  => 50,
            'storage_gb'   => 5,
            'is_active'    => true,
        ], $overrides));
    }

    protected function makeOrganization(array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'name'        => 'Test Org',
            'owner_email' => 'owner@example.com',
            'is_active'   => true,
        ], $overrides));
    }
}
