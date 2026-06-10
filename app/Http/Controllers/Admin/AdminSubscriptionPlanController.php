<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class AdminSubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('organizations')->get();

        return response()->json([
            'message' => 'Subscription plans retrieved successfully.',
            'data' => SubscriptionPlanResource::collection($plans),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_projects' => 'required|integer|min:1',
            'max_members' => 'required|integer|min:1',
            'storage_gb' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'message' => 'Subscription plan created successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ], 201);
    }

    public function show($id)
    {
        $plan = SubscriptionPlan::withCount('organizations')->find($id);

        if (!$plan) {
            return response()->json(['message' => 'Subscription plan not found.'], 404);
        }

        return response()->json([
            'message' => 'Subscription plan retrieved successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json(['message' => 'Subscription plan not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'max_projects' => 'sometimes|required|integer|min:1',
            'max_members' => 'sometimes|required|integer|min:1',
            'storage_gb' => 'sometimes|required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Subscription plan updated successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    public function destroy($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json(['message' => 'Subscription plan not found.'], 404);
        }

        if ($plan->organizations()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan that is assigned to organizations.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Subscription plan deleted successfully.']);
    }
}
