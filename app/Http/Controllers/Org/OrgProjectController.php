<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ProjectTeamSyncService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrgProjectController extends Controller
{
    public function __construct(
        private WorkspaceService $workspaceService,
        private ProjectTeamSyncService $teamSync
    ) {}

    public function index()
    {
        $user = Auth::user();

        $projects = Workspace::where('organization_id', $user->organization_id)
            ->with(['owner:id,name,email', 'workspaceMembers.user:id,name,email', 'team'])
            ->withCount('tickets')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Projects retrieved successfully.',
            'data' => WorkspaceResource::collection($projects),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $organization = $user->organization;

        if ($organization->isAtProjectLimit()) {
            return response()->json([
                'message' => 'Project limit reached for your current subscription plan.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_type' => 'nullable|in:software,it_support,marketing,hr,construction,general',
            'project_mode' => 'nullable|in:kanban,scrum',
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organization->id)],
            'members' => 'nullable|array',
            'members.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('organization_id', $organization->id)],
            'members.*.role' => ['required', Rule::in(WorkspaceMember::ROLES)],
        ]);

        $validated['organization_id'] = $organization->id;
        $validated['project_key'] = $this->generateProjectKey($validated['name']);

        $project = $this->workspaceService->createWorkspace($user, $validated);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => new WorkspaceResource($project),
        ], 201);
    }

    public function destroy($id)
    {
        $project = $this->findOrgProject($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    public function assignTeam(Request $request, $id)
    {
        $project = $this->findOrgProject($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $organization = Auth::user()->organization;

        $validated = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organization->id)],
        ]);

        $this->workspaceService->assignTeam($project, (int) $validated['team_id']);

        return response()->json([
            'message' => 'Team assigned to project. Existing members synced.',
            'data' => new WorkspaceResource(
                $project->load(['workspaceMembers.user:id,name,email', 'team'])
            ),
        ]);
    }

    public function addMember(Request $request, $id)
    {
        $project = $this->findOrgProject($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $organization = Auth::user()->organization;

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('organization_id', $organization->id)],
            'role' => ['required', Rule::in(WorkspaceMember::ROLES)],
        ]);

        $member = WorkspaceMember::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        // Keep role in sync if the member already existed.
        if (!$member->wasRecentlyCreated && $member->role !== $validated['role']) {
            $member->update(['role' => $validated['role']]);
        }

        $this->teamSync->syncUser($project, (int) $validated['user_id']);

        return response()->json([
            'message' => 'Member added successfully.',
            'data' => new WorkspaceResource(
                $project->load(['workspaceMembers.user:id,name,email', 'team'])
            ),
        ], 201);
    }

    public function removeMember($id, $userId)
    {
        $project = $this->findOrgProject($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        WorkspaceMember::where('project_id', $project->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['message' => 'Member removed successfully.']);
    }

    private function findOrgProject($id): ?Workspace
    {
        return Workspace::where('organization_id', Auth::user()->organization_id)
            ->find($id);
    }

    private function generateProjectKey(string $name): string
    {
        $key = strtoupper(Str::of($name)->replaceMatches('/[^A-Za-z0-9]/', '')->substr(0, 4));

        return $key !== '' ? $key : 'PROJ';
    }
}
