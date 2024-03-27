<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CountryService;
use Illuminate\Support\Facades\Http;
use App\Utils\ErrorCodes;
use Illuminate\Support\Facades\Validator;
use ClhGroup\ClhBookings\Utils\ClhUtils;

class CountryController extends BaseController
{
    public function __construct(CountryService $countryService )
    {
        $this->countryService = $countryService;
    }
    /**
     * @OA\Get(
     *     path="/countries",
     *     tags={"Country"},
     *     summary="Get list of countries",
     *     operationId="getCountries",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                  @OA\Property(property="countries", type="array",
       *                 @OA\Items(
        *                     @OA\Property(property="isoCode", type="string", description="Country code (ISO 3166-1 alpha-2)", example="BR"),
        *                     @OA\Property(property="name", type="string", example="Brasil")
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
    public function getCountries(Request $request)
    {
        $language = $request->input('language', 'pt');

        $countries = $this->countryService->getListByLanguage($language);
        
        $countries = ["countries" => $countries];

        return $this->sendResponse($countries, 'Countries retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/getDataByIP/{ipAddress}",
     *     tags={"Country"},
     *     summary="Get currency and country code data by IP address",
     *     operationId="getDataByIP",
     *     security={{"sanctum":{}}},
    *      @OA\Parameter( 
        *          name="ipAddress", 
        *          in="path", 
        *          description="IP address from where the user is connecting", 
        *          required=true, 
        *          example="101.188.67.134",
        *          @OA\Schema( type="string", format="ipv4" ),
        *     ),
 *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                  @OA\Property(property="countryCode", description="Country code (ISO 3166-1 alpha-2)", type="string", example="AR"),
     *                  @OA\Property(property="currencyCode", description="Currency code (ISO 4217 alpha-3)", type="string", example="ARS")
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
    public function getDataByIP($ipAddress)
    {
        try{
            $ipData = Http::get('http://api.db-ip.com/v2/1214543a9970d1e82854403fd7d8108477439ef0/'.$ipAddress);

            $response = null;
            $json = $ipData->json();
            if (!is_null($json) && !isset($json["error"])){
                $countryCode = $json["countryCode"];
                $currencyCode = $this->countryService->getCurrencyByCountryCode($countryCode);

                $response["countryCode"] = $countryCode;
                $response["currencyCode"] = $currencyCode;
                return $this->sendResponse($response, 'Ip Data retrieved successfully');
            }else{
				ClhUtils::sendNotification(var_export($json, true), "CountryController/getDataByIP");
				return $this->sendResponse(["countryCode"=>"BR", "currencyCode"=>"BRL"], 'Ip Data retrieved successfully');
                //return $this->sendError(ErrorCodes::EXCEPTION_ERROR, ["error" => $json["error"]], 400);
            }
            
        } catch (\Exception $e) {
            ClhUtils::sendNotification($e->getMessage(), $e->getFile(). " Line: ".  $e->getLine());
            return $this->sendResponse(["countryCode"=>"BR", "currencyCode"=>"BRL"], 'Ip Data retrieved successfully');
            //return $this->sendError(ErrorCodes::EXCEPTION_ERROR, ["error" => $e->getMessage()], 400);
        }
    }

}
