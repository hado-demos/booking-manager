<?php

namespace App\Services\Strategies;

use Illuminate\Http\Request;
use App\Services\BookingService;
use App\Services\OccupationService;
use ClhGroup\ClhBookings\Services\ClhCurrencyService;
use ClhGroup\ClhBookings\Services\ClhHotelService;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use App\Rules\MaxLengthStay;
use App\Rules\ValidCPF;
use App\Utils\ErrorCodes;
use Illuminate\Support\Facades\Validator;

class CustomerBookingStrategy implements BookingStrategy {
    protected $bookingService;
    protected $hotelService;
    protected $occupationService;
    protected $currencyService;

    public function __construct(BookingService $bookingService, ClhHotelService $hotelService, OccupationService $occupationService, ClhCurrencyService $currencyService) {
        $this->bookingService = $bookingService;
        $this->hotelService = $hotelService;
        $this->occupationService = $occupationService;
        $this->currencyService = $currencyService;
    }

    public function validate(Request $request) {
        
        $rules = [
            'propertyId' => 'required|integer',
            'guestInfo' => 'required',
            'guestInfo.name' => 'required',
            'guestInfo.surname' => 'required',
            'guestInfo.email' => 'required|email',
            'guestInfo.cellphone' => 'required',
            'guestInfo.bornDate' => 'required',
            'guestInfo.countryResidence' => 'required|size:2',
            'guestInfo.cityResidenceId' => 'required',
            'guestInfo.documentNumber' => [
                'required',
                new ValidCPF($request->input('paymentMethod')) 
            ],
            'adults' => 'required',
            'children' => 'required',
            'stay' => 'required',
            'stay.checkin' => 'required|date_format:Y-m-d|after:yesterday',
            'stay.checkout' => [
                'required',
                'date_format:Y-m-d',
                'after:stay.checkin',
                new MaxLengthStay($request->input('stay.checkin'), 30) 
            ],
            'countryCode' => 'required|size:2',
            'currencyCode' => 'size:3',
            'intentionId' => 'required',
            'paymentMethod'=>'required',
            'cardInfo' => 'required_if:paymentMethod,CC'
        ];
    
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return  (object)[ "ok" => false, "message" => ErrorCodes::FIELD_ERROR, "errors" => $validator->errors()];
        }

        $propertyId = $request->input("propertyId");
        $countryCode = $request->input("countryCode");
        $currencyCode = $request->input("currencyCode");
        $cardInfo = $request->input('cardInfo');
        $adults = $request->input('adults');
        $children = $request->input('children');
        $guestInfo = $request->input('guestInfo');
        $stay = $request->input('stay');
        $couponCode = $request->input("couponCode");
        $intentionId = $request->input("intentionId");
        $paymentMethod = $request->input('paymentMethod');
        $language = $request->input('language', "pt");
        $specialRequests = $request->input('specialRequests');
        $domain = $request->input('domain', 'chelagarto');

        $data = (object) ["propertyId" => $propertyId, "language" => $language, "couponCode" => $couponCode, "currencyCode" => $currencyCode, "countryCode" => $countryCode,
            "cardInfo" => $cardInfo, "adults" => $adults, "children" => $children, "guestInfo" => $guestInfo, "stay" => $stay, "intentionId" => $intentionId, "paymentMethod" => $paymentMethod,
            "specialRequests" => $specialRequests, "domain" => $domain
        ];

        return (object) [ "ok" => true, "data" => $data];

    }

    public function processBooking($params,$coupon) {
      
        $hotel = $this->hotelService->getHotelById($params->propertyId);
        $currency = $this->currencyService->getCurrencyByIsoCode($params->currencyCode); 
        $stay = $this->bookingService->buildStay($params->stay, $hotel, $coupon, $currency, $params->countryCode, $params->adults, $params->children, $params->specialRequests);
       
        if (!$stay->ok){
            return $stay;
        }
        
        $bookingData = array_merge([
                            "guest" => $this->bookingService->buildGuest($params->guestInfo,$params->language)
                            ],  $stay->data
                        );

        if ($params->domain=="chelagarto"){
            $bookingData["booking"]["idOrigenReserva"] = ClhConstants::BOOKING_ORIGIN_CHELAGARTO_MOBILE;
        }else{
            $bookingData["booking"]["idOrigenReserva"] = ClhConstants::BOOKING_ORIGIN_CLHSUITES_MOBILE;
        }
        $booking = $this->bookingService->createBooking($bookingData);
        if (strlen($params->couponCode)){
            $booking->redeemCoupon($params->couponCode);
        }
        if (strlen($params->intentionId)){
            $this->bookingService->deleteBookingIntention($params->intentionId);
        }
        
        $this->occupationService->dispatchAvailabilityUpdateForBooking($booking->id, 'book');

        $this->bookingService->dispatchProcessPayment($booking->id,$params->paymentMethod,$params->cardInfo['encryptedData'] ?? null,$params->cardInfo['bin'] ?? null);
        
        return (object) [ "ok" => true, "booking" => $booking];
            
    }
}
