<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/store', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'getProfile']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);