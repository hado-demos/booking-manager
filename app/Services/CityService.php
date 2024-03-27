<?php

namespace App\Services;

use ClhGroup\ClhBookings\Models\ClhCity;

class CityService
{

    public function getListByCountryCode($countryCode, $q){
        return ClhCity::select("id", "nombre")->where('iso_pais', $countryCode)
                    ->whereRaw('nombre LIKE "'.$q.'%"')
                    ->orderBy("nombre")->get()->toArray();
        
    }

}