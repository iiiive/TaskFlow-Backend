<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class OrgOverviewController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $orgId = $user->organization_id;

        $organization = $user->organization()->with('subscriptionPlan')->first();

        return response()->json([
            'message' => 'Organization overview retrieved successfully.',
            'data' => [
                'organization' => $organization
                    ? new OrganizationResource($organization)
                    : null,
                'counts' => [
                    'projects' => Workspace::where('organization_id', $orgId)->count(),
                    'users'    => User::where('organization_id', $orgId)->count(),
                    'teams'    => Team::where('organization_id', $orgId)->count(),
                ],
            ],
        ]);
    }
}
