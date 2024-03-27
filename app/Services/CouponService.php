<?php

namespace App\Services;

use MichaelRubel\Couponables\Models\Coupon;

class CouponService
{

    public function getCouponByCode($couponCode){
        return Coupon::where("code", "=", $couponCode)->first();
    }
}