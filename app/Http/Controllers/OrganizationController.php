<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrganizationController extends Controller
{
    /**
     * Get the authenticated user's membership record for an organization.
     */
    private function getUserMembership(int $organizationId, int $userId): ?OrganizationMember
    {
        return OrganizationMember::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Check if the user has admin-level access (owner or admin role).
     */
    private function isAdminOrOwner(?OrganizationMember $membership): bool
    {
        return $membership && in_array($membership->role, ['owner', 'admin']);
    }

    #[OA\Get(
        path: '/api/organization',
        summary: 'List all organizations the authenticated user belongs to',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organizations retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'organizations',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'slug', type: 'string'),
                                            new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
                                            new OA\Property(property: 'user_role', type: 'string', enum: ['owner', 'admin', 'member']),
                                            new OA\Property(property: 'members_count', type: 'integer'),
                                            new OA\Property(property: 'projects_count', type: 'integer'),
                                            new OA\Property(
                                                property: 'owner',
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
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all organization IDs where user is a member
        $membershipMap = OrganizationMember::where('user_id', $user->id)
            ->pluck('role', 'organization_id');

        if ($membershipMap->isEmpty()) {
            return response()->json([
                'status' => 'true',
                'message' => 'No organizations found.',
                'data' => [
                    'organizations' => [],
                ],
            ]);
        }

        $organizations = Organization::whereIn('id', $membershipMap->keys())
            ->withCount(['members', 'projects'])
            ->with(['owner:id,name,email'])
            ->get();

        // Attach user's role to each organization
        $organizations->each(function ($org) use ($membershipMap) {
            $org->user_role = $membershipMap[$org->id] ?? null;
        });

        return response()->json([
            'status' => 'true',
            'message' => 'Organizations retrieved.',
            'data' => [
                'organizations' => $organizations,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/organization/create',
        summary: 'Create a new organization',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Organization created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'organization', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 409, description: 'Organization slug already taken'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        // Check if the slug is already taken
        $existingOrg = Organization::where('slug', $validated['slug'])->first();
        if ($existingOrg) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization slug already taken.',
            ], 409);
        }

        try {
            // Create the organization and owner membership atomically
            $org = DB::transaction(function () use ($validated, $user) {
                $org = Organization::create([
                    'name' => $validated['name'],
                    'slug' => $validated['slug'],
                    'owner_id' => $user->id,
                    'avatar_url' => '',
                ]);

                OrganizationMember::create([
                    'organization_id' => $org->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'joined_at' => now(),
                ]);

                return $org;
            });

            return response()->json([
                'status' => 'true',
                'message' => 'Organization created successfully.',
                'data' => [
                    'organization' => $org,
                ],
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Organization slug already taken.',
                ], 409);
            }

            return response()->json([
                'status' => 'false',
                'message' => 'Failed to create organization.',
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/organization/{orgSlug}',
        summary: 'Get organization details (requires membership)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization details retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'organization',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'slug', type: 'string'),
                                        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
                                        new OA\Property(property: 'user_role', type: 'string', enum: ['owner', 'admin', 'member']),
                                        new OA\Property(property: 'members_count', type: 'integer'),
                                        new OA\Property(property: 'projects_count', type: 'integer'),
                                        new OA\Property(
                                            property: 'owner',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer'),
                                                new OA\Property(property: 'name', type: 'string'),
                                                new OA\Property(property: 'email', type: 'string'),
                                            ],
                                            type: 'object'
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
            new OA\Response(response: 403, description: 'Not a member of this organization'),
            new OA\Response(response: 404, description: 'Organization not found'),
        ]
    )]
    public function getOrganization(Request $request, $orgSlug)
    {
        $existingOrg = Organization::where('slug', $orgSlug)
            ->withCount(['members', 'projects'])
            ->with(['owner:id,name,email'])
            ->first();

        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        if (empty($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You are not a member of this organization.',
            ], 403);
        }

        $existingOrg->user_role = $membership->role;

        return response()->json([
            'status' => 'true',
            'message' => 'Organization retrieved.',
            'data' => [
                'organization' => $existingOrg,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/organization/{orgSlug}',
        summary: 'Update organization details (owner or admin only)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'organization', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — requires owner or admin role'),
            new OA\Response(response: 404, description: 'Organization not found'),
            new OA\Response(response: 409, description: 'Slug already taken'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateOrganization(Request $request, $orgSlug)
    {
        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        // Only owner or admin can update organization details
        if (!$this->isAdminOrOwner($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You do not have permission to update this organization.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
        ]);

        // Check slug uniqueness if slug is being changed
        if (isset($validated['slug'])) {
            $slugTaken = Organization::where('slug', $validated['slug'])
                ->where('id', '!=', $existingOrg->id)
                ->exists();

            if ($slugTaken) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Slug already taken.',
                ], 409);
            }
        }

        $existingOrg->update($validated);
        $existingOrg->refresh();
        $existingOrg->load('owner:id,name,email');
        $existingOrg->loadCount(['members', 'projects']);

        return response()->json([
            'status' => 'true',
            'message' => 'Organization updated successfully.',
            'data' => [
                'organization' => $existingOrg,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/organization/{orgSlug}',
        summary: 'Delete an organization (owner only)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — owner only'),
            new OA\Response(response: 404, description: 'Organization not found'),
        ]
    )]
    public function deleteOrganization(Request $request, $orgSlug)
    {
        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();

        // Only the organization owner can delete
        if ($existingOrg->owner_id !== $user->id) {
            return response()->json([
                'status' => 'false',
                'message' => 'Only the organization owner can delete this organization.',
            ], 403);
        }

        // TODO: Implement soft delete feature later
        $existingOrg->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Organization deleted successfully.',
        ]);
    }

    #[OA\Get(
        path: '/api/organization/{orgSlug}/members',
        summary: 'List members of an organization (requires membership)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Members retrieved',
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
                                            new OA\Property(property: 'organization_id', type: 'integer'),
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
            new OA\Response(response: 403, description: 'Not a member of this organization'),
            new OA\Response(response: 404, description: 'Organization not found'),
        ]
    )]
    public function getOrganizationMembers(Request $request, $orgSlug)
    {
        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        // Any member can view the members list
        if (empty($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You are not a member of this organization.',
            ], 403);
        }

        // Eager load user relationship to avoid N+1 queries
        $members = OrganizationMember::where('organization_id', $existingOrg->id)
            ->with('user:id,name,email')
            ->get();

        $members->each(function ($member) {
            $member->user_name = $member->user?->name;
            $member->user_email = $member->user?->email;
        });

        return response()->json([
            'status' => 'true',
            'message' => 'Organization members retrieved.',
            'data' => [
                'members' => $members,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/organization/{orgSlug}/members',
        summary: 'Add a member to an organization (owner or admin only)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'role'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role', type: 'string', enum: ['member', 'admin']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Member added',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'true'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'member', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — requires owner or admin role'),
            new OA\Response(response: 404, description: 'Organization or user not found'),
            new OA\Response(response: 409, description: 'User is already a member'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function addOrganizationMember(Request $request, $orgSlug)
    {
        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        // Only owner or admin can add members
        if (!$this->isAdminOrOwner($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You do not have permission to add members to this organization.',
            ], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['nullable', 'string', 'in:member,admin'],
        ]);

        // Find the user to be added
        $targetUser = User::where('email', $validated['email'])->first();
        if (empty($targetUser)) {
            return response()->json([
                'status' => 'false',
                'message' => 'No user found with this email address.',
            ], 404);
        }

        // Check if user is already a member
        $existingMember = OrganizationMember::where('organization_id', $existingOrg->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($existingMember) {
            return response()->json([
                'status' => 'false',
                'message' => 'User is already a member of this organization.',
            ], 409);
        }

        $newMember = OrganizationMember::create([
            'organization_id' => $existingOrg->id,
            'user_id' => $targetUser->id,
            'role' => $validated['role'] ?? 'member',
            'joined_at' => now(),
        ]);

        $newMember->load('user:id,name,email');
        $newMember->user_name = $newMember->user?->name;
        $newMember->user_email = $newMember->user?->email;

        return response()->json([
            'status' => 'true',
            'message' => 'Member added to the organization successfully.',
            'data' => [
                'member' => $newMember,
            ],
        ], 201);
    }

    #[OA\Put(
        path: '/api/organization/{orgSlug}/members/{user}',
        summary: 'Update a member\'s role (owner or admin only)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['member', 'admin']),
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
                                new OA\Property(property: 'member', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Organization or member not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateOrganizationMember(Request $request, $orgSlug, $memberId)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:member,admin'],
        ]);

        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        // Only owner or admin can update member roles
        if (!$this->isAdminOrOwner($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You do not have permission to update member roles.',
            ], 403);
        }

        $targetMember = OrganizationMember::where('organization_id', $existingOrg->id)
            ->where('user_id', $memberId)
            ->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Member not found in this organization.',
            ], 404);
        }

        // Cannot modify the organization owner's role
        if ((int) $memberId === $existingOrg->owner_id) {
            return response()->json([
                'status' => 'false',
                'message' => 'Cannot modify the organization owner\'s role. Use the ownership transfer flow instead.',
            ], 403);
        }

        // Admins cannot modify other admins — only owners can
        if ($membership->role === 'admin' && $targetMember->role === 'admin') {
            return response()->json([
                'status' => 'false',
                'message' => 'Admins cannot modify other admin members. Only the owner can do this.',
            ], 403);
        }

        $targetMember->role = $validated['role'];
        $targetMember->save();
        $targetMember->load('user:id,name,email');
        $targetMember->user_name = $targetMember->user?->name;
        $targetMember->user_email = $targetMember->user?->email;

        return response()->json([
            'status' => 'true',
            'message' => 'Member role updated successfully.',
            'data' => [
                'member' => $targetMember,
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/organization/{orgSlug}/members/{user}',
        summary: 'Remove a member from an organization (owner or admin only)',
        tags: ['Organization'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
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
            new OA\Response(response: 404, description: 'Organization or member not found'),
        ]
    )]
    public function removeOrganizationMember(Request $request, $orgSlug, $memberId)
    {
        $existingOrg = Organization::where('slug', $orgSlug)->first();
        if (empty($existingOrg)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Organization not found.',
            ], 404);
        }

        $user = $request->user();
        $membership = $this->getUserMembership($existingOrg->id, $user->id);

        // Only owner or admin can remove members
        if (!$this->isAdminOrOwner($membership)) {
            return response()->json([
                'status' => 'false',
                'message' => 'You do not have permission to remove members from this organization.',
            ], 403);
        }

        $targetMember = OrganizationMember::where('organization_id', $existingOrg->id)
            ->where('user_id', $memberId)
            ->first();

        if (empty($targetMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Member not found in this organization.',
            ], 404);
        }

        // Cannot remove the organization owner
        if ((int) $memberId === $existingOrg->owner_id) {
            return response()->json([
                'status' => 'false',
                'message' => 'Cannot remove the organization owner. Use the ownership transfer flow instead.',
            ], 403);
        }

        // Admins cannot remove other admins — only owners can
        if ($membership->role === 'admin' && $targetMember->role === 'admin') {
            return response()->json([
                'status' => 'false',
                'message' => 'Admins cannot remove other admin members. Only the owner can do this.',
            ], 403);
        }

        $targetMember->delete();

        return response()->json([
            'status' => 'true',
            'message' => 'Member removed from the organization successfully.',
        ]);
    }
}
