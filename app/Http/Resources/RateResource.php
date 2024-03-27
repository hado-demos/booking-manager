<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use OpenApi\Annotations as OA;
/**
 * @OA\Schema(
 *     schema="RateResource",
 *     type="object",
 *     title="Rate Resource",
 * )
 */
class RateResource extends JsonResource
{

    /**
     * @OA\Property(
     *     property="priceAfterTax", type="array", @OA\Items(
     *             @OA\Property(property="currency", type="string", example="ARS"),
     *             @OA\Property(property="symbol", type="string", example="$"),
     *             @OA\Property(property="accommodation", type="string", example="87.314"),
     *             @OA\Property(property="breakfast", type="string", example="12.000")
     *         )
     * )
     *
     * @var array
     */
    public $priceAfterTax;



    protected $totals;
    protected $symbols;
    
    public function __construct($resource, $totals, $symbols)
    {
        parent::__construct($resource);
        $this->totals = $totals;
        $this->symbols = $symbols;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priceAfterTax = [];  
            
        foreach($this->totals as $currency => $total){
            $decimals = ClhUtils::getDecimalsByBookingOrigin($currency, ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE);

            $t =  round($total["accommodationWithoutTax"]+$total["accommodationTaxes"],$decimals);
            $bk = round($total["breakfastWithoutTax"]+$total["breakfastTaxes"],$decimals);

            $priceAfterTax[] = ["currency"=>$currency,"symbol"=>$this->symbols[$currency],
                                    "accommodation" => ClhUtils::moneyFormat($t,$decimals),
                                     "breakfast" => ClhUtils::moneyFormat($bk,$decimals)
                                ];      
        }

        return [
                "priceAfterTax" => $priceAfterTax
            ];
    }
}
