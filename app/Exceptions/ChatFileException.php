<?php

namespace App\Exceptions;

use Exception;

class ChatFileException extends Exception
{
    protected string $errorCode;

    protected array $context;

    public function __construct(
        string $message = '',
        string $errorCode = 'CHAT_FILE_ERROR',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;

        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
