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

Route::post('/store', [AuthController::class, 'store']);
Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::post('/workspaces', [WorkspaceController::class, 'store']);
    Route::get('/workspaces/{id}', [WorkspaceController::class, 'show']);
    Route::put('/workspaces/{id}', [WorkspaceController::class, 'update']);
    Route::delete('/workspaces/{id}', [WorkspaceController::class, 'destroy']);

    Route::get('/workspaces/{workspaceId}/members', [WorkspaceMemberController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/members', [WorkspaceMemberController::class, 'store']);
    Route::put('/workspaces/{workspaceId}/members/{memberId}', [WorkspaceMemberController::class, 'update']);
    Route::delete('/workspaces/{workspaceId}/members/{memberId}', [WorkspaceMemberController::class, 'destroy']);

    Route::get('/workspaces/{workspaceId}/tickets', [TicketController::class, 'index']);
    Route::post('/workspaces/{workspaceId}/tickets', [TicketController::class, 'store']);

    Route::get('/tickets/{ticketId}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticketId}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticketId}', [TicketController::class, 'destroy']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/tickets/{ticketId}/comments', [TicketCommentController::class, 'index']);
    Route::post('/tickets/{ticketId}/comments', [TicketCommentController::class, 'store']);

    Route::get('/workspaces/{workspaceId}/activity', [ActivityLogController::class, 'workspaceLogs']);
    Route::get('/tickets/{ticketId}/activity', [ActivityLogController::class, 'ticketLogs']);

    Route::get('/tickets/{ticketId}/insights', [TicketController::class, 'insights']);

    Route::get('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'index']);
    Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store']);
    Route::delete('/attachments/{attachment}', [TicketAttachmentController::class, 'destroy']);
});