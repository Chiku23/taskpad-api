<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    /**
     * Resolve a project by slug or key.
     */
    private function resolveProject(string $identifier): ?Project
    {
        return Project::where('slug', $identifier)
            ->orWhere('key', $identifier)
            ->first();
    }

    /**
     * Retrieve the user's project and organization memberships.
     */
    private function getMembershipContext(int $projectId, int $organizationId, int $userId): array
    {
        $projectMember = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        $orgMember = OrganizationMember::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->first();

        return [$projectMember, $orgMember];
    }

    /**
     * Determine if the user has administrative privileges for the project.
     */
    private function hasAdminAccess(?ProjectMember $projectMember, ?OrganizationMember $orgMember): bool
    {
        if ($projectMember && in_array($projectMember->role, ['owner', 'admin'])) {
            return true;
        }

        if ($orgMember && in_array($orgMember->role, ['owner', 'admin'])) {
            return true;
        }

        return false;
    }

    #[OA\Get(
        path: '/api/organization/{orgSlug}/projects',
        summary: 'List projects in an organization',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Projects retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'projects',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'organization_id', type: 'integer'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'key', type: 'string'),
                                            new OA\Property(property: 'slug', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string', nullable: true),
                                            new OA\Property(property: 'type', type: 'string', enum: ['kanban', 'scrum']),
                                            new OA\Property(property: 'lead_id', type: 'integer', nullable: true),
                                            new OA\Property(property: 'is_archived', type: 'boolean'),
                                            new OA\Property(property: 'user_role', type: 'string', nullable: true),
                                            new OA\Property(property: 'members_count', type: 'integer'),
                                            new OA\Property(
                                                property: 'lead',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer'),
                                                    new OA\Property(property: 'name', type: 'string'),
                                                    new OA\Property(property: 'email', type: 'string'),
                                                ],
                                                type: 'object',
                                                nullable: true
                                            ),
                                        ]
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'User does not belong to the organization'),
            new OA\Response(response: 404, description: 'Organization not found'),
        ]
    )]
    public function getOrganizationProjects(Request $request, $orgSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->first();

        if (empty($organization)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $orgMember = OrganizationMember::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this organization.',
            ], 403);
        }

        $projects = Project::where('organization_id', $organization->id)
            ->with(['lead:id,name,email'])
            ->withCount('members')
            ->get();

        $userProjectRoles = ProjectMember::whereIn('project_id', $projects->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('role', 'project_id');

        $projects->each(function ($project) use ($userProjectRoles, $orgMember) {
            $project->user_role = $userProjectRoles[$project->id] ?? ($orgMember->role !== 'member' ? $orgMember->role : null);
        });

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
                    new OA\Property(property: 'key', type: 'string', maxLength: 10),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['kanban', 'scrum']),
                    new OA\Property(property: 'lead', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'project', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Organization or user not found'),
            new OA\Response(response: 409, description: 'Project key already exists in organization'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function createOrganizationProject(Request $request, $orgSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->first();

        if (empty($organization)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $orgMember = OrganizationMember::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this organization.',
            ], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:10',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:kanban,scrum',
            'lead' => 'nullable|integer',
        ]);

        $projectKey = strtoupper($validatedData['key']);

        $existingProject = Project::where('organization_id', $organization->id)
            ->where('key', $projectKey)
            ->first();

        if ($existingProject) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project key already exists in this organization.',
            ], 409);
        }

        if (!empty($validatedData['lead'])) {
            $lead = User::find($validatedData['lead']);
            if (empty($lead)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Project lead user not found.',
                ], 404);
            }

            $leadOrgMember = OrganizationMember::where('organization_id', $organization->id)
                ->where('user_id', $lead->id)
                ->first();

            if (empty($leadOrgMember)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Project lead must be a member of the organization.',
                ], 422);
            }
        }

        $project = DB::transaction(function () use ($organization, $validatedData, $projectKey) {
            $createdProject = Project::create([
                'organization_id' => $organization->id,
                'name' => $validatedData['name'],
                'key' => $projectKey,
                'slug' => Str::slug($validatedData['name']),
                'description' => $validatedData['description'] ?? null,
                'type' => $validatedData['type'] ?? 'kanban',
                'lead_id' => $validatedData['lead'] ?? null,
            ]);

            if (!empty($validatedData['lead'])) {
                ProjectMember::create([
                    'project_id' => $createdProject->id,
                    'user_id' => $validatedData['lead'],
                    'role' => 'owner',
                    'joined_at' => now(),
                ]);
            }

            return $createdProject;
        });

        $project->load(['lead:id,name,email']);
        $project->loadCount('members');

        return response()->json([
            'status' => 'true',
            'message' => 'Project created successfully.',
            'data' => [
                'project' => $project,
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/api/project/{projectSlug}',
        summary: 'Get project details',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project details retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'project',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'organization_id', type: 'integer'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'key', type: 'string'),
                                        new OA\Property(property: 'slug', type: 'string'),
                                        new OA\Property(property: 'description', type: 'string', nullable: true),
                                        new OA\Property(property: 'type', type: 'string'),
                                        new OA\Property(property: 'lead_id', type: 'integer', nullable: true),
                                        new OA\Property(property: 'is_archived', type: 'boolean'),
                                        new OA\Property(property: 'user_role', type: 'string', nullable: true),
                                        new OA\Property(property: 'members_count', type: 'integer'),
                                        new OA\Property(
                                            property: 'organization',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer'),
                                                new OA\Property(property: 'name', type: 'string'),
                                                new OA\Property(property: 'slug', type: 'string'),
                                            ],
                                            type: 'object'
                                        ),
                                        new OA\Property(
                                            property: 'lead',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer'),
                                                new OA\Property(property: 'name', type: 'string'),
                                                new OA\Property(property: 'email', type: 'string'),
                                            ],
                                            type: 'object',
                                            nullable: true
                                        ),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'User does not have access to this project'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    public function getProject(Request $request, $projectSlug)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $user->id);

        $hasAccess = $projectMember !== null || ($orgMember && in_array($orgMember->role, ['owner', 'admin']));

        if (!$hasAccess) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ], 403);
        }

        $project->load(['lead:id,name,email', 'organization:id,name,slug']);
        $project->loadCount('members');
        $project->user_role = $projectMember ? $projectMember->role : ($orgMember ? $orgMember->role : null);

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
        summary: 'Update a project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'key', type: 'string', maxLength: 10),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'lead', type: 'integer', nullable: true),
                    new OA\Property(property: 'is_archived', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'project', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or lead user not found'),
            new OA\Response(response: 409, description: 'Project key already exists'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateProject(Request $request, $projectSlug)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $user->id);

        if (!$this->hasAdminAccess($projectMember, $orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to update this project.',
            ], 403);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:10',
            'description' => 'nullable|string',
            'lead' => 'nullable|integer',
            'is_archived' => 'sometimes|boolean',
        ]);

        if (isset($validatedData['key'])) {
            $newKey = strtoupper($validatedData['key']);
            $keyExists = Project::where('organization_id', $project->organization_id)
                ->where('key', $newKey)
                ->where('id', '!=', $project->id)
                ->exists();

            if ($keyExists) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Project key already taken in this organization.',
                ], 409);
            }

            $validatedData['key'] = $newKey;
        }

        if (array_key_exists('lead', $validatedData) && !empty($validatedData['lead'])) {
            $leadUser = User::find($validatedData['lead']);
            if (empty($leadUser)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Project lead user not found.',
                ], 404);
            }

            $leadOrgMember = OrganizationMember::where('organization_id', $project->organization_id)
                ->where('user_id', $leadUser->id)
                ->first();

            if (empty($leadOrgMember)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Project lead must be a member of the organization.',
                ], 422);
            }
        }

        $updateData = [];
        if (isset($validatedData['name'])) {
            $updateData['name'] = $validatedData['name'];
            $updateData['slug'] = Str::slug($validatedData['name']);
        }
        if (isset($validatedData['key'])) {
            $updateData['key'] = $validatedData['key'];
        }
        if (array_key_exists('description', $validatedData)) {
            $updateData['description'] = $validatedData['description'];
        }
        if (array_key_exists('lead', $validatedData)) {
            $updateData['lead_id'] = $validatedData['lead'];
        }
        if (array_key_exists('is_archived', $validatedData)) {
            $updateData['is_archived'] = $validatedData['is_archived'];
        }

        DB::transaction(function () use ($project, $updateData, $validatedData) {
            $project->update($updateData);

            if (array_key_exists('lead', $validatedData) && !empty($validatedData['lead'])) {
                ProjectMember::where('project_id', $project->id)
                    ->where('role', 'owner')
                    ->update(['role' => 'admin']);

                ProjectMember::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'user_id' => $validatedData['lead'],
                    ],
                    [
                        'role' => 'owner',
                        'joined_at' => now(),
                    ]
                );
            }
        });

        $project->refresh();
        $project->load(['lead:id,name,email', 'organization:id,name,slug']);
        $project->loadCount('members');

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
        summary: 'Delete a project',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    public function deleteProject(Request $request, $projectSlug)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $user->id);

        if (!$this->hasAdminAccess($projectMember, $orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User is not authorized to delete this project.',
            ], 403);
        }

        $project->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Project deleted successfully.',
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
            new OA\Response(
                response: 200,
                description: 'Project members retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'members',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'project_id', type: 'integer'),
                                            new OA\Property(property: 'user_id', type: 'integer'),
                                            new OA\Property(property: 'user_name', type: 'string', nullable: true),
                                            new OA\Property(property: 'user_email', type: 'string', nullable: true),
                                            new OA\Property(property: 'role', type: 'string', enum: ['owner', 'admin', 'member']),
                                            new OA\Property(property: 'joined_at', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(
                                                property: 'user',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer'),
                                                    new OA\Property(property: 'name', type: 'string'),
                                                    new OA\Property(property: 'email', type: 'string'),
                                                ],
                                                type: 'object'
                                            ),
                                        ]
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project not found'),
        ]
    )]
    public function getProjectMembers(Request $request, $projectSlug)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $user->id);

        $hasAccess = $projectMember !== null || ($orgMember && in_array($orgMember->role, ['owner', 'admin']));

        if (!$hasAccess) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ], 403);
        }

        $members = ProjectMember::where('project_id', $project->id)
            ->with('user:id,name,email')
            ->get();

        $members->each(function ($member) {
            $member->user_name = $member->user?->name;
            $member->user_email = $member->user?->email;
        });

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
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'member']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Member added to project',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'project_member', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or user not found'),
            new OA\Response(response: 409, description: 'User already in project'),
            new OA\Response(response: 422, description: 'Validation error or user not in organization'),
        ]
    )]
    public function addProjectMember(Request $request, $projectSlug)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $user = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $user->id);

        if (!$this->hasAdminAccess($projectMember, $orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to add members to this project.',
            ], 403);
        }

        $validatedData = $request->validate([
            'user_id' => 'required|integer',
            'role' => 'required|string|in:admin,member',
        ]);

        $targetUser = User::find($validatedData['user_id']);
        if (empty($targetUser)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User not found.',
            ], 404);
        }

        $isOrgMember = OrganizationMember::where('organization_id', $project->organization_id)
            ->where('user_id', $targetUser->id)
            ->exists();

        if (!$isOrgMember) {
            return response()->json([
                'status' => 'false',
                'message' => 'User must be a member of the organization before joining this project.',
            ], 422);
        }

        $existingProjectMember = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($existingProjectMember) {
            return response()->json([
                'status' => 'false',
                'message' => 'User is already a member of this project.',
            ], 409);
        }

        $newMember = ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $targetUser->id,
            'role' => $validatedData['role'],
            'joined_at' => now(),
        ]);

        $newMember->load('user:id,name,email');
        $newMember->user_name = $newMember->user?->name;
        $newMember->user_email = $newMember->user?->email;

        return response()->json([
            'status' => 'true',
            'message' => 'Member added successfully.',
            'data' => [
                'project_member' => $newMember,
            ],
        ], 201);
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
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'member']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Member role updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'project_member', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or member not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateProjectMember(Request $request, $projectSlug, $user)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $currentUser = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $currentUser->id);

        if (!$this->hasAdminAccess($projectMember, $orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to update members in this project.',
            ], 403);
        }

        $targetMember = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $user)
            ->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project member not found.',
            ], 404);
        }

        if ($targetMember->role === 'owner') {
            return response()->json([
                'status' => 'false',
                'message' => 'Cannot modify the project owner role directly.',
            ], 403);
        }

        $validatedData = $request->validate([
            'role' => 'required|string|in:admin,member',
        ]);

        $targetMember->update([
            'role' => $validatedData['role'],
        ]);

        $targetMember->load('user:id,name,email');
        $targetMember->user_name = $targetMember->user?->name;
        $targetMember->user_email = $targetMember->user?->email;

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
            new OA\Response(
                response: 200,
                description: 'Member removed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Project or member not found'),
        ]
    )]
    public function removeProjectMember(Request $request, $projectSlug, $user)
    {
        $project = $this->resolveProject($projectSlug);

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ], 404);
        }

        $currentUser = $request->user();
        [$projectMember, $orgMember] = $this->getMembershipContext($project->id, $project->organization_id, $currentUser->id);

        if (!$this->hasAdminAccess($projectMember, $orgMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to remove members from this project.',
            ], 403);
        }

        $targetMember = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $user)
            ->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project member not found.',
            ], 404);
        }

        if ($targetMember->role === 'owner') {
            return response()->json([
                'status' => 'false',
                'message' => 'Cannot remove the project owner. Reassign the project lead first.',
            ], 403);
        }

        $targetMember->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Member removed successfully.',
        ]);
    }
}
