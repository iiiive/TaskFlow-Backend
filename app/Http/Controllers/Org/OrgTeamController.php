<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrgTeamController extends Controller
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function index()
    {
        $user = Auth::user();

        $teams = Team::where('organization_id', $user->organization_id)
            ->with(['teamMembers.user:id,name,email', 'project:id,name'])
            ->withCount('teamMembers')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Teams retrieved successfully.',
            'data' => TeamResource::collection($teams),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'capacity_hours' => 'nullable|integer|min:0',
        ]);

        $validated['organization_id'] = $user->organization_id;

        $team = $this->teamService->createTeam($validated, $user->id, addCreator: false);

        return response()->json([
            'message' => 'Team created successfully.',
            'data' => new TeamResource($team),
        ], 201);
    }

    public function addMember(Request $request, $id)
    {
        $user = Auth::user();
        $team = Team::where('organization_id', $user->organization_id)->find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
            'role' => ['nullable', Rule::in(\App\Models\WorkspaceMember::ROLES)],
        ]);

        $userId = (int) $validated['user_id'];
        $role = $validated['role'] ?? 'developer';

        $existing = TeamMember::where('team_id', $team->id)->where('user_id', $userId)->exists();
        if ($existing) {
            return response()->json(['message' => 'User is already a member of this team.'], 422);
        }

        $this->teamService->addMember($team, $userId, $role);

        // If team is assigned to a project, sync the same role to the project.
        if ($team->project_id) {
            $member = WorkspaceMember::firstOrCreate(
                ['project_id' => $team->project_id, 'user_id' => $userId],
                ['role' => $role]
            );
            if (!$member->wasRecentlyCreated) {
                $member->update(['role' => $role]);
            }
        }

        $team->load(['teamMembers.user:id,name,email', 'project:id,name']);
        $team->loadCount('teamMembers');

        return response()->json([
            'message' => 'Member added to team.',
            'data' => new TeamResource($team),
        ], 201);
    }

    public function removeMember($id, $userId)
    {
        $user = Auth::user();
        $team = Team::where('organization_id', $user->organization_id)->find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        TeamMember::where('team_id', $team->id)->where('user_id', $userId)->delete();

        $team->load(['teamMembers.user:id,name,email', 'project:id,name']);
        $team->loadCount('teamMembers');

        return response()->json([
            'message' => 'Member removed from team.',
            'data' => new TeamResource($team),
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        $team = Team::where('organization_id', $user->organization_id)->find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->delete();

        return response()->json(['message' => 'Team deleted successfully.']);
    }
}
