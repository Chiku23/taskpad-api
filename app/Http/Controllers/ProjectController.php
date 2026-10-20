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
    
    // Get a specific project
    public function getProject(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

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

    // Update a specific project
    public function updateProject(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

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

        // Update the project
        $project->update($validatedData);

        // Update the project lead as a member of the project if lead is not null
        if ($validatedData['lead'] != null) {
            ProjectMember::where('project_id', $project->id)->where('user_id', $project->lead)->delete();
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

    // Delete a specific project
    public function deleteProject(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

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

    // Get all members of a specific project
    public function getProjectMembers(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

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

    // Add a new member to a specific project
    public function addProjectMember(Request $request, $projectSlug)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

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

    // Update a specific member of a project
    public function updateProjectMember(Request $request, $projectSlug, $user)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the user has access to the project
        $currentUser = $request->user();
        $projectMember = ProjectMember::where('project_id', $project->id)->where('user_id', $currentUser->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Validate request data
        $validatedData = $request->validate([
            'role' => 'nullable|string|max:255',
        ]);

        // Update the project member
        $projectMember->update($validatedData);

        // Return the project member
        return response()->json([
            'status' => 'true',
            'message' => 'Member updated successfully.',
            'data' => [
                'project_member' => $projectMember,
            ],
        ]);
    }

    // Remove a specific member from a project
    public function removeProjectMember(Request $request, $projectSlug, $user)
    {
        // Check if the project exists
        $project = Project::where('slug', $projectSlug)->first();

        if (empty($project)) {
            return response()->json([
                'status' => 'false',
                'message' => 'Project not found.',
            ]);
        }

        // Check if the user has access to the project
        $currentUser = $request->user();
        $projectMember = ProjectMember::where('project_id', $project->id)->where('user_id', $currentUser->id)->first();

        if (empty($projectMember)) {
            return response()->json([
                'status' => 'false',
                'message' => 'User does not have access to this project.',
            ]);
        }

        // Remove the member from the project
        $projectMember->delete();

        // Return the project member
        return response()->json([
            'status' => 'true',
            'message' => 'Member removed successfully.',
            'data' => [
                'project_member' => $projectMember,
            ],
        ]);
    }
}
