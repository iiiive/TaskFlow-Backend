<?php

namespace App\Providers;

use App\Models\Epic;
use App\Models\KanbanColumn;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketTimeLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Policies\AttachmentPolicy;
use App\Policies\EpicPolicy;
use App\Policies\IssuePolicy;
use App\Policies\KanbanColumnPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TimeLogPolicy;
use App\Policies\WorkspaceMemberPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiters();

        Gate::policy(Workspace::class, ProjectPolicy::class);
        Gate::policy(Ticket::class, IssuePolicy::class);
        Gate::policy(WorkspaceMember::class, WorkspaceMemberPolicy::class);
        Gate::policy(Epic::class, EpicPolicy::class);
        Gate::policy(KanbanColumn::class, KanbanColumnPolicy::class);
        Gate::policy(TicketAttachment::class, AttachmentPolicy::class);
        Gate::policy(TicketTimeLog::class, TimeLogPolicy::class);
    }

    /**
     * Named rate limiters applied to the API.
     */
    private function configureRateLimiters(): void
    {
        // General authenticated API traffic — keyed per user, falling back to IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection for credential submission — keyed by email + IP.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });
    }
}

