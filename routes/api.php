<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;

// Health Check
Route::get('/health', [HealthController::class, 'index']);    

// Authentication
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Organization Routes
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::post('/organization/create', [OrganizationController::class, 'store']);
    Route::get('/organization/{orgSlug}', [OrganizationController::class, 'getOrganization']);
    Route::post('/organization/{orgSlug}', [OrganizationController::class, 'updateOrganization']);
    Route::delete('/organization/{orgSlug}', [OrganizationController::class, 'deleteOrganization']);
    Route::get('/organization/{orgSlug}/members', [OrganizationController::class, 'getOrganizationMembers']);
    Route::post('/organization/{orgSlug}/members', [OrganizationController::class, 'addOrganizationMember']);
    Route::put('/organization/{orgSlug}/members/{user}', [OrganizationController::class, 'updateOrganizationMember']);
    Route::delete('/organization/{orgSlug}/members/{user}', [OrganizationController::class, 'removeOrganizationMember']);

    // Project Routes
    Route::get('/organization/{orgSlug}/projects', [ProjectController::class, 'getOrganizationProjects']);
    Route::post('/organization/{orgSlug}/projects', [ProjectController::class, 'createOrganizationProject']);
    
});