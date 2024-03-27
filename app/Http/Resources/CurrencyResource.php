<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema( schema="CurrencyResource", title="Currency Resource", description="" )
 */
class CurrencyResource extends JsonResource
{

    /**
      *@OA\Property(property="isoCode", type="string", example="ARS"),
     * @var string
     */
    public $isoCode;

  /**
      *@OA\Property(property="symbol", type="string", example="$"),
     * @var string
     */
    public $symbol;

  /**
      *@OA\Property(property="name", type="string", example="Peso Argentino"),
     * @var string
     */
    public $name;

    protected $language;

    public function __construct($resource, $language)
    {
        parent::__construct($resource);
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
            'isoCode' => $this->codigoISO,
            'symbol' => $this->signoMoneda,
        ];
    }
}
