<?php

namespace App\Services\Strategies;

use Illuminate\Http\Request;

interface BookingStrategy {
    
    public function validate(Request $request);
    public function processBooking($validatedData,$coupon);
}