<?php

namespace App\Exceptions;

use Exception;

class DecryptionException extends Exception
{
    public function __construct(string $message = 'Decryption failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
