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

    public function index($workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::with([
            'kanbanColumns.tickets.creator:id,name,email',
            'kanbanColumns.tickets.assignee:id,name,email',
            'kanbanColumns.tickets.kanbanColumn',
            'kanbanColumns.tickets.epic',
        ])->find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canView($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'You do not have access to this workspace.',
            ], 403);
        }

        $columns = $workspace->kanbanColumns()
            ->with([
                'tickets.creator:id,name,email',
                'tickets.assignee:id,name,email',
                'tickets.kanbanColumn',
                'tickets.epic',
            ])
            ->orderBy('position')
            ->get();

        return response()->json([
            'message' => 'Kanban columns retrieved successfully.',
            'data' => KanbanColumnResource::collection($columns),
        ], 200);
    }

    public function store(Request $request, $workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can add Kanban columns.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'position' => 'nullable|integer|min:1',
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json([
                'message' => 'Column name cannot be empty.',
            ], 422);
        }

        $position = $validated['position']
            ?? ((int) $workspace->kanbanColumns()->max('position') + 1);

        $column = DB::transaction(function () use ($workspace, $name, $position) {
            $this->shiftColumnsRight($workspace->id, $position);

            /*
            |--------------------------------------------------------------------------
            | Important Fix
            |--------------------------------------------------------------------------
            | Every added/custom column must have status_key = null.
            | This prevents custom columns like "Blocker" from being treated as
            | old statuses like "todo" or "dev_in_progress".
            */
            return $workspace->kanbanColumns()->create([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($workspace->id, $name),
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
            return response()->json([
                'message' => 'Kanban column not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($column->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can update Kanban columns.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'position' => 'sometimes|required|integer|min:1',
        ]);

        if (array_key_exists('name', $validated)) {
            $name = trim($validated['name']);

            if ($name === '') {
                return response()->json([
                    'message' => 'Column name cannot be empty.',
                ], 422);
            }

            $column->name = $name;

            /*
            |--------------------------------------------------------------------------
            | Main Fix for Your Bug
            |--------------------------------------------------------------------------
            | If a default middle column is renamed, it should behave as the
            | displayed column name, not the old hardcoded status_key.
            |
            | Example:
            | Old default column: Dev In Progress, status_key = dev_in_progress
            | Renamed column: Blocker
            |
            | Before:
            | Email says moved to Dev In Progress.
            |
            | After:
            | Email says moved to Blocker.
            |
            | Backlog and Done keep their status keys because they have special
            | behavior in your system.
            */
            if (!$column->is_backlog_column && !$column->is_done_column) {
                $column->status_key = null;
            }
        }

        if (array_key_exists('position', $validated)) {
            $column->position = $validated['position'];
        }

        $column->save();

        $this->normalizePositions($column->workspace_id);

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
            return response()->json([
                'message' => 'Kanban column not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($column->workspace_id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can delete Kanban columns.',
            ], 403);
        }

        if ($column->is_backlog_column || $column->is_done_column) {
            return response()->json([
                'message' => 'Backlog and Done columns cannot be deleted.',
            ], 422);
        }

        if ($column->tickets_count > 0) {
            return response()->json([
                'message' => 'This column still has tickets. Move or delete the tickets first before deleting the column.',
            ], 422);
        }

        $workspaceId = $column->workspace_id;

        $column->delete();

        $this->normalizePositions($workspaceId);

        return response()->json([
            'message' => 'Kanban column deleted successfully.',
        ], 200);
    }

    public function reorder(Request $request, $workspaceId)
    {
        $user = Auth::user();

        $workspace = Workspace::find($workspaceId);

        if (!$workspace) {
            return response()->json([
                'message' => 'Workspace not found.',
            ], 404);
        }

        if (!$this->permissionService->canCreateOrUpdateTicket($workspace->id, $user->id)) {
            return response()->json([
                'message' => 'Only owners and editors can reorder Kanban columns.',
            ], 403);
        }

        $validated = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*.id' => 'required|integer|exists:kanban_columns,id',
            'columns.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($workspace, $validated) {
            foreach ($validated['columns'] as $columnData) {
                KanbanColumn::where('id', $columnData['id'])
                    ->where('workspace_id', $workspace->id)
                    ->update([
                        'position' => $columnData['position'],
                    ]);
            }
        });

        $this->normalizePositions($workspace->id);

        $columns = $workspace->kanbanColumns()
            ->orderBy('position')
            ->get();

        return response()->json([
            'message' => 'Kanban columns reordered successfully.',
            'data' => KanbanColumnResource::collection($columns),
        ], 200);
    }

    private function generateUniqueSlug(int $workspaceId, string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'column';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            KanbanColumn::where('workspace_id', $workspaceId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function shiftColumnsRight(int $workspaceId, int $position): void
    {
        KanbanColumn::where('workspace_id', $workspaceId)
            ->where('position', '>=', $position)
            ->increment('position');
    }

    private function normalizePositions(int $workspaceId): void
    {
        $columns = KanbanColumn::where('workspace_id', $workspaceId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($columns as $index => $column) {
            $column->update([
                'position' => $index + 1,
            ]);
        }
    }
}