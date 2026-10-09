<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/login",
        summary: "User login",
        tags: ["Auth"],
        parameters: [
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "password", in: "query", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "OK")
        ]
    )]
    public function login(Request $request)
    {
        return response()->json($request->all());
    }

    #[OA\Post(
        path: "/api/register",
        summary: "User registration",
        tags: ["Auth"],
        parameters: [
            new OA\Parameter(name: "name", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "password", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "confirmpassword", in: "query", required: true, schema: new OA\Schema(type: "string"))
        ],
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
            'password' => $password
        ]);

        return response()->json(["status"=>"true", "message"=>"user created."]);
    }
}