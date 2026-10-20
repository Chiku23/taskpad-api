<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;

class ProjectController extends Controller
{
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
            'lead' => $validatedData['lead'],
        ]);

        // Add the project lead as a member of the project if lead is not null
        if ($validatedData['lead'] != null) {
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
    
}
