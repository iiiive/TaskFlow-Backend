<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketTimeLogController;
use App\Http\Controllers\KanbanColumnController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\Admin\AdminOrganizationController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Middleware\SuperAdminMiddleware;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Auth Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/store', [AuthController::class, 'store']);
        Route::post('/register', [AuthController::class, 'store']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactorLogin']);
    });

    /*
    |--------------------------------------------------------------------------
    | Google Auth Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/profile', [AuthController::class, 'getProfile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [AuthController::class, 'updatePassword']);
        Route::post('/profile/avatar', [AuthController::class, 'updateAvatar']);
        Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);

        /*
        |--------------------------------------------------------------------------
        | 2FA Routes
        |--------------------------------------------------------------------------
        */

        Route::post('/2fa/setup', [AuthController::class, 'setupTwoFactor']);
        Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);
        Route::post('/2fa/recovery-codes/regenerate', [AuthController::class, 'regenerateRecoveryCodes']);

        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Super Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware(SuperAdminMiddleware::class)->prefix('admin')->group(function () {
            Route::apiResource('organizations', AdminOrganizationController::class);
            Route::apiResource('subscription-plans', AdminSubscriptionPlanController::class);
        });

        /*
        |--------------------------------------------------------------------------
        | Projects (previously Workspaces)
        |--------------------------------------------------------------------------
        */

        Route::get('/projects', [WorkspaceController::class, 'index']);
        Route::post('/projects', [WorkspaceController::class, 'store']);
        Route::get('/projects/{id}', [WorkspaceController::class, 'show']);
        Route::put('/projects/{id}', [WorkspaceController::class, 'update']);
        Route::delete('/projects/{id}', [WorkspaceController::class, 'destroy']);
        Route::post('/projects/{id}/archive', [WorkspaceController::class, 'archive']);
        Route::post('/projects/{id}/unarchive', [WorkspaceController::class, 'unarchive']);

        /*
        |--------------------------------------------------------------------------
        | Project Members
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/members', [WorkspaceMemberController::class, 'index']);
        Route::post('/projects/{projectId}/members', [WorkspaceMemberController::class, 'store']);
        Route::put('/projects/{projectId}/members/{memberId}', [WorkspaceMemberController::class, 'update']);
        Route::delete('/projects/{projectId}/members/{memberId}', [WorkspaceMemberController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Epics
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/epics', [EpicController::class, 'index']);
        Route::post('/projects/{projectId}/epics', [EpicController::class, 'store']);
        Route::get('/epics/{epicId}', [EpicController::class, 'show']);
        Route::put('/epics/{epicId}', [EpicController::class, 'update']);
        Route::delete('/epics/{epicId}', [EpicController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Labels
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/labels', [LabelController::class, 'index']);
        Route::post('/projects/{projectId}/labels', [LabelController::class, 'store']);
        Route::put('/labels/{labelId}', [LabelController::class, 'update']);
        Route::delete('/labels/{labelId}', [LabelController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Kanban Columns
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/kanban-columns', [KanbanColumnController::class, 'index']);
        Route::post('/projects/{projectId}/kanban-columns', [KanbanColumnController::class, 'store']);
        Route::put('/projects/{projectId}/kanban-columns/reorder', [KanbanColumnController::class, 'reorder']);
        Route::put('/kanban-columns/{columnId}', [KanbanColumnController::class, 'update']);
        Route::delete('/kanban-columns/{columnId}', [KanbanColumnController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Tickets
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/tickets', [TicketController::class, 'index']);
        Route::post('/projects/{projectId}/tickets', [TicketController::class, 'store']);

        Route::get('/tickets/{ticketId}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticketId}', [TicketController::class, 'update']);
        Route::delete('/tickets/{ticketId}', [TicketController::class, 'destroy']);
        Route::get('/tickets/{ticketId}/insights', [TicketController::class, 'insights']);

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/notifications', [DashboardController::class, 'notifications']);

        /*
        |--------------------------------------------------------------------------
        | Comments
        |--------------------------------------------------------------------------
        */

        Route::get('/tickets/{ticketId}/comments', [TicketCommentController::class, 'index']);
        Route::post('/tickets/{ticketId}/comments', [TicketCommentController::class, 'store']);

        /*
        |--------------------------------------------------------------------------
        | Activity Logs
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/activity', [ActivityLogController::class, 'workspaceLogs']);
        Route::get('/tickets/{ticketId}/activity', [ActivityLogController::class, 'ticketLogs']);

        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */

        Route::get('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'index']);
        Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store']);
        Route::delete('/attachments/{attachment}', [TicketAttachmentController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Time Logs
        |--------------------------------------------------------------------------
        */

        Route::get('/tickets/{ticket}/time-logs', [TicketTimeLogController::class, 'ticketIndex']);
        Route::post('/tickets/{ticket}/time-logs', [TicketTimeLogController::class, 'store']);
        Route::get('/projects/{project}/timesheet', [TicketTimeLogController::class, 'workspaceTimesheet']);

        /*
        |--------------------------------------------------------------------------
        | Teams
        |--------------------------------------------------------------------------
        */

        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::get('/teams/{team}', [TeamController::class, 'show']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
        Route::post('/teams/{team}/members', [TeamController::class, 'addMember']);
        Route::put('/teams/{team}/members/{member}', [TeamController::class, 'updateMember']);
        Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'removeMember']);

        /*
        |--------------------------------------------------------------------------
        | Sprints
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/sprints', [SprintController::class, 'index']);
        Route::post('/projects/{projectId}/sprints', [SprintController::class, 'store']);
        Route::get('/sprints/{sprint}', [SprintController::class, 'show']);
        Route::put('/sprints/{sprint}', [SprintController::class, 'update']);
        Route::delete('/sprints/{sprint}', [SprintController::class, 'destroy']);
        Route::post('/sprints/{sprint}/start', [SprintController::class, 'start']);
        Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete']);

        /*
        |--------------------------------------------------------------------------
        | Workflows
        |--------------------------------------------------------------------------
        */

        Route::get('/projects/{projectId}/workflows', [WorkflowController::class, 'index']);
        Route::post('/projects/{projectId}/workflows', [WorkflowController::class, 'store']);
        Route::get('/workflows/{workflow}', [WorkflowController::class, 'show']);
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy']);
        Route::post('/workflows/{workflow}/states', [WorkflowController::class, 'addState']);
        Route::put('/workflows/{workflow}/states/{state}', [WorkflowController::class, 'updateState']);
        Route::delete('/workflows/{workflow}/states/{state}', [WorkflowController::class, 'removeState']);
        Route::post('/workflows/{workflow}/transitions', [WorkflowController::class, 'addTransition']);
        Route::delete('/workflows/{workflow}/transitions/{transition}', [WorkflowController::class, 'removeTransition']);
        Route::post('/workflows/{workflow}/activate', [WorkflowController::class, 'activate']);

    });

});
