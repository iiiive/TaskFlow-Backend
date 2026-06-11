<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    public function test_user_can_register(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => '', 'email' => 'not-an-email', 'password' => 'short',
        ])->assertStatus(422);
    }

    public function test_login_with_valid_credentials_sets_session(): void
    {
        $user = $this->makeUser(); // factory password = "password"

        // A stateful Referer makes Sanctum apply the session middleware.
        $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('token');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(401);
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/v1/login', ['email' => '', 'password' => ''])
            ->assertStatus(422);
    }

    public function test_protected_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
