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

/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/

Route::post('/store', [AuthController::class, 'store']);
Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| 2FA Login Verification
|--------------------------------------------------------------------------
| Public because the user does not have a real auth token yet.
*/

Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactorLogin']);

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
    | 2FA Settings Routes
    |--------------------------------------------------------------------------
    */

    Route::post('/2fa/setup', [AuthController::class, 'setupTwoFactor']);
    Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);
    Route::post('/2fa/recovery-codes/regenerate', [AuthController::class, 'regenerateRecoveryCodes']);

    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Workspaces
    |--------------------------------------------------------------------------
    */

    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::post('/workspaces', [WorkspaceController::class, 'store']);
    Route::get('/workspaces/{id}', [WorkspaceController::class, 'show']);
    Route::put('/workspaces/{id}', [WorkspaceController::class, 'update']);
    Route::delete('/workspaces/{id}', [WorkspaceController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Workspace Members
    |--------------------------------------------------------------------------
    */

    Route::get('/workspaces/{workspaceId}/members', [WorkspaceMemberController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/members', [WorkspaceMemberController::class, 'store']);
    Route::put('/workspaces/{workspaceId}/members/{memberId}', [WorkspaceMemberController::class, 'update']);
    Route::delete('/workspaces/{workspaceId}/members/{memberId}', [WorkspaceMemberController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Epics
    |--------------------------------------------------------------------------
    */

    Route::get('/workspaces/{workspaceId}/epics', [EpicController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/epics', [EpicController::class, 'store']);
    Route::get('/epics/{epicId}', [EpicController::class, 'show']);
    Route::put('/epics/{epicId}', [EpicController::class, 'update']);
    Route::delete('/epics/{epicId}', [EpicController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Dynamic Kanban Columns
    |--------------------------------------------------------------------------
    */

    Route::get('/workspaces/{workspaceId}/kanban-columns', [KanbanColumnController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/kanban-columns', [KanbanColumnController::class, 'store']);
    Route::put('/workspaces/{workspaceId}/kanban-columns/reorder', [KanbanColumnController::class, 'reorder']);
    Route::put('/kanban-columns/{columnId}', [KanbanColumnController::class, 'update']);
    Route::delete('/kanban-columns/{columnId}', [KanbanColumnController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Tickets
    |--------------------------------------------------------------------------
    */

    Route::get('/workspaces/{workspaceId}/tickets', [TicketController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/tickets', [TicketController::class, 'store']);

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

    Route::get('/workspaces/{workspaceId}/activity', [ActivityLogController::class, 'workspaceLogs']);
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

    Route::get('/workspaces/{workspace}/timesheet', [TicketTimeLogController::class, 'workspaceTimesheet']);
});