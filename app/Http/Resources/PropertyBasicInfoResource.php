<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema( schema="PropertyBasicInfoResource", title="Property Basic Info Resource", description="" )
 */
class PropertyBasicInfoResource extends JsonResource
{
    /**
    * @OA\Property(property="id", type="int", example="40"),
     * @var int
     */
    public $id;

    /**
      *@OA\Property(property="name", type="string", example="Copacabana"),
     * @var string
     */
    public $name;

/**
 *         @OA\Property(property="country",  type="object", 
            *     @OA\Property(property="isoCode", type="string", example="BRA"),
            *     @OA\Property(property="name", type="string", example="Brazil"),
*           )
     * @var object
     */
    public $country;



    protected $language;

    public function __construct($hotel, $language)
    {
        parent::__construct($hotel);
        $this->id = $hotel->id;
        $this->country = $hotel->country;
        $this->language = $language;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //   return parent::toArray($request);

        return [
            'id' => $this->id,
            'name' => $this->detailsBy($this->language)->nombre,
            'country' =>[
                "isoCode" => $this->country->codIso3,
                "name" => $this->country->detailsBy($this->language)->nombre,
            ],
        ];
    }
}
