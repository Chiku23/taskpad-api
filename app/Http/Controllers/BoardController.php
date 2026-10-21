<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Board;
use App\Models\BoardColumn;
use OpenApi\Attributes as OA;

class BoardController extends Controller
{
    #[OA\Get(
        path: '/api/project/{projectSlug}/board',
        summary: 'Get the board with columns and issues',
        tags: ['Boards'],
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
    // Get the board with columns and issues for a project
    public function getBoard(Request $request, $projectSlug)
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

        // Get or create the board
        $board = Board::where('project_id', $project->id)->first();

        if (empty($board)) {
            // Auto-create a default board with standard columns
            $board = Board::create([
                'project_id' => $project->id,
                'name' => 'Main Board',
            ]);

            $defaultColumns = [
                ['name' => 'Backlog', 'mapped_status' => 'backlog', 'position' => 0],
                ['name' => 'To Do', 'mapped_status' => 'todo', 'position' => 1],
                ['name' => 'In Progress', 'mapped_status' => 'in_progress', 'position' => 2],
                ['name' => 'In Review', 'mapped_status' => 'in_review', 'position' => 3],
                ['name' => 'Done', 'mapped_status' => 'done', 'position' => 4],
            ];

            foreach ($defaultColumns as $col) {
                BoardColumn::create(array_merge($col, ['board_id' => $board->id]));
            }
        }

        // Load columns with their issues
        $board->load(['columns.issues.assignees']);

        return response()->json([
            'status' => 'true',
            'message' => 'Board found.',
            'data' => [
                'board' => $board,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/project/{projectSlug}/board/columns',
        summary: 'Reorder board columns',
        tags: ['Boards'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['columns'],
                properties: [
                    new OA\Property(
                        property: 'columns',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'position', type: 'integer'),
                            ]
                        )
                    ),
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
    // Reorder board columns
    public function reorderColumns(Request $request, $projectSlug)
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
            'columns' => 'required|array',
            'columns.*.id' => 'required|integer|exists:board_columns,id',
            'columns.*.position' => 'required|integer|min:0',
        ]);

        // Update positions
        foreach ($validatedData['columns'] as $columnData) {
            BoardColumn::where('id', $columnData['id'])->update(['position' => $columnData['position']]);
        }

        // Return updated board
        $board = Board::where('project_id', $project->id)->first();
        $board->load('columns');

        return response()->json([
            'status' => 'true',
            'message' => 'Columns reordered successfully.',
            'data' => [
                'board' => $board,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/project/{projectSlug}/board/columns',
        summary: 'Add a custom column to the board',
        tags: ['Boards'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'mapped_status'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'mapped_status', type: 'string', enum: ['backlog', 'todo', 'in_progress', 'in_review', 'done']),
                    new OA\Property(property: 'position', type: 'integer'),
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
    // Add a custom column to the board
    public function addColumn(Request $request, $projectSlug)
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
            'mapped_status' => 'required|string|in:backlog,todo,in_progress,in_review,done',
            'position' => 'nullable|integer|min:0',
        ]);

        // Get the board
        $board = Board::where('project_id', $project->id)->first();

        if (empty($board)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Board not found. Access the board first to auto-create it.',
            ]);
        }

        // Determine position (append to end if not specified)
        $position = $validatedData['position'] ?? BoardColumn::where('board_id', $board->id)->max('position') + 1;

        // Create the column
        $column = BoardColumn::create([
            'board_id' => $board->id,
            'name' => $validatedData['name'],
            'mapped_status' => $validatedData['mapped_status'],
            'position' => $position,
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Column added successfully.',
            'data' => [
                'column' => $column,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/board-columns/{columnId}',
        summary: 'Rename a board column',
        tags: ['Boards'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'columnId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Column not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Rename a board column
    public function updateColumn(Request $request, $columnId)
    {
        // Check if the column exists
        $column = BoardColumn::find($columnId);

        if (empty($column)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Column not found.',
            ]);
        }

        // Check if the user has access to the project via board
        $user = $request->user();
        $board = Board::find($column->board_id);
        $projectMember = ProjectMember::where('project_id', $board->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        // Update the column name
        $column->update($validatedData);

        return response()->json([
            'status' => 'true',
            'message' => 'Column updated successfully.',
            'data' => [
                'column' => $column,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/board-columns/{columnId}',
        summary: 'Remove a board column',
        tags: ['Boards'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'columnId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Column not found'),
        ]
    )]
    // Remove a board column
    public function deleteColumn(Request $request, $columnId)
    {
        // Check if the column exists
        $column = BoardColumn::find($columnId);

        if (empty($column)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Column not found.',
            ]);
        }

        // Check if the user has access to the project via board
        $user = $request->user();
        $board = Board::find($column->board_id);
        $projectMember = ProjectMember::where('project_id', $board->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Delete the column
        $column->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Column deleted successfully.',
        ]);
    }
}
