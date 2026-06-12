<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\OrganizationProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminOrganizationController extends Controller
{
    public function __construct(
        private OrganizationProvisioningService $provisioning
    ) {}

    public function index()
    {
        $organizations = Organization::with(['subscriptionPlan', 'owner'])
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
            'owner_name' => 'required|string|max:80',
            // The owner email becomes the org admin's login, so it must be a
            // brand-new user account.
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
        ], [
            'owner_email.unique' => 'A user with this email already exists.',
        ]);

        $result = $this->provisioning->provision($validated);

        return response()->json([
            'message' => 'Organization created. Admin account provisioned and credentials emailed.',
            'data' => new OrganizationResource($result['organization']),
            'temporary_password' => $result['temporary_password'],
        ], 201);
    }

    public function renew(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json(['message' => 'Organization not found.'], 404);
        }

        $validated = $request->validate([
            'ends_at' => 'nullable|date|after:today',
        ]);

        $organization = $this->provisioning->renew($organization, $validated['ends_at'] ?? null);

        return response()->json([
            'message' => 'Subscription renewed successfully.',
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function show($id)
    {
        $organization = Organization::with(['subscriptionPlan', 'users', 'owner'])
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
