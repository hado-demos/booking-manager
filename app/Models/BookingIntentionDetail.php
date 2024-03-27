<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingIntentionDetail extends Model
{
    use HasFactory;

    protected $connection = 'mysql_cheltoco';

    protected $table = 'intenciones_detalle';

    public $timestamps = false;

    protected $fillable = ['id_intencion', 'id_producto', 'cantidad'];


}
