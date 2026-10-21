<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Issue;
use App\Models\Attachment;
use App\Models\ProjectMember;
use OpenApi\Attributes as OA;

class AttachmentController extends Controller
{
    #[OA\Post(
        path: '/api/issues/{issueId}/attachments',
        summary: 'Upload a file attachment to an issue',
        tags: ['Attachments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'issueId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['file'],
                        properties: [
                            new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Issue not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Upload a file attachment to an issue
    public function uploadAttachment(Request $request, $issueId)
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
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');

        // Store the file
        $path = $file->store('attachments/' . $issue->project_id . '/' . $issueId, 'public');

        // Create the attachment record
        $attachment = Attachment::create([
            'issue_id' => $issueId,
            'uploaded_by' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'true',
            'message' => 'Attachment uploaded successfully.',
            'data' => [
                'attachment' => $attachment,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/attachments/{attachmentId}',
        summary: 'Get attachment details or download URL',
        tags: ['Attachments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Attachment not found'),
        ]
    )]
    // Get attachment details or download URL
    public function getAttachment(Request $request, $attachmentId)
    {
        // Check if the attachment exists
        $attachment = Attachment::find($attachmentId);

        if (empty($attachment)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Attachment not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $issue = Issue::find($attachment->issue_id);
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        return response()->json([
            'status' => 'true',
            'message' => 'Attachment found.',
            'data' => [
                'attachment' => $attachment,
                'download_url' => asset('storage/' . $attachment->path),
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/attachments/{attachmentId}',
        summary: 'Delete an attachment',
        tags: ['Attachments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Attachment not found'),
        ]
    )]
    // Delete an attachment
    public function deleteAttachment(Request $request, $attachmentId)
    {
        // Check if the attachment exists
        $attachment = Attachment::find($attachmentId);

        if (empty($attachment)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Attachment not found.',
            ]);
        }

        // Check if the user has access to the project
        $user = $request->user();
        $issue = Issue::find($attachment->issue_id);
        $projectMember = ProjectMember::where('project_id', $issue->project_id)->where('user_id', $user->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Delete the file from storage
        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->path);

        // Delete the attachment record
        $attachment->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}
