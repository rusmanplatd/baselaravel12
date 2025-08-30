<?php

namespace App\Exceptions;

use Exception;

class QuantumCryptoException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'error' => 'Quantum Cryptography Error',
            'message' => $this->getMessage(),
            'quantum_safe' => false,
        ], 500);
    }
}