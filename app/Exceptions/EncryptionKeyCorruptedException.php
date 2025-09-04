<?php

namespace App\Exceptions;

use Exception;

class EncryptionKeyCorruptedException extends Exception
{
    protected $userId;
    
    public function __construct(string $message = "", ?string $userId = null, int $code = 0, ?Exception $previous = null)
    {
        $this->userId = $userId;
        parent::__construct($message, $code, $previous);
    }
    
    public function getUserId(): ?string
    {
        return $this->userId;
    }
}