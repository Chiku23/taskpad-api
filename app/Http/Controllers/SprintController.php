<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Sprint;
use App\Models\Issue;
use App\Models\ActivityLog;
use OpenApi\Attributes as OA;

class SprintController extends Controller
{
    #[OA\Get(
        path: '/api/project/{projectSlug}/sprints',
        summary: 'List all sprints for a project',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    // List all sprints for a project
    public function getProjectSprints(Request $request, $projectSlug)
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

        $sprints = Sprint::where('project_id', $project->id)->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Sprints found.',
            'data' => [
                'sprints' => $sprints,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/project/{projectSlug}/sprints',
        summary: 'Create a new sprint',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'goal', type: 'string'),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date'),
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
    // Create a new sprint
    public function createSprint(Request $request, $projectSlug)
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
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        // Create the sprint
        $sprint = Sprint::create([
            'project_id' => $project->id,
            'name' => $validatedData['name'],
            'goal' => $validatedData['goal'] ?? null,
            'status' => 'planned',
            'starts_at' => $validatedData['starts_at'] ?? null,
            'ends_at' => $validatedData['ends_at'] ?? null,
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint created successfully.',
            'data' => [
                'sprint' => $sprint,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/sprints/{sprintId}',
        summary: 'Get sprint details and issues',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sprint not found'),
        ]
    )]
    // Get sprint details and its issues
    public function getSprint(Request $request, $sprintId)
    {
        // Check if the sprint exists
        $sprint = Sprint::with(['issues.assignees'])->find($sprintId);

        if (empty($sprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Sprint not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $sprint->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint found.',
            'data' => [
                'sprint' => $sprint,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/sprints/{sprintId}',
        summary: 'Update sprint name, goal, dates',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'goal', type: 'string'),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sprint not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Update sprint details
    public function updateSprint(Request $request, $sprintId)
    {
        // Check if the sprint exists
        $sprint = Sprint::find($sprintId);

        if (empty($sprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Sprint not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $sprint->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'goal' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        // Update the sprint
        $sprint->update($validatedData);

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint updated successfully.',
            'data' => [
                'sprint' => $sprint,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/sprints/{sprintId}/start',
        summary: 'Activate a planned sprint',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 400, description: 'Bad Request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sprint not found'),
        ]
    )]
    // Start a planned sprint
    public function startSprint(Request $request, $sprintId)
    {
        // Check if the sprint exists
        $sprint = Sprint::find($sprintId);

        if (empty($sprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Sprint not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $sprint->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Only planned sprints can be started
        if ($sprint->status !== 'planned') {
            return response()->json([
                'status' => 'false',
                'message' => 'Only planned sprints can be started.',
            ]);
        }

        // Check if there is already an active sprint in this project
        $activeSprint = Sprint::where('project_id', $sprint->project_id)->where('status', 'active')->first();

        if (!empty($activeSprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'There is already an active sprint in this project. Complete it before starting a new one.',
            ]);
        }

        // Activate the sprint
        $sprint->update([
            'status' => 'active',
            'starts_at' => $sprint->starts_at ?? now()->toDateString(),
        ]);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'sprint',
            'entity_id' => $sprint->id,
            'action' => 'started',
            'after' => $sprint->toArray(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint started successfully.',
            'data' => [
                'sprint' => $sprint,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/sprints/{sprintId}/complete',
        summary: 'Complete a sprint and move unfinished issues to backlog',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 400, description: 'Bad Request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sprint not found'),
        ]
    )]
    // Complete a sprint and move unfinished issues to backlog
    public function completeSprint(Request $request, $sprintId)
    {
        // Check if the sprint exists
        $sprint = Sprint::find($sprintId);

        if (empty($sprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Sprint not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $sprint->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Only active sprints can be completed
        if ($sprint->status !== 'active') {
            return response()->json([
                'status' => 'false',
                'message' => 'Only active sprints can be completed.',
            ]);
        }

        // Move unfinished issues back to backlog (remove sprint assignment)
        $unfinishedIssues = Issue::where('sprint_id', $sprint->id)
            ->where('status', '!=', 'done')
            ->get();

        foreach ($unfinishedIssues as $issue) {
            $issue->update([
                'sprint_id' => null,
                'status' => 'backlog',
            ]);
        }

        // Mark the sprint as completed
        $sprint->update([
            'status' => 'completed',
            'ends_at' => $sprint->ends_at ?? now()->toDateString(),
        ]);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'sprint',
            'entity_id' => $sprint->id,
            'action' => 'completed',
            'after' => array_merge($sprint->toArray(), [
                'unfinished_issues_moved' => $unfinishedIssues->count(),
            ]),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint completed successfully. ' . $unfinishedIssues->count() . ' unfinished issue(s) moved to backlog.',
            'data' => [
                'sprint' => $sprint,
                'unfinished_issues_moved' => $unfinishedIssues->count(),
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/sprints/{sprintId}',
        summary: 'Delete a planned sprint',
        tags: ['Sprints'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 400, description: 'Bad Request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Sprint not found'),
        ]
    )]
    // Delete a sprint (planned only)
    public function deleteSprint(Request $request, $sprintId)
    {
        // Check if the sprint exists
        $sprint = Sprint::find($sprintId);

        if (empty($sprint)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Sprint not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $sprint->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Only planned sprints can be deleted
        if ($sprint->status !== 'planned') {
            return response()->json([
                'status' => 'false',
                'message' => 'Only planned sprints can be deleted. Complete or cancel active sprints first.',
            ]);
        }

        // Delete the sprint
        $sprint->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Sprint deleted successfully.',
        ]);
    }
}
