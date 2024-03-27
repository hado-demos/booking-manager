<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends BaseController
{
   /**
     * @OA\Post(
     *     path="/user/check",
     *     tags={"User"},
     *     summary="Check user credentials",
     *     operationId="checkUser",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="username",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="john_doe",
     *         @OA\Schema(type="string"),
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="123456",
     *         @OA\Schema(type="string"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="john_doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function check(Request $request)
    {
        // Simulated response data
        $response = [
                "id" => 1,
                "username" => "john_doe",
                "email" => "john@example.com",
        ];

        return $this->sendResponse($response, 'User credentials are valid.');
    }

}
