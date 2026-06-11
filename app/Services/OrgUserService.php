<?php

namespace App\Services;

use App\Mail\UserAccountCreatedMail;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrgUserService
{
    public function __construct(
        protected ProjectTeamSyncService $teamSync
    ) {}

    /**
     * Create a user inside an organization, optionally assigning them to projects
     * with a role, and email them their credentials.
     *
     * @param array{name:string, email:string, projects?:array<int,array{project_id:int, role:string}>} $data
     */
    public function createUser(Organization $organization, array $data): User
    {
        if ($organization->isAtMemberLimit()) {
            throw ValidationException::withMessages([
                'email' => ['Member limit reached for your current subscription plan.'],
            ]);
        }

        return DB::transaction(function () use ($organization, $data) {
            $temporaryPassword = Str::password(12);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'email_verified_at' => now(),
            ]);

            $assignedProjects = [];

            foreach ($data['projects'] ?? [] as $assignment) {
                $project = Workspace::where('id', $assignment['project_id'])
                    ->where('organization_id', $organization->id)
                    ->first();

                if (!$project) {
                    continue;
                }

                WorkspaceMember::firstOrCreate(
                    ['project_id' => $project->id, 'user_id' => $user->id],
                    ['role' => $assignment['role']]
                );

                $this->teamSync->syncUser($project, $user->id);

                $assignedProjects[] = ['name' => $project->name, 'role' => $assignment['role']];
            }

            Mail::to($user->email)->queue(
                new UserAccountCreatedMail($user, $temporaryPassword, $organization, $assignedProjects)
            );

            return $user;
        });
    }
}
