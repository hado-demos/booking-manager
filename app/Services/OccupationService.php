<?php

namespace App\Services;

use App\Models\Occupation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Jobs\UpdateAvailabilityForBookingJob;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use ClhGroup\ClhBookings\Traits\ClhAvailability;
use App\Utils\Constants;

class OccupationService
{
    use ClhAvailability;
    
    public function existsPackage($propertyId,$checkin,$checkout)
    {
        // 1. Check if there is a tourist package in the range
        $isPackage = false;
        $packageFrom = $checkin;
        $packageTo = $checkout;
        if (DB::table('jos_milh_ocupacion as o')
                ->join('productos as p', 'p.id', '=', 'o.idProducto')
                ->where('p.id_hostel', $propertyId)
                ->where('p.anulado', 0)
                ->where('fecha', '=', $checkin)
                ->where('minimaestadia', '<>', '0')
                ->exists()) {

            $aux = DB::table('jos_milh_ocupacion as o')
                ->join('productos as p', 'p.id', '=', 'o.idProducto')
                ->where('p.id_hostel', $propertyId)
                ->where('p.anulado', 0)
                ->where('fecha', '<', $checkin)
                ->where('minimaestadia', '=', '0')
                ->orderBy('fecha', 'desc')
                ->limit(1)
                ->value(DB::raw("DATE(fecha)"));
                $aux = Carbon::parse($aux)->addDay()->format("Y-m-d");
           
                if ($aux != $checkin){
                    $isPackage = true;
                    $packageFrom = $aux;
                }
        }
    
        $aux = Carbon::parse($checkout)->subDay()->format("Y-m-d");
        if (DB::table('jos_milh_ocupacion as o')
                ->join('productos as p', 'p.id', '=', 'o.idProducto')
                ->where('p.id_hostel', $propertyId)
                ->where('p.anulado', 0)
                ->where('fecha', '=', $aux)
                ->where('minimaestadia', '<>', '0')
                ->exists()) {

            $aux = DB::table('jos_milh_ocupacion as o')
                ->join('productos as p', 'p.id', '=', 'o.idProducto')
                ->where('p.id_hostel', $propertyId)
                ->where('p.anulado', 0)
                ->where('fecha', '>', $aux)
                ->where('minimaestadia', '=', '0')
                ->orderBy('fecha', 'asc')
                ->limit(1)
                ->value(DB::raw("DATE(fecha)"));

            if ($aux != $checkout){
                $isPackage = true;
                $packageTo = $aux;
            }
        }
        
        $result = (object) [
            'isPackage' => $isPackage,
            'from' => $packageFrom,
            'to' => $packageTo
        ];

        return $result;
    }

     
    public function getAvailableProductsByParams($propertyId,$checkin,$checkout,$guests,$children){
        $query = DB::table('jos_milh_ocupacion as o')
            ->join('productos as p', 'p.id', '=', 'o.idProducto')
            ->select('idProducto', DB::raw('COUNT(*) as c'), DB::raw('MIN(dispoactual) as mindispo'), DB::raw("if(cantPersonas=$guests, 1, if(cantPersonas>$guests, 2, $guests/cantPersonas)) as personas"))
            ->where('p.id_hostel', $propertyId)
            ->where('p.anulado', 0)
            ->where('DiaCerrado', 0)
            ->where('fecha', '>=', $checkin)
            ->where('fecha', '<', $checkout)
            ->whereRaw('IF (enlazado=0, tarifaestandar, precioMoneda) > 0');

            if (intval($children) > 0){
                $query = $query->whereIn('tipocuarto', [Constants::PRODUCT_TYPE_PRIVATE, Constants::PRODUCT_TYPE_DEPARTMENT]);
            }

            $products = $query ->groupBy('idProducto')
            ->orderBy('personas')
            ->orderByRaw('IF (enlazado=0, tarifaestandar, precioMoneda)')
            ->havingRaw('MIN(dispoactual) > 0 and (c = DATEDIFF(?, ?))', [$checkout, $checkin])
            ->get();

        return $products->pluck("mindispo", "idProducto")->toArray();
    }

    public function getRatesByProductId($hotel,$productId,$checkin,$checkout){
        $usd = $hotel->pricesCurrency->valorDolar;

            return DB::table('jos_milh_ocupacion')
                ->select("idProducto","fecha")
                ->selectRaw("CAST( IF (enlazado=0, tarifaestandar, precioMoneda * (1+(IFNULL(pPrecio,0)/100)) ) * $usd  AS DECIMAL(11,4)) as unitPrice")
                ->where('idProducto', $productId)
                        ->where("fecha", ">=", $checkin)
                        ->where("fecha", "<",  $checkout)
                        ->orderBy('fecha')
                        ->get();
    }

    public function isAvailableProduct($productId,$checkin,$checkout,$quantity){

            return DB::table('jos_milh_ocupacion')
                ->select('idProducto', DB::raw('COUNT(*) as c'))
                ->where('idProducto', $productId)
                        ->where("fecha", ">=", $checkin)
                        ->where("fecha", "<",  $checkout)
                        ->where('DiaCerrado', 0)
                        ->whereRaw('dispoactual >= ?', [$quantity])
                        ->whereRaw('IF (enlazado=0, tarifaestandar, precioMoneda) > 0')
                        ->groupBy('idProducto')
                        ->havingRaw('c = DATEDIFF(?, ?)', [$checkout, $checkin])
                        ->exists();
    }    

    public function dispatchAvailabilityUpdateForBooking($bookingId, $operation) {
        UpdateAvailabilityForBookingJob::dispatch($bookingId, $operation);
    }
    
    //@TODO improve operator management
    public function getRoleOperator($operatorId){
        return DB::connection("mysql")->scalar('select role_id from role_user where user_id='.$operatorId);
    }


    
}