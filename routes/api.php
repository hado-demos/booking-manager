<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\AuthController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('booking/availability/get', [BookingController::class, 'getAvailabilities']);
    Route::post('booking/new', [BookingController::class, 'newBooking']);
    Route::post('booking/intention/new', [BookingController::class, 'newIntention']);
    Route::post('booking/calculateTotals', [BookingController::class, 'calculateTotals']);
    Route::post('booking/{id}', [BookingController::class, 'getBookingById']);
    Route::post('booking/changePaymentMethod/{id}', [BookingController::class, 'changePaymentMethod']);
    Route::delete('booking/{id}', [BookingController::class, 'cancelBookingById']);
    Route::get('booking/totals/{id}', [BookingController::class, 'getTotalsBookingById']);
    Route::post('booking/clearAddOns/{id}', [BookingController::class, 'clearBookingAddOns']);

    Route::post('booking/regenerateRates/{id}', [BookingController::class, 'regenerateRatesByNewBookingTotal']);

    Route::get('property/{propertyId}', [HotelController::class, 'getProperty']);
    Route::get('destinations', [HotelController::class, 'getDestinations']);
    Route::get('currencies', [CurrencyController::class, 'getCurrencies']);
    Route::get('getDataByIP/{ipAddress}', [CountryController::class, 'getDataByIP']);
    Route::get('countries', [CountryController::class, 'getCountries']);
    Route::get('cities', [CityController::class, 'getCities']);
    Route::get('home', [HotelController::class, 'getHomeData']);
    Route::get('termsAndConditions', [HotelController::class, 'getTermsAndConditions']);

    Route::post('user/check', [UserController::class, 'check']); 
});



