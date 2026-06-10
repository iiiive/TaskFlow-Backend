<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Http\Resources\TeamMemberResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private TeamService $teamService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Team::withCount('teamMembers')
            ->with(['creator'])
            ->where(function ($q) use ($request) {
                if ($request->has('project_id')) {
                    $q->where('project_id', $request->project_id);
                } else {
                    $q->whereHas('teamMembers', fn($m) => $m->where('user_id', $request->user()->id));
                }
            })
            ->latest();

        return response()->json([
            'data' => TeamResource::collection($query->paginate(20)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:500',
            'color'           => 'nullable|string|max:20',
            'project_id'      => 'nullable|integer|exists:projects,id',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ]);

        $team = $this->teamService->createTeam($validated, $request->user()->id);

        return response()->json(['data' => new TeamResource($team)], 201);
    }

    public function show(Team $team): JsonResponse
    {
        $team->load(['teamMembers.user', 'creator']);
        $team->loadCount('teamMembers');

        return response()->json(['data' => new TeamResource($team)]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:20',
            'project_id'  => 'nullable|integer|exists:projects,id',
        ]);

        $team = $this->teamService->updateTeam($team, $validated);

        return response()->json(['data' => new TeamResource($team)]);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(['message' => 'Team deleted successfully.']);
    }

    public function addMember(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role'  => 'nullable|string|in:team_lead,member',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($team->teamMembers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User is already a team member.'], 422);
        }

        $member = $this->teamService->addMember($team, $user->id, $validated['role'] ?? 'member');

        return response()->json(['data' => new TeamMemberResource($member)], 201);
    }

    public function updateMember(Request $request, Team $team, TeamMember $member): JsonResponse
    {
        abort_if($member->team_id !== $team->id, 404);

        $validated = $request->validate([
            'role' => 'required|string|in:team_lead,member',
        ]);

        $member = $this->teamService->updateMemberRole($member, $validated['role']);

        return response()->json(['data' => new TeamMemberResource($member)]);
    }

    public function removeMember(Team $team, TeamMember $member): JsonResponse
    {
        abort_if($member->team_id !== $team->id, 404);

        $this->teamService->removeMember($member);

        return response()->json(['message' => 'Member removed from team.']);
    }
}
