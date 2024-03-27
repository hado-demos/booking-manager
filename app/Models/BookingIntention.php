<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingIntention extends Model
{
    use HasFactory;

    protected $connection = 'mysql_cheltoco';

    protected $table = 'intenciones_de_reserva';

    protected $fillable = ['id_hotel', 'nombre', 'apellido', 'email' , 'pais' , 'moneda', 'adultos', 'menores', 'checkin', 'checkout', 'idioma'];

    public function details(): HasMany
    {
        return $this->hasMany(BookingIntentionDetail::class, 'id_intencion', 'id');
    }
    
    
}
