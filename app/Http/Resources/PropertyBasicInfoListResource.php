<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema( schema="PropertyBasicInfoListResource", title="Property Basic Info List Resource", description="" )
 */
class PropertyBasicInfoListResource extends JsonResource
{
    /**
    *     @OA\Property(
     *         property="properties",
     *         type="array",
     *          @OA\Items(ref="#/components/schemas/PropertyBasicInfoResource"),
     *         description=""
     *     )
     * @var array
     */
    public $properties;

    protected $language;

    public function __construct($properties, $language)
    {
        $this->properties = $properties;
        $this->language = $language;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $destinations = [];
        foreach($this->properties as $hotel){
            $destinations[] = PropertyBasicInfoResource::make($hotel, $this->language);
        }
        return ["properties" => $destinations];
    }
}
