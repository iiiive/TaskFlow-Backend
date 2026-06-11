<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\OrgUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrgUserController extends Controller
{
    public function __construct(
        private OrgUserService $orgUsers
    ) {}

    public function index()
    {
        $user = Auth::user();

        $users = User::where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Users retrieved successfully.',
            'data' => UserResource::collection($users),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $organization = $user->organization;

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'projects' => 'nullable|array',
            'projects.*.project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('organization_id', $organization->id)],
            'projects.*.role' => ['required', Rule::in(WorkspaceMember::ROLES)],
        ]);

        $created = $this->orgUsers->createUser($organization, $validated);

        return response()->json([
            'message' => 'User created. Credentials emailed.',
            'data' => new UserResource($created),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $authUser = Auth::user();

        $user = User::where('organization_id', $authUser->organization_id)->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:80',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy($id)
    {
        $authUser = Auth::user();

        if ((int) $id === (int) $authUser->id) {
            return response()->json(['message' => 'You cannot remove your own account.'], 422);
        }

        $user = User::where('organization_id', $authUser->organization_id)->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User removed successfully.']);
    }
}
