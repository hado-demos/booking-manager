<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class POSMovement extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'id_pdv', 'id_hostel', 'id_medio_de_pago', 'fecha_hora', 'descripcion', 'id_moneda', 'tipo_movimiento', 'total_ml', 'total_usd', 'indice_ml','id_cobro','id_reserva'
    ];

    protected $table = 'movimientos_x_pdv';

}