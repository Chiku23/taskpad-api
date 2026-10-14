<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
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

            // create the organization
            $org = $organization->create([
                "name"=>$orgName,
                "slug"=>$orgSlug,
                "owner_id"=>$user->id,
                "avatar_url"=>""
            ]);
        } catch (QueryException $e) {
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

        // TODO: Add the user to the organization

        return response()->json([
            "status"=>"true",
            "message"=>"Organization created successfully."
        ]);
    }

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
}