<?php

namespace App\Http\Controllers;

use App\Http\Resources\KanbanColumnResource;
use App\Models\KanbanColumn;
use App\Models\Workspace;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KanbanColumnController extends Controller
{
    public function __construct(
        protected WorkspacePermissionService $permissionService
    ) {}

    public function index($projectId)
    {
        $user = Auth::user();

        $project = Workspace::with([
            'kanbanColumns.tickets.creator:id,name,email',
            'kanbanColumns.tickets.assignee:id,name,email',
            'kanbanColumns.tickets.kanbanColumn',
            'kanbanColumns.tickets.epic',
            'kanbanColumns.tickets.labels',
        ])->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canView($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        $columns = $project->kanbanColumns()
            ->with([
                'tickets.creator:id,name,email',
                'tickets.assignee:id,name,email',
                'tickets.kanbanColumn',
                'tickets.epic',
                'tickets.labels',
            ])
            ->orderBy('position')
            ->get();

        return response()->json([
            'message' => 'Kanban columns retrieved successfully.',
            'data' => KanbanColumnResource::collection($columns),
        ], 200);
    }

    public function store(Request $request, $projectId)
    {
        $user = Auth::user();

        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to add Kanban columns.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'position' => 'nullable|integer|min:1',
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json(['message' => 'Column name cannot be empty.'], 422);
        }

        $position = $validated['position']
            ?? ((int) $project->kanbanColumns()->max('position') + 1);

        $column = DB::transaction(function () use ($project, $name, $position) {
            $this->shiftColumnsRight($project->id, $position);

            return $project->kanbanColumns()->create([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($project->id, $name),
                'position' => $position,
                'status_key' => null,
                'is_backlog_column' => false,
                'is_done_column' => false,
            ]);
        });

        return response()->json([
            'message' => 'Kanban column created successfully.',
            'data' => new KanbanColumnResource($column),
        ], 201);
    }

    public function update(Request $request, $columnId)
    {
        $user = Auth::user();

        $column = KanbanColumn::with('workspace')->find($columnId);

        if (!$column) {
            return response()->json(['message' => 'Kanban column not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($column->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to update Kanban columns.'], 403);
        }

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:80',
            'position'  => 'sometimes|required|integer|min:1',
            'wip_limit' => 'nullable|integer|min:1|max:999',
        ]);

        if (array_key_exists('name', $validated)) {
            $name = trim($validated['name']);

            if ($name === '') {
                return response()->json(['message' => 'Column name cannot be empty.'], 422);
            }

            $column->name = $name;

            if (!$column->is_backlog_column && !$column->is_done_column) {
                $column->status_key = null;
            }
        }

        if (array_key_exists('position', $validated)) {
            $column->position = $validated['position'];
        }

        if (array_key_exists('wip_limit', $validated)) {
            $column->wip_limit = $validated['wip_limit'];
        }

        $column->save();

        $this->normalizePositions($column->project_id);
        $column->refresh();

        return response()->json([
            'message' => 'Kanban column updated successfully.',
            'data' => new KanbanColumnResource($column),
        ], 200);
    }

    public function destroy($columnId)
    {
        $user = Auth::user();

        $column = KanbanColumn::withCount('tickets')->find($columnId);

        if (!$column) {
            return response()->json(['message' => 'Kanban column not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($column->project_id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to delete Kanban columns.'], 403);
        }

        if ($column->is_backlog_column || $column->is_done_column) {
            return response()->json(['message' => 'Backlog and Done columns cannot be deleted.'], 422);
        }

        if ($column->tickets_count > 0) {
            return response()->json(['message' => 'Move or delete the tickets in this column before deleting it.'], 422);
        }

        $projectId = $column->project_id;
        $column->delete();
        $this->normalizePositions($projectId);

        return response()->json(['message' => 'Kanban column deleted successfully.'], 200);
    }

    public function reorder(Request $request, $projectId)
    {
        $user = Auth::user();

        $project = Workspace::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($project->id, $user->id)) {
            return response()->json(['message' => 'You do not have permission to reorder Kanban columns.'], 403);
        }

        $validated = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*.id' => 'required|integer|exists:kanban_columns,id',
            'columns.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($project, $validated) {
            foreach ($validated['columns'] as $columnData) {
                KanbanColumn::where('id', $columnData['id'])
                    ->where('project_id', $project->id)
                    ->update(['position' => $columnData['position']]);
            }
        });

        $this->normalizePositions($project->id);

        $columns = $project->kanbanColumns()->orderBy('position')->get();

        return response()->json([
            'message' => 'Kanban columns reordered successfully.',
            'data' => KanbanColumnResource::collection($columns),
        ], 200);
    }

    private function generateUniqueSlug(int $projectId, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'column';
        $slug = $baseSlug;
        $counter = 2;

        while (KanbanColumn::where('project_id', $projectId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    private function shiftColumnsRight(int $projectId, int $position): void
    {
        KanbanColumn::where('project_id', $projectId)
            ->where('position', '>=', $position)
            ->increment('position');
    }

    private function normalizePositions(int $projectId): void
    {
        $columns = KanbanColumn::where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($columns as $index => $column) {
            $column->update(['position' => $index + 1]);
        }
    }
}
