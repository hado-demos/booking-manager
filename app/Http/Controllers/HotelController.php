<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\PropertyBasicInfoListResource;
use Illuminate\Support\Facades\Validator;
use App\Utils\ErrorCodes;
use ClhGroup\ClhBookings\Services\ClhHotelService;
use ClhGroup\ClhBookings\Utils\ClhConstants;

class HotelController extends BaseController
{
    protected $hotelService;

    public function __construct( ClhHotelService $hotelService)
    {
        $this->hotelService = $hotelService;
    }
    /**
     * @OA\Get(
     *     path="/property/{propertyId}",
     *     tags={"Property"},
     *     summary="Get property details by ID.",
     *     operationId="getProperty",
     *      security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         description="",
     *         required=true,
     *           example="47",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
          *     @OA\Parameter(
     *         name="domain",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/PropertyResource",
     *             ),
     *             @OA\Property(property="message", type="string", example=""),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function getProperty($propertyId, Request $request)
    { 
        if (is_null($propertyId)) {
            return $this->sendError(ErrorCodes::REQUIRED_FIELD, $validator->errors(), 400);
        }

        $language = $request->query('language', 'pt');
        $domain = $request->query('domain', 'chelagarto');

        $hotel = $this->hotelService->getHotelById($propertyId);
        if (is_null($hotel)){
            return $this->sendError(ErrorCodes::NOT_FOUND, [], 404);
        }

        return $this->sendResponse(PropertyResource::make($hotel, $language, $domain), 'Property retrieved successfully.');
    }

    /**
     * @OA\Get(
     *     path="/destinations",
     *     tags={"Property"},
     *     summary="Get destinations.",
     *     operationId="getDestinations",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
     *     @OA\Parameter(
     *         name="domain",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *            @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/PropertyBasicInfoListResource",
     *             ),
     *             @OA\Property(property="message", type="string", example="Destinations retrieved successfully.")
     *         )
     *     )
     * )
     */
    public function getDestinations(Request $request)
    {
        $language = $request->input('language', 'pt');
        $domain = $request->input('domain', 'chelagarto');

        if ($domain=="chelagarto"){
            $hotels = $this->hotelService->getList();
        }else{
            $hotels = $this->hotelService->getList(ClhConstants::CATEGORY_HOTEL_CLHSUITES);
        }
        
        return $this->sendResponse(PropertyBasicInfoListResource::make($hotels, $language), 'Destinations retrieved successfully.');
    }

/**
     * @OA\Get(
     *     path="/home",
     *     tags={"Property"},
     *     summary="Get home data.",
     *     operationId="getHomeData",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
     *     @OA\Parameter(
     *         name="domain",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *          @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", type="object", 
     *                 @OA\Property(property="title1", type="string", example="Booking now"),
     *                 @OA\Property(property="title2", type="string", example="And pay later"),
     *                 @OA\Property(property="whatsappNumber", type="string", example="+55 (45) 9921 4327"),
     *                 @OA\Property(property="linkMedia", type="string", example="https://s3.us-west-2.amazonaws.com/clhgroup.filesmobileweb/home-chelagarto.jpg"),
     *             ),
     *             @OA\Property(property="message", type="string", example=""),
     *         ),
     *     )
     * )
     */
    public function getHomeData(Request $request)
    {
        $language = $request->input('language', 'pt');
        $domain = $request->input('domain', 'chelagarto');

        if ($domain=="chelagarto"){
            $whatsappNumber = "+55 (45) 9921 4327";
            $image = "home-chelagarto.jpg";
        }else{
            $whatsappNumber = "+55 (21) 97126 2495";
            $image = "home-clhsuites.jpg";
        }
        $response =
        [   
            "title1" => __('api.Home title 1'),
            "title2" => __('api.Home title 2'),
            "whatsappNumber" => $whatsappNumber,
            "linkMedia" => "https://s3.us-west-2.amazonaws.com/clhgroup.filesmobileweb/$image",
        ];

        return $this->sendResponse($response, 'Home data retrieved successfully.');
    }

    /**
     * @OA\Get(
     *     path="/termsAndConditions",
     *     tags={"Property"},
     *     summary="Get terms and conditions.",
     *     operationId="getTermsAndConditions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
     *     @OA\Parameter(
     *         name="domain",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *          @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", type="object", 
     *                 @OA\Property(property="html", type="string", example=""),
     *             ),
     *             @OA\Property(property="message", type="string", example=""),
     *         ),
     *     )
     * )
     */
    public function getTermsAndConditions(Request $request){

        $language = $request->input('language', 'pt');
        $domain = $request->input('domain', 'chelagarto');
        
        $language = in_array(strtolower($language), ["es", "pt"])? $language : "pt";
        $response =
        [   
            "html" => view("terms.$language")->with("language", $language)->render()
        ];
        return $this->sendResponse($response, 'HTML retrieved successfully.');

    }
}
