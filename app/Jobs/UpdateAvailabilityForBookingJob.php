<?php

namespace App\Jobs;

use App\Services\OccupationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateAvailabilityForBookingJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookingId;
    protected $operation;

    public function __construct($bookingId, $operation) {
        $this->bookingId = $bookingId;
        $this->operation = $operation;
    }

    public function handle(OccupationService $occupationService) {
        $occupationService->updateBookingAvailability($this->bookingId, $this->operation);
    }
}
