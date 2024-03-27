<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use ClhGroup\ClhBookings\Models\ClhHotel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        
		$hotels = ClhHotel::where("visible", "1")->get();
        foreach($hotels as $hotel){
            $schedule->command("app:generate-accommodation-sales-movements {$hotel->id}")
            ->dailyAt('00:05')
            ->timezone('America/Argentina/Buenos_Aires');

            $schedule->command("app:generate-accommodation-sales-details-by-product {$hotel->id}")
            ->dailyAt('00:05')
            ->timezone('America/Argentina/Buenos_Aires');
        }
        
        
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
