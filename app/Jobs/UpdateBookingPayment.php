<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\GeneralParameters;
use GuzzleHttp\Client;
use ClhGroup\ClhBookings\Utils\ClhUtils;

class UpdateBookingPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;
    protected $paymentMethod;
    protected $encryptedData;
    protected $bin;
    protected $language;

    /**
     * Create a new job instance.
     */
    public function __construct($booking,$paymentMethod,$encryptedData,$bin,$language)
    {
        $this->booking = $booking;
        $this->paymentMethod = $paymentMethod;
        $this->encryptedData = $encryptedData;
        $this->bin = $bin;
        $this->language = $language;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $API_PM = GeneralParameters::where('nombre', '=', 'API_PM2_DOMAIN')->first()->valor;
        $API_KEY= GeneralParameters::where('nombre', '=', 'API_KEY2_INTERNAL')->first()->valor;
        try{
            // Actualizacion del lenguage del pasajero que viene por parametro
            $guest = $this->booking->guest;
            $guest->id_idioma = ClhUtils::mapToLanguage($this->language);
            $guest->save();
            $isCC = strtoupper($this->paymentMethod) == "CC";
            $endpoint = 'changePaymentMethod/';
            $client= new Client(["verify" => false]);
            $jsonData = $isCC 
                ? [
                    'paymentMethod' => strtoupper($this->paymentMethod),
                    'encryptedData' => $this->encryptedData,
                    'createToken' => 1,
                    'bin' => $this->bin
                ] 
                : ['paymentMethod' => strtoupper($this->paymentMethod)];
            $resp= $client->post($API_PM.$endpoint.$this->booking->id,
                [
                'headers' => [
                    'Authorization' => "Bearer $API_KEY",
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonData
            ])->getBody();

            $resp=json_decode($resp, true);
        }catch(Exception $e) {
            throw $e;
        }
    }
}
