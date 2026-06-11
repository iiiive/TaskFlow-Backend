<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private WorkspacePermissionService $permissions
    ) {}

    public function burndown(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $request->validate(['sprint_id' => 'required|integer|exists:sprints,id']);

        $data = $this->reportService->getBurndown($projectId, (int) $request->sprint_id);

        return response()->json(['data' => $data]);
    }

    public function velocity(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $data = $this->reportService->getVelocity($projectId);

        return response()->json(['data' => $data]);
    }

    public function sla(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $data = $this->reportService->getSlaCompliance(
            $projectId,
            $request->from,
            $request->to
        );

        return response()->json(['data' => $data]);
    }

    public function resolutionTime(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $data = $this->reportService->getAverageResolutionTime($projectId);

        return response()->json(['data' => $data]);
    }

    public function workload(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $data = $this->reportService->getWorkload($projectId);

        return response()->json(['data' => $data]);
    }

    public function distribution(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $data = $this->reportService->getIssueDistribution($projectId);

        return response()->json(['data' => $data]);
    }

    public function overdue(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $data = $this->reportService->getOverdueTickets($projectId);

        return response()->json($data);
    }

    public function progress(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        return response()->json(['data' => $this->reportService->getProjectProgress($projectId)]);
    }

    public function responseTime(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json([
            'data' => $this->reportService->getResponseTime($projectId, $request->from, $request->to),
        ]);
    }

    public function agentPerformance(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json([
            'data' => $this->reportService->getAgentPerformance($projectId, $request->from, $request->to),
        ]);
    }

    public function summary(Request $request, int $projectId): JsonResponse
    {
        $this->authorizeProjectAccess($request, $projectId);

        return response()->json(['data' => $this->reportService->getExecutiveSummary($projectId)]);
    }

    private function authorizeProjectAccess(Request $request, int $projectId): void
    {
        if (!$this->permissions->canView($projectId, $request->user()->id)) {
            abort(403, 'You do not have access to this project.');
        }
    }
}
