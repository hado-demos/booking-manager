<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\BookingService;
use ClhGroup\ClhBookings\Models\ClhGeneralParameters;
use GuzzleHttp\Client;

class ProcessPosCancellation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;

    /**
     * Create a new job instance.
     */
    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    public function handle(BookingService $bookingService): void
    {
        $booking = $this->booking;
        $this->manageBookingCancellationByPMS($booking);
        $penalty = $bookingService->calculateCancellationPenalty($booking);
        if(!is_null($penalty->type)){
            $this->manageBookingCancellationByPM($booking,$penalty);
        }
        
        
    }

    private function manageBookingCancellationByPMS($booking){
        $bookingId = $booking->id;
        $API_PMS = ClhGeneralParameters::where('nombre', '=', 'API_PMS_DOMAIN')->first()->valor;
        $API_KEY= ClhGeneralParameters::where('nombre', '=', 'API_KEY2_INTERNAL')->first()->valor;
        try{
            $client= new Client(["verify" => false]);
            $resp= $client->get($API_PMS."checkin/undoAssignments/$bookingId",
                [
                'headers' => [
                    'Authorization' => "Bearer $API_KEY",
                ],
            ])->getBody();

            $resp=json_decode($resp, true);
            // TODO procesamiento de errores
        }catch(Exception $e) {
            throw $e;
        }
    }

    private function manageBookingCancellationByPM($booking,$penalty){
        $bookingId = $booking->id;
        $API_PM = ClhGeneralParameters::where('nombre', '=', 'API_PM2_DOMAIN')->first()->valor;
        $API_KEY= ClhGeneralParameters::where('nombre', '=', 'API_KEY2_INTERNAL')->first()->valor;
        try{
            $client= new Client(["verify" => false]);
            $resp= $client->post($API_PM."processWarranty/".$booking->id,
            [
                'headers' => [
                    'Authorization' => "Bearer $API_KEY",
                ],
                'form_params' => [
                    'penalty'=>$penalty->penalty,
                    'currencyIsoCode'=>$penalty->currencyIsoCode,
                    'type'=>$penalty->type
                ]
            ])->getBody();

            $resp=json_decode($resp, true);
            // TODO procesamiento de errores
        }catch(Exception $e) {
            throw $e;
        }
    }
}
