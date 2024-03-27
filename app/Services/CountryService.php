<?php

namespace App\Services;

use ClhGroup\ClhBookings\Models\ClhCountry;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use App\Utils\Constants;

class CountryService
{

    
    public function getListByLanguage($language="pt"){
        $mapped = ClhUtils::mapToJoomlaLanguage($language);
        return ClhCountry::select("codIso2 as isoCode", "jos_milh_paises_idm.nombre as name")
                    ->join("jos_milh_paises_idm", "jos_milh_paises.id", "=", "jos_milh_paises_idm.idPais")
                    ->whereIn("jos_milh_paises_idm.idIdioma", $mapped)
                    ->orderBy("name")->get()->toArray();
        
    }

    public function getCurrencyByCountryCode($countryCode){
        $country = ClhCountry::where("codIso2", $countryCode)->first();
        if (in_array($country->codIso3Moneda, Constants::API_CURRENCIES)){
            return $country->codIso3Moneda;
        }else{
            return "USD";
        }   
    }

    public function getCountryByIsoCode2($countryCode){
        return ClhCountry::where("codIso2", $countryCode)->first();
    }
}