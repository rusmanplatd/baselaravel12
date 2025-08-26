<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatException extends Exception
{
    protected $errorCode;

    protected $context;

    public function __construct(
        string $message = 'Chat operation failed',
        string $errorCode = 'CHAT_ERROR',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function render(Request $request): JsonResponse
    {
        $response = [
            'error' => true,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if (app()->environment('local', 'testing') && ! empty($this->context)) {
            $response['context'] = $this->context;
        }

        $statusCode = match ($this->errorCode) {
            'CONVERSATION_NOT_FOUND' => 404,
            'MESSAGE_NOT_FOUND' => 404,
            'UNAUTHORIZED_ACCESS' => 403,
            'ENCRYPTION_FAILED' => 422,
            'DECRYPTION_FAILED' => 422,
            'INVALID_PARTICIPANT' => 422,
            'CONVERSATION_LIMIT_EXCEEDED' => 429,
            'MESSAGE_RATE_LIMITED' => 429,
            default => 500,
        };

        return response()->json($response, $statusCode);
    }

    public function report(): void
    {
        Log::error('Chat Exception', [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'stack_trace' => $this->getTraceAsString(),
        ]);
    }
}

class ConversationNotFoundException extends ChatException
{
    public function __construct(string $conversationId)
    {
        parent::__construct(
            'Conversation not found',
            'CONVERSATION_NOT_FOUND',
            ['conversation_id' => $conversationId]
        );
    }
}

class MessageNotFoundException extends ChatException
{
    public function __construct(string $messageId)
    {
        parent::__construct(
            'Message not found',
            'MESSAGE_NOT_FOUND',
            ['message_id' => $messageId]
        );
    }
}

class EncryptionException extends ChatException
{
    public function __construct(string $operation, ?Throwable $previous = null)
    {
        parent::__construct(
            "Encryption operation failed: {$operation}",
            'ENCRYPTION_FAILED',
            ['operation' => $operation],
            0,
            $previous
        );
    }
}

class DecryptionException extends ChatException
{
    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct(
            "Decryption failed: {$reason}",
            'DECRYPTION_FAILED',
            ['reason' => $reason],
            0,
            $previous
        );
    }
}

class UnauthorizedChatAccessException extends ChatException
{
    public function __construct(string $resource, string $userId)
    {
        parent::__construct(
            "Unauthorized access to {$resource}",
            'UNAUTHORIZED_ACCESS',
            ['resource' => $resource, 'user_id' => $userId]
        );
    }
}
