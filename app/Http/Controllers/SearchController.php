<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Global, role-scoped search. All result sets are constrained server-side so a
 * caller can never see data outside their tenant / project membership.
 *
 * Response shape (consumed by the front-end top bar):
 *   { data: [ { type, label, items: [ { id, type, label, sublabel, route } ] } ] }
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $user = Auth::user();

        if ($user->is_super_admin) {
            $groups = $this->superAdminResults($term);
        } elseif ($user->is_org_admin && $user->organization_id) {
            $groups = $this->orgAdminResults($term, (int) $user->organization_id);
        } else {
            $groups = $this->memberResults($term, $user->id);
        }

        // Drop empty groups so the UI only renders sections with hits.
        $groups = array_values(array_filter($groups, fn ($g) => count($g['items']) > 0));

        return response()->json(['data' => $groups]);
    }

    /* ------------------------------------------------------------------ */

    private function superAdminResults(string $term): array
    {
        $orgs = Organization::query()
            ->where(fn ($q) => $this->like($q, ['name', 'slug', 'owner_email'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Organization $o) => [
                'id'       => $o->id,
                'type'     => 'organization',
                'label'    => $o->name,
                'sublabel' => $o->owner_email,
                'route'    => '/admin/organizations',
            ]);

        $plans = SubscriptionPlan::query()
            ->where(fn ($q) => $this->like($q, ['name', 'description'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (SubscriptionPlan $p) => [
                'id'       => $p->id,
                'type'     => 'plan',
                'label'    => $p->name,
                'sublabel' => 'Subscription plan',
                'route'    => '/admin/plans',
            ]);

        $users = User::query()
            ->where(fn ($q) => $this->like($q, ['name', 'email'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (User $u) => [
                'id'       => $u->id,
                'type'     => 'user',
                'label'    => $u->name,
                'sublabel' => $u->email,
                'route'    => '/admin/organizations',
            ]);

        return [
            ['type' => 'organizations', 'label' => 'Organizations', 'items' => $orgs->all()],
            ['type' => 'plans', 'label' => 'Subscription Plans', 'items' => $plans->all()],
            ['type' => 'users', 'label' => 'Users', 'items' => $users->all()],
        ];
    }

    private function orgAdminResults(string $term, int $orgId): array
    {
        $users = User::query()
            ->where('organization_id', $orgId)
            ->where(fn ($q) => $this->like($q, ['name', 'email'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (User $u) => [
                'id'       => $u->id,
                'type'     => 'user',
                'label'    => $u->name,
                'sublabel' => $u->email,
                'route'    => '/org/users',
            ]);

        $projects = Workspace::query()
            ->where('organization_id', $orgId)
            ->where(fn ($q) => $this->like($q, ['name', 'project_key', 'description'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Workspace $p) => [
                'id'       => $p->id,
                'type'     => 'project',
                'label'    => $p->name,
                'sublabel' => $p->project_key,
                'route'    => '/org/projects',
            ]);

        $teams = Team::query()
            ->where('organization_id', $orgId)
            ->where(fn ($q) => $this->like($q, ['name', 'description'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Team $t) => [
                'id'       => $t->id,
                'type'     => 'team',
                'label'    => $t->name,
                'sublabel' => 'Team',
                'route'    => '/org/teams',
            ]);

        return [
            ['type' => 'projects', 'label' => 'Projects', 'items' => $projects->all()],
            ['type' => 'teams', 'label' => 'Teams', 'items' => $teams->all()],
            ['type' => 'users', 'label' => 'Users', 'items' => $users->all()],
        ];
    }

    private function memberResults(string $term, int $userId): array
    {
        $projectIds = WorkspaceMember::where('user_id', $userId)->pluck('project_id');

        if ($projectIds->isEmpty()) {
            return [];
        }

        $projects = Workspace::query()
            ->whereIn('id', $projectIds)
            ->where(fn ($q) => $this->like($q, ['name', 'project_key', 'description'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Workspace $p) => [
                'id'       => $p->id,
                'type'     => 'project',
                'label'    => $p->name,
                'sublabel' => $p->project_key,
                'route'    => "/projects/{$p->id}/board",
            ]);

        $tickets = Ticket::query()
            ->whereIn('project_id', $projectIds)
            ->where(fn ($q) => $this->like($q, ['title'], $term))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Ticket $t) => [
                'id'       => $t->id,
                'type'     => 'ticket',
                'label'    => $t->title,
                'sublabel' => $t->issue_number ? "#{$t->issue_number}" : 'Ticket',
                'route'    => "/projects/{$t->project_id}/board",
            ]);

        return [
            ['type' => 'projects', 'label' => 'Projects', 'items' => $projects->all()],
            ['type' => 'tickets', 'label' => 'Tickets', 'items' => $tickets->all()],
        ];
    }

    /**
     * Case-insensitive OR-LIKE across the given columns (works on MySQL & Postgres).
     */
    private function like($query, array $columns, string $term): void
    {
        $needle = '%' . mb_strtolower($term) . '%';

        $query->where(function ($q) use ($columns, $needle) {
            foreach ($columns as $column) {
                $q->orWhereRaw('LOWER(' . $column . ') LIKE ?', [$needle]);
            }
        });
    }
}
