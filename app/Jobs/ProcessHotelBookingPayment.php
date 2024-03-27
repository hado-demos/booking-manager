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

class ProcessHotelBookingPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookingId;
    protected $paymentMethod;
    protected $encryptedData;
    protected $bin;

    /**
     * Create a new job instance.
     */
    public function __construct($bookingId,$paymentMethod,$encryptedData,$bin)
    {
        $this->bookingId = $bookingId;
        $this->paymentMethod = $paymentMethod;
        $this->encryptedData = $encryptedData;
        $this->bin = $bin;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $API_PM = GeneralParameters::where('nombre', '=', 'API_PM2_DOMAIN')->first()->valor;
        $API_KEY= GeneralParameters::where('nombre', '=', 'API_KEY2_INTERNAL')->first()->valor;
        try{
            $isCC = strtoupper($this->paymentMethod) == "CC";
            $endpoint = $isCC ? 'processPayment/':'generatePIX/';
            $client= new Client(["verify" => false]);
            $jsonData = $isCC 
                ? [
                    'encryptedData' => $this->encryptedData,
                    'createToken' => 1,
                    'bin' => $this->bin
                ] 
                : [];
            $resp= $client->post($API_PM.$endpoint.$this->bookingId,
                [
                'headers' => [
                    'Authorization' => "Bearer $API_KEY",
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonData
            ])->getBody();

            $resp=json_decode($resp, true);
            // TODO procesamiento de errores
        }catch(Exception $e) {
            throw $e;
        }
    }
}
