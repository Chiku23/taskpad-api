<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrganizationController extends Controller
{
    #[OA\Get(
        path: "/api/organization",
        summary: "List organizations owned by the authenticated user",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    // Returns the organizations a user has
    public function index(Request $request)
    {
        $user = $request->user();
        
        $organizations = Organization::where("owner_id", $user->id)->get();
        
        if(count($organizations) < 1){
            return response()->json([
                "status" => "false",
                "message" => "User has no organizations.",
                "data" => [
                    "organizations" => []
                ]
            ]);
        }

        return response()->json([
            "status" => "true",
            "message" => "Organizations found.",
            "data" => [
                "organizations" => $organizations
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/organization/create",
        summary: "Create a new organization",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "slug"],
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255, example: "Acme Corp"),
                    new OA\Property(property: "slug", type: "string", maxLength: 255, example: "acme-corp")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    // Create a new organization
    public function store(Request $request)
    {
        // Get the details
        $user = $request->user();
        // Validate the submitted data
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $orgName = $validated['name'];
        $orgSlug = $validated['slug'];

        $organization = new Organization;
        // check if the slug is already taken
        $existingOrg = $organization->where("slug", $orgSlug)->first();

        if($existingOrg){
            return response()->json([
                "status"=>"false",
                "message"=>"Organization already exists."
            ]);
        }
        try {

            // Create the organization and owner membership atomically
            $org = DB::transaction(function () use ($organization, $orgName, $orgSlug, $user) {
                $org = $organization->create([
                    "name"=>$orgName,
                    "slug"=>$orgSlug,
                    "owner_id"=>$user->id,
                    "avatar_url"=>""
                ]);

                $orgMember = new OrganizationMember;
                $orgMember->create([
                    "organization_id"=>$org->id,
                    "user_id"=>$user->id,
                    "role"=>"owner",
                    "joined_at"=>now()
                ]);

                return $org;
            });

            return response()->json([
                "status"=>"true",
                "message"=>"Organization created successfully."
            ]);
        } 
        catch (QueryException $e) {
            // 1062 is the error code for duplicate entry in MySQL
            if ($e->errorInfo[1] == 1062){
                return response()->json([
                    "status" => "false",
                    "message" => "Organization already exists."
                ]);
            }
            // For other DB related errors
            return response()->json([
                "status" => "false",
                "message" => "Error creating organization."
            ]);
        }
    }

    #[OA\Get(
        path: "/api/organization/{orgSlug}",
        summary: "Get organization details by slug",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orgSlug", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Organization not found")
        ]
    )]
    // Get the Organization Details
    public function getOrganization(Request $request, $orgSlug)
    {
        if(empty($orgSlug)){
            return response()->json([
                "status" => "false",
                "message" => "Organization slug is required."
            ]);
        }
        // Check if the organization exists
        $existingOrg = Organization::where("slug", $orgSlug)->first();
        
        if(empty($existingOrg)){
            return response()->json([
                "status" => "false",
                "message" => "Organization not found."
            ]);
        }

        // Return the organization details | Will add the condition to check if the user has access to the organization later 
        return response()->json([
            "status" => "true",
            "message" => "Organization found.",
            "data" => [
                "organization" => $existingOrg
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/organization/{orgSlug}",
        summary: "Update organization details",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orgSlug", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255, example: "Acme Corp"),
                    new OA\Property(property: "slug", type: "string", maxLength: 255, example: "acme-corp")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Organization not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    // Update Organization Details
    public function updateOrganization(Request $request, $orgSlug)
    {
        if(empty($orgSlug)){
            return response()->json([
                "status" => "false",
                "message" => "Organization slug is required."
            ]);
        }
        // Check if the organization exists
        $existingOrg = Organization::where("slug", $orgSlug)->first();
        if(empty($existingOrg)){
            return response()->json([
                "status" => "false",
                "message" => "Organization not found."
            ]);
        }

        $authenticatedUser = $request->user();

        // Check if the Organization belongs to the user
        if($existingOrg->owner_id !== $authenticatedUser->id){
            return response()->json([
                "status"=>"false",
                "message" => "User is not authorized to update this organization."
            ]);
        }
        
        // Validate the submitted data
        $validated = $request->validate([
            "name"=>["sometimes", "string", "max:255"],
            "slug"=>["sometimes", "string", "max:255"]
        ]);

        // Check if the slug is already taken
        $existingSlug = Organization::where("slug", $validated["slug"])->where("id", "!=", $existingOrg->id)->first();
        if($existingSlug){
            return response()->json([
                "status" => "false",
                "message" => "Slug already taken."
            ]);
        }

        // Update the organization details
        Organization::where("slug", $orgSlug)->update($validated);
        
        // Get the updated organization details
        $updatedOrg = Organization::where("id", $existingOrg->id)->first();
        
        return response()->json([
            "status" => "true",
            "message" => "Organization updated successfully.",
            "data" => [
                "organization" => $updatedOrg
            ]
        ]);
    }

    #[OA\Delete(
        path: "/api/organization/{orgSlug}",
        summary: "Delete an organization",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orgSlug", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Organization not found")
        ]
    )]
    // Delete a organization
    public function deleteOrganization(Request $request, $orgSlug){
        // Get the authenticated user
        $authenticatedUser = $request->user();
        
        // Check if the organization exist
        $existingOrg = Organization::where("slug", $orgSlug)->first();
        if(empty($existingOrg)){
            return response()->json([
                "status"=>"false",
                "message"=>"Organization not found."
            ]);
        }

        // Check if the Organization belongs to the user
        if($existingOrg->owner_id !== $authenticatedUser->id){
            return response()->json([
                "status"=>"false",
                "message"=>"User is not authorized to delete this organization."
            ]);
        }

        // Delete the organization
        // TODO: Implement Soft delete feature later
        $existingOrg->delete();
        
        return response()->json([
            "status"=>"true",
            "message"=>"Organization deleted successfully."
        ]);
    }

    #[OA\Get(
        path: "/api/organization/{orgSlug}/members",
        summary: "List members of an organization",
        tags: ["Organization"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orgSlug", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Organization not found")
        ]
    )]
    // Get the list of users in the organization
    public function getOrganizationMembers(Request $request, $orgSlug)
    {
        // Check if the authenticated user is allowed to view the organization members
        $authenticatedUser = $request->user();
        $existingOrg = Organization::where("slug", $orgSlug)->first();
        if(empty($existingOrg)){
            return response()->json([
                "status"=>"false",
                "message"=>"Organization not found."
            ]);
        }

        if($existingOrg->owner_id !== $authenticatedUser->id){
            return response()->json([
                "status"=>"false",
                "message"=>"User is not authorized to view this organization."
            ]);
        }

        // Get the organization members
        $members = OrganizationMember::where("organization_id", $existingOrg->id)->get();
        
        return response()->json([
            "status"=>"true",
            "message"=>"Organization members found.",
            "data"=>[
                "members"=>$members
            ]
        ]);
    }
}