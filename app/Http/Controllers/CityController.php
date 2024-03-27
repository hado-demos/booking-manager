<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CityService;
use Illuminate\Support\Facades\Http;
use App\Utils\ErrorCodes;
use Illuminate\Support\Facades\Validator;

class CityController extends BaseController
{
    public function __construct(CityService $cityService )
    {
        $this->cityService = $cityService;
    }
    /**
     * @OA\Get(
     *     path="/cities",
     *     tags={"City"},
     *     summary="Get list of cities",
     *     operationId="getCities",
     *     security={{"sanctum":{}}},
     *      @OA\Parameter( 
    *          name="countryCode", 
    *          in="query", 
    *          description="Country code (ISO 3166-1 alpha-2)", 
    *          required=true, 
    *          example="AR",
    *          @OA\Schema( type="string" ),
    *     ),  
         *      @OA\Parameter( 
    *          name="q", 
    *          in="query", 
    *          description="Query to be searched like", 
    *          required=true, 
    *          example="Alde",
    *          @OA\Schema( type="string" ),
    *     ),    
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                  @OA\Property(property="cities", type="array",
       *                 @OA\Items(
        *                     @OA\Property(property="id", type="id", example="1"),
        *                     @OA\Property(property="name", type="string", example="Copacabana")
     *                      )
     *                  )
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function getCities(Request $request)
    {
        try{
            $countryCode = $request->input('countryCode');
            $q = $request->input('q');
    
            $cities = $this->cityService->getListByCountryCOde($countryCode, $q);
            
            $cities = ["cities" => $cities];
    
            return $this->sendResponse($cities, 'Cities retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError(ErrorCodes::EXCEPTION_ERROR, ["error" => $e->getMessage()], 400);
        }
    }

   
}
