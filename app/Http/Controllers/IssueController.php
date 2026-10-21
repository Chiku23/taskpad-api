<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Issue;
use App\Models\ActivityLog;
use OpenApi\Attributes as OA;

class IssueController extends Controller
{
    #[OA\Get(
        path: '/api/project/{projectSlug}/issues',
        summary: 'List all issues in a project',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['backlog', 'todo', 'in_progress', 'in_review', 'done'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['epic', 'story', 'task', 'bug', 'subtask'])),
            new OA\Parameter(name: 'assignee', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sprint_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['lowest', 'low', 'medium', 'high', 'highest'])),
            new OA\Parameter(name: 'label', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    // List all issues in a project with optional filters
    public function getProjectIssues(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->orWhere('key', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Build query with optional filters
        $query = Issue::where('project_id', $project->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('assignee')) {
            $query->whereHas('assignees', function ($q) use ($request) {
                $q->where('user_id', $request->assignee);
            });
        }

        if ($request->has('sprint_id')) {
            $query->where('sprint_id', $request->sprint_id);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('label')) {
            $query->whereHas('labels', function ($q) use ($request) {
                $q->where('label_id', $request->label);
            });
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $issues = $query->with(['assignees', 'labels'])->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Issues found.',
            'data' => [
                'issues' => $issues,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/project/{projectSlug}/issues',
        summary: 'Create a new issue in a project',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 512),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['epic', 'story', 'task', 'bug', 'subtask']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['lowest', 'low', 'medium', 'high', 'highest']),
                    new OA\Property(property: 'story_points', type: 'integer'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'parent_id', type: 'integer'),
                    new OA\Property(property: 'sprint_id', type: 'integer'),
                    new OA\Property(property: 'assignee_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'label_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Create a new issue in a project
    public function createIssue(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->orWhere('key', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'title' => 'required|string|max:512',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:epic,story,task,bug,subtask',
            'priority' => 'nullable|string|in:lowest,low,medium,high,highest',
            'story_points' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
            'parent_id' => 'nullable|integer|exists:issues,id',
            'sprint_id' => 'nullable|integer|exists:sprints,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:users,id',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:labels,id',
        ]);

        // Generate the issue code (project key + incremented count)
        $issueCount = Issue::where('project_id', $project->id)->count();
        $code = $project->key . '-' . ($issueCount + 1);

        // Create the issue
        $issue = Issue::create([
            'project_id' => $project->id,
            'code' => $code,
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'type' => $validatedData['type'] ?? 'task',
            'status' => 'backlog',
            'priority' => $validatedData['priority'] ?? 'medium',
            'story_points' => $validatedData['story_points'] ?? null,
            'due_date' => $validatedData['due_date'] ?? null,
            'parent_id' => $validatedData['parent_id'] ?? null,
            'sprint_id' => $validatedData['sprint_id'] ?? null,
            'reporter_id' => $user->id,
        ]);

        // Attach assignees if provided
        if (!empty($validatedData['assignee_ids'])) {
            $issue->assignees()->attach($validatedData['assignee_ids']);
        }

        // Attach labels if provided
        if (!empty($validatedData['label_ids'])) {
            $issue->labels()->attach($validatedData['label_ids']);
        }

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'created',
            'after' => $issue->toArray(),
            'created_at' => now(),
        ]);

        $issue->load(['assignees', 'labels']);

        return response()->json([
            'status' => 'true',
            'message' => 'Issue created successfully.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/issues/{issueId}',
        summary: 'Get full issue details',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
        ]
    )]
    // Get full issue detail
    public function getIssue(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::with(['assignees', 'labels', 'comments', 'attachments', 'reporter', 'sprint', 'parent', 'children'])->find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        return response()->json([
            'status' => 'true',
            'message' => 'Issue found.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/issues/{issueId}',
        summary: 'Update issue fields',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 512),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['epic', 'story', 'task', 'bug', 'subtask']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['lowest', 'low', 'medium', 'high', 'highest']),
                    new OA\Property(property: 'story_points', type: 'integer'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'parent_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Update issue fields
    public function updateIssue(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'title' => 'nullable|string|max:512',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:epic,story,task,bug,subtask',
            'priority' => 'nullable|string|in:lowest,low,medium,high,highest',
            'story_points' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
            'parent_id' => 'nullable|integer|exists:issues,id',
        ]);

        $before = $issue->toArray();

        // Update the issue
        $issue->update($validatedData);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'updated',
            'before' => $before,
            'after' => $issue->toArray(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Issue updated successfully.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/issues/{issueId}',
        summary: 'Delete an issue',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
        ]
    )]
    // Delete an issue
    public function deleteIssue(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Log the activity before deletion
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'deleted',
            'before' => $issue->toArray(),
            'created_at' => now(),
        ]);

        // Delete the issue
        $issue->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Issue deleted successfully.',
        ]);
    }

    #[OA\Patch(
        path: '/api/issues/{issueId}/status',
        summary: 'Move issue to a new status/column',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['backlog', 'todo', 'in_progress', 'in_review', 'done']),
                    new OA\Property(property: 'board_column_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Move issue to a new status/column
    public function updateIssueStatus(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'status' => 'required|string|in:backlog,todo,in_progress,in_review,done',
            'board_column_id' => 'nullable|integer|exists:board_columns,id',
        ]);

        $before = ['status' => $issue->status, 'board_column_id' => $issue->board_column_id];

        // Update status and optionally the board column
        $issue->update($validatedData);

        // Log the status change
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'status_changed',
            'before' => $before,
            'after' => ['status' => $issue->status, 'board_column_id' => $issue->board_column_id],
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Issue status updated successfully.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }

    #[OA\Patch(
        path: '/api/issues/{issueId}/assignees',
        summary: 'Set or update issue assignees',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['assignee_ids'],
                properties: [
                    new OA\Property(property: 'assignee_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Set/update assignees on an issue
    public function updateIssueAssignees(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'assignee_ids' => 'required|array',
            'assignee_ids.*' => 'integer|exists:users,id',
        ]);

        $before = $issue->assignees->pluck('id')->toArray();

        // Sync assignees (replaces existing set)
        $issue->assignees()->sync($validatedData['assignee_ids']);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'assignees_updated',
            'before' => ['assignee_ids' => $before],
            'after' => ['assignee_ids' => $validatedData['assignee_ids']],
            'created_at' => now(),
        ]);

        $issue->load('assignees');

        return response()->json([
            'status' => 'true',
            'message' => 'Issue assignees updated successfully.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }

    #[OA\Patch(
        path: '/api/issues/{issueId}/sprint',
        summary: 'Move issue to a sprint or back to backlog',
        tags: ['Issues'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'sprint_id', type: 'integer', nullable: true, description: 'Set to null to move to backlog'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Move issue to a sprint or back to backlog
    public function updateIssueSprint(Request $request, $issueId)
    {
        // Check if the issue exists
        $issue = Issue::find($issueId);

        if (empty($issue)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Issue not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'sprint_id' => 'nullable|integer|exists:sprints,id',
        ]);

        $before = ['sprint_id' => $issue->sprint_id];

        // Update sprint assignment
        $issue->update(['sprint_id' => $validatedData['sprint_id'] ?? null]);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issue->id,
            'action' => 'sprint_changed',
            'before' => $before,
            'after' => ['sprint_id' => $issue->sprint_id],
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Issue sprint updated successfully.',
            'data' => [
                'issue' => $issue,
            ],
        ]);
    }
}
