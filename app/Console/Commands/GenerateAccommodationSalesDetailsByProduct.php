<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use App\Services\BookingService;
use App\Services\POSMovementService;
use ClhGroup\ClhBookings\Services\ClhTaxService;
use ClhGroup\ClhBookings\Services\ClhHotelService;
use App\Services\SalesByBookingByDateService;
use Carbon\Carbon;

class GenerateAccommodationSalesDetailsByProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-accommodation-sales-details-by-product {propertyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula detalle de ventas por producto y por fecha';

    protected $bookingService;
    protected $hotelService;
    protected $taxService;
    protected $salesByBookingByDateService;

    public function __construct(BookingService $bookingService, ClhHotelService $hotelService, ClhTaxService $taxService, SalesByBookingByDateService $salesByBookingByDateService)
    {
        parent::__construct();
        $this->bookingService = $bookingService;
        $this->hotelService = $hotelService;
        $this->taxService = $taxService;
        $this->salesByBookingByDateService = $salesByBookingByDateService;
    }


    public function handle()
    {
        $propertyId = $this->argument('propertyId');
        $yesterday=Carbon::yesterday("America/Argentina/Buenos_Aires")->format('Y-m-d');

        $hotel = $this->hotelService->getHotelById($propertyId);
        $bookings = $this->bookingService->getBookingsToCalculateSalesDetailsByProduct($propertyId, $yesterday);

        $currency = $hotel->localCurrency;
        $decimals = ClhUtils::getDecimalsByBookingOrigin($currency->codigoISO);
        $breakfastUSDValue = 0; //en este servicio no consideramos los valores del desayuno, solo totales por producto
        
        foreach ($bookings as $booking){

            if (in_array($booking->estado, [8,15])){
                $products = $booking->products();
                $taxesId = $booking->taxesByProduct()->groupBy('id_impuesto')->pluck('id_impuesto')->toArray();
                $taxes = $this->taxService->getTaxesListById($taxesId);
                $coupon = !is_null($booking->codCupon)? $booking->coupons()->first() : null;

                foreach ($products as $id => $productId){
                    $details = $booking->details()->where("idProducto", $productId)->whereDate("fecha",$yesterday)->get();
                    if (!is_null($details->first())){
                        $quantityByProd = $details->first()->cant;
                        
                        $totals = $this->bookingService->calculateTotalsByProductByCurrency($currency,0,$decimals,$details,$quantityByProd,$breakfastUSDValue,0,$taxes,$coupon);

                        $salesLocalCurrency = $totals["accommodationWithoutTax"]-$totals["accommodationDiscount"]+$totals["accommodationTaxes"];
                        $salesUSD = $salesLocalCurrency*$booking->indiceMoneda;

                        if (in_array($booking->id_medio_de_pago, [ClhConstants::PAYMENT_METHOD_TC_CLH, ClhConstants::PAYMENT_METHOD_PIX])){
                            $total_tc_ml = $totals["accommodationWithoutTax"]-$totals["accommodationDiscount"]+$totals["accommodationTaxes"];
                            $total_tc_usd = $salesLocalCurrency*$booking->indiceMoneda;
                            $total_notc_ml = 0;
                            $total_notc_usd = 0;
                        }else{
                            $total_notc_ml = $totals["accommodationWithoutTax"]-$totals["accommodationDiscount"]+$totals["accommodationTaxes"];
                            $total_notc_usd = $salesLocalCurrency*$booking->indiceMoneda;
                            $total_tc_ml = 0;
                            $total_tc_usd = 0;
                        }
                        $salesByBookingByDate = [
                            "fecha" => $yesterday , "id_hostel" => $booking->idHostel, "id_reserva" => $booking->id, "id_producto" => $productId,
                            "id_origen_reserva" => $booking->idOrigenReserva, 
                            "total_tc_ml" => $total_tc_ml, 
                            "total_tc_usd" => $total_tc_usd, 
                            "total_notc_ml" => $total_notc_ml, 
                            "total_notc_usd" => $total_notc_usd, 
                            "cant_roomnights" => $quantityByProd
                        ];
            
                        $this->salesByBookingByDateService->create($salesByBookingByDate);
                    }
                }

            }else{
                if ($booking->anticipo > 0){
                    $salesUSD = $booking->anticipo;
                    $salesLocalCurrency = $booking->anticipo/$booking->indiceMoneda;
                    $productId = $booking->products()[0];

                    if (in_array($booking->id_medio_de_pago, [ClhConstants::PAYMENT_METHOD_TC_CLH, ClhConstants::PAYMENT_METHOD_PIX])){
                        $total_tc_ml = $salesLocalCurrency;
                        $total_tc_usd = $salesUSD;
                        $total_notc_ml = 0;
                        $total_notc_usd = 0;
                    }else{
                        $total_notc_ml = $salesLocalCurrency;
                        $total_notc_usd = $salesUSD;
                        $total_tc_ml = 0;
                        $total_tc_usd = 0;
                    }
                    $salesByBookingByDate = [
                        "fecha" => $yesterday , "id_hostel" => $booking->idHostel, "id_reserva" => $booking->id, "id_producto" => $productId,
                        "id_origen_reserva" => $booking->idOrigenReserva, 
                        "total_tc_ml" => $total_tc_ml, 
                        "total_tc_usd" => $total_tc_usd, 
                        "total_notc_ml" => $total_notc_ml, 
                        "total_notc_usd" => $total_notc_usd, 
                        "cant_roomnights" => 1 //$quantityByProd
                    ];
        
                    $this->salesByBookingByDateService->create($salesByBookingByDate);
                }
            }
        }
        
    }
}
