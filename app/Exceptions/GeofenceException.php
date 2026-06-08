<?php

namespace App\Exceptions;

use Exception;

class GeofenceException extends Exception
{
    public $userMessage;

    public function __construct($message = "You are outside the allowed office geofence.", $code = 403, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->userMessage = $message;
    }
}
