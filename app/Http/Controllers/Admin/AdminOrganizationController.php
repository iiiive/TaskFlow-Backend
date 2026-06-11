<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Mail\OrganizationWelcomeMail;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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

    public function update(UpdateOrganizationRequest $request, $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $validated = $request->validated();

        // Handle logo upload separately from scalar fields.
        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('org-logos', 'public');
        }

        unset($validated['logo']);

        $organization->update($validated);
        $organization->load('subscriptionPlan');

        return response()->json([
            'message' => 'Organization updated successfully.',
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function billing($id)
    {
        $organization = Organization::with('subscriptionPlan')
            ->withCount(['users', 'projects'])
            ->find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $plan = $organization->subscriptionPlan;
        $storageLimit = $organization->storageLimitBytes();
        $storageUsed = $organization->storageUsedBytes();

        return response()->json([
            'message' => 'Billing details retrieved successfully.',
            'data' => [
                'organization' => new OrganizationResource($organization),
                'plan' => $plan ? new \App\Http\Resources\SubscriptionPlanResource($plan) : null,
                'usage' => [
                    'projects' => [
                        'used'  => $organization->projects_count,
                        'limit' => $plan?->max_projects,
                    ],
                    'members' => [
                        'used'  => $organization->users_count,
                        'limit' => $plan?->max_members,
                    ],
                    'storage' => [
                        'used_bytes'  => $storageUsed,
                        'limit_bytes' => $storageLimit,
                        'used_gb'     => round($storageUsed / 1073741824, 2),
                        'limit_gb'    => $plan?->storage_gb,
                    ],
                ],
            ],
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
