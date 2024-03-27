<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Utils\ClhConstants;
use App\Services\BookingService;
use App\Services\POSMovementService;
use Carbon\Carbon;

class GenerateAccommodationSalesMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-accommodation-sales-movements {propertyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula venta de reservas confirmadas y canceladas con penalidad cobrada, diferenciando el alojamiento del desayuno en el caso de las confirmadas';

    protected $bookingService;
    protected $posMovementService;

    public function __construct(BookingService $bookingService, POSMovementService $posMovementService)
    {
        parent::__construct();
        $this->bookingService = $bookingService;
        $this->posMovementService = $posMovementService;
    }

    /**
     * Insertamos en movimientos_x_pdv:  si anticipo>0, lo tomamos de anticipo, sino lo tomamos del total de la reserva
    *   1-Reservas Canceladas y No Show (penalidades)
    *   2-Reservas Confirmadas con preautorización
    *   3-Reservas Confirmadas sin preautorización
     */
    public function handle()
    {
        $propertyId = $this->argument('propertyId');
        $yesterday=Carbon::yesterday("America/Argentina/Buenos_Aires")->format('Y-m-d');

        $bookings = $this->bookingService->getBookingsToCalculateSalesByCheckout($propertyId, $yesterday);

        foreach ($bookings as $booking){
            
            if (in_array($booking->estado, [8,15])){
                $movementType = "venta";
                $accommodationUSD = $booking->getAccommodationTotalInCurrency(ClhConstants::CURRENCY_USD_TYPE)-$booking->getAccommodationDiscountInCurrency(ClhConstants::CURRENCY_USD_TYPE)+$booking->getAccommodationTaxesInCurrency(ClhConstants::CURRENCY_USD_TYPE);
                $accommodationLocalCurrency = $booking->getAccommodationTotalInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE)-$booking->getAccommodationDiscountInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE)+$booking->getAccommodationTaxesInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE);

                $posMovement = [
                    "id_pdv" => 1 , "id_hostel" => $booking->idHostel, "id_medio_de_pago" => $booking->id_medio_de_pago, "fecha_hora" => "$yesterday",
                     "id_moneda" => $booking->idMonedaHostel, "tipo_movimiento" => "$movementType", "total_ml" => $accommodationLocalCurrency, "total_usd" => $accommodationUSD, 
                     "indice_ml" => $booking->indiceMoneda, "id_reserva" => $booking->id
                ];
    
                $this->posMovementService->create($posMovement);

                $breakfastUSD = $booking->getBreakfastInCurrency(ClhConstants::CURRENCY_USD_TYPE)-$booking->getBreakfastDiscountInCurrency(ClhConstants::CURRENCY_USD_TYPE)+$booking->getBreakfastTaxesInCurrency(ClhConstants::CURRENCY_USD_TYPE);
                if ($breakfastUSD > 0){
                    $breakfastLocalCurrency = $booking->getBreakfastInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE)-$booking->getBreakfastDiscountInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE)+$booking->getBreakfastTaxesInCurrency(ClhConstants::CURRENCY_LOCAL_TYPE);

                    $posMovement = [
                        "id_pdv" => 4 , "id_hostel" => $booking->idHostel, "id_medio_de_pago" => $booking->id_medio_de_pago, "fecha_hora" => "$yesterday",
                        "id_moneda" => $booking->idMonedaHostel, "tipo_movimiento" => "$movementType", "total_ml" => $breakfastLocalCurrency, "total_usd" => $breakfastUSD, 
                        "indice_ml" => $booking->indiceMoneda, "id_reserva" => $booking->id
                    ];
        
                    $this->posMovementService->create($posMovement);
                }

            }else{
                $movementType = "penalidad";

                if ($booking->anticipo > 0){
                    $salesUSD = $booking->anticipo;
                    $salesLocalCurrency = $booking->anticipo/$booking->indiceMoneda;

                    $posMovement = [
                        "id_pdv" => 1 , "id_hostel" => $booking->idHostel, "id_medio_de_pago" => $booking->id_medio_de_pago, "fecha_hora" => "$yesterday",
                         "id_moneda" => $booking->idMonedaHostel, "tipo_movimiento" => "$movementType", "total_ml" => $salesLocalCurrency, "total_usd" => $salesUSD, 
                         "indice_ml" => $booking->indiceMoneda, "id_reserva" => $booking->id
                    ];
        
                    $this->posMovementService->create($posMovement);
                }

            }

        }
        
    }
}
