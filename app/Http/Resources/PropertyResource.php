<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema( schema="PropertyResource", title="Property Resource", description="" )
 */
class PropertyResource extends JsonResource
{
    /**
     * @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=40
     * )
     * @var int
     */
    public $id;

     /**
     * @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Copacabana"
     * ),
     * @var string
     */
    public $name;

     /**
     * @OA\Property(
     *     property="description",
     *     type="string",
     *     example="Um Hotel único na maravilhosa cidade de Bonito! Com as instalações, o conforto, o nível e o serviço de um Hotel de uma grande cidade, porém em um destino de extrema natureza, aventura e biodiversidade. Estrategicamente localizado em frente à avenida principal, CLH Suites Bonito Sul, conta com estacionamento, lavanderia e agência de turismo. Mas, se além de conforto, você também quiser se divertir, esperamos você para relaxar em nossa espetacular piscina enquanto prepara um delicioso churrasco em nossa churrasqueira. Prefere curtir o bar ou jogar com os amigos? Este é o lugar! 33 quartos confortáveis, amplos e muito bem equipados. Tanto os quartos privativos quanto os compartilhados possuem banheiro privado, ar condicionado e muito mais! Suas férias têm que ser as melhores, esperamos você para desfrutar uma estadia incomparável. Você vai se surpreender!"
     * )
    *
     * @var string
     */
    public $description;

     /**
     * @OA\Property(
     *     property="phone",
     *     type="string",
     *     example="+55 (21) 3209-0348"
     * )
     *
     * @var string
     */
    public $phone;

     /**
     * @OA\Property(
     *     property="email",
     *     type="string",
     *     example="copacabana@chelagarto.com"
     * )
    *
     * @var string
     */
    public $email;

     /**
     * @OA\Property(
     *     property="whatsapp",
     *     type="string",
     *     example="+55 (21) 3209-0348"
     * )
    *
     * @var string
     */
    public $whatsapp;

     /**
     * @OA\Property(
     *     property="showBreakfast",
     *     type="boolean",
     *     example=true
     * )
      *
     * @var bool
     */
    public $showBreakfast;

     /**
     * @OA\Property(
     *     property="location",
     *     type="object",
     *     @OA\Property(
     *         property="address",
     *         type="string",
     *         example="Rua Barata Ribeiro 111"
     *     ),
     *     @OA\Property(
     *         property="latitude",
     *         type="string",
     *         example="-22.963890"
     *     ),
     *     @OA\Property(
     *         property="longitude",
     *         type="string",
     *         example="-43.178493"
     *     ),
     *     @OA\Property(
     *         property="zoom",
     *         type="string",
     *         example="13"
     *     )
     * )
    *
     * @var object
     */
    public $location;

     /**
     * @OA\Property(
     *     property="country",
     *     type="object",
     *     @OA\Property(
     *         property="codIso",
     *         type="string",
     *         example="BRA"
     *     ),
     *     @OA\Property(
     *         property="name",
     *         type="string",
     *         example="Brazil"
     *     )
     * )
    *
     * @var object
     */
    public $country;

     /**
     * @OA\Property(
     *     property="rating",
     *     type="object",
     *     @OA\Property(
     *         property="stars",
     *         type="integer",
     *         example=9
     *     ),
     *     @OA\Property(
     *         property="percent",
     *         type="integer",
     *         example=92
     *     )
     * )
    *
     * @var object
     */
    public $rating;
     /**
     * @OA\Property(
     *     property="mainImage",
     *     type="string"
     * )
    *
     * @var string
     */
    public $mainImage;
     /**
     *  @OA\Property(
     *      property="photos",
     *      type="array",
     *      @OA\Items(type="string"),
     *      description=""
     *  )
    *
     * @var array
     */
    public $photos;

     /**
     * @OA\Property(
     *     property="paymentMethods",
     *     type="array",
     *     description="All properties manage CC, but not all use PIX.",
     *     @OA\Items(
     *         type="string",
     *         enum={"CC", "PIX"},
     *         example="CC"
     *     )
     * ),
     * @var object
     */
    public $paymentMethods;

        /**
    *     @OA\Property(
     *         property="services",
     *         type="array",
     *          @OA\Items(ref="#/components/schemas/ServiceResource"),
     *         description=""
     *     ),
     * @var object
     */
    public $services;

    protected $language;

    protected $domain;

    public function __construct($resource, $language, $domain="chelagarto")
    {
        parent::__construct($resource);
        //los atributos que debo declarar publicos para las OA, hay que reasignarlos sino rompe
        $this->id = $resource->id;
        $this->country = $resource->country;
        $this->whatsapp = $resource->whatsapp;
        $this->language = $language;
        $this->domain = $domain;
        $this->paymentMethods = $this->getPaymentMethods();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //    return parent::toArray($request);
        $details = $this->detailsBy($this->language);
        $country = $this->country;

        $hotelServices = $this->services()->get();
        $services = [];
        foreach($hotelServices as $dummy => $sbh){
            $services[] = ServiceResource::make($sbh->service, $this->language);
        }

        $hotelPhotos = $this->photos()->orderBy("orden")->get();
        $photos = [];
        foreach($hotelPhotos as $dummy => $sbp){
            $photos[] = $sbp->url;
        }

        return [
                
                'id' =>$this->id,
                'name' => $details->nombre,
                'description' => $details->descripcion,
                'phone' =>$this->telefono,
                'email' =>$this->mail,
                'whatsapp' =>preg_replace('/[^0-9]/', '', $this->whatsapp),
                'showBreakfast' => !is_null($this->breakfast()),
                'location' =>[
                    "address" => $this->direccion,
                    "latitude" => $this->latitud,
                    "longitude" => $this->longitud,
                    "zoom" =>  $this->zoomgmap,
                ],
                'country' =>[
                    "isoCode" => $country->codIso3,
                    "name" => $country->nombre,
                ],
                "mainImage" => count($photos)?$photos[0]:"",
                "photos" => $photos,
                "paymentMethods" => $this->paymentMethods,

               "services" => $services
        ];
    }
    public function setPaymentMethods($pm){
        $this->paymentMethods = $pm;
    }
}
