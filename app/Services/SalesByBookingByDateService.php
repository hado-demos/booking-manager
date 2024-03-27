<?php

namespace App\Services;

use App\Models\SalesByBookingByDate;

class SalesByBookingByDateService
{

    public function create($data): SalesByBookingByDate
    {
        return SalesByBookingByDate::create($data);
    }

}