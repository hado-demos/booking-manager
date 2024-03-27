<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Utils\Constants;

/**
 * @OA\Schema(
 *     schema="ProductResource",
 *     type="object",
 *     title="Product Resource",
 * )
 */
class ProductResource extends JsonResource
{
    /**
     * @OA\Property(
     *     property="id",
     *     type="integer",
     *     description="",
     *      example=2382
     * )
     *
     * @var int
     */
    public $id;

    /**
     * @OA\Property(
     *     property="content",
     *     type="object",
     *     @OA\Property(
     *         property="name",
     *         type="string",
     *         example="Apartamento Superior (2amb)"
     *     ),
     *     @OA\Property(
     *         property="code",
     *         type="string",
     *         example="2AMB"
     *     ),
     *     @OA\Property(
     *         property="description",
     *         type="string",
     *         example="Apartamento Superior con kitchenette, frigobar y elementos básicos de cocina- Wifi incluso"
     *     ),
     *     @OA\Property(
     *         property="type",
     *         type="string",
     *         example="private",
     *         enum={"private", "shared"}
     *     ),
     *     @OA\Property(
     *         property="gender",
     *         type="string",
     *         example="female",
     *         enum={"female", "male", "mixed"}
     *     ),
     *     @OA\Property(
     *         property="occupancy",
     *         type="integer",
     *          example=2,
     *         description="Maximum occupancy of the room."
     *          
     *     ),
     *     @OA\Property(
     *         property="services",
     *         type="array",
     *          @OA\Items(ref="#/components/schemas/ServiceResource"),
     *         description=""
     *     ),
     *     @OA\Property(
     *         property="photos",
     *         type="array",
     *         @OA\Items(type="string"),
     *         description=""
     *     )
     * )
     *
     * @var array
     */
    public $content;

    /**
     * @OA\Property(
     *     property="availability",
     *     type="integer",
     *     example="15"
     * )
     *
     * @var int
     */
    public $availability;

    /**
     * @OA\Property(
     *     property="rates",
     *     type="array",
     *      @OA\Items(ref="#/components/schemas/RateResource"),
     *     description=""
     * )
     * 
     *
     * @var array
     */
    public $rates;

    public function __construct($resource,$language, $minAvailability, $rates)
    {
        parent::__construct($resource);
        $this->id = $resource->id;
        $this->rates = $rates;
        $this->language = $language;
        $this->minAvailability = $minAvailability;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $details = $this->detailsBy($this->language);

        $productServices = $this->services()->get();
        $services = [];
        foreach($productServices as $dummy => $sbp){
            $services[] = ServiceResource::make($sbp->service, $this->language);
        }

        $productPhotos = $this->photos()->orderBy("orden")->get();
        $photos = [];
        foreach($productPhotos as $dummy => $sbp){
            $photos[] = $sbp->url;
        }

        return ['id' =>$this->id,    
        'content'=>[
            'name'=>$details->nombre,
            'code'=>$this->codigo,
            'description'=>$details->descripcion,
            'type'=>in_array($this->tipocuarto, [Constants::PRODUCT_TYPE_PRIVATE, Constants::PRODUCT_TYPE_DEPARTMENT])?'private' :'shared',
            'gender'=>$this->tipocuarto == Constants::PRODUCT_TYPE_SHARED_FEMALE? "female" : ($this->tipocuarto == Constants::PRODUCT_TYPE_SHARED_MALE? "male":"mixed"), 
            'occupancy'=>$this->cantPersonas,
            'services'=> $services,
            'photos'=> $photos,
            ],
            'availability'=>$this->minAvailability,
            'rates' => $this->rates,
        ];
    }

}
