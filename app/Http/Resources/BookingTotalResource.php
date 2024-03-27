<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Utils\Utils;
use App\Models\Tax;
use App\Utils\Constants;
use OpenApi\Annotations as OA;
use MichaelRubel\Couponables\Models\Contracts\CouponContract;
use ClhGroup\ClhBookings\Utils\ClhUtils;

/**
 * @OA\Schema(
 *     schema="BookingTotalResource",
 *     type="object",
 *     title="Booking Total Resource",
 * )
 */
class BookingTotalResource extends JsonResource
{
 /**
     * @OA\Property(
     *     property="grandTotal",
     *     type="object",
*         description="The booking final price.",
     *     @OA\Property(
     *         property="priceBeforeTax",
     *         type="string",
     *          example="10.000",
     *         description="The booking price not including taxes."
     *     ),
     *     @OA\Property(
     *         property="priceAfterTax",
     *         type="string",
     *         description="The booking final price including taxes.",
     *          example="12.100"
     *     ),
     *     @OA\Property(
     *         property="taxes",
     *          description="The breakdown of the applied taxes.",
     *          type="object",
     *             @OA\Property(property="total", type="string"),
     *             @OA\Property(property="list", type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="taxName", type="string", example="IVA"),
     *                      @OA\Property(property="taxValue", type="string", example="2.100")
     *                  )   ,
     *              description=""),
     *     ),
     * )
     *
     * @var array
     */
    public $grandTotal;

    /**
     * @OA\Property(
     *     property="products",
     *     type="array",
     * description="The list of selected products and their prices, before and after taxes.",
     *     @OA\Items(
     *              type="object",
     *             @OA\Property(property="productName",  type="string", example="Apartamento Superior"),
     *             @OA\Property(property="priceBeforeTax", type="string", example="87.000"),
     *             @OA\Property(property="priceAfterTax", type="string", example="87.314"),
     *             @OA\Property(property="taxes", type="string", example="314"),
     *      ),
     *     description=""
     * )
     *
     * @var array
     */
    public $products;

/**
     * @OA\Property(
     *     property="breakfast",
     *     type="object",
     *    description="The total price of the breakfast for all selected products, before and after taxes.",
     *     @OA\Property(
     *         property="priceBeforeTax",
     *         type="string",
     *         description="",
     *          example="87.000"
     *     ),
     *     @OA\Property(
     *         property="priceAfterTax",
     *         type="string",
     *         description="",
     *          example="87.500"
     *     ),
     *     @OA\Property(
     *         property="taxes",
     *          description="",
     *          type="string",
     *          example="500"
     *     ),
     * )
     *
     * @var array
     */
    public $breakfast;

    /**
     * @OA\Property(
     *     property="promotion",
     *     ref="#/components/schemas/PromotionResource"
     * )
     *
     * @var array
     */
    public $promotion;

    /**
     * @OA\Property(
     *     property="currency",
     *     ref="#/components/schemas/CurrencyResource"
     * )
     *
     * @var array
     */
    public $currency;

    public function __construct($resource)
     {
        parent::__construct($resource);

    }
    public function toArray(Request $request): array
    {
        $decimals = ClhUtils::getDecimalsByBookingOrigin($this["currency"]["isoCode"]);

        $taxesList = [];
        foreach ($this["grandTotal"]["taxes"]["list"] as $tax){
            $taxesList[] = ["taxName" => $tax["taxName"], "taxValue" => ClhUtils::moneyFormat($tax["taxValue"],$decimals)];
        }
        $products = [];
        foreach ($this["products"] as $product){
            $products[] = [
                "productName" => $product["productName"],
                "priceBeforeTax" => ClhUtils::moneyFormat($product["priceBeforeTax"],$decimals),
                "priceAfterTax" => ClhUtils::moneyFormat($product["priceAfterTax"],$decimals),
                "taxes" => ClhUtils::moneyFormat($product["taxes"],$decimals)
            ];

        }
        $promotion = $this["promotion"];
        $promotion["discountValue"] = ClhUtils::moneyFormat($promotion["discountValue"],$decimals);
        $promotion["grandTotalBeforeDiscountBeforeTax"] = ClhUtils::moneyFormat($promotion["grandTotalBeforeDiscountBeforeTax"],$decimals);
        return [
            "grandTotal" => 
            [
                "priceBeforeTax" => ClhUtils::moneyFormat($this["grandTotal"]["priceBeforeTax"],$decimals),
                "priceAfterTax" => ClhUtils::moneyFormat($this["grandTotal"]["priceAfterTax"],$decimals),
            "taxes" => ["total"=> ClhUtils::moneyFormat($this["grandTotal"]["taxes"]["total"],$decimals), "list" => $taxesList],
            ],
            "products" => $products,
            "breakfast" => 
            [
                "priceBeforeTax" => ClhUtils::moneyFormat($this["breakfast"]["priceBeforeTax"],$decimals),
                "priceAfterTax" => ClhUtils::moneyFormat($this["breakfast"]["priceAfterTax"],$decimals),
                "taxes" => ClhUtils::moneyFormat($this["breakfast"]["taxes"],$decimals),
            ],
            "promotion" => $promotion,
            "currency" =>  ["isoCode"=> $this["currency"]["isoCode"], "symbol"=> $this["currency"]["symbol"]]
            ];
    }

}
