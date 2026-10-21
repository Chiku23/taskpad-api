<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Label;
use OpenApi\Attributes as OA;

class LabelController extends Controller
{
    #[OA\Get(
        path: '/api/project/{projectSlug}/labels',
        summary: 'List all labels in a project',
        tags: ['Labels'],
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
    // List all labels in a project
    public function getProjectLabels(Request $request, $projectSlug)
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

        $labels = Label::where('project_id', $project->id)->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Labels found.',
            'data' => [
                'labels' => $labels,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/project/{projectSlug}/labels',
        summary: 'Create a label in a project',
        tags: ['Labels'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'color'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'color', type: 'string', maxLength: 7, example: '#e11d48'),
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
    // Create a label in a project
    public function createLabel(Request $request, $projectSlug)
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
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);

        // Check if label name already exists in this project
        $existingLabel = Label::where('project_id', $project->id)->where('name', $validatedData['name'])->first();

        if (!empty($existingLabel)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Label with this name already exists in this project.',
            ]);
        }

        // Create the label
        $label = Label::create([
            'project_id' => $project->id,
            'name' => $validatedData['name'],
            'color' => $validatedData['color'],
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Label created successfully.',
            'data' => [
                'label' => $label,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/labels/{labelId}',
        summary: 'Update label name or color',
        tags: ['Labels'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'labelId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'color', type: 'string', maxLength: 7),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Label not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Update label name or color
    public function updateLabel(Request $request, $labelId)
    {
        // Check if the label exists
        $label = Label::find($labelId);

        if (empty($label)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Label not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $label->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        // Update the label
        $label->update($validatedData);

        return response()->json([
            'status' => 'true',
            'message' => 'Label updated successfully.',
            'data' => [
                'label' => $label,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/labels/{labelId}',
        summary: 'Delete a label',
        tags: ['Labels'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'labelId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Label not found'),
        ]
    )]
    // Delete a label
    public function deleteLabel(Request $request, $labelId)
    {
        // Check if the label exists
        $label = Label::find($labelId);

        if (empty($label)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Label not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $projectMember = ProjectMember::where('project_id', $label->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Delete the label
        $label->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Label deleted successfully.',
        ]);
    }
}
