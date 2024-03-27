<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema( schema="ServiceResource", title="Service Resource", description="" )
 */
class ServiceResource extends JsonResource
{

    /**
      *@OA\Property(property="name", type="string", example="Piscina"),
     * @var string
     */
    public $name;

  /**
      *@OA\Property(property="description", type="string", example="Piscina"),
     * @var string
     */
    public $description;

      /**
      *@OA\Property(property="iconType", type="string", example="pool", enum={"computer","kitchen","bar","locker","breakfast","minibar","cleaning_service","dinner","tv","jacuzzi","microwave","telephone","game_room","laundry_service","wifi","towels","air_conditioning","terrace","24h_reception","bbq","parking","private_bathroom","fan","pool","lunch","heating"}),
     * @var string
     */
    public $iconType;

    protected $language;

    public function __construct($service, $language)
    {
        parent::__construct($service);
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
        $details = $this->detailsBy($this->language);
        return [
            'name' => $details->nombre,
            'description' => $details->descripcion,
            'iconType' => $this->tipo_icono,
        ];
    }
}
