<?php

namespace App\Services;

use ClhGroup\ClhBookings\Models\ClhGuest;
use ClhGroup\ClhBookings\Models\ClhCurrency;
use ClhGroup\ClhBookings\Models\ClhBookingExtra;
use ClhGroup\ClhBookings\Models\ClhBookingProductDiscount;
use ClhGroup\ClhBookings\Models\ClhBookingExtraDiscount;
use ClhGroup\ClhBookings\Models\ClhBookingFixedDiscount;
use ClhGroup\ClhBookings\Models\ClhBookingProductTax;
use ClhGroup\ClhBookings\Models\ClhBookingExtraTax;
use ClhGroup\ClhBookings\Models\ClhBookingFixedTax;
use ClhGroup\ClhBookings\Models\ClhBookingDetail;
use ClhGroup\ClhBookings\Models\ClhProduct;
use ClhGroup\ClhBookings\Models\ClhTax;
use ClhGroup\ClhBookings\Models\ClhBookingTracking;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use ClhGroup\ClhBookings\Services\ClhTaxService;
use ClhGroup\ClhBookings\Services\ClhCurrencyService;
use MichaelRubel\Couponables\Models\Contracts\CouponContract;
use App\Models\Booking;
use App\Models\BookingIntention;
use App\Models\BookingIntentionDetail;
use App\Services\ProductService;
use App\Services\OccupationService;
use App\Services\CountryService;
use App\Jobs\ProcessPosCancellation;
use App\Jobs\ProcessHotelBookingPayment;
use App\Jobs\UpdateBookingPayment;
use App\Utils\ErrorCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BookingService
{
    protected $taxService;
    protected $currencyService;
    protected $productService;
    protected $occupationService;
    protected $countryService;

    public function __construct(ClhTaxService $taxService, ClhCurrencyService $currencyService, ProductService $productService, OccupationService $occupationService, CountryService $countryService)
    {
        $this->taxService = $taxService;
        $this->currencyService = $currencyService;
        $this->productService = $productService;
        $this->occupationService = $occupationService;
        $this->countryService = $countryService;
    }

    public function getBookingById($id): ?Booking
    {
        try {
            return Booking::find($id);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createBooking($bookingData): Booking
    {
        try {

            DB::beginTransaction();
    
            $guest = ClhGuest::create($bookingData["guest"]);

            $bookingData["booking"]["idPasajero"] = $guest->id;
            $booking = Booking::create($bookingData["booking"]);

            foreach($bookingData["details"] as $details){
                $details["idReserva"] = $booking->id;
                ClhBookingDetail::create($details);
            }

            if (count($bookingData["extras"])){
                $bookingData["extras"]["id_reserva"] =  $booking->id;
                $bookingExtras = ClhBookingExtra::create($bookingData["extras"]);
            }

            if (count($bookingData["product_discounts"])){
                foreach($bookingData["product_discounts"] as $discounts){
                    $discounts["id_reserva"] = $booking->id;
                    ClhBookingProductDiscount::create($discounts);
                }
            }

            if (count($bookingData["extras_discounts"])){
                foreach($bookingData["extras_discounts"] as $discounts){
                    $discounts["id_reserva"] = $booking->id;
                    ClhBookingExtraDiscount::create($discounts);
                }
            }

            if (count($bookingData["fixed_discounts"])){
                foreach($bookingData["fixed_discounts"] as $discounts){
                    $discounts["id_reserva"] = $booking->id;
                    ClhBookingFixedDiscount::create($discounts);
                }
            }

            if (count($bookingData["product_taxes"])){
                foreach($bookingData["product_taxes"] as $taxes){
                    $taxes["id_reserva"] = $booking->id;
                    ClhBookingProductTax::create($taxes);
                }
            }

            if (count($bookingData["extras_taxes"])){
                foreach($bookingData["extras_taxes"] as $taxes){
                    $taxes["id_reserva"] = $booking->id;
                    ClhBookingExtraTax::create($taxes);
                }
            }

            if (count($bookingData["fixed_taxes"])){
                foreach($bookingData["fixed_taxes"] as $taxes){
                    $taxes["id_reserva"] = $booking->id;
                    ClhBookingFixedTax::create($taxes);
                }
            }
            ClhBookingTracking::insert($booking->id, "Se dió de alta la reserva");
            DB::commit();

            return $booking;

        } catch (Exception $e) {

            DB::rollback();
            throw $e;
        }
    }

    public function calculateTotalsByProductByCurrency($currency,$markup,$decimals,$rates,$quantityByProd,$breakfastUSDValue,$breakfastQty,$taxes,$coupon=null){
        $exchangeRate = $currency->valorDolar;
 
        $quantityByProd = isset($quantityByProd)? $quantityByProd : 1;
        $return["quantity"] = $quantityByProd;
        //Calcula total de alojamiento por producto
        foreach ($rates as $rate){
            $calculatedRate = round($rate->unitPrice/$exchangeRate*(1+$markup/100) , $decimals) * $quantityByProd;
            $return["accommodationWithoutTax"] = isset($return["accommodationWithoutTax"])? $return["accommodationWithoutTax"]+$calculatedRate : $calculatedRate;
        }

        //Desayuno
        if ($breakfastUSDValue>0){
            $return["breakfastWithoutTax"] = round($breakfastUSDValue/$exchangeRate*(1+$markup/100) , $decimals) * $breakfastQty;
        }else{
            $return["breakfastWithoutTax"] = 0;
        }

        //Cupon
        if (!is_null( $coupon)){
            //si es cupon de tipo porcentaje, el % de descuento se aplica a alojamiento y desayubi, pero si es valor fijo, se resta de alojamiento
            if ($coupon->type==CouponContract::TYPE_PERCENTAGE){
                $discount = $coupon->value; 
                $return["accommodationDiscount"] = round($return["accommodationWithoutTax"]*$discount/100, $decimals);
                $return["breakfastDiscount"] = round($return["breakfastWithoutTax"]*$discount/100, $decimals);
            }else{
                $return["accommodationDiscount"] =  $return["breakfastDiscount"] = 0;
            }
        }else{
            $return["accommodationDiscount"] = $return["breakfastDiscount"] =0;
        }
        
        //Impuestos
        $calculatedTaxes = $this->taxService->calculateTaxesByValue($taxes, $return["accommodationWithoutTax"] -$return["accommodationDiscount"], $decimals);
        $return["accommodationTaxes"] = $calculatedTaxes["taxesTotal"];
        $return["accommodationTaxesDetails"] = $calculatedTaxes["taxesList"];

        //$calculatedTaxes = $this->taxService->calculateTaxesByValue($taxes, $return["breakfastWithoutTax"] -$return["breakfastDiscount"], $decimals);
        $calculatedTaxes = $this->taxService->calculateTaxesByValue([], $return["breakfastWithoutTax"] -$return["breakfastDiscount"], $decimals); //Sacamos impuestos de desayuno
        $return["breakfastTaxes"] = $calculatedTaxes["taxesTotal"];
        $return["breakfastTaxesDetails"] = $calculatedTaxes["taxesList"];
        return $return;
    }

    public function calculateFixedDiscountsByCurrency($coupon, $currency, $decimals){
        $fixedDiscount = 0;
        if(!is_null($coupon) && $coupon->type==CouponContract::TYPE_SUBTRACTION && !is_null($coupon->data->get('currencyCode'))){
            if ($coupon->data->get('currencyCode')==$currency->codigoISO){
                $discount = $coupon->value; 
            }else{
                //si el descuento está en otra moneda, convertimos a dolar y luego a la moneda destino
                $currencyCoupon = $this->currencyService->getCurrencyByIsoCode($coupon->data->get('currencyCode'));
                $discount = ($coupon->value*$currencyCoupon->valorDolar)/$currency->valorDolar; 
            }
            $fixedDiscount = round($discount, $decimals);
        }
        return $fixedDiscount;
    }
 
    
    public function buildStay($stay, $hotel, $coupon, $guestCurrency, $countryCode, $adults, $children, $specialRequests){
        
        $checkin = $stay["checkin"];
        $checkout = $stay["checkout"];
        $checkinDate = Carbon::createFromFormat("Y-m-d", $checkin);
        $checkoutDate = Carbon::createFromFormat("Y-m-d", $checkout);
        $nights = $checkinDate->diffInDays($checkoutDate);
  
        $checkPackage = $this->occupationService->existsPackage($hotel->id,$checkin,$checkout);
        if ($checkPackage->isPackage){
            return (object)["ok"=>false, "message"=> ErrorCodes::PACKAGE_IN_SELECTED_DATES, "data" => ["from"=>$checkPackage->from , "to"=>$checkPackage->to ]];
        }

        $bookingDetails = $totalsByProd = [];
        $includeBreakfast = null;
        $breakfastQty =  $breakfastWithoutTax = $breakfastDiscountTotal = $accommodationDiscountTotal = 0;
        $breakfastUSDValue = 0;
		$guestMarkup = $guestCurrency->getMarkupByHotelAndGuestCountry($hotel->country->codIso2, $countryCode);
		$currency = $hotel->localCurrency;
        $markup = 0;
        $decimals = ClhUtils::getDecimalsByBookingOrigin($currency->codigoISO, ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE);
        $taxes = $hotel->taxesByCustomerCountryCode($countryCode, ClhConstants::TAX_TYPE_PERCENT_OF_TOTAL);

        $importeTotal = $cantPersonas = 0;
        foreach($stay["products"] as $prod){
            if (is_null($includeBreakfast)){
                $includeBreakfast = $prod["includeBreakfast"]; //asigna desayuno en funcion del primer producto
                $breakfastUSDValue = $includeBreakfast && !is_null($hotel->breakfast())? $hotel->breakfast()->currentPrice()->toUSD() : 0;
            }
            if ($this->occupationService->isAvailableProduct($prod["productId"],$checkin,$checkout,$prod["quantity"])){
                if (isset($totalsByProd[$prod["productId"]])){ //si viene duplicado el mismo productId, retornamos error
                    return (object)["ok"=>false, "message"=> ErrorCodes::DUPLICATED_PRODUCT, "data" => ["productId" => $prod["productId"]]];
                }
                $product = $this->productService->getProductById($prod["productId"]);
                $rates = $this->occupationService->getRatesByProductId($hotel,$prod["productId"],$checkin,$checkout);   
                $cantPersonas += $product->cantPersonas*$prod["quantity"];
                foreach ($rates as $rate){

                    $bookingDetails[] = ["idProducto" => $rate->idProducto, "fecha"=> $rate->fecha,  "cant" => $prod["quantity"], 
                                            "PU"=>$rate->unitPrice, "desayuno" => $includeBreakfast? 1 : 2,
                                            "adultos"=>$product->cantPersonas, "idReserva"=>""
                                        ];
                    $breakfastQty += $includeBreakfast?  $product->cantPersonas : 0;
                }
                $totals = $this->calculateTotalsByProductByCurrency($currency,$markup,$decimals,$rates,$prod["quantity"],$breakfastUSDValue,$nights*$product->cantPersonas*$prod["quantity"],$taxes,$coupon);
                $totalsByProd[$prod["productId"]] = $totals;

                $importeTotal += $totals["accommodationWithoutTax"]+$totals["accommodationTaxes"]+$totals["breakfastWithoutTax"]+$totals["breakfastTaxes"];
                $accommodationDiscountTotal += $totals["accommodationDiscount"];
                $breakfastDiscountTotal += $totals["breakfastDiscount"];
                $breakfastWithoutTax += $totals["breakfastWithoutTax"];

            }else{
                return (object)["ok"=>false, "message"=> ErrorCodes::UNAVAILABLE_DATES, "data" => ["productId" => $prod["productId"]]];
            }
        }

        $response =  (object)["ok" => true];
        $fixedTaxes = $this->taxService->calculateFixedTaxesByCurrency($hotel->taxesByCustomerCountryCode($countryCode, ClhConstants::TAX_TYPE_FIXED_PER_ROOMNIGHT_PER_PERSON),$currency,$decimals,$nights,$adults);
        $fixedDiscount = $this->calculateFixedDiscountsByCurrency($coupon,$currency,$decimals);

        $importeTotal += $fixedTaxes["fixedTaxes"]-$fixedDiscount;
        $importeTotal = ($importeTotal * $currency->valorDolar) / (1+ ($markup/100));
        $vDescuentoCupon = (( $accommodationDiscountTotal+$breakfastDiscountTotal+$fixedDiscount) * $currency->valorDolar) / (1+ ($markup/100));
        
        $now = Carbon::now('America/Argentina/Buenos_Aires');
        $booking =  ["idHostel" => $hotel->id,
                            "idPasajero" => "",
                            "fechaReserva" => $now->format('Y-m-d'),
                            "hora" => $now->format('H:i:s'),
                            "estado" => ClhConstants::BOOKING_STATUS_CONFIRMED_UNPAID,
                            "fechaLlegada" => $checkin,
                            "importeTotal" =>  $importeTotal, // dolares sin markup sin descuentos con impuesto
                            "vDescuentoCupon" =>  $vDescuentoCupon, 
                            "codCupon" => ($vDescuentoCupon > 0)?$coupon->code:null,
                            "cantNoches" => $nights,
                            "cantPersonas" => $cantPersonas,
                            "adultos" => $adults,
                            "menores" => $children,
                            "esPeriodoEspecial" => $this->isSpecialPeriod($hotel->id, $checkin, $checkout),
                            "observaciones" => $specialRequests,
                            "idMonedaPasajero" => $guestCurrency->id,
                            "indiceMonedaPasajero" => $guestCurrency->valorDolar,
                            "marckupConfigurado" => $guestMarkup,
                            "idMonedaHostel" => $currency->id,
                            "indiceMoneda" => $currency->valorDolar,
                            "idMonedaPrecios" => $hotel->pricesCurrency->id,
                            "indiceMonedaPrecios" => $hotel->pricesCurrency->valorDolar,
                    ];

        if ($breakfastQty > 0){
            $breakfast = $hotel->breakfast()->currentPrice()->toUSD();
            $breakfastWithoutTax = ($breakfastWithoutTax * $currency->valorDolar) / (1+ ($markup/100));
            $bookingExtras = ["id_reserva" => "", 
                            "id_extra" => $hotel->breakfast()->id, 
                            "cantidad" => $breakfastQty,
                            "precio_unitario_usd" =>$breakfastWithoutTax/$breakfastQty,
                            "total_usd" => $breakfastWithoutTax,
                            "fecha_alta" => $now->format("Y-m-d"),
                            "fecha_uso_desde" => $checkin,
                            "fecha_uso_hasta" => $checkout
                            ];
        }else{
            $bookingExtras = [];
        }
        
        $breakfastTaxesDetails = $bookingBreakfastTaxes = $bookingProductTaxes = $bookingFixedTaxes = [];
        $bookingProductDiscounts = $bookingBreakfastDiscounts = $bookingFixedDiscounts = [];
        if (is_array($totalsByProd) &&  count($totalsByProd)> 0){
            foreach ($totalsByProd as $productId => $totalByProd){
                if ($totalByProd["accommodationDiscount"] > 0){
                    $bookingProductDiscounts[] =  ["id_reserva" => "", "id_descuento" => $coupon->id, "id_producto" => $productId, "total_usd" => ($totalByProd["accommodationDiscount"] * $currency->valorDolar) / (1+ ($markup/100)) ];
                }
                
                foreach($totalByProd["accommodationTaxesDetails"] as $taxId => $taxTotal){
                    $bookingProductTaxes[] = ["id_impuesto" => $taxId, "id_reserva" => "", "id_producto" => $productId, "total_usd" => ($taxTotal * $currency->valorDolar) / (1+ ($markup/100))  ];
                }
                foreach($totalByProd["breakfastTaxesDetails"] as $taxId => $taxTotal){
                    $breakfastTaxesDetails[$taxId] = isset($breakfastTaxesDetails[$taxId])? $breakfastTaxesDetails[$taxId]+$taxTotal : $taxTotal;
                }
            }
        }
        if ($breakfastQty > 0){
            foreach($breakfastTaxesDetails as $taxId => $taxTotal){
                $bookingBreakfastTaxes[] = ["id_reserva" => "","id_extra" => $hotel->breakfast()->id, "id_impuesto" => $taxId, "total_usd" => ($taxTotal * $currency->valorDolar) / (1+ ($markup/100))  ];
            }
            if ($breakfastDiscountTotal > 0){
                $bookingBreakfastDiscounts[] = ["id_reserva" => "", "id_extra" => $hotel->breakfast()->id, "id_descuento" => $coupon->id, "total_usd" => ($breakfastDiscountTotal * $currency->valorDolar) / (1+ ($markup/100))];
            }
        }
        if ($fixedDiscount > 0){
            $bookingFixedDiscounts[] = ["id_reserva" => "", "id_descuento" => $coupon->id, "total_usd" => ($fixedDiscount * $currency->valorDolar) / (1+ ($markup/100))];
        }

        if (is_array($fixedTaxes["fixedTaxesDetails"]) &&  count($fixedTaxes["fixedTaxesDetails"])> 0){
            foreach($fixedTaxes["fixedTaxesDetails"] as $taxId => $taxTotal){
                $bookingFixedTaxes[] = ["id_impuesto" => $taxId, "id_reserva" => "", "total_usd" => ($taxTotal * $currency->valorDolar) / (1+ ($markup/100))  ];
            }
        }
        
        $response->data = ["booking" => $booking, "details" => $bookingDetails, "extras" => $bookingExtras, 
                    "product_discounts" => $bookingProductDiscounts, "extras_discounts" => $bookingBreakfastDiscounts, "fixed_discounts" => $bookingFixedDiscounts,
                    "product_taxes" => $bookingProductTaxes, "extras_taxes" => $bookingBreakfastTaxes, "fixed_taxes" => $bookingFixedTaxes];
        return $response;
    }

    public function buildGuest($guestInfo,$language){

        $country = $this->countryService->getCountryByIsoCode2($guestInfo["countryResidence"]);
          return ["apellido" => $guestInfo["surname"],
                "nombre" => $guestInfo["name"],
                "mail" => $guestInfo["email"],
                "fechaNac" => $guestInfo["bornDate"],
                "telefono" => $guestInfo["cellphone"],
                "pais" => $country->codIso3,
                "id_ciudad" => $guestInfo["cityResidenceId"],
                "dni" => $guestInfo["documentNumber"],
                "idIdioma" => ClhUtils::mapToJoomlaLanguage($language)[0],
                "id_idioma" => ClhUtils::mapToLanguage($language)
        ];
    }


    public function calculatePrebookingTotals($totals,$fixedTaxes,$fixedDiscount,$coupon,$includeBreakfast,$language,$currency){
        $GTpriceBeforeTax = $GTpriceAfterTax = $GTtaxes = 0;  
        $BKpriceBeforeTax = $BKpriceAfterTax = $BKtaxes = 0;
        $grandTotalBeforeDiscountBeforeTax = 0;  
        $discount = 0;

        $products = $taxesList = [];
        foreach($totals as $productId => $total){
            $priceBeforeTax = $total["accommodationWithoutTax"]-$total["accommodationDiscount"];
            $grandTotalBeforeDiscountBeforeTax += $total["accommodationWithoutTax"];
            $taxes = $total["accommodationTaxes"];
            $discount += $total["accommodationDiscount"];
            $productDetails = $this->productService->getProductById($productId)->detailsBy($language);
            $productName = !is_null($productDetails) ? $productDetails->nombre:"";
            if ($total["quantity"] > 1){
                $productName .= " (x{$total["quantity"]})";
            }

            $products[] = ["productName" => $productName, 
                        "priceBeforeTax"=> $priceBeforeTax, 
                        "priceAfterTax"=> $priceBeforeTax+$taxes, 
                        "taxes"=> $taxes];

            if ($includeBreakfast){
                $priceBeforeTax += $total["breakfastWithoutTax"]-$total["breakfastDiscount"];
                $grandTotalBeforeDiscountBeforeTax += $total["breakfastWithoutTax"];
                $taxes += $total["breakfastTaxes"];
                $discount += $total["breakfastDiscount"];
            }
            $GTpriceBeforeTax += $priceBeforeTax;
            $GTpriceAfterTax += $priceBeforeTax+$taxes;
            $GTtaxes += $taxes;

            if ($includeBreakfast){
                $BKpriceBeforeTax += $total["breakfastWithoutTax"]-$total["breakfastDiscount"];
                $BKpriceAfterTax += $total["breakfastWithoutTax"]-$total["breakfastDiscount"]+$total["breakfastTaxes"];
                $BKtaxes += $total["breakfastTaxes"];
            }

            $taxesList = $this->joinTaxes($total["accommodationTaxesDetails"],$taxesList);
            if ($includeBreakfast){
                $taxesList = $this->joinTaxes($total["breakfastTaxesDetails"],$taxesList);
            }
        }
        $taxesList = $this->joinTaxes($fixedTaxes["fixedTaxesDetails"],$taxesList);
        
        $GTpriceAfterTax += $fixedTaxes["fixedTaxes"];

        if ($fixedDiscount > 0){
            $GTpriceAfterTax -= $fixedDiscount;
            $discount += $fixedDiscount;
        }

        if ($GTpriceAfterTax < 0){
            $GTpriceAfterTax = 0;
        }

        if (!is_null($coupon)){
            
            $promotion = [
                "id" => $coupon->id, "description" => $coupon->code, "hasDiscount" => true, 
                "discountType" => $coupon->type==CouponContract::TYPE_PERCENTAGE? "percentage":"fixed", 
                "discountPercent" => $coupon->type==CouponContract::TYPE_PERCENTAGE? $coupon->value : 0,
                "discountValue" => $discount,
                "grandTotalBeforeDiscountBeforeTax" => $grandTotalBeforeDiscountBeforeTax
               
            ];
        }else{
            $promotion = [
                "id" => "", "description" => "", "hasDiscount" => false, "discountType" => "", "discountPercent" => 0, "discountValue" => 0, 
                "grandTotalBeforeDiscountBeforeTax" => 0
            ];
        }

        $taxesData = [];
        foreach($taxesList as $taxId => $taxValue){
            $taxesData[] = ["taxName"=> $this->taxService->getTaxById($taxId)->nombre_corto, "taxValue"=>$taxValue];
        }

        return [
            "grandTotal" => 
            [
                "priceBeforeTax" => $GTpriceBeforeTax,
                "priceAfterTax" => $GTpriceAfterTax,
                "taxes" => ["total"=> $GTtaxes, "list" => $taxesData],
            ],
            "products" => $products,
            "breakfast" => 
            [
                "priceBeforeTax" => $BKpriceBeforeTax,
                "priceAfterTax" => $BKpriceAfterTax,
                "taxes" => $BKtaxes,
            ],
            "promotion" => $promotion,
            "currency" =>  ["isoCode"=> $currency->codigoISO, "symbol"=> $currency->signoMoneda]
            ];
    }

    public function calculateBookingTotals(Booking $booking, $language="pt"){
        $cur="guest";
        $currency = $booking->guestCurrency;
        $GTpriceBeforeTax = $booking->getAccommodationTotalInCurrency($cur)+$booking->getExtraServicesTotalInCurrency($cur)-$booking->getDiscountsInCurrency($cur);
        $GTTaxes = $booking->getTaxesInCurrency($cur);
        $GTpriceAfterTax = $GTpriceBeforeTax+$GTTaxes;

        $taxesList = $booking->getTaxesListInCurrency($cur);
        $taxesData = [];
        foreach($taxesList as $taxId => $taxValue){
            $taxesData[] = ["taxName"=> $this->taxService->getTaxById($taxId)->nombre_corto, "taxValue"=>$taxValue];
        }

        $accommodationByProduct = $booking->getAcommodationByProductInCurrency($cur);
        $quantityByProduct = $booking->getQuantityByProductInCurrency($cur);
        $discountByProduct = $booking->getDiscountsByProductInCurrency($cur);
        $taxesByProduct = $booking->getTaxesByProductInCurrency($cur);
        $products = [];
        foreach($accommodationByProduct as $productId => $acommodation){
            $discount = isset($discountByProduct[$productId])? $discountByProduct[$productId] : 0;
            $productName = $this->productService->getProductById($productId)->detailsBy($language)->nombre;
            $quantity = isset($quantityByProduct[$productId]) ? $quantityByProduct[$productId] : 1;
            if ($quantity > 1){
                $productName .= " (x{$quantity})";
            }

            $taxesByProd = isset($taxesByProduct[$productId])? $taxesByProduct[$productId] : 0;
            $products[] = ["productName" => $productName, 
                            "priceBeforeTax" => $acommodation-$discount,
                            "priceAfterTax" => $acommodation-$discount+$taxesByProd,
                            "taxes" => $taxesByProd,
            ];
        }

        $BKpriceBeforeTax = $booking->getBreakfastInCurrency($cur)-$booking->getBreakfastDiscountInCurrency($cur);
        $BKTaxes = $booking->getBreakfastTaxesInCurrency($cur);

        if (!is_null($booking->codCupon) && !is_null($booking->coupons()->first())){
            $coupon = $booking->coupons()->first();
            $promotion = [
                "id" => $coupon->id, "description" => $booking->codCupon, "hasDiscount" => true, 
                "discountType" => $coupon->type==CouponContract::TYPE_PERCENTAGE? "percentage":"fixed", 
                "discountPercent" => $coupon->type==CouponContract::TYPE_PERCENTAGE? $coupon->value: 0 ,
                "discountValue" => $booking->getDiscountsInCurrency($cur),
                "grandTotalBeforeDiscountBeforeTax" => $booking->getAccommodationTotalInCurrency($cur)+$booking->getExtraServicesTotalInCurrency($cur),
            ];
            
        }else{
            $promotion = [
                "id" => "", "description" => "", "hasDiscount" => false, "discountType" => "", "discountPercent" => 0, "discountValue" => 0,
                "grandTotalBeforeDiscountBeforeTax" => $booking->getAccommodationTotalInCurrency($cur)+$booking->getExtraServicesTotalInCurrency($cur),
                
            ];
        }
        
        return [
            //descuento incluido
            "grandTotal" => 
            [
                "priceBeforeTax" => $GTpriceBeforeTax,
                "priceAfterTax" => $GTpriceAfterTax,
                "taxes" => ["total"=> $GTTaxes, "list" => $taxesData],
            ],
            //productos con descuento incluido
            "products" => $products,
            "breakfast" => 
            [
                "priceBeforeTax" => $BKpriceBeforeTax,
                "priceAfterTax" => $BKpriceBeforeTax+$BKTaxes,
                "taxes" => $BKTaxes,
            ],
            "promotion" => $promotion,
            //desayuno con descuento incluido
            "currency" =>  ["isoCode"=> $currency->codigoISO, "symbol"=> $currency->signoMoneda]
            ];
    }

    private function joinTaxes($taxesList , $return){
        foreach($taxesList as $taxId => $taxValue){
            if (isset($return[$taxId])){
                $return[$taxId] += $taxValue;
            }else{
                $return[$taxId] = $taxValue;
            }
        }
        return $return;
    }

    public function createBookingIntention($bookingData): BookingIntention
    {
        try {
            DB::beginTransaction();
    
            $intention = BookingIntention::create($bookingData["intention"]);

            foreach($bookingData["details"] as $details){
                $details["id_intencion"] = $intention->id;
                BookingIntentionDetail::create($details);
            }
            DB::commit();

            return $intention;

        } catch (\Exception $e) {

            DB::rollback();
            throw $e;
        }
    }
    
    public function deleteBookingIntention($intentionId)
    {
        try {

            return BookingIntention::destroy([$intentionId]);

        } catch (\Exception $e) {
            throw $e;
            return false;
        }
    }

    public function dispatchProcessPayment($bookingId,$paymentMethod,$encryptedData,$bin)
    {
        ProcessHotelBookingPayment::dispatch($bookingId,$paymentMethod,$encryptedData,$bin);
    }

    public function isSpecialPeriod($propertyId,$checkin,$checkout)
    {
        return DB::table('jos_milh_hostels_feriados as f')
                ->where('f.idhostel', $propertyId)
                ->whereRaw("'{$checkin}' BETWEEN f.fechaInicio AND DATE_ADD(f.fechaInicio,INTERVAL f.cantDias DAY)".
                " OR DATE_SUB('{$checkout}', INTERVAL 1 DAY) BETWEEN f.fechaInicio AND DATE_ADD(f.fechaInicio,INTERVAL f.cantDias DAY) ".
                " OR f.fechaInicio BETWEEN '{$checkin}' AND DATE_SUB('{$checkout}', INTERVAL 1 DAY) ".
                " OR DATE_ADD(f.fechaInicio,INTERVAL f.cantDias DAY)  BETWEEN '{$checkin}' AND DATE_SUB('{$checkout}', INTERVAL 1 DAY)")
                ->exists();
    }

    public function calculateBookingDetailsTotals(Booking $booking,$lang = 'pt',$cur = 'local'){
    
        $config = $booking->getConfigByCurrencyType($cur);
        $bookingDetails = [];
        foreach($booking->details as $detail){
            $product = $this->productService->getProductById($detail->idProducto);
            $bookingDetails[] = ["id" => $detail->id,
                                 "date" =>$detail->fecha,
                                 "productCode" => $product->codigo,
                                 "productDescription" => $product->detailsBy($lang)->nombre,
                                 "quantity" => $detail->cant,
                                 "unitPrice" => $detail->getUnitPriceInCurrency($config),
                                 "total" => $detail->getTotalInCurrency($config),
                                 "adults" => $detail->adultos,
                                 "breakfast" => $detail->desayuno == 1 ? "yes" : "no"
                                ];
        }
        return $bookingDetails;
    }
    public function clearBookingAddOns($id)
    {
        try{
            \DB::beginTransaction();
            
            ClhBookingProductTax::where("id_reserva", $id)->delete();
            ClhBookingExtraTax::where("id_reserva", $id)->delete();
            ClhBookingFixedTax::where("id_reserva", $id)->delete();
            ClhBookingExtra::where("id_reserva", $id)->delete();
            ClhBookingProductDiscount::where("id_reserva", $id)->delete();
            ClhBookingExtraDiscount::where("id_reserva", $id)->delete();
            ClhBookingFixedDiscount::where("id_reserva", $id)->delete();
            
            \DB::commit();


        } catch (\Exception $e) {

            \DB::rollback();
            throw $e;
        }
        return true;
    }

    public function getBookingsToCalculateSalesByCheckout($propertyId,$checkout){
        $db = \Config::get('database.connections')["mysql"]["database"];
        return   Booking::select("reservas.*")->leftJoin("$db.movimientos_x_pdv", function (JoinClause $join)  use ($db){
                        $join->on("$db.movimientos_x_pdv.id_reserva", '=', 'reservas.id')
                            ->where("$db.movimientos_x_pdv.tipo_movimiento", '=', "venta");
                    })
                    ->where("idHostel", $propertyId)
                    ->whereRaw('(idOrigenReserva in ('.ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE.','.ClhConstants::BOOKING_ORIGIN_CLHSUITES_MOBILE.') OR id_operador is not null)')
                    ->where(function (Builder $query) {
                        $query->whereIn("estado", [8, 15])
                        ->orWhere(function (Builder $query2) {
                            $query2->whereIn('reservas.estado', [9,11,17])
                                ->where('reservas.anticipo', '>', 0);
                        });
                    })
                    ->whereRaw("ifnull((select max(date(fecha_hora_out)) from $db.app_estadias_clientes where id_reserva=reservas.id),DATE_ADD(fechaLlegada, INTERVAL cantNoches DAY)) = '{$checkout}'")
                    ->whereNull("$db.movimientos_x_pdv.id")
                    ->get();
    }
    
    public function getBookingsToCalculateSalesDetailsByProduct($propertyId,$date){
        //@TODO INCLUIR LOGICA DE PENALIDAD PERO NECESITAMOS TENER TIPO DE PENALIDAD COMPLETA O 1 NOCHE
        $db = \Config::get('database.connections')["mysql"]["database"];
        return   Booking::where("idHostel", $propertyId)
                    ->whereRaw('(idOrigenReserva in ('.ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE.','.ClhConstants::BOOKING_ORIGIN_CLHSUITES_MOBILE.') OR id_operador is not null)')
                    ->where(function (Builder $query) {
                        $query->whereIn("estado", [8, 15])
                        ->orWhere(function (Builder $query2) {
                            $query2->whereIn('estado', [9,11,17])
                                ->where('anticipo', '>', 0);
                        });
                    })
                    ->whereRaw("'{$date}' >= fechallegada and '{$date}' < ifnull((select max(date(fecha_hora_out)) from $db.app_estadias_clientes where id_reserva=reservas.id),DATE_ADD(fechaLlegada, INTERVAL cantNoches DAY))")
                    ->get();
    }

    public function dispatchUpdatePayment($booking,$paymentMethod,$encryptedData,$bin,$language)
    {
        UpdateBookingPayment::dispatch($booking,$paymentMethod,$encryptedData,$bin,$language);
    }

    public function cancelBooking($booking,$cancelledByNoWarranty = null)
    {
        try{
            if (!$booking->isCancelable()){
                return  (object)[ "ok" => false, "message" => ErrorCodes::BOOKING_NOT_CANCELABLE, "data" => []];
            }

            if (!is_null($cancelledByNoWarranty)){
                $booking->estado = ClhConstants::BOOKING_STATUS_CANCELLED_DUE_TO_NONPAYMENT;
                $booking->save();
                ClhBookingTracking::insert($booking->id, "Se canceló la reserva por falta de garantía");
                
            }else{
                $booking->estado = ClhConstants::BOOKING_STATUS_CANCELLED;
                $booking->save();
                ClhBookingTracking::insert($booking->id, "Se canceló la reserva");
            }
            
            ProcessPosCancellation::dispatch($booking);
            return (object)[ "ok" => true];
        }catch(Exception $e){
           // \ClhGroup\ClhBookings\Utils\ClhUtils::sendNotification('Error al cancelar reserva con id:'.$bookingId. ' '.var_export($e,true), "Error al cancelar reserva en CancelBookingsWithExpiredPIX con id $bookingId");
           throw $e;
        }
    }

    public function calculateCancellationPenalty($booking){
        if (in_array($booking->id_medio_de_pago, [ClhConstants::PAYMENT_METHOD_TC_CLH, ClhConstants::PAYMENT_METHOD_PIX])){
            $timezone= !is_null($booking->hotel->timezone)? $booking->hotel->timezone : 'America/Argentina/Buenos_Aires';
            $now = Carbon::now($timezone);
            if($booking->esPeriodoEspecial){
                $in = $booking->getDatetimeCheckin()->subDays(14);
                $am = Carbon::create($in->format('Y'), $in->format('m'), $in->format('d'), 23, 59, 0, $timezone);
            }else{
                $in = $booking->getDatetimeCheckin()->subDays(1);
                $am = Carbon::create($in->format('Y'), $in->format('m'), $in->format('d'), 9, 0, 0, $timezone);
            }

            if($now->greaterThan($am)){
                if($booking->esPeriodoEspecial || $booking->cantNoches==1){
                    //penalidad completa = 2
                    return (object)["penalty" =>$booking->getFinalPriceInCurrency(ClhConstants::CURRENCY_GUEST_TYPE), "currencyIsoCode"=>$booking->guestCurrency->codigoISO, "type"=> 2];
                }else{
                    //penalidad 1 noche = 1
                    $penalty = $this->getBookingFirstNightTotalInGuestCurrency($booking);
                    return (object)["penalty"=>$penalty->total, "currencyIsoCode"=>$penalty->currencyIsoCode, "type"=>1];
                }
            }else{
                return (object)["penalty" =>0, 'currencyIsoCode'=>null, "type"=> 0]; // no corresponde aplicar penalidad se envia a procesar la garantia
            }
        }
        return (object)["penalty" =>0, "type"=> null]; // el medio de pago no admite penalidad (no se envia a procesar la garantia) 
    }

    private function getBookingFirstNightTotalInGuestCurrency($booking){
        $currency = $booking->guestCurrency;
        $markup = $booking->marckupConfigurado;
        $decimals = ClhUtils::getDecimalsByBookingOrigin($currency->codigoISO, ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE);

        $breakfast = $booking->extras()->join('extras', 'extras_x_reserva.id_extra','=', 'extras.id')->where('extras.tipo', "desayuno")->first();
        $breakfastUSDValue = !is_null($breakfast)? $breakfast->precio_unitario_usd : 0; 
       
        $products = $booking->products();
        $taxesId = $booking->taxesByProduct()->groupBy('id_impuesto')->pluck('id_impuesto')->toArray();
        $taxes = $this->taxService->getTaxesListById($taxesId);
        $coupon = !is_null($booking->codCupon)? $booking->coupons()->first() : null;
        
        foreach ($products as $id => $productId){
            $details = $booking->details()->where("idProducto", $productId)->whereDate("fecha",$booking->fechaLlegada)->get();
            $booking->__set("details", $details);
            $d = $details->first();
            if (!is_null($d)){
                $quantityByProd = $d->cant;
                $adults = $d->adultos;
                
                $totals[$d->idProducto] = $this->calculateTotalsByProductByCurrency($currency,$markup,$decimals,$details,$quantityByProd,$breakfastUSDValue,$adults*$quantityByProd,$taxes,$coupon);
            }
        }
            
        $fixedTaxes = $this->taxService->calculateFixedTaxesByCurrency($booking->fixedTaxes(),$currency,$decimals,1,$booking->adultos);
        $fixedDiscount = $this->calculateFixedDiscountsByCurrency($coupon,$currency,$decimals);

        $totals = $this->calculatePrebookingTotals($totals,$fixedTaxes,$fixedDiscount,$coupon,true,null,$currency);
        
        return (object)["total" => $totals["grandTotal"]["priceAfterTax"], "currencyIsoCode"=> $currency->codigoISO];
    }
}