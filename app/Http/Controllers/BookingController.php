<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Occupation;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingTotalResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\RateResource;
use App\Services\BookingService;
use App\Services\ProductService;
use App\Services\OccupationService;
use App\Services\CouponService;
use App\Services\CountryService;
use ClhGroup\ClhBookings\Services\ClhTaxService;
use ClhGroup\ClhBookings\Services\ClhCurrencyService;
use ClhGroup\ClhBookings\Services\ClhHotelService;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use ClhGroup\ClhBookings\Models\ClhBooking;
use App\Utils\Constants;
use App\Utils\ErrorCodes;
use Carbon\Carbon;
use App\Rules\MaxLengthStay;
use App\Rules\ValidCPF;
use App\Services\Strategies\BookingStrategyFactory;
use ClhGroup\ClhBookings\Models\ClhBookingTracking;

class BookingController extends BaseController
{
    protected $bookingService;
    protected $productService;
    protected $hotelService;
    protected $occupationService;
    protected $couponService;
    protected $currencyService;
    protected $countryService;
    protected $taxService;

    public function __construct(BookingService $bookingService, ProductService $productService, ClhHotelService $hotelService, OccupationService $occupationService,
        CouponService $couponService, ClhCurrencyService $currencyService, CountryService $countryService, ClhTaxService $taxService )
    {
        $this->bookingService = $bookingService;
        $this->productService = $productService;
        $this->hotelService = $hotelService;
        $this->occupationService = $occupationService;
        $this->couponService = $couponService;
        $this->currencyService = $currencyService;
        $this->countryService = $countryService;
        $this->taxService = $taxService;
    }
/**
* @OA\Post(
*      path="/booking/availability/get",
*      operationId="getAvailabilities",
*      tags={"Booking"},
*      summary="Get the list of availabilities according to parameters.",
*      description="Get the list of availabilities according to parameters.",
*      security={{"sanctum":{}}},
*     @OA\Parameter(
*         name="propertyId", in="query", description="", required=true, example="47", @OA\Schema(type="integer")
*     ),
*      @OA\Parameter(
*         name="checkin", in="query", description="", required=true, example="2023-12-20", @OA\Schema( type="string", format="date" )
*     ),
*      @OA\Parameter(
*         name="checkout", in="query", description="", required=true, example="2023-12-22", @OA\Schema( type="string", format="date" )
*     ),
*       @OA\Parameter(
*         name="currencyCode", required=true, in="query", example="ARS", description="Currency code (ISO 4217 alpha-3)", @OA\Schema( type="string" )
*     ),
*      @OA\Parameter( 
*          name="countryCode", 
*          in="query", 
*          description="Country code (ISO 3166-1 alpha-2)", 
*          required=true, 
*          example="AR",
*          @OA\Schema( type="string" ),
*     ),
*      @OA\Parameter( 
*          name="adults", in="query", description="", required=true, example="2", @OA\Schema( type="integer" )
*     ),
*      @OA\Parameter( 
*          name="children", in="query", description="", required=true, example="2", @OA\Schema( type="integer" )
*     ),
*       @OA\Parameter(
*         name="language", required=false, in="query", description="", @OA\Schema( enum={"es", "pt", "en"}, default="pt" )
*     ),
*     @OA\Response(
*         response="200",
*         description="Successful operation",
*         content={
*             @OA\MediaType(
*                 mediaType="application/json",
*                 @OA\Schema(
*                     @OA\Property( property="success", type="boolean", description=""),
*                     @OA\Property( property="data", type="object", description="", 
*                           @OA\Property( property="isPackage", type="boolean", description="If there is a package, its date range must be included in the reservation, because it couldn't be broken. Customer dates should be replaced with new returned dates."),
*                           @OA\Property( property="checkin", type="string", example="2023-12-24" ,description="Checkin date"),
*                           @OA\Property( property="checkout", type="string", example="2024-01-02",description="Checkout date" ),
*                           @OA\Property( property="nights", type="int", example="9",description="Nights" ),
 *                          @OA\Property(
 *                              property="availabilities",
 *                              type="array",
 *                              @OA\Items(ref="#/components/schemas/ProductResource")
 *                          ),
*                   ),
*                   @OA\Property( property="message", type="string", description="")
*                 )
*             )
*         }
*     ),
 *     @OA\Response(response=400, description="Bad request",
    *           @OA\JsonContent(
    *           type="object",
    *           @OA\Property(
    *               property="success",
    *               type="boolean",
    *               example=false,
    *               description="Indicates whether the request was successful or not."
    *           ),
   *             @OA\Property(
    *               property="message",
    *               type="string",
    *               example="FIELD_ERROR",
    *               description="A message indicating the reason for the bad request."
    *           ),
    *           @OA\Property(
    *               property="data",
    *               type="object",
    *               additionalProperties={
   *                    "type": "array",
   *                    "items": {
   *                        "type": "string"
   *                    },
    *               "description": "An array of error messages for specific fields."
    *               },
     *              description="A collection of field-specific error messages."
    *           )
    *           )
    *       ),
*     @OA\Response( response=404, description="Not found" ),
*     @OA\Response( response=405, description="Method Not Allowed" ),
*     @OA\Response( response=422, description="Unprocessable Entity" ),
*     @OA\Response( response=500, description="Internal server error" ),
*  )
*/
    public function getAvailabilities(Request $request)
    {
        $params = $this->parseAvailabilitiesRequest($request);
        if (!$params->ok){
            return $this->sendError($params->message, $params->data, 400);
        }

        $hotel = $this->hotelService->getHotelById($params->propertyId);
        $currencies = $this->currencyService->getCurrencies(array_unique(array_merge(Constants::API_CURRENCIES,[$params->currencyCode])));

        $isPackage = false;
        $checkPackage = $this->occupationService->existsPackage($params->propertyId,$params->checkin,$params->checkout);
        if ($checkPackage->isPackage){
            $params->checkin = $checkPackage->from;
            $params->checkout = $checkPackage->to;
        }
        $checkinDate = Carbon::createFromFormat("Y-m-d", $params->checkin);
        $checkoutDate = Carbon::createFromFormat("Y-m-d", $params->checkout);
        $params->nights = $checkinDate->diffInDays($checkoutDate);

        $availabilityByProd = $this->occupationService->getAvailableProductsByParams($params->propertyId,$params->checkin,$params->checkout,$params->adults+$params->children,$params->children);         

        $response = array_merge(
            [   'isPackage'=> $checkPackage->isPackage, 'checkin'=>$params->checkin, 'checkout'=>$params->checkout, 'nights'=> $params->nights  ],
            $this->buildAvailabilities($params, $hotel, $currencies, $availabilityByProd)
       );

        return $this->sendResponse($response, 'Availabilities retrieved successfully');
    }

    
    private function parseAvailabilitiesRequest(Request $request){
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|integer',
            'checkin' => 'required|date_format:Y-m-d|after:yesterday',
            'checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:checkin',
                new MaxLengthStay($request->input('checkin'), 30) 
            ],
            'adults' => 'required|integer',
            'children' => 'required|integer',
            'countryCode' => 'required|size:2',
            'currencyCode' => 'size:3'
        ]);
    
        if ($validator->fails()) {
            return  (object)[ "ok" => false, "message" => ErrorCodes::FIELD_ERROR, "data" => $validator->errors()];
        }

        $propertyId = $request->input("propertyId");
        $checkin = $request->input("checkin");
        $checkout = $request->input("checkout");
        $adults = $request->input("adults");
        $children = $request->input("children");
        $countryCode = $request->input("countryCode");
        $currencyCode = $request->input("currencyCode");
        $language = $request->input("language", "pt");

        return (object) [ "ok" => true, "propertyId" => $propertyId, "currencyCode" => $currencyCode, "language" => $language,
                        "checkin" => $checkin, "checkout" => $checkout, "adults" => $adults, "children" => $children, 
                        "countryCode" => $countryCode
                    ];
    }
    
    private function buildAvailabilities($params, $hotel, $currencies, $availabilityByProd){
        $availabilities = [];

        $nights = $params->nights;
        $language = $params->language;
        $countryCode = $params->countryCode;
        $checkin = $params->checkin;
        $checkout = $params->checkout;
        $taxes = $hotel->taxesByCustomerCountryCode($countryCode, ClhConstants::TAX_TYPE_PERCENT_OF_TOTAL);
        $breakfastUSDValue = !is_null($hotel->breakfast())? $hotel->breakfast()->currentPrice()->toUSD() : 0;
        
        foreach($currencies as $currency){
            $markup[$currency->codigoISO] = $currency->getMarkupByHotelAndGuestCountry($hotel->country->codIso2, $countryCode);
            $decimals[$currency->codigoISO] = ClhUtils::getDecimalsByBookingOrigin($currency->codigoISO, ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE);
            $symbols[$currency->codigoISO] = $currency->signoMoneda;
        }

        foreach($availabilityByProd as $productId => $minAvailability){
            $product = $this->productService->getProductById($productId);
            $rates = $this->occupationService->getRatesByProductId($hotel,$productId,$checkin,$checkout);   
            
            foreach($currencies as $currency){
                $totals[$currency->codigoISO] = $this->bookingService->calculateTotalsByProductByCurrency($currency,$markup[$currency->codigoISO],$decimals[$currency->codigoISO],$rates,1,$breakfastUSDValue, $nights*$product->cantPersonas, $taxes);
            }
            $responseRates = [RateResource::make($product,$totals,$symbols)];

            $availabilities[] = ProductResource::make($product,$language,$minAvailability,$responseRates);
        }

        return ["availabilities" => $availabilities];
    }

 /**
     * @OA\Post(
     *     path="/booking/intention/new",
     *     tags={"Booking"},
     *     summary="Create a new booking intention.",
     *     operationId="newIntention",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Booking details",
     *         @OA\JsonContent(
     *              @OA\Property(property="propertyId", type="integer", example="47"),
     *              @OA\Property(property="domain", type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto"),
     *              @OA\Property(property="language", type="string", enum={"pt", "es", "en"}, default="pt"),
    *                 @OA\Property(property="countryCode", type="string", example="AR", description="Country code (ISO 3166-1 alpha-2)"),
    *                 @OA\Property(property="currencyCode", type="string", description="Currency code (ISO 4217 alpha-3)", example="ARS"),
     *             @OA\Property(property="guestInfo", type="object",
     *                 @OA\Property(property="name", type="string", example="Joe"),
     *                 @OA\Property(property="surname", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="joedoe@gmail.com"),
     *                 @OA\Property(property="cellphone", type="string"),
     *             ),
     *              @OA\Property(property="adults", type="integer", example="2"),
     *              @OA\Property(property="children", type="integer", example="2"),
     *             @OA\Property(property="stay", type="object",
     *                     @OA\Property(property="checkin", type="string", format="date", example="2023-12-20"),
     *                     @OA\Property(property="checkout", type="string", format="date", example="2023-12-22"),
     *                     @OA\Property(property="products", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="productId", type="integer", example="2382"),
     *                             @OA\Property(property="includeBreakfast", type="boolean"), 
     *                             @OA\Property(property="quantity", type="integer", example="1")
     *                         )
     *                     )
     *             )
     *         ),
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                  @OA\Property(property="intentionId", type="integer", example="1")
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response( response=422, description="Unprocessable Entity" ),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function newIntention(Request $request)
    {
        $params = $this->parseNewBookingIntentionRequest($request);
        if (!$params->ok){
            return $this->sendError($params->message, $params->data, 400);
        }

        $guestInfo = $params->guestInfo;
        $intention =  ["id_hotel" => $params->propertyId,"idioma" => $params->language,"nombre" => $guestInfo["name"],
                            "apellido" => $guestInfo["surname"], "email" => $guestInfo["email"],"pais" => $params->countryCode,
                            "moneda" => $params->currencyCode,"adultos"=>$params->adults, "menores"=> $params->children,
                            "checkin" => $params->stay["checkin"],"checkout" => $params->stay["checkout"]
        ];

        $details = [];
        foreach($params->stay["products"] as $product){
            $details[] =  ["id_intencion" => "", "id_producto" => $product["productId"],"cantidad" => $product["quantity"]  ];
        }

        try {

            $intention = $this->bookingService->createBookingIntention(["intention" => $intention, "details" => $details]);
            
            return $this->sendResponse(['intentionId' => $intention->id], 'Booking intention created successfully.');
            
        } catch (\Exception $e) {

            \DB::rollback();
            ClhUtils::sendNotification($e->getMessage(), $e->getFile(). " Line: ".  $e->getLine());
            return $this->sendError(ErrorCodes::EXCEPTION_ERROR, ["error" => $e->getMessage()], 400);
        }
         

    }

    private function parseNewBookingIntentionRequest($request){

        $rules = [
            'propertyId' => 'required|integer',
            'guestInfo' => 'required',
            'guestInfo.name' => 'required',
            'guestInfo.surname' => 'required',
            'guestInfo.email' => 'required|email',
            'guestInfo.cellphone' => 'required',
            'adults' => 'required',
            'children' => 'required',
            'stay' => 'required',
            'stay.checkin' => 'required|date_format:Y-m-d|after:yesterday',
            'stay.checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:stay.checkin',
                new MaxLengthStay($request->input('stay.checkin'), 30) 
            ],
            'countryCode' => 'required|size:2',
            'currencyCode' => 'size:3',
        ];
    
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return  (object)[ "ok" => false, "message" => ErrorCodes::FIELD_ERROR, "data" => $validator->errors()];
        }

        $propertyId = $request->input("propertyId");
        $language = $request->input("language");
        $countryCode = $request->input("countryCode");
        $currencyCode = $request->input("currencyCode");
        $guestInfo = $request->input('guestInfo');
        $adults = $request->input('adults');
        $children = $request->input('children');
        $stay = $request->input('stay');

        return (object) [ "ok" => true, "propertyId" => $propertyId, "language" => $language, "currencyCode" => $currencyCode, "countryCode" => $countryCode,
           "guestInfo" => $guestInfo, "adults" => $adults, "children" => $children, "stay" => $stay
        ];

    }

    
/**
     * @OA\Post(
     *     path="/booking/calculateTotals",
     *     tags={"Booking"},
     *     summary="Calculate booking total according to selected products.",
     *     operationId="calculateTotals",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="47",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="checkin",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="2023-12-20",
     *         @OA\Schema(type="string", format="date"),
     *     ),
     *     @OA\Parameter(
     *         name="checkout",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="2023-12-22",
     *         @OA\Schema(type="string", format="date"),
     *     ),
    *     @OA\Parameter(
     *         name="currencyCode",
     *         in="query",
     *         description="Currency code (ISO 4217 alpha-3)",
     *         required=true,
     *         example="ARS",
     *         @OA\Schema(type="string"),
     *     ),
    *      @OA\Parameter( 
    *          name="countryCode", 
    *          in="query", 
    *          description="Country code (ISO 3166-1 alpha-2)", 
    *          required=true, 
    *          example="AR",
    *          @OA\Schema( type="string" ),
    *     ),
     *     @OA\Parameter(
     *         name="adults",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="2",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="children",
     *         in="query",
     *         description="",
     *         required=true,
     *         example="2",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="couponCode",
     *         in="query",
     *         description="",
     *         required=false,
     *          example="CODE10",
     *         @OA\Schema( type="string" ),
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="",
     *         required=false,
     *         @OA\Schema(type="string", enum={"es", "pt", "en"}, default="pt"),
     *     ),
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         description="Selected products",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="productId", type="integer", example="2382"),
     *                 @OA\Property(property="includeBreakfast", type="boolean"),
     *                 @OA\Property(property="quantity", type="integer", example="1"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
    *              @OA\Property(
    *                       property="data",
    *                       type="object",
    *                        ref="#/components/schemas/BookingTotalResource"
    *              ),
    *                   @OA\Property( property="message", type="string", description="")
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request",
    *           @OA\JsonContent(
    *           type="object",
    *           @OA\Property(
    *               property="success",
    *               type="boolean",
    *               example=false,
    *               description="Indicates whether the request was successful or not."
    *           ),
   *             @OA\Property(
    *               property="message",
    *               type="string",
    *               example="FIELD_ERROR",
    *               description="A message indicating the reason for the bad request."
    *           ),
    *           @OA\Property(
    *               property="data",
    *               type="object",
    *               additionalProperties={
   *                    "type": "array",
   *                    "items": {
   *                        "type": "string"
   *                    },
    *               "description": "An array of error messages for specific fields."
    *               },
     *              description="A collection of field-specific error messages."
    *           )
    *           )
    *       ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response( response=422, description="Unprocessable Entity" ),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function calculateTotals(Request $request)
    {
        $params = $this->parseTotalsRequest($request);
        if (!$params->ok){
            return $this->sendError($params->message, $params->data, 400);
        }

        $coupon = null;
        if (strlen($params->couponCode)){
            $coupon = $this->couponService->getCouponByCode($params->couponCode);
            if (is_null($coupon)){
                return $this->sendError(ErrorCodes::INVALID_COUPON, ["couponCode" => [__('api.Invalid coupon')]], 422);
            }else if ($coupon->isExpired() ){
                return $this->sendError(ErrorCodes::EXPIRED_COUPON, ["couponCode" => [__('api.Expired coupon')]], 422);
            }else if ($coupon->isOverQuantity() ){
                return $this->sendError(ErrorCodes::UNAVAILABLE_COUPON, ["couponCode" => [__('api.Unavailable coupon')]], 422);
            }
        }

        $hotel = $this->hotelService->getHotelById($params->propertyId);
        $currency = $this->currencyService->getCurrencyByIsoCode($params->currencyCode);
        $nights = $params->nights;
        $checkin = $params->checkin;
        $checkout = $params->checkout;
        $countryCode = $params->countryCode;
        $includeBreakfast = null;
        $totals = [];

        $localCurrency = $hotel->localCurrency;
        $markup = $currency->getMarkupByHotelAndGuestCountry($hotel->country->codIso2, $countryCode);
        $decimals = ClhUtils::getDecimalsByBookingOrigin($currency->codigoISO, ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE);
        $breakfastUSDValue = !is_null($hotel->breakfast())? $hotel->breakfast()->currentPrice()->toUSD() : 0;
        $taxes = $hotel->taxesByCustomerCountryCode($countryCode, ClhConstants::TAX_TYPE_PERCENT_OF_TOTAL);
        
        foreach($params->products as $p){
            $product = $this->productService->getProductById($p->productId);
            if (is_null($product)) {
                return $this->sendError(ErrorCodes::NOT_FOUND, ["productId"=>$p->productId ], 404);
            }
            $rates = $this->occupationService->getRatesByProductId($hotel,$p->productId,$checkin,$checkout); 
            if (is_null($includeBreakfast)){ //asigna desayuno en funcion del primer producto
                $includeBreakfast = $p->includeBreakfast; 
            }
                 
            if (isset($totals[$p->productId])){ //si viene duplicado el mismo productId, retornamos error
                return $this->sendError(ErrorCodes::DUPLICATED_PRODUCT, ["productId"=>$p->productId ], 422);
            }
            $totals[$p->productId] = $this->bookingService->calculateTotalsByProductByCurrency($currency,$markup,$decimals,$rates,$p->quantity,$includeBreakfast?$breakfastUSDValue:0,$nights*$product->cantPersonas*$p->quantity,$taxes,$coupon);
        }
        $fixedTaxes = $this->taxService->calculateFixedTaxesByCurrency($hotel->taxesByCustomerCountryCode($countryCode, ClhConstants::TAX_TYPE_FIXED_PER_ROOMNIGHT_PER_PERSON),$currency,$decimals,$nights,$params->adults);
        $fixedDiscount = $this->bookingService->calculateFixedDiscountsByCurrency($coupon,$currency,$decimals);

        $totals = $this->bookingService->calculatePrebookingTotals($totals,$fixedTaxes,$fixedDiscount,$coupon,$includeBreakfast,$params->language,$currency);
        
        $response = BookingTotalResource::make($totals);

        return $this->sendResponse($response, 'Totals retrieved successfully');
    }

    private function parseTotalsRequest(Request $request){
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|integer',
            'adults' => 'required|integer',
            'children' => 'required|integer',
            'checkin' => 'required|date_format:Y-m-d|after:yesterday',
            'checkout' => 'required|date_format:Y-m-d|after:checkin',
            'countryCode' => 'required|size:2',
            'currencyCode' => 'required|size:3'
        ]);
    
        if ($validator->fails()) {
            return  (object)[ "ok" => false, "message" => ErrorCodes::FIELD_ERROR, "data" => $validator->errors()];
        }

        $propertyId = $request->input("propertyId");
        $checkin = $request->input("checkin");
        $checkout = $request->input("checkout");
        $currencyCode = $request->input("currencyCode");
        $adults = $request->input("adults");
        $countryCode = $request->input("countryCode");
        $couponCode = $request->input("couponCode");
        $language = $request->input("language", "pt");
        $products = json_decode($request->getContent());

        $checkinDate = Carbon::createFromFormat("Y-m-d", $checkin);
        $checkoutDate = Carbon::createFromFormat("Y-m-d", $checkout);
        $nights = $checkinDate->diffInDays($checkoutDate);

        return (object) [ "ok" => true, "propertyId" => $propertyId, "adults" => $adults, "currencyCode" => $currencyCode,  "couponCode" => $couponCode,
                        "checkin" => $checkin, "checkout" => $checkout, "nights" => $nights, "products" => $products, "countryCode" => $countryCode,
                        "language" => $language
                    ];

    }
    
    
    /**
     * @OA\Post(
     *     path="/booking/new",
     *     tags={"Booking"},
     *     summary="Create a new booking.",
     *     operationId="new",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Booking details",
     *         @OA\JsonContent(
     *              @OA\Property(property="propertyId", type="integer", example="47"),
     *              @OA\Property(property="domain", type="string", enum={"chelagarto", "clhsuites"}, default="chelagarto"),
     *              @OA\Property(property="language", type="string", enum={"pt", "es", "en"}, default="pt"),
    *                 @OA\Property(property="countryCode", type="string", example="AR", description="Country code (ISO 3166-1 alpha-2)"),
    *                 @OA\Property(property="currencyCode", type="string", description="Currency code (ISO 4217 alpha-3)", example="ARS"),
    *              @OA\Property(property="paymentMethod", @OA\Schema( enum={"CC", "PIX"}, default="CC",example="CC" )),
     *             @OA\Property(property="cardInfo", type="object",
     *                 @OA\Property(property="encryptedData", type="string"),
     *                 @OA\Property(property="bin", type="string")
     *             ),
     *             @OA\Property(property="guestInfo", type="object",
     *                 @OA\Property(property="name", type="string", example="Juan"),
     *                 @OA\Property(property="surname", type="string", example="Pérez"),
     *                 @OA\Property(property="email", type="string", example="jperez@gmail.com"),
     *                 @OA\Property(property="cellphone", type="string"),
     *                 @OA\Property(property="bornDate", type="string", format="date", example="1990-10-10"),
     *                 @OA\Property(property="countryResidence", type="string",description="Country code (ISO 3166-1 alpha-2)", example="AR"),
     *                 @OA\Property(property="cityResidenceId", type="int",description="", example="1"),
     *                 @OA\Property(property="documentNumber", type="string", example="20365523"),
     *             ),
     *              @OA\Property(property="adults", type="integer", example="2"),
     *              @OA\Property(property="children", type="integer", example="2"),
     *              @OA\Property(property="couponCode", type="string", example="CODE10"),
     *              @OA\Property(property="intentionId", type="int", example="2"),
     *              @OA\Property(property="specialRequests", type="string"),
     *             @OA\Property(property="stay", type="object",
     *                     @OA\Property(property="checkin", type="string", format="date", example="2023-12-20"),
     *                     @OA\Property(property="checkout", type="string", format="date", example="2023-12-22"),
     *                     @OA\Property(property="products", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="productId", type="integer", example="2382"),
     *                             @OA\Property(property="includeBreakfast", type="boolean"),
     *                             @OA\Property(property="quantity", type="integer", example="1")
     *                         )
     *                     )
     *             ),
     *              @OA\Property(property="operatorId", type="integer", example="1")
     *         ),
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BookingResource"
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response( response=422, description="Unprocessable Entity" ),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function newBooking(Request $request) {
        $type = $this->determineBookingType($request); 
      
        $strategy = BookingStrategyFactory::create($type, $this->bookingService, $this->hotelService , $this->occupationService, $this->currencyService);
        $validation = $strategy->validate($request);
        if (!$validation->ok) {
            return $this->sendError($validation->message, $validation->errors, 400);
        }

        $params = $validation->data;
        $coupon = null;
        if (strlen($params->couponCode)){
            $coupon = $this->couponService->getCouponByCode($params->couponCode);
            if (is_null($coupon)){
                return $this->sendError(ErrorCodes::INVALID_COUPON, ["couponCode" => [__('api.Invalid coupon')]], 422);
            }else if ($coupon->isExpired() ){
                return $this->sendError(ErrorCodes::EXPIRED_COUPON, ["couponCode" => [__('api.Expired coupon')]], 422);
            }else if ($coupon->isOverQuantity() ){
                return $this->sendError(ErrorCodes::UNAVAILABLE_COUPON, ["couponCode" => [__('api.Unavailable coupon')]], 422);
            }
        }
        
        try {
            $processBooking = $strategy->processBooking($params,$coupon);
            if (!$processBooking->ok){
                return $this->sendError($processBooking->message, $processBooking->data, 422);
            }
            
            $booking = $processBooking->booking;
            $totals = $this->bookingService->calculateBookingTotals($booking,$params->language);
            $response = BookingResource::make($booking,$totals,$params->language);

            return $this->sendResponse($response, 'Booking created successfully.');

        } catch (\Exception $e) {
            ClhUtils::sendNotification($e->getMessage(), $e->getFile(). " Line: ".  $e->getLine());
            return $this->sendError(ErrorCodes::EXCEPTION_ERROR, ["error" => $e->getMessage()], 400);
        }
    }
    
    private function determineBookingType(Request $request) {
        // Lógica para determinar si la reserva es de un operador o un cliente
        // Esto puede depender de la presencia de ciertos campos en la solicitud
        return $request->has('operatorId') && strlen($request->input("operatorId")) ? 'operator' : 'customer';
    }


    public function getTotalsBookingById(Request $request,$id)
    {
        $booking = $this->bookingService->getBookingById($id);
        $lang = $request->input("lang","pt");
        $includeDetails = $request->input("includeDetails",false);
        $configOptions = [ ClhConstants::CURRENCY_USD_TYPE, ClhConstants::CURRENCY_GUEST_TYPE, ClhConstants::CURRENCY_LOCAL_TYPE];
        $response = [];
        foreach($configOptions as $configOption){
            $response[$configOption] = ["accommodationTotal"=>$booking->getAccommodationTotalInCurrency($configOption),
                                        "extrasTotal"=>$booking->getExtraServicesTotalInCurrency($configOption),     
                                        "discountsTotal" => $booking->getDiscountsInCurrency($configOption),
                                        "taxesTotal" =>$booking->getTaxesInCurrency($configOption), 
                                        "finalPrice"=>$booking->getFinalPriceInCurrency($configOption),
                                        "totalPaid" =>$booking->getTotalPaidInCurrency($configOption),
                                        ];
        }
        // El detalle por noche solo lo incluimos en moneda local y usd
        if($includeDetails){
            $configDetailOptions = [ ClhConstants::CURRENCY_USD_TYPE, ClhConstants::CURRENCY_LOCAL_TYPE];
            $details = [];
            foreach($configDetailOptions as $configDetailOption){
                $details [$configDetailOption] = $this->bookingService->calculateBookingDetailsTotals($booking,$lang,$configDetailOption);
            }    
            $response = array_merge($response, ["details"=>$details]);
        }
        return $this->sendResponse($response, 'Totals retrieved successfully');
    }
    public function clearBookingAddOns($id)
    {
        $booking = $this->bookingService->getBookingById($id);
        if (is_null($booking)) {
            return (object)["ok"=>false, "message"=> ErrorCodes::NOT_FOUND, "data" => ["bookingId"=>$id ]];
        }
        $this->bookingService->clearBookingAddOns($id);
        return $this->sendResponse([], 'AddOns cleared successfully');

    }
    
    /**
    * @OA\Post(
    *      path="/booking/{id}",
    *      operationId="getBookingById",
    *      tags={"Booking"},
    *      summary="Get the booking details.",
    *      description="Get the booking details.",
    *      security={{"sanctum":{}}},
    *     @OA\Parameter(
    *         name="id", in="path", description="", required=true, example="956", @OA\Schema(type="integer")
    *     ),
    *       @OA\Parameter(
    *         name="language", required=false, in="query", description="", @OA\Schema( enum={"es", "pt", "en"}, default="pt" )
    *     ),
    *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BookingResource"
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
    *     @OA\Response(response=400, description="Bad request",
    *           @OA\JsonContent(
    *           type="object",
    *           @OA\Property(
    *               property="success",
    *               type="boolean",
    *               example=false,
    *               description="Indicates whether the request was successful or not."
    *           ),
   *             @OA\Property(
    *               property="message",
    *               type="string",
    *               example="FIELD_ERROR",
    *               description="A message indicating the reason for the bad request."
    *           ),
    *           @OA\Property(
    *               property="data",
    *               type="object",
    *               additionalProperties={
   *                    "type": "array",
   *                    "items": {
   *                        "type": "string"
   *                    },
    *               "description": "An array of error messages for specific fields."
    *               },
     *              description="A collection of field-specific error messages."
    *           )
    *           )
    *       ),
    *     @OA\Response( response=404, description="Not found" ),
    *     @OA\Response( response=405, description="Method Not Allowed" ),
    *     @OA\Response( response=422, description="Unprocessable Entity" ),
    *     @OA\Response( response=500, description="Internal server error" ),
    *  )
    */
    public function getBookingById(Request $request, $id)
    {
        $language = $request->input("language","pt");
        $booking = $this->bookingService->getBookingById($id);
        if (is_null($booking)) {
            return $this->sendError("Not found", [], 404);
        }
        $totals = $this->bookingService->calculateBookingTotals($booking,$language);
        $response = BookingResource::make($booking,$totals,$language);
        
        return $this->sendResponse($response, 'Booking returned successfully.');
    }

    /**
     * @OA\Post(
     *     path="/booking/changePaymentMethod/{id}",
     *     tags={"Booking"},
     *     summary="Change booking payment method.",
     *     operationId="changePaymentMethod",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id", in="path", description="", required=true, example="956", @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Booking details",
     *         @OA\JsonContent(
     *              @OA\Property(property="language", type="string", enum={"pt", "es", "en"}, default="pt"),
    *              @OA\Property(property="paymentMethod", @OA\Schema( enum={"CC", "PIX"}, default="CC",example="CC" )),
     *             @OA\Property(property="cardInfo", type="object",
     *                 @OA\Property(property="encryptedData", type="string"),
     *                 @OA\Property(property="bin", type="string")
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BookingResource"
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=405, description="Method Not Allowed"),
     *     @OA\Response( response=422, description="Unprocessable Entity" ),
     *     @OA\Response(response=500, description="Internal server error"),
     * )
     */
    public function changePaymentMethod(Request $request, $id)
    {
        $params = $this->parseUpdateBookingPaymentRequest($request);
        if (!$params->ok){
            return $this->sendError($params->message, $params->data, 400);
        }
        $language = $request->input("language","pt");
        $booking = $this->bookingService->getBookingById($id);
        // Por el momento si la reserva esta cancelada le retornamos not found al front end
        if (is_null($booking) || $booking->getStatus() == "cancelled") {
            return $this->sendError("Not found", [], 404);
        }
        $this->bookingService->dispatchUpdatePayment($booking,$params->paymentMethod,$params->cardInfo['encryptedData'] ?? null,$params->cardInfo['bin'] ?? null,$params->language);
        $totals = $this->bookingService->calculateBookingTotals($booking,$language);
        $response = BookingResource::make($booking,$totals,$language);
        
        return $this->sendResponse($response, 'Booking updated successfully.');
    }
    /**
    * @OA\Delete(
    *      path="/booking/{id}",
    *      operationId="cancelBookingById",
    *      tags={"Booking"},
    *      summary="Cancel booking.",
    *      description="Cancel booking.",
    *      security={{"sanctum":{}}},
    *     @OA\Parameter(
    *         name="id", in="path", description="", required=true, example="956", @OA\Schema(type="integer")
    *     ),
    *       
    *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BookingResource"
     *             ),
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
    *     @OA\Response(response=400, description="Bad request",
    *           @OA\JsonContent(
    *           type="object",
    *           @OA\Property(
    *               property="success",
    *               type="boolean",
    *               example=false,
    *               description="Indicates whether the request was successful or not."
    *           ),
   *             @OA\Property(
    *               property="message",
    *               type="string",
    *               example="FIELD_ERROR",
    *               description="A message indicating the reason for the bad request."
    *           ),
    *           @OA\Property(
    *               property="data",
    *               type="object",
    *               additionalProperties={
   *                    "type": "array",
   *                    "items": {
   *                        "type": "string"
   *                    },
    *               "description": "An array of error messages for specific fields."
    *               },
     *              description="A collection of field-specific error messages."
    *           )
    *           )
    *       ),
    *     @OA\Response( response=404, description="Not found" ),
    *     @OA\Response( response=405, description="Method Not Allowed" ),
    *     @OA\Response( response=422, description="Unprocessable Entity" ),
    *     @OA\Response( response=500, description="Internal server error" ),
    *  )
    */
    public function cancelBookingById(Request $request, $id)
    {
        $cancelledByNoWarranty = $request->input("cancelledByNoWarranty",null);
        $language = 'pt';
        $booking = $this->bookingService->getBookingById($id);
        // Por el momento si la reserva esta cancelada le retornamos not found al front end
        if (is_null($booking) || $booking->getStatus() == "cancelled") {
            return $this->sendError("Not found", [], 404);
        }
        $response= $this->bookingService->cancelBooking($booking,$cancelledByNoWarranty);
        if (!$response->ok){
            return $this->sendError($response->message, $response->data, 404);
        }else{
            $totals = $this->bookingService->calculateBookingTotals($booking,$language);
            $response = BookingResource::make($booking,$totals,$language);
            
            return $this->sendResponse($response, 'Booking cancelled successfully.');
        }

    }
    private function parseUpdateBookingPaymentRequest($request){
        
        $rules = [
            'language' => 'required',
            'paymentMethod'=>'required',
            'cardInfo' => 'required_if:paymentMethod,CC'
        ];
    
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return  (object)[ "ok" => false, "message" => ErrorCodes::FIELD_ERROR, "data" => $validator->errors()];
        }
        $cardInfo = $request->input('cardInfo');
        $paymentMethod = $request->input('paymentMethod');
        $language = $request->input('language', "pt");
        

        return (object) [ "ok" => true, "language" => $language,"cardInfo" => $cardInfo, "paymentMethod" => $paymentMethod,
        ];

    }

    /**
     * @TODO repensar!!
     */
    public function regenerateRatesByNewBookingTotal(Request $request, $id)
    {   //newBookingTotal, indexAlternativeUSD
        $newBookingTotalLocalCurrency = $request->input("newBookingTotal");
        $booking = $this->bookingService->getBookingById($id);
        if (is_null($booking)) {
            return $this->sendError("Not found", [], 404);
        }
        $indice = ($request->input("indexAlternativeUSD") > 0)? $request->input("indexAlternativeUSD") :  $booking->indiceMoneda;
        $booking->indiceMoneda = $indice;
        $localCurrency = $booking->localCurrency;
        $isoLocalCurrency = $localCurrency->codigoISO;
        $t = ClhConstants::CURRENCY_LOCAL_TYPE;
        $oldBookingTotalLocalCurrency = $booking->getFinalPriceInCurrency($t);
        if(abs($newBookingTotalLocalCurrency - $oldBookingTotalLocalCurrency) > 0.05){
            $newAccommodationLocalCurrency = $newBookingTotalLocalCurrency-($booking->getBreakfastInCurrency($t)-$booking->getBreakfastDiscountInCurrency($t)+$booking->getBreakfastTaxesInCurrency($t));
            $oldAccommodationLocalCurrency = $oldBookingTotalLocalCurrency-($booking->getBreakfastInCurrency($t)-$booking->getBreakfastDiscountInCurrency($t)+$booking->getBreakfastTaxesInCurrency($t));
            $adjustmentFactor = $oldAccommodationLocalCurrency != 0 ? $newAccommodationLocalCurrency / $oldAccommodationLocalCurrency : 1;
            foreach ($booking->details as $detail) {
				$aux = $detail->PU;
                $newPU = $detail->PU * $adjustmentFactor; // Ajusta según tu lógica de negocio
                $detail->PU = $newPU;
                $detail->save();

                $msg = 'Se actualizó la tarifa de alojamiento '.$detail->product->codigo.' de '.ClhUtils::moneyFormat(($aux * $detail->cant)/$indice,2)." $isoLocalCurrency a ".ClhUtils::moneyFormat(($detail->PU * $detail->cant)/$indice,2)." $isoLocalCurrency para ". Carbon::parse($detail->fecha)->format('d/m/Y'). ' por cambio de medio de pago';
				ClhBookingTracking::insert($booking->id, $msg);
            }

            foreach ($booking->taxesByProduct as $tax) {
                $newTotalUsd = $tax->total_usd * $adjustmentFactor; // Ajusta según tu lógica de negocio
				
				$detail->update(["id_impuesto"=>$tax->id_impuesto,"id_reserva"=>$tax->id_reserva,"id_producto"=>$tax->id_producto],["total_usd"=>$newTotalUsd]);
            }
			
			foreach ($booking->discountsProduct as $tax) {
                $newTotalUsd = $tax->total_usd * $adjustmentFactor; // Ajusta según tu lógica de negocio
				
				$detail->update(["id_descuento"=>$tax->id_descuento,"id_reserva"=>$tax->id_reserva,"id_producto"=>$tax->id_producto],["total_usd"=>$newTotalUsd]);
            }

            $booking->importeTotal = $booking->getFinalPriceInCurrency();
            $booking->totalCMarckup=$booking->importeTotal;
            $booking->marckup = 0;
            $booking->marckupConfigurado= 0;
            $booking->idMonedaPasajero=$booking->idMonedaHostel;
            $booking->indiceMonedaPasajero=$booking->indiceMoneda;

            $booking->save();
            
        }
        return $this->sendResponse([], 'Booking regenerated successfully.');

    }
}
