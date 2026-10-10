<?php

namespace App\Http\Controllers;


use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Taskpad API Documentation",
    description: "API documentation for Taskpad"
)]
#[OA\Server(
    url: "http://localhost:8000/",
    description: "API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your Bearer token in the format: Bearer <token>"
)]
abstract class Controller
{
    //
}
