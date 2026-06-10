<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Mail\OrganizationWelcomeMail;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminOrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::with('subscriptionPlan')
            ->withCount('users')
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'Organizations retrieved successfully.',
            'data' => OrganizationResource::collection($organizations),
            'meta' => [
                'total' => $organizations->total(),
                'per_page' => $organizations->perPage(),
                'current_page' => $organizations->currentPage(),
                'last_page' => $organizations->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $organization = Organization::create($validated);
        $organization->load('subscriptionPlan');

        Mail::to($organization->owner_email)->queue(
            new OrganizationWelcomeMail($organization)
        );

        return response()->json([
            'message' => 'Organization created successfully. Welcome email sent.',
            'data' => new OrganizationResource($organization),
        ], 201);
    }

    public function show($id)
    {
        $organization = Organization::with(['subscriptionPlan', 'users'])
            ->withCount('users')
            ->find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        return response()->json([
            'message' => 'Organization retrieved successfully.',
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function update(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'owner_email' => 'sometimes|required|email|max:255',
            'subscription_plan_id' => 'sometimes|required|exists:subscription_plans,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $organization->update($validated);
        $organization->load('subscriptionPlan');

        return response()->json([
            'message' => 'Organization updated successfully.',
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function destroy($id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $organization->delete();

        return response()->json(['message' => 'Organization deleted successfully.']);
    }
}
