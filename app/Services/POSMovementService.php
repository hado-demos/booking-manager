<?php

namespace App\Services;

use App\Models\POSMovement;

class POSMovementService
{

    public function create($data): POSMovement
    {
        return POSMovement::create($data);
    }

}