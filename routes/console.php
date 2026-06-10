<?php

use App\Models\Sprint;
use App\Mail\SprintEndingReminderMail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $tomorrow = now()->addDay()->toDateString();

    Sprint::where('status', 'active')
        ->whereDate('end_date', $tomorrow)
        ->with(['project.workspaceMembers.user'])
        ->each(function (Sprint $sprint) {
            foreach ($sprint->project->workspaceMembers as $member) {
                if ($member->user?->email) {
                    Mail::to($member->user->email)
                        ->queue(new SprintEndingReminderMail($sprint, $sprint->project));
                }
            }
        });
})->dailyAt('08:00')->name('sprint-ending-reminders');
