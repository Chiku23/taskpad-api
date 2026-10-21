<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Issue;
use App\Models\Comment;
use App\Models\ProjectMember;
use App\Models\ActivityLog;
use OpenApi\Attributes as OA;

class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/issues/{issueId}/comments',
        summary: 'List comments on an issue',
        tags: ['Comments'],
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
    // List comments on an issue
    public function getIssueComments(Request $request, $issueId)
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

        // Get top-level comments with replies
        $comments = Comment::where('issue_id', $issueId)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'true',
            'message' => 'Comments found.',
            'data' => [
                'comments' => $comments,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/issues/{issueId}/comments',
        summary: 'Add a comment to an issue',
        tags: ['Comments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
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
    // Add a comment to an issue
    public function addComment(Request $request, $issueId)
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
            'body' => 'required|string',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        // Create the comment
        $comment = Comment::create([
            'issue_id' => $issueId,
            'user_id' => $user->id,
            'parent_id' => $validatedData['parent_id'] ?? null,
            'body' => $validatedData['body'],
        ]);

        // Log the activity
        ActivityLog::create([
            'user_id' => $user->id,
            'entity_type' => 'issue',
            'entity_id' => $issueId,
            'action' => 'commented',
            'after' => ['comment_id' => $comment->id, 'body' => $comment->body],
            'created_at' => now(),
        ]);

        $comment->load('user');

        return response()->json([
            'status' => 'true',
            'message' => 'Comment added successfully.',
            'data' => [
                'comment' => $comment,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/comments/{commentId}',
        summary: 'Edit a comment (own comments only)',
        tags: ['Comments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Edit a comment (own comments only)
    public function updateComment(Request $request, $commentId)
    {
        // Check if the comment exists
        $comment = Comment::find($commentId);

        if (empty($comment)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Comment not found.',
            ]);
        }

        // Check if the user owns this comment
        $user = $request->user();
        if ($comment->user_id !== $user->id) {
            return response()->json([
                'status' => 'false',
                'message' => 'User can only edit their own comments.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'body' => 'required|string',
        ]);

        // Update the comment
        $comment->update($validatedData);

        return response()->json([
            'status' => 'true',
            'message' => 'Comment updated successfully.',
            'data' => [
                'comment' => $comment,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/comments/{commentId}',
        summary: 'Delete a comment (own or admin)',
        tags: ['Comments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Comment not found'),
        ]
    )]
    // Delete a comment (own or project admin)
    public function deleteComment(Request $request, $commentId)
    {
        // Check if the comment exists
        $comment = Comment::find($commentId);

        if (empty($comment)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Comment not found.',
            ]);
        }

        // Check if the user owns this comment or is a project admin
        $user = $request->user();
        $issue = Issue::find($comment->issue_id);
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if ($comment->user_id !== $user->id && (empty($projectMember) || $projectMember->role !== 'admin')) {
            return response()->json([
                'status' => 'false',
                'message' => 'User can only delete their own comments or must be a project admin.',
            ]);
        }

        // Delete the comment
        $comment->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
