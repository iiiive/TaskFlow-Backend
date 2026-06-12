<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/search?q=acme')->assertUnauthorized();
    }

    public function test_short_queries_return_no_results(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/search?q=a')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_super_admin_can_find_organizations(): void
    {
        $this->makeSuperAdmin();
        $this->makeOrganization(['name' => 'Acme Industries', 'owner_email' => 'boss@acme.com']);

        $this->getJson('/api/v1/search?q=acme')
            ->assertOk()
            ->assertJsonFragment(['type' => 'organization', 'label' => 'Acme Industries']);
    }

    public function test_org_admin_search_is_scoped_to_own_organization(): void
    {
        $admin = $this->actingAsOrgAdmin();

        // A user in the admin's org and a user in a different org, same name token.
        $this->makeUser(['name' => 'Mona Insider', 'organization_id' => $admin->organization_id]);
        $otherOrg = $this->makeOrganization(['name' => 'Other Org', 'owner_email' => 'o@other.com']);
        $this->makeUser(['name' => 'Mona Outsider', 'organization_id' => $otherOrg->id]);

        $this->getJson('/api/v1/search?q=mona')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Mona Insider'])
            ->assertJsonMissing(['label' => 'Mona Outsider']);
    }

    public function test_member_only_sees_their_own_projects(): void
    {
        $user = $this->actingAsUser();
        $mine = $this->makeProject($user, ['name' => 'Apollo Launch']);

        $stranger = $this->makeUser();
        $this->makeProject($stranger, ['name' => 'Apollo Secret']);

        $this->getJson('/api/v1/search?q=apollo')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Apollo Launch', 'route' => "/projects/{$mine->id}/board"])
            ->assertJsonMissing(['label' => 'Apollo Secret']);
    }
}
