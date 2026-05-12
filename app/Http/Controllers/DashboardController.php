<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = $this->dashboardService->getUserDashboardData(Auth::id());

        return response()->json([
            'message' => 'Dashboard data retrieved successfully.',
            'data' => [
                'summary' => $dashboardData['summary'],
                'recent_activity' => ActivityLogResource::collection($dashboardData['recent_activity']),
            ],
        ], 200);
    }
}