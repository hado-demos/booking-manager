<?php

namespace App\Http\Controllers;

use ClhGroup\ClhBookings\Services\ClhCurrencyService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Utils\Constants;
use App\Http\Resources\CurrencyResource;

class CurrencyController extends BaseController
{

    public function __construct(ClhCurrencyService $currencyService)
{
    $this->currencyService = $currencyService;
}
    /**
     * @OA\Get(
     *     path="/currencies",
     *     tags={"Currency"},
     *     summary="Get list of currencies",
     *     operationId="getCurrencies",
     *     security={{"sanctum":{}}},
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
     *                 type="object",
     *                  @OA\Property(property="currencies", type="array",
     *                      @OA\Items(ref="#/components/schemas/CurrencyResource"),
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
    public function getCurrencies(Request $request)
    {
        $language = $request->input('language', 'pt');

        $currencies = $this->currencyService->getCurrencies(Constants::API_CURRENCIES);
    
        foreach ($currencies as $currency){
            $response[] = CurrencyResource::make($currency,$language);
        }

        return $this->sendResponse(["currencies" => $response], 'Currencies retrieved successfully');
    }


}
