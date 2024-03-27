<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesByBookingByDate extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'fecha', 'id_hostel', 'id_reserva', 'id_producto', 'id_origen_reserva', 'total_tc_ml', 'total_tc_usd', 'total_notc_ml','total_notc_usd','cant_roomnights'
    ];

    protected $table = 'ventas_x_reserva_x_fecha';

    public $timestamps = false;
}