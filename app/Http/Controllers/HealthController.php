<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    #[OA\Get(
        path: "/api/health",
        summary: "Check API health",
        tags: ["Health"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK"
            )
        ]
    )]
    public function index()
    {
        return response()->json(['status' => 'OK']);
    }
}