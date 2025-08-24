<?php

namespace Tests\Unit\Services;

use App\Exceptions\E2EEException;
use App\Services\E2EEErrorHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E2EEErrorHandlerTest extends TestCase
{
    use RefreshDatabase;

    private E2EEErrorHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new E2EEErrorHandler;
    }

    /** @test */
    public function it_can_handle_device_not_initialized_error()
    {
        $exception = $this->handler->createDeviceNotInitializedError();

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('DEVICE_NOT_INITIALIZED', $exception->getCode());
        $this->assertStringContainsString('Device must be initialized', $exception->getMessage());
        $this->assertTrue($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_encryption_failed_error()
    {
        $conversationId = 'conv-123';
        $originalError = new \Exception('WebCrypto failed');

        $exception = $this->handler->createEncryptionFailedError($conversationId, $originalError);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('ENCRYPTION_FAILED', $exception->getCode());
        $this->assertEquals($conversationId, $exception->getConversationId());
        $this->assertEquals($originalError, $exception->getOriginalError());
        $this->assertTrue($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_decryption_failed_error()
    {
        $conversationId = 'conv-123';
        $keyVersion = 2;
        $originalError = new \Exception('Invalid key');

        $exception = $this->handler->createDecryptionFailedError($conversationId, $keyVersion, $originalError);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('DECRYPTION_FAILED', $exception->getCode());
        $this->assertEquals($conversationId, $exception->getConversationId());
        $this->assertEquals($keyVersion, $exception->getKeyVersion());
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_device_verification_failed_error()
    {
        $deviceId = 'device-123';

        $exception = $this->handler->createDeviceVerificationFailedError($deviceId);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('DEVICE_VERIFICATION_FAILED', $exception->getCode());
        $this->assertEquals($deviceId, $exception->getDeviceId());
        $this->assertTrue($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_network_errors()
    {
        $originalError = new \Exception('Network timeout');

        $exception = $this->handler->createNetworkError($originalError);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('NETWORK_ERROR', $exception->getCode());
        $this->assertEquals($originalError, $exception->getOriginalError());
        $this->assertTrue($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_storage_errors()
    {
        $operation = 'save device keys';
        $originalError = new \Exception('LocalStorage quota exceeded');

        $exception = $this->handler->createStorageError($operation, $originalError);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('STORAGE_ERROR', $exception->getCode());
        $this->assertStringContainsString($operation, $exception->getMessage());
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_api_errors()
    {
        $httpStatus = 429;
        $responseBody = 'Rate limit exceeded';

        $exception = $this->handler->createApiError($httpStatus, $responseBody);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('API_ERROR', $exception->getCode());
        $this->assertEquals($httpStatus, $exception->getHttpStatus());
        $this->assertEquals($responseBody, $exception->getResponseBody());
        $this->assertTrue($exception->isRecoverable()); // 4xx errors are generally recoverable
    }

    /** @test */
    public function it_can_handle_message_authentication_failed()
    {
        $exception = $this->handler->createMessageAuthenticationFailedError();

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('MESSAGE_AUTHENTICATION_FAILED', $exception->getCode());
        $this->assertStringContainsString('tampered', $exception->getMessage());
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_message_too_old_error()
    {
        $timestamp = now()->subDays(2)->timestamp;

        $exception = $this->handler->createMessageTooOldError($timestamp);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('MESSAGE_TOO_OLD', $exception->getCode());
        $this->assertArrayHasKey('messageTimestamp', $exception->getContext());
        $this->assertEquals($timestamp, $exception->getContext()['messageTimestamp']);
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_key_generation_failed()
    {
        $originalError = new \Exception('WebCrypto not supported');

        $exception = $this->handler->createKeyGenerationFailedError($originalError);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('KEY_GENERATION_FAILED', $exception->getCode());
        $this->assertEquals($originalError, $exception->getOriginalError());
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_security_violations()
    {
        $reason = 'Multiple failed authentication attempts';

        $exception = $this->handler->createSecurityViolationError($reason);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('SECURITY_VIOLATION', $exception->getCode());
        $this->assertStringContainsString($reason, $exception->getMessage());
        $this->assertEquals($reason, $exception->getContext()['reason']);
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_can_handle_rate_limiting()
    {
        $retryAfter = 60;

        $exception = $this->handler->createRateLimitedError($retryAfter);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('RATE_LIMITED', $exception->getCode());
        $this->assertEquals($retryAfter, $exception->getContext()['retryAfter']);
        $this->assertTrue($exception->isRecoverable());
        $this->assertStringContainsString('60 seconds', $exception->getUserMessage());
    }

    /** @test */
    public function it_provides_user_friendly_messages()
    {
        $deviceException = $this->handler->createDeviceNotInitializedError();
        $encryptionException = $this->handler->createEncryptionFailedError('conv-123');
        $networkException = $this->handler->createNetworkError();

        $this->assertStringContainsString('set up your device', $deviceException->getUserMessage());
        $this->assertStringContainsString('try again', $encryptionException->getUserMessage());
        $this->assertStringContainsString('internet connection', $networkException->getUserMessage());
    }

    /** @test */
    public function it_categorizes_errors_by_recoverability()
    {
        $recoverableErrors = [
            $this->handler->createDeviceNotInitializedError(),
            $this->handler->createEncryptionFailedError('conv-123'),
            $this->handler->createNetworkError(),
            $this->handler->createRateLimitedError(60),
        ];

        $nonRecoverableErrors = [
            $this->handler->createDecryptionFailedError('conv-123', 1),
            $this->handler->createMessageAuthenticationFailedError(),
            $this->handler->createKeyGenerationFailedError(),
            $this->handler->createSecurityViolationError('test'),
        ];

        foreach ($recoverableErrors as $error) {
            $this->assertTrue($error->isRecoverable(), "Error {$error->getCode()} should be recoverable");
        }

        foreach ($nonRecoverableErrors as $error) {
            $this->assertFalse($error->isRecoverable(), "Error {$error->getCode()} should not be recoverable");
        }
    }

    /** @test */
    public function it_includes_context_information()
    {
        $conversationId = 'conv-123';
        $keyVersion = 2;
        $deviceId = 'device-456';

        $exception = $this->handler->createDecryptionFailedError($conversationId, $keyVersion);
        $exception->setDeviceId($deviceId);

        $this->assertEquals($conversationId, $exception->getConversationId());
        $this->assertEquals($keyVersion, $exception->getKeyVersion());
        $this->assertEquals($deviceId, $exception->getDeviceId());

        $timestamp = $exception->getTimestamp();
        $this->assertInstanceOf(\DateTimeInterface::class, $timestamp);
        $this->assertTrue($timestamp->getTimestamp() <= time());
    }

    /** @test */
    public function it_can_serialize_error_details()
    {
        $exception = $this->handler->createEncryptionFailedError(
            'conv-123',
            new \Exception('Original error')
        );
        $exception->setDeviceId('device-456');

        $details = $exception->toArray();

        $this->assertIsArray($details);
        $this->assertArrayHasKey('code', $details);
        $this->assertArrayHasKey('message', $details);
        $this->assertArrayHasKey('user_message', $details);
        $this->assertArrayHasKey('conversation_id', $details);
        $this->assertArrayHasKey('device_id', $details);
        $this->assertArrayHasKey('timestamp', $details);
        $this->assertArrayHasKey('recoverable', $details);

        $this->assertEquals('ENCRYPTION_FAILED', $details['code']);
        $this->assertEquals('conv-123', $details['conversation_id']);
        $this->assertEquals('device-456', $details['device_id']);
        $this->assertTrue($details['recoverable']);
    }

    /** @test */
    public function it_can_create_from_array()
    {
        $data = [
            'code' => 'DECRYPTION_FAILED',
            'message' => 'Failed to decrypt message',
            'conversation_id' => 'conv-123',
            'key_version' => 2,
            'device_id' => 'device-456',
            'recoverable' => false,
        ];

        $exception = $this->handler->createFromArray($data);

        $this->assertInstanceOf(E2EEException::class, $exception);
        $this->assertEquals('DECRYPTION_FAILED', $exception->getCode());
        $this->assertEquals('Failed to decrypt message', $exception->getMessage());
        $this->assertEquals('conv-123', $exception->getConversationId());
        $this->assertEquals(2, $exception->getKeyVersion());
        $this->assertEquals('device-456', $exception->getDeviceId());
        $this->assertFalse($exception->isRecoverable());
    }

    /** @test */
    public function it_logs_errors_appropriately()
    {
        $this->expectOutputRegex('/ERROR.*ENCRYPTION_FAILED/');

        $exception = $this->handler->createEncryptionFailedError('conv-123');
        $this->handler->logError($exception);
    }

    /** @test */
    public function it_can_handle_bulk_error_processing()
    {
        $errors = [
            ['code' => 'DEVICE_NOT_INITIALIZED', 'message' => 'Device not ready'],
            ['code' => 'NETWORK_ERROR', 'message' => 'Connection failed'],
            ['code' => 'ENCRYPTION_FAILED', 'message' => 'Encryption error', 'conversation_id' => 'conv-123'],
        ];

        $exceptions = $this->handler->createBulkFromArray($errors);

        $this->assertCount(3, $exceptions);
        $this->assertInstanceOf(E2EEException::class, $exceptions[0]);
        $this->assertInstanceOf(E2EEException::class, $exceptions[1]);
        $this->assertInstanceOf(E2EEException::class, $exceptions[2]);

        $this->assertEquals('DEVICE_NOT_INITIALIZED', $exceptions[0]->getCode());
        $this->assertEquals('NETWORK_ERROR', $exceptions[1]->getCode());
        $this->assertEquals('ENCRYPTION_FAILED', $exceptions[2]->getCode());
        $this->assertEquals('conv-123', $exceptions[2]->getConversationId());
    }

    /** @test */
    public function it_provides_error_statistics()
    {
        // Create multiple errors
        $this->handler->createDeviceNotInitializedError();
        $this->handler->createEncryptionFailedError('conv-1');
        $this->handler->createEncryptionFailedError('conv-2');
        $this->handler->createDecryptionFailedError('conv-3', 1);

        $stats = $this->handler->getErrorStatistics();

        $this->assertArrayHasKey('total_errors', $stats);
        $this->assertArrayHasKey('error_types', $stats);
        $this->assertArrayHasKey('recoverable_count', $stats);
        $this->assertArrayHasKey('non_recoverable_count', $stats);

        $this->assertEquals(4, $stats['total_errors']);
        $this->assertArrayHasKey('DEVICE_NOT_INITIALIZED', $stats['error_types']);
        $this->assertArrayHasKey('ENCRYPTION_FAILED', $stats['error_types']);
        $this->assertArrayHasKey('DECRYPTION_FAILED', $stats['error_types']);

        $this->assertEquals(1, $stats['error_types']['DEVICE_NOT_INITIALIZED']);
        $this->assertEquals(2, $stats['error_types']['ENCRYPTION_FAILED']);
        $this->assertEquals(1, $stats['error_types']['DECRYPTION_FAILED']);
    }
}
