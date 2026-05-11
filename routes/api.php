<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\TicketController;

Route::post('/store', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

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
});