<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema( schema="BookingResource", title="Booking Resource", description="" )
 */
class BookingResource extends JsonResource
{
    /**
    * @OA\Property(property="id", type="int", example="50"),
     * @var int
     */
    public $id;

    /**
      *@OA\Property(property="checkin", type="string", format="date", example="2023-12-02"),
     * @var string
     */
    public $checkin;

    /**
      *@OA\Property(property="checkout", type="string", format="date", example="2023-12-04"),
     * @var string
     */
    public $checkout;

    /**
    * @OA\Property(property="nights", type="int", example="2"),
     * @var int
     */
    public $nights;

    /**
      *@OA\Property(property="timein", type="string", example="14:00"),
     * @var string
     */
    public $timein;

    /**
      *@OA\Property(property="timeout", type="string", example="11:00"),
     * @var string
     */
    public $timeout;

    /**
      *@OA\Property(property="status", type="string", example="confirmed"),
     * @var string
     */
    public $status;

        /**
    *     @OA\Property(
     *         property="guest",
     *         type="object",
     *          @OA\Property(property="name", type="string", example="Joe"),
     *          @OA\Property(property="surname", type="string", example="Doe"),
     *          @OA\Property(property="email", type="string", example="joedoe@gmail.com"),
     *         description=""
     *     ),
     * @var object
     */
  public $guest;

    /**
      *@OA\Property(property="property", ref="#/components/schemas/PropertyResource"),
     * @var string
     */
    public $property;
    /**
    * @OA\Property(
      *                       property="total",
      *                       type="object",
      *                        ref="#/components/schemas/BookingTotalResource"
      *              ),
    *  @var object
    */
    public $total;

    protected $language; 
    protected $domain; 
    protected $totals; 

    public function __construct($resource,$totals,$language, $domain="chelagarto")
    {
        parent::__construct($resource);
        $this->language = $language;
        $this->domain = $domain;
        $this->id = $resource->id;
        $this->guest = $resource->guest;
        $this->totals = $totals;

    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         //  return parent::toArray($request);
        $checkin = $this->getDatetimeCheckin();
        $checkout = $this->getDatetimeCheckout();
        
        $property =  PropertyResource::make($this->hotel, $this->language, $this->domain);
        if (!$this->guest->isBrazilian()){
          $property->setPaymentMethods(["CC"]); //se elimina PIX para no Brasileros
        }
        
        return [
            'id' => $this->id,
            'checkin' => $checkin->format("Y-m-d"),
            'checkout' => $checkout->format("Y-m-d"),
            'nights' => $this->cantNoches,
            'timein' => $checkin->format("H:i"),
            'timeout' => $checkout->format("H:i"),
            'status' => $this->getStatus(),
            'guest' => [
               'name' => $this->guest->nombre,
               'surname' => $this->guest->apellido,
               'email' => $this->guest->mail,
            ],
            'property' => $property,
            'total' => BookingTotalResource::make($this->totals)
        ];
    }
}
