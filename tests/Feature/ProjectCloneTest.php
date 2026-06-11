<?php

namespace Tests\Feature;

use App\Models\KanbanColumn;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_clone_copies_columns_and_labels_without_issues_by_default(): void
    {
        $user = User::factory()->create();

        $source = Workspace::create([
            'owner_id'     => $user->id,
            'name'         => 'Source Project',
            'project_type' => 'software',
        ]);

        WorkspaceMember::create([
            'project_id' => $source->id,
            'user_id'    => $user->id,
            'role'       => 'owner',
        ]);

        KanbanColumn::create([
            'project_id' => $source->id,
            'name'       => 'To Do',
            'slug'       => 'to-do',
            'position'   => 1,
            'is_backlog_column' => true,
            'is_done_column'    => false,
        ]);

        Label::create([
            'project_id' => $source->id,
            'name'       => 'Frontend',
            'color'      => '#4f46e5',
        ]);

        $source->tickets()->create([
            'created_by' => $user->id,
            'title'      => 'Source ticket',
            'status'     => 'todo',
            'priority'   => 'medium',
        ]);

        $clone = app(WorkspaceService::class)->cloneProject($source, $user, ['name' => 'Cloned Project']);

        $this->assertNotEquals($source->id, $clone->id);
        $this->assertEquals('Cloned Project', $clone->name);
        $this->assertEquals($user->id, $clone->owner_id);

        // Structure copied
        $this->assertEquals(1, $clone->kanbanColumns()->count());
        $this->assertEquals(1, $clone->labels()->count());

        // Issues NOT copied by default
        $this->assertEquals(0, $clone->tickets()->count());

        // Owner membership created
        $this->assertTrue(
            WorkspaceMember::where('project_id', $clone->id)->where('user_id', $user->id)->where('role', 'owner')->exists()
        );
    }

    public function test_clone_copies_issues_when_requested(): void
    {
        $user = User::factory()->create();

        $source = Workspace::create([
            'owner_id' => $user->id,
            'name'     => 'Source',
        ]);

        WorkspaceMember::create([
            'project_id' => $source->id,
            'user_id'    => $user->id,
            'role'       => 'owner',
        ]);

        $source->createDefaultKanbanColumns();

        $source->tickets()->create([
            'created_by' => $user->id,
            'title'      => 'Copy me',
            'status'     => 'todo',
            'priority'   => 'high',
        ]);

        $clone = app(WorkspaceService::class)->cloneProject($source, $user, [
            'name'        => 'With Issues',
            'with_issues' => true,
        ]);

        $this->assertEquals(1, $clone->tickets()->count());
    }
}
