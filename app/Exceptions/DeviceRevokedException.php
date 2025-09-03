<?php

namespace App\Exceptions;

use Exception;

class DeviceRevokedException extends Exception
{
    public function __construct(string $message = 'Device has been revoked and cannot be used', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}