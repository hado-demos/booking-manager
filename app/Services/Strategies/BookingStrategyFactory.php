<?php

namespace App\Services\Strategies;


class BookingStrategyFactory {
    public static function create($type, $bookingService, $hotelService, $occupationService, $currencyService) {
        switch ($type) {
            case 'customer':
                return new CustomerBookingStrategy($bookingService, $hotelService, $occupationService, $currencyService);
            case 'operator':
                return new OperatorBookingStrategy($bookingService, $hotelService, $occupationService, $currencyService);
            default:
                throw new \Exception("Tipo de reserva no soportado");
        }
    }
}