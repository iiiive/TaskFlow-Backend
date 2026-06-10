<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Workspace;
use App\Mail\SprintStartedMail;
use App\Mail\SprintCompletedMail;
use Illuminate\Support\Facades\Mail;

class SprintService
{
    public function createSprint(Workspace $project, array $data, int $userId): Sprint
    {
        return Sprint::create([
            'project_id' => $project->id,
            'created_by' => $userId,
            'name'       => $data['name'],
            'goal'       => $data['goal'] ?? null,
            'status'     => 'planning',
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
        ]);
    }

    public function startSprint(Sprint $sprint): Sprint
    {
        $sprint->update([
            'status'     => 'active',
            'start_date' => $sprint->start_date ?? now()->toDateString(),
        ]);

        $project = $sprint->project()->with('workspaceMembers.user')->first();

        if ($project) {
            foreach ($project->workspaceMembers as $member) {
                if ($member->user?->email) {
                    Mail::to($member->user->email)->queue(new SprintStartedMail($sprint, $project));
                }
            }
        }

        return $sprint->fresh();
    }

    public function completeSprint(Sprint $sprint): Sprint
    {
        $sprint->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $project = $sprint->project()->with('workspaceMembers.user')->first();

        if ($project) {
            foreach ($project->workspaceMembers as $member) {
                if ($member->user?->email) {
                    Mail::to($member->user->email)->queue(new SprintCompletedMail($sprint, $project));
                }
            }
        }

        return $sprint->fresh();
    }
}
