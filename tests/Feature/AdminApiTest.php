<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAsUser(['is_super_admin' => false]);
        $this->getJson('/api/v1/admin/organizations')->assertForbidden();
    }

    public function test_super_admin_can_manage_plans(): void
    {
        $this->makeSuperAdmin();

        $this->postJson('/api/v1/admin/subscription-plans', [
            'name' => 'Pro', 'max_projects' => 20, 'max_members' => 100,
        ])->assertCreated();

        $this->getJson('/api/v1/admin/subscription-plans')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pro']);
    }

    public function test_super_admin_can_create_organization(): void
    {
        $this->makeSuperAdmin();
        $plan = $this->makePlan();

        $this->postJson('/api/v1/admin/organizations', [
            'name' => 'Acme', 'owner_name' => 'Acme Boss', 'owner_email' => 'boss@acme.com', 'subscription_plan_id' => $plan->id,
        ])->assertCreated()->assertJsonPath('data.name', 'Acme');
    }

    public function test_create_org_requires_plan(): void
    {
        $this->makeSuperAdmin();

        $this->postJson('/api/v1/admin/organizations', [
            'name' => 'NoPlan', 'owner_email' => 'a@b.com',
        ])->assertStatus(422)->assertJsonValidationErrors('subscription_plan_id');
    }

    public function test_billing_endpoint_returns_usage(): void
    {
        $this->makeSuperAdmin();
        $plan = $this->makePlan(['max_projects' => 5, 'max_members' => 10]);
        $org = $this->makeOrganization(['subscription_plan_id' => $plan->id]);

        $this->getJson("/api/v1/admin/organizations/{$org->id}/billing")
            ->assertOk()
            ->assertJsonStructure(['data' => ['organization', 'plan', 'usage' => ['projects', 'members']]]);
    }
}
