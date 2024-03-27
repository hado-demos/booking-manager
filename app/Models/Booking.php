<?php

namespace App\Models;

use MichaelRubel\Couponables\Traits\HasCoupons;
use ClhGroup\ClhBookings\Models\ClhBooking;

class Booking extends ClhBooking
{
    use HasCoupons;

    
    public function products(){
        return $this->details()->groupBy('idProducto')->pluck('idProducto')->toArray();
    }
}
