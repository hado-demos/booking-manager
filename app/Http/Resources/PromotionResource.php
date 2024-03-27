<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;
use MichaelRubel\Couponables\Models\Contracts\CouponContract;

/**
 * @OA\Schema(
 *     schema="PromotionResource",
 *     type="object",
 *     title="Promotion Resource",
 * )
 */
class PromotionResource extends JsonResource
{
    /**
     * @OA\Property(
     *     property="id",
     *     type="integer",
     *     example="4521"
     * )
     *
     * @var int
     */
    public $id;

    /**
     * @OA\Property(
     *     property="description",
     *     type="string",
     *     example="CODE10"
     * )
     *
     * @var string
     */
    public $description;

    /**
     * @OA\Property(
     *     property="hasDiscount",
     *     type="boolean",
     *     description=""
     * )
     *
     * @var bool
     */
    public $hasDiscount;

    /**
     * @OA\Property(
     *     property="discountType",
     *     type="string",
     *     example="percent",
     *      enum={"percent", "fixed"}
     * )
     *
     * @var string
     */
    public $discountType;
        
    /**
     * @OA\Property(
     *     property="discountPercent",
     *     type="float",
     *     example="10"
     * )
     *
     * @var int
     */
    public $discountPercent;

    /**
     * @OA\Property(
     *     property="discountValue",
     *     type="float",
     *     example="100"
     * )
     *
     * @var float
     */
    public $discountValue;

    /**
     * @OA\Property(
     *     property="grandTotalBeforeDiscountBeforeTax",
     *     type="float",
     *     example="1000"
     * )
     *
     * @var float
     */
    public $grandTotalBeforeDiscountBeforeTax;

}