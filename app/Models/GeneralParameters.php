<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralParameters extends Model
{
    use HasFactory;

    protected $connection = 'mysql_cheltoco';

    protected $table = 'jos_milh_parametros_generales';

}
