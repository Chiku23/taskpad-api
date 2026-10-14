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
}