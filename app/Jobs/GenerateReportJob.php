<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $projectId,
        public readonly string $reportType,
        public readonly array $params = []
    ) {}

    public function handle(ReportService $reportService): void
    {
        $cacheKey = "report.{$this->reportType}.{$this->projectId}." . md5(serialize($this->params));

        $data = match ($this->reportType) {
            'burndown'        => $reportService->getBurndown($this->projectId, $this->params['sprint_id']),
            'velocity'        => $reportService->getVelocity($this->projectId),
            'sla'             => $reportService->getSlaCompliance($this->projectId, $this->params['from'] ?? null, $this->params['to'] ?? null),
            'resolution_time' => $reportService->getAverageResolutionTime($this->projectId),
            'workload'        => $reportService->getWorkload($this->projectId),
            'distribution'    => $reportService->getIssueDistribution($this->projectId),
            'progress'        => $reportService->getProjectProgress($this->projectId),
            'response_time'   => $reportService->getResponseTime($this->projectId, $this->params['from'] ?? null, $this->params['to'] ?? null),
            'agent_performance' => $reportService->getAgentPerformance($this->projectId, $this->params['from'] ?? null, $this->params['to'] ?? null),
            'summary'         => $reportService->getExecutiveSummary($this->projectId),
            default           => null,
        };

        if ($data !== null) {
            Cache::put($cacheKey, $data, now()->addMinutes(30));
        }
    }
}
