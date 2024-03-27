<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Support\Facades\Notification;
use ClhGroup\ClhBookings\Utils\ClhUtils;
use Illuminate\Support\Facades\Request;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $requestParameters = Request::all(); 
            $path = Request::path();

            $detStrMsg = var_export([
                'message' => $e->getMessage().". Path: $path. Params:".var_export($requestParameters, true),
                'file' => $e->getFile(),
                'line' => $e->getLine()
                
            ],true);
            try {
                ClhUtils::sendNotification($detStrMsg,"Error desde Handler","Error");
            } catch (\Exception $ex) {
                \Log::error('Falló el envío de notificación de error: ' . $detStrMsg); 
            }
        });
    }
}
