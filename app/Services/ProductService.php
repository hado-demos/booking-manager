<?php

namespace App\Services;

use ClhGroup\ClhBookings\Models\ClhProduct;

class ProductService
{
    public function getProductById($id): ?ClhProduct
    {
        try {
            return ClhProduct::find($id);
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

}