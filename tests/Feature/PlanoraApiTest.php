<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanoraApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    private function createWorkspace(User $owner, array $overrides = []): Workspace
    {
        $workspace = Workspace::create(array_merge([
            'owner_id' => $owner->id,
            'name' => 'Test Workspace',
            'description' => 'Workspace for API testing.',
        ], $overrides));

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        return $workspace;
    }

    private function createWorkspaceMember(Workspace $workspace, User $user, string $role = 'editor'): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    private function createTicket(Workspace $workspace, User $creator, ?User $assignee = null, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee?->id,
            'title' => 'Test Ticket',
            'description' => 'This is a test ticket.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Charlin Test',
            'email' => 'charlin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'charlin@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $this->createUser([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $this->createUser([
            'email' => 'wrongpass@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_create_workspace(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Planora Workspace',
            'description' => 'Main test workspace.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Planora Workspace',
            'owner_id' => $user->id,
        ]);

        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_authenticated_user_can_view_own_workspaces(): void
    {
        $user = $this->createUser();
        $this->createWorkspace($user);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/workspaces');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_workspace_owner_can_update_workspace(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'Updated Workspace',
            'description' => 'Updated description.',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Workspace',
        ]);
    }

    public function test_workspace_owner_can_delete_workspace(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_workspace_owner_can_add_member(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/members", [
            'email' => $member->email,
            'role' => 'editor',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'editor',
        ]);
    }

    public function test_workspace_owner_can_view_members(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_workspace_owner_can_update_member_role(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $workspace = $this->createWorkspace($owner);
        $workspaceMember = $this->createWorkspaceMember($workspace, $member, 'viewer');

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/workspaces/{$workspace->id}/members/{$workspaceMember->id}", [
            'role' => 'editor',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('workspace_members', [
            'id' => $workspaceMember->id,
            'role' => 'editor',
        ]);
    }

    public function test_workspace_owner_can_remove_member(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $workspace = $this->createWorkspace($owner);
        $workspaceMember = $this->createWorkspaceMember($workspace, $member, 'viewer');

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/members/{$workspaceMember->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('workspace_members', [
            'id' => $workspaceMember->id,
        ]);
    }

    public function test_editor_can_create_ticket(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $assignee = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $editor, 'editor');
        $this->createWorkspaceMember($workspace, $assignee, 'viewer');

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/tickets", [
            'title' => 'Create API Tests',
            'description' => 'Write feature tests for Planora APIs.',
            'status' => 'todo',
            'priority' => 'high',
            'assigned_to' => $assignee->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tickets', [
            'workspace_id' => $workspace->id,
            'created_by' => $editor->id,
            'assigned_to' => $assignee->id,
            'title' => 'Create API Tests',
        ]);
    }

    public function test_viewer_cannot_create_ticket(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $viewer, 'viewer');

        Sanctum::actingAs($viewer);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/tickets", [
            'title' => 'Viewer Ticket',
            'description' => 'Viewer should not create this.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_workspace_member_can_view_tickets(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');
        $this->createTicket($workspace, $owner, $member);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/tickets");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_editor_can_update_ticket(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $assignee = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $editor, 'editor');
        $this->createWorkspaceMember($workspace, $assignee, 'viewer');

        $ticket = $this->createTicket($workspace, $owner);

        Sanctum::actingAs($editor);

        $response = $this->putJson("/api/tickets/{$ticket->id}", [
            'title' => 'Updated Ticket Title',
            'description' => 'Updated ticket description.',
            'status' => 'dev_in_progress',
            'priority' => 'urgent',
            'assigned_to' => $assignee->id,
            'due_date' => now()->addDays(1)->toDateString(),
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'title' => 'Updated Ticket Title',
            'status' => 'dev_in_progress',
            'priority' => 'urgent',
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_viewer_cannot_update_ticket(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $viewer, 'viewer');

        $ticket = $this->createTicket($workspace, $owner);

        Sanctum::actingAs($viewer);

        $response = $this->putJson("/api/tickets/{$ticket->id}", [
            'title' => 'Unauthorized Update',
            'description' => 'Viewer should not update this.',
            'status' => 'done',
            'priority' => 'low',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_ticket(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspace($owner);
        $ticket = $this->createTicket($workspace, $owner);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
        ]);
    }

    public function test_workspace_member_can_add_comment_to_ticket(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'editor');

        $ticket = $this->createTicket($workspace, $owner, $member);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'comment' => 'This is a test comment.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $member->id,
            'comment' => 'This is a test comment.',
        ]);
    }

    public function test_workspace_member_can_view_ticket_comments(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');

        $ticket = $this->createTicket($workspace, $owner, $member);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'comment' => 'Existing comment.',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/tickets/{$ticket->id}/comments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_workspace_member_can_view_workspace_activity_logs(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'ticket_id' => null,
            'user_id' => $owner->id,
            'action' => 'member_added',
            'description' => 'A member was added.',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/activity");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_workspace_member_can_view_ticket_activity_logs(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');

        $ticket = $this->createTicket($workspace, $owner, $member);

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'action' => 'ticket_updated',
            'description' => 'Ticket details were updated.',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/tickets/{$ticket->id}/activity");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace($user);
        $this->createTicket($workspace, $user, $user);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_assigned_user_can_receive_notifications(): void
    {
        $owner = $this->createUser([
            'name' => 'Owner User',
        ]);

        $assignee = $this->createUser([
            'name' => 'Assigned User',
        ]);

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $assignee, 'editor');

        $ticket = $this->createTicket($workspace, $owner, $assignee, [
            'title' => 'Notification Test Ticket',
        ]);

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'action' => 'ticket_assigned',
            'description' => 'Ticket was assigned to a workspace member.',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'comment' => 'Please check this ticket.',
        ]);

        Sanctum::actingAs($assignee);

        $response = $this->getJson('/api/dashboard/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_user_does_not_receive_own_notification_actions(): void
    {
        $user = $this->createUser();

        $workspace = $this->createWorkspace($user);

        $ticket = $this->createTicket($workspace, $user, $user, [
            'title' => 'Own Action Ticket',
        ]);

        ActivityLog::create([
            'workspace_id' => $workspace->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'ticket_updated',
            'description' => 'Ticket details were updated.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/notifications');

        $response->assertStatus(200);

        $notifications = $response->json('data');

        foreach ($notifications as $notification) {
            $this->assertStringNotContainsString('Ticket details were updated.', $notification['message'] ?? '');
        }
    }

    public function test_workspace_member_can_upload_ticket_attachment(): void
    {
        Storage::fake('public');

        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'editor');

        $ticket = $this->createTicket($workspace, $owner, $member);

        Sanctum::actingAs($member);

        $file = UploadedFile::fake()->create('test-document.pdf', 300, 'application/pdf');

        $response = $this->postJson("/api/tickets/{$ticket->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'user_id' => $member->id,
            'file_name' => 'test-document.pdf',
        ]);
    }

    public function test_workspace_member_can_view_ticket_attachments(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $workspace = $this->createWorkspace($owner);
        $this->createWorkspaceMember($workspace, $member, 'viewer');

        $ticket = $this->createTicket($workspace, $owner, $member);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/tickets/{$ticket->id}/attachments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(401);
    }
}