<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\ActivityLog;
use OpenApi\Attributes as OA;

class ActivityLogController extends Controller
{
    #[OA\Get(
        path: '/api/issues/{issueId}/activity',
        summary: 'Get the activity timeline for an issue',
        tags: ['Activity Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Issue not found'),
        ]
    )]
    // Get the activity timeline for an issue
    public function getIssueActivity(Request $request, $issueId)
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

        // Get activity logs for this issue
        $logs = ActivityLog::where('entity_type', 'issue')
            ->where('entity_id', $issue->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Activity logs found.',
            'data' => [
                'activity_logs' => $logs,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/project/{projectSlug}/activity',
        summary: 'Get recent activity across a project',
        tags: ['Activity Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    // Get recent activity across a project
    public function getProjectActivity(Request $request, $projectSlug)
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

        $issueIds = Issue::where('project_id', $project->id)->pluck('id');
        $sprintIds = Sprint::where('project_id', $project->id)->pluck('id');

        // Get activity logs for the project, its issues, and its sprints
        $logs = ActivityLog::where(function ($query) use ($project, $issueIds, $sprintIds) {
            $query->where(function ($subQuery) use ($project) {
                $subQuery->where('entity_type', 'project')->where('entity_id', $project->id);
            })->orWhere(function ($subQuery) use ($issueIds) {
                $subQuery->where('entity_type', 'issue')->whereIn('entity_id', $issueIds);
            })->orWhere(function ($subQuery) use ($sprintIds) {
                $subQuery->where('entity_type', 'sprint')->whereIn('entity_id', $sprintIds);
            });
        })
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Activity logs found.',
            'data' => [
                'activity_logs' => $logs,
            ],
        ]);
    }
}
