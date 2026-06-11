<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPlanoraData;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlanoraData;

    /** JSON header so validation/authorization failures return 4xx (not a 302 redirect). */
    private array $json = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_editor_can_upload_image(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        // create() (not image()) avoids needing the GD extension.
        $file = UploadedFile::fake()->create('shot.png', 100, 'image/png');

        $this->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file], $this->json)
            ->assertCreated();

        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $ticket->id]);
    }

    public function test_can_upload_video(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $file = UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4');

        $this->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file], $this->json)
            ->assertCreated();
    }

    public function test_disallowed_type_is_rejected(): void
    {
        $user = $this->actingAsUser();
        $project = $this->makeProject($user);
        $ticket = $this->makeTicket($project);

        $file = UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload');

        $this->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file], $this->json)
            ->assertStatus(422);
    }

    public function test_viewer_cannot_upload(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProject($owner);
        $ticket = $this->makeTicket($project);

        $viewer = $this->actingAsUser();
        $this->addMember($project, $viewer, 'viewer');

        $file = UploadedFile::fake()->create('shot.png', 100, 'image/png');

        $this->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file], $this->json)
            ->assertForbidden();
    }
}
