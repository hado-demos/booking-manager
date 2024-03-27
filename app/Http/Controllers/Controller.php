<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;


/**
 * @OA\Info(
 *     description="API Chelagarto definition",
 *     version="1.0.0",
 *     title="API Chelagarto",
 *     termsOfService="http://swagger.io/terms/",
 *     @OA\Contact(
 *         email="corina.v@chelagarto.com"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 * @OA\Server(
 *     description="API Chelagarto",
 *     url=L5_SWAGGER_CONST_HOST
 * )
 *
 * ),
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
