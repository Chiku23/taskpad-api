<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/login",
        summary: "User login",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OK")
        ]
    )]
    public function login(Request $request)
    {
        $email = $request->email ?? '';
        $password = $request->password ?? '';

        $user = new User;
        $existingUser = $user->where('email',$email)->first();
        
        if(empty($existingUser)) {
            return response()->json(["status"=>"false", "message"=>"Invalid credenatials."]);
        }

        // Check password is correct
        $checkPassword = Hash::check($password, $existingUser->password);
        if(!$checkPassword) {
            return response()->json(["status"=>"false", "message"=>"Invalid credenatials."]);
        }

        // Generate the token
        $token = $existingUser->createToken("taskpad")->plainTextToken;
        
        return response()->json([
            "status"=>"true",
            "message"=>"User found.",
            "data"=>[
                "token"=> $token,
                "name"=> $existingUser->name,
                "email"=> $existingUser->email
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/register",
        summary: "User registration",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "confirmpassword"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password"),
                    new OA\Property(property: "confirmpassword", type: "string", format: "password")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OK")
        ]
    )]
    public function register(Request $request)
    {
        $email = $request->email ?? '';
        $name = $request->name ?? '';
        $password = $request->password ?? '';
        $confirmPassword = $request->confirmpassword ?? '';
        
        // Empty values check
        if(empty($email) || empty($name) || empty($password) || empty($confirmPassword)){
            return response()->json(["status"=>"false", "message"=>"please fill all the fields."]);
        }

        // Confirm password check
        if($password !== $confirmPassword) {
            return response()->json(["status"=>"false", "message"=>"confirm password does not match."]);
        }

        // hash the password
        $hashedPassword = Hash::make($password);

        // Create user
        $user = new User;
        $existingUser = $user->where('email',$email)->first();

        if($existingUser) {
            return response()->json(["status"=>"false", "message"=>"email already registered."]);
        }
        
        // Create a new user
        $newUser = $user->create([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        return response()->json(["status"=>"true", "message"=>"user created."]);
    }

    #[OA\Get(
        path: "/api/me",
        summary: "Get authenticated user profile",
        tags: ["Auth"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function me(Request $request)
    {
        $user = $request->user();
        
        if($user) {
            return response()->json([
                "status"=>"true", 
                "message"=>"User found.", 
                "data"=>[
                    "name"=> $user->name,
                    "email"=> $user->email
                ]
            ]);
        }else{
            return response()->json(["status"=>"false", "message"=>"User not found."]);
        }
    }

    #[OA\Post(
        path: "/api/logout",
        summary: "Logout user",
        tags: ["Auth"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function logout(Request $request)
    {
        // Deletes only the current token used in this request
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            "status" => "true",
            "message" => "Logged out."
        ]);
    }

}