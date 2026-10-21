<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ActivityLogController;

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
    Route::get('/project/{projectSlug}', [ProjectController::class, 'getProject']);
    Route::put('/project/{projectSlug}', [ProjectController::class, 'updateProject']);
    Route::delete('/project/{projectSlug}', [ProjectController::class, 'deleteProject']);
    Route::get('/project/{projectSlug}/members', [ProjectController::class, 'getProjectMembers']);
    Route::post('/project/{projectSlug}/members', [ProjectController::class, 'addProjectMember']);
    Route::put('/project/{projectSlug}/members/{user}', [ProjectController::class, 'updateProjectMember']);
    Route::delete('/project/{projectSlug}/members/{user}', [ProjectController::class, 'removeProjectMember']);

    // Issue Routes
    Route::get('/project/{projectSlug}/issues', [IssueController::class, 'getProjectIssues']);
    Route::post('/project/{projectSlug}/issues', [IssueController::class, 'createIssue']);
    Route::get('/projects/{projectSlug}/issues', [IssueController::class, 'getProjectIssues']);
    Route::post('/projects/{projectSlug}/issues', [IssueController::class, 'createIssue']);
    Route::get('/issues/{issueId}', [IssueController::class, 'getIssue']);
    Route::put('/issues/{issueId}', [IssueController::class, 'updateIssue']);
    Route::delete('/issues/{issueId}', [IssueController::class, 'deleteIssue']);
    Route::patch('/issues/{issueId}/status', [IssueController::class, 'updateIssueStatus']);
    Route::patch('/issues/{issueId}/assignees', [IssueController::class, 'updateIssueAssignees']);
    Route::patch('/issues/{issueId}/sprint', [IssueController::class, 'updateIssueSprint']);

    // Board Routes
    Route::get('/project/{projectSlug}/board', [BoardController::class, 'getBoard']);
    Route::put('/project/{projectSlug}/board/columns', [BoardController::class, 'reorderColumns']);
    Route::post('/project/{projectSlug}/board/columns', [BoardController::class, 'addColumn']);
    Route::get('/projects/{projectSlug}/board', [BoardController::class, 'getBoard']);
    Route::put('/projects/{projectSlug}/board/columns', [BoardController::class, 'reorderColumns']);
    Route::post('/projects/{projectSlug}/board/columns', [BoardController::class, 'addColumn']);
    Route::put('/board-columns/{columnId}', [BoardController::class, 'updateColumn']);
    Route::delete('/board-columns/{columnId}', [BoardController::class, 'deleteColumn']);

    // Sprint Routes
    Route::get('/project/{projectSlug}/sprints', [SprintController::class, 'getProjectSprints']);
    Route::post('/project/{projectSlug}/sprints', [SprintController::class, 'createSprint']);
    Route::get('/projects/{projectSlug}/sprints', [SprintController::class, 'getProjectSprints']);
    Route::post('/projects/{projectSlug}/sprints', [SprintController::class, 'createSprint']);
    Route::get('/sprints/{sprintId}', [SprintController::class, 'getSprint']);
    Route::put('/sprints/{sprintId}', [SprintController::class, 'updateSprint']);
    Route::post('/sprints/{sprintId}/start', [SprintController::class, 'startSprint']);
    Route::post('/sprints/{sprintId}/complete', [SprintController::class, 'completeSprint']);
    Route::delete('/sprints/{sprintId}', [SprintController::class, 'deleteSprint']);

    // Comment Routes
    Route::get('/issues/{issueId}/comments', [CommentController::class, 'getIssueComments']);
    Route::post('/issues/{issueId}/comments', [CommentController::class, 'addComment']);
    Route::put('/comments/{commentId}', [CommentController::class, 'updateComment']);
    Route::delete('/comments/{commentId}', [CommentController::class, 'deleteComment']);

    // Label Routes
    Route::get('/project/{projectSlug}/labels', [LabelController::class, 'getProjectLabels']);
    Route::post('/project/{projectSlug}/labels', [LabelController::class, 'createLabel']);
    Route::get('/projects/{projectSlug}/labels', [LabelController::class, 'getProjectLabels']);
    Route::post('/projects/{projectSlug}/labels', [LabelController::class, 'createLabel']);
    Route::put('/labels/{labelId}', [LabelController::class, 'updateLabel']);
    Route::delete('/labels/{labelId}', [LabelController::class, 'deleteLabel']);

    // Attachment Routes
    Route::post('/issues/{issueId}/attachments', [AttachmentController::class, 'uploadAttachment']);
    Route::get('/attachments/{attachmentId}', [AttachmentController::class, 'getAttachment']);
    Route::delete('/attachments/{attachmentId}', [AttachmentController::class, 'deleteAttachment']);

    // Activity Log Routes
    Route::get('/issues/{issueId}/activity', [ActivityLogController::class, 'getIssueActivity']);
    Route::get('/project/{projectSlug}/activity', [ActivityLogController::class, 'getProjectActivity']);
    Route::get('/projects/{projectSlug}/activity', [ActivityLogController::class, 'getProjectActivity']);
});