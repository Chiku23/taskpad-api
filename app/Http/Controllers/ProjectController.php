<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        path: '/api/organization/{orgSlug}/projects',
        summary: 'List projects in an organization',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Organization not found'),
        ]
    )]
    // Get all projects in an organization
    public function getOrganizationProjects(Request $request, $orgSlug)
    {
        // Check if the organization exists
        $organization = Organization::where('slug', $orgSlug)->first();

        if (empty($organization)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ]);
        }

        // Check if the user has access to the organization
        $user = $request->user();
        $organizationMember = OrganizationMember::where('organization_id', $organization->id)->where('user_id', $user->id)->first();

        if (empty($organizationMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this organization.',
            ]);
        }

        // Get the projects in the organization
        $projects = Project::where('organization_id', $organization->id)->get();

        // Return the projects
        return response()->json([
            'status' => 'true',
            'message' => 'Projects found.',
            'data' => [
                'projects' => $projects,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/organization/{orgSlug}/projects',
        summary: 'Create a new project in an organization',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'key'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'key', type: 'string', maxLength: 255),
                    new OA\Property(property: 'lead', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Organization not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Create a new project in an organization
    public function createOrganizationProject(Request $request, $orgSlug)
    {
        // Check if the organization exists
        $organization = Organization::where('slug', $orgSlug)->first();

        if (empty($organization)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ]);
        }

        // Check if the user has access to the organization
        $user = $request->user();
        $organizationMember = OrganizationMember::where('organization_id', $organization->id)->where('user_id', $user->id)->first();

        if (empty($organizationMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this organization.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255',
            'lead' => 'nullable|integer',
        ]);

        // Create the project
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => $validatedData['name'],
            'key' => $validatedData['key'],
            'slug' => Str::slug($validatedData['name']),
            'lead_id' => $validatedData['lead'] ?? null,
        ]);

        // Add the project lead as a member of the project if lead is not null
        if (!empty($validatedData['lead'])) {
            ProjectMember::create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'user_id' => $validatedData['lead'],
                'role' => 'lead',
            ]);
        }

        // Return the project
        return response()->json([
            'status' => 'true',
            'message' => 'Project created successfully.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }
    
    #[OA\Get(
        path: '/api/project/{projectSlug}',
        summary: 'Get a specific project',
        tags: ['Projects'],
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
    // Get a specific project
    public function getProject(Request $request, $projectSlug)
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

        // Return the project
        return response()->json([
            'status' => 'true',
            'message' => 'Project found.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/project/{projectSlug}',
        summary: 'Update a specific project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'key', type: 'string', maxLength: 255),
                    new OA\Property(property: 'lead', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Update a specific project
    public function updateProject(Request $request, $projectSlug)
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
            'name' => 'nullable|string|max:255',
            'key' => 'nullable|string|max:255',
            'lead' => 'nullable|integer',
        ]);

        $updateData = [];
        if (isset($validatedData['name'])) {
            $updateData['name'] = $validatedData['name'];
            $updateData['slug'] = Str::slug($validatedData['name']);
        }
        if (isset($validatedData['key'])) {
            $updateData['key'] = $validatedData['key'];
        }
        if (array_key_exists('lead', $validatedData)) {
            $updateData['lead_id'] = $validatedData['lead'];
        }

        // Update the project
        $project->update($updateData);

        // Update the project lead as a member of the project if lead is not null
        if (!empty($validatedData['lead'])) {
            ProjectMember::where('project_id', $project->id)->where('user_id', $project->lead_id)->delete();
            ProjectMember::create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'user_id' => $validatedData['lead'],
                'role' => 'lead',
            ]);
        }

        // Return the project
        return response()->json([
            'status' => 'true',
            'message' => 'Project updated successfully.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/project/{projectSlug}',
        summary: 'Delete a specific project',
        tags: ['Projects'],
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
    // Delete a specific project
    public function deleteProject(Request $request, $projectSlug)
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

        // Delete the project
        $project->delete();

        // Return the project
        return response()->json([
            'status' => 'true',
            'message' => 'Project deleted successfully.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/project/{projectSlug}/members',
        summary: 'List project members',
        tags: ['Projects'],
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
    // Get all members of a specific project
    public function getProjectMembers(Request $request, $projectSlug)
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

        // Get the members of the project
        $members = ProjectMember::where('project_id', $project->id)->get();

        // Return the members
        return response()->json([
            'status' => 'true',
            'message' => 'Members found.',
            'data' => [
                'members' => $members,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/project/{projectSlug}/members',
        summary: 'Add a new member to a project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'role'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'role', type: 'string', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Add a new member to a specific project
    public function addProjectMember(Request $request, $projectSlug)
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
            'user_id' => 'required|integer',
            'role' => 'required|string|max:255',
        ]);

        // Add the member to the project
        $projectMember = ProjectMember::create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'user_id' => $validatedData['user_id'],
            'role' => $validatedData['role'],
        ]);

        // Return the project member
        return response()->json([
            'status' => 'true',
            'message' => 'Member added successfully.',
            'data' => [
                'project_member' => $projectMember,
            ],
        ]);
    }   

    #[OA\Put(
        path: '/api/project/{projectSlug}/members/{user}',
        summary: 'Update member role in a project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'role', type: 'string', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or member not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    // Update a specific member of a project
    public function updateProjectMember(Request $request, $projectSlug, $user)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->orWhere('key', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the current user has access to the project
        $currentUser = $request->user();
        $currentMember = ProjectMember::where('project_id', $project->id)->where('user_id', $currentUser->id)->first();

        if (empty($currentMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Check if the target member exists
        $targetMember = ProjectMember::where('project_id', $project->id)->where('user_id', $user)->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project member not found.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'role' => 'nullable|string|max:255',
        ]);

        // Update the project member
        $targetMember->update($validatedData);

        // Return the project member
        return response()->json([
            'status' => 'true',
            'message' => 'Member updated successfully.',
            'data' => [
                'project_member' => $targetMember,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/project/{projectSlug}/members/{user}',
        summary: 'Remove a member from a project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or member not found'),
        ]
    )]
    // Remove a specific member from a project
    public function removeProjectMember(Request $request, $projectSlug, $user)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->orWhere('key', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the current user has access to the project
        $currentUser = $request->user();
        $currentMember = ProjectMember::where('project_id', $project->id)->where('user_id', $currentUser->id)->first();

        if (empty($currentMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Check if the target member exists
        $targetMember = ProjectMember::where('project_id', $project->id)->where('user_id', $user)->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project member not found.',
            ]);
        }

        // Remove the member from the project
        $targetMember->delete();

        // Return the project member
        return response()->json([
            'status' => 'true',
            'message' => 'Member removed successfully.',
            'data' => [
                'project_member' => $targetMember,
            ],
        ]);
    }
}
