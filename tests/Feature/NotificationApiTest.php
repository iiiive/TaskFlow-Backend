<?php

namespace Tests\Feature;

use App\Models\PlanoraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_list_and_unread_count(): void
    {
        $user = $this->actingAsUser();
        PlanoraNotification::createForUser($user->id, 'test', 'Hello');
        PlanoraNotification::createForUser($user->id, 'test', 'World');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['unread_count']]);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_mark_one_and_all_read(): void
    {
        $user = $this->actingAsUser();
        $n = PlanoraNotification::createForUser($user->id, 'test', 'One');
        PlanoraNotification::createForUser($user->id, 'test', 'Two');

        $this->postJson("/api/v1/notifications/{$n->id}/read")->assertOk();
        $this->assertNotNull($n->fresh()->read_at);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('count', 0);
    }

    public function test_can_delete_notification(): void
    {
        $user = $this->actingAsUser();
        $n = PlanoraNotification::createForUser($user->id, 'test', 'Del');

        $this->deleteJson("/api/v1/notifications/{$n->id}")->assertOk();
        $this->assertDatabaseMissing('planora_notifications', ['id' => $n->id]);
    }

    public function test_cannot_touch_another_users_notification(): void
    {
        $other = $this->makeUser();
        $n = PlanoraNotification::createForUser($other->id, 'test', 'Theirs');

        $this->actingAsUser(); // different user
        $this->postJson("/api/v1/notifications/{$n->id}/read")->assertNotFound();
    }
}
