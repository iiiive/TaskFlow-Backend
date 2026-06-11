<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_can_fetch_profile(): void
    {
        $user = $this->actingAsUser();

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_can_update_name_timezone_and_preferences(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/v1/profile', [
            'name'        => 'Updated Name',
            'email'       => $user->email,
            'timezone'    => 'America/New_York',
            'preferences' => ['theme' => 'dark', 'email_notifications' => false, 'default_board_view' => 'backlog'],
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertEquals('Updated Name', $fresh->name);
        $this->assertEquals('America/New_York', $fresh->timezone);
        $this->assertEquals('dark', $fresh->preferences['theme']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/v1/profile', [
            'name' => $user->name, 'email' => $user->email, 'timezone' => 'Mars/Phobos',
        ])->assertStatus(422)->assertJsonValidationErrors('timezone');
    }

    public function test_can_change_password(): void
    {
        $this->actingAsUser(); // factory password is "password"

        $this->putJson('/api/v1/profile/password', [
            'current_password'      => 'password',
            'password'              => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();
    }
}
