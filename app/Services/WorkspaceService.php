<?php

namespace App\Services;

use App\Models\KanbanColumn;
use App\Models\Label;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkflowState;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getUserWorkspaces(User $user): Collection
    {
        return Workspace::whereHas('workspaceMembers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'owner:id,name,email',
                'workspaceMembers.user:id,name,email',
                'kanbanColumns',
            ])
            ->latest()
            ->get();
    }

    public function createWorkspace(User $user, array $data): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'organization_id' => $user->organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'project_key' => $data['project_key'] ?? null,
            'project_type' => $data['project_type'] ?? 'software',
            'project_mode' => $data['project_mode'] ?? 'kanban',
        ]);

        WorkspaceMember::create([
            'project_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $workspace->createDefaultKanbanColumns();

        $this->activityLogService->create(
            $workspace->id,
            null,
            $user->id,
            'project_created',
            'Project "' . $workspace->name . '" was created.'
        );

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);
    }

    public function updateWorkspace(Workspace $workspace, array $data, int $userId): Workspace
    {
        $workspace->update(array_filter([
            'name' => $data['name'] ?? $workspace->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $workspace->description,
            'project_key' => $data['project_key'] ?? $workspace->project_key,
            'project_type' => $data['project_type'] ?? $workspace->project_type,
            'project_mode' => $data['project_mode'] ?? $workspace->project_mode,
        ], fn ($v) => $v !== null));

        $this->activityLogService->create(
            $workspace->id,
            null,
            $userId,
            'project_updated',
            'Project details were updated.'
        );

        return $workspace->load([
            'owner:id,name,email',
            'workspaceMembers.user:id,name,email',
            'kanbanColumns',
        ]);
    }

    /**
     * Clone a project's structure (and optionally its issues) into a brand new
     * project owned by $user. Runs atomically so a partial copy can never persist.
     *
     * @param array{name?:string, with_issues?:bool, as_template?:bool} $options
     */
    public function cloneProject(Workspace $source, User $user, array $options = []): Workspace
    {
        $source->load([
            'kanbanColumns',
            'labels',
            'workflowTemplates.states',
            'workflowTemplates.transitions',
        ]);

        return DB::transaction(function () use ($source, $user, $options) {
            $clone = Workspace::create([
                'owner_id'        => $user->id,
                'organization_id' => $user->organization_id,
                'name'            => $options['name'] ?? ($source->name . ' (Copy)'),
                'description'     => $source->description,
                'project_key'    => null,
                'project_type'    => $source->project_type,
                'project_mode'    => $source->project_mode,
                'is_template'     => $options['as_template'] ?? false,
                'last_issue_number' => 0,
                'archived_at'     => null,
            ]);

            WorkspaceMember::create([
                'project_id' => $clone->id,
                'user_id'    => $user->id,
                'role'       => 'owner',
            ]);

            // Copy Kanban columns; keep a map old->new for ticket placement.
            $columnMap = [];
            foreach ($source->kanbanColumns as $column) {
                $newColumn = KanbanColumn::create([
                    'project_id'        => $clone->id,
                    'name'              => $column->name,
                    'slug'              => $column->slug,
                    'position'          => $column->position,
                    'wip_limit'         => $column->wip_limit,
                    'status_key'        => $column->status_key,
                    'is_backlog_column' => $column->is_backlog_column,
                    'is_done_column'    => $column->is_done_column,
                ]);
                $columnMap[$column->id] = $newColumn->id;
            }

            if ($source->kanbanColumns->isEmpty()) {
                $clone->createDefaultKanbanColumns();
            }

            // Copy labels.
            foreach ($source->labels as $label) {
                Label::create([
                    'project_id' => $clone->id,
                    'name'       => $label->name,
                    'color'      => $label->color,
                ]);
            }

            // Copy workflow templates with their states + transitions.
            foreach ($source->workflowTemplates as $template) {
                $newTemplate = WorkflowTemplate::create([
                    'project_id'  => $clone->id,
                    'created_by'  => $user->id,
                    'name'        => $template->name,
                    'description' => $template->description,
                    'is_active'   => $template->is_active,
                ]);

                $stateMap = [];
                foreach ($template->states as $state) {
                    $newState = WorkflowState::create([
                        'workflow_template_id' => $newTemplate->id,
                        'name'                 => $state->name,
                        'color'                => $state->color,
                        'position'             => $state->position,
                        'is_initial'           => $state->is_initial,
                        'is_final'             => $state->is_final,
                        'requires_approval'    => $state->requires_approval,
                        'required_fields'      => $state->required_fields,
                    ]);
                    $stateMap[$state->id] = $newState->id;
                }

                foreach ($template->transitions as $transition) {
                    if (!isset($stateMap[$transition->from_state_id], $stateMap[$transition->to_state_id])) {
                        continue;
                    }
                    WorkflowTransition::create([
                        'workflow_template_id' => $newTemplate->id,
                        'from_state_id'        => $stateMap[$transition->from_state_id],
                        'to_state_id'          => $stateMap[$transition->to_state_id],
                        'name'                 => $transition->name,
                    ]);
                }
            }

            // Optionally copy issues (excludes comments/attachments/time logs).
            if (!empty($options['with_issues'])) {
                $this->copyIssues($source, $clone, $columnMap, $user->id);
            }

            $this->activityLogService->create(
                $clone->id,
                null,
                $user->id,
                'project_created',
                ($clone->is_template ? 'Template' : 'Project') . ' "' . $clone->name . '" was created from "' . $source->name . '".'
            );

            return $clone->load([
                'owner:id,name,email',
                'workspaceMembers.user:id,name,email',
                'kanbanColumns',
            ]);
        });
    }

    private function copyIssues(Workspace $source, Workspace $clone, array $columnMap, int $userId): void
    {
        $tickets = $source->tickets()->get();

        foreach ($tickets as $ticket) {
            $clone->tickets()->create([
                'kanban_column_id' => $columnMap[$ticket->kanban_column_id] ?? null,
                'issue_type'       => $ticket->issue_type,
                'issue_number'     => $clone->generateNextIssueNumber(),
                'created_by'       => $userId,
                'reporter_id'      => $userId,
                'assigned_to'      => null,
                'title'            => $ticket->title,
                'description'      => $ticket->description,
                'status'           => $ticket->status,
                'priority'         => $ticket->priority,
                'story_points'     => $ticket->story_points,
                'category'         => $ticket->category,
                'due_date'         => $ticket->due_date,
            ]);
        }
    }

    public function deleteWorkspace(Workspace $workspace, int $userId): void
    {
        $this->activityLogService->create(
            $workspace->id,
            null,
            $userId,
            'project_deleted',
            'Project "' . $workspace->name . '" was deleted.'
        );

        $workspace->delete();
    }
}
