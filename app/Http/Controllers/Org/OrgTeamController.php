<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $team = $this->teamService->createTeam($validated, $user->id);

        return response()->json([
            'message' => 'Team created successfully.',
            'data' => new TeamResource($team),
        ], 201);
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
