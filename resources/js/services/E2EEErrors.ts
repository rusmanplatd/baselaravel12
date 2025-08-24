/**
 * Custom error classes for Multi-Device E2EE operations
 */

export enum E2EEErrorCode {
  DEVICE_NOT_INITIALIZED = 'DEVICE_NOT_INITIALIZED',
  DEVICE_REGISTRATION_FAILED = 'DEVICE_REGISTRATION_FAILED',
  DEVICE_VERIFICATION_FAILED = 'DEVICE_VERIFICATION_FAILED',
  DEVICE_NOT_TRUSTED = 'DEVICE_NOT_TRUSTED',
  KEY_GENERATION_FAILED = 'KEY_GENERATION_FAILED',
  ENCRYPTION_FAILED = 'ENCRYPTION_FAILED',
  DECRYPTION_FAILED = 'DECRYPTION_FAILED',
  MESSAGE_AUTHENTICATION_FAILED = 'MESSAGE_AUTHENTICATION_FAILED',
  MESSAGE_INTEGRITY_FAILED = 'MESSAGE_INTEGRITY_FAILED',
  MESSAGE_TOO_OLD = 'MESSAGE_TOO_OLD',
  NETWORK_ERROR = 'NETWORK_ERROR',
  API_ERROR = 'API_ERROR',
  STORAGE_ERROR = 'STORAGE_ERROR',
  INVALID_KEY_FORMAT = 'INVALID_KEY_FORMAT',
  CONVERSATION_NOT_FOUND = 'CONVERSATION_NOT_FOUND',
  KEY_SHARE_FAILED = 'KEY_SHARE_FAILED',
  KEY_ROTATION_FAILED = 'KEY_ROTATION_FAILED',
  SECURITY_VIOLATION = 'SECURITY_VIOLATION',
  INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS',
  RATE_LIMITED = 'RATE_LIMITED',
  SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE',
}

export interface E2EEErrorDetails {
  code: E2EEErrorCode;
  message: string;
  deviceId?: string;
  conversationId?: string;
  keyVersion?: number;
  httpStatus?: number;
  originalError?: Error;
  context?: Record<string, any>;
  timestamp: Date;
  recoverable: boolean;
  userMessage: string;
}

export class E2EEError extends Error {
  public readonly details: E2EEErrorDetails;

  constructor(details: Omit<E2EEErrorDetails, 'timestamp'>) {
    super(details.message);
    this.name = 'E2EEError';
    this.details = {
      ...details,
      timestamp: new Date(),
    };

    // Maintain proper stack trace
    if (Error.captureStackTrace) {
      Error.captureStackTrace(this, E2EEError);
    }
  }

  static deviceNotInitialized(): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.DEVICE_NOT_INITIALIZED,
      message: 'Device must be initialized before performing this operation',
      recoverable: true,
      userMessage: 'Please set up your device for secure messaging first.',
    });
  }

  static deviceRegistrationFailed(httpStatus?: number, originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.DEVICE_REGISTRATION_FAILED,
      message: 'Failed to register device with server',
      httpStatus,
      originalError,
      recoverable: true,
      userMessage: 'Unable to register your device. Please check your connection and try again.',
    });
  }

  static deviceVerificationFailed(deviceId?: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.DEVICE_VERIFICATION_FAILED,
      message: 'Device verification failed',
      deviceId,
      recoverable: true,
      userMessage: 'Device verification failed. Please try the verification process again.',
    });
  }

  static deviceNotTrusted(deviceId?: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.DEVICE_NOT_TRUSTED,
      message: 'Device is not trusted for this operation',
      deviceId,
      recoverable: true,
      userMessage: 'Your device needs to be verified before you can access encrypted messages.',
    });
  }

  static keyGenerationFailed(originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.KEY_GENERATION_FAILED,
      message: 'Failed to generate encryption keys',
      originalError,
      recoverable: false,
      userMessage: 'Unable to create secure encryption keys. Your browser may not support the required security features.',
    });
  }

  static encryptionFailed(conversationId?: string, originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.ENCRYPTION_FAILED,
      message: 'Failed to encrypt message',
      conversationId,
      originalError,
      recoverable: true,
      userMessage: 'Unable to encrypt your message. Please try again.',
    });
  }

  static decryptionFailed(conversationId?: string, keyVersion?: number, originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.DECRYPTION_FAILED,
      message: 'Failed to decrypt message',
      conversationId,
      keyVersion,
      originalError,
      recoverable: false,
      userMessage: 'Unable to decrypt this message. It may have been sent from an unrecognized device.',
    });
  }

  static messageAuthenticationFailed(): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.MESSAGE_AUTHENTICATION_FAILED,
      message: 'Message authentication failed - message may have been tampered with',
      recoverable: false,
      userMessage: 'This message could not be authenticated and may not be genuine.',
    });
  }

  static messageIntegrityFailed(): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.MESSAGE_INTEGRITY_FAILED,
      message: 'Message integrity check failed',
      recoverable: false,
      userMessage: 'This message appears to be corrupted or has been modified.',
    });
  }

  static messageTooOld(timestamp: number): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.MESSAGE_TOO_OLD,
      message: 'Message timestamp is too old',
      context: { messageTimestamp: timestamp },
      recoverable: false,
      userMessage: 'This message is too old and cannot be processed for security reasons.',
    });
  }

  static networkError(originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.NETWORK_ERROR,
      message: 'Network error occurred during operation',
      originalError,
      recoverable: true,
      userMessage: 'Network connection issue. Please check your internet connection and try again.',
    });
  }

  static apiError(httpStatus: number, responseBody?: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.API_ERROR,
      message: `API request failed with status ${httpStatus}`,
      httpStatus,
      context: { responseBody },
      recoverable: httpStatus >= 500,
      userMessage: httpStatus >= 500 
        ? 'Server is temporarily unavailable. Please try again later.'
        : 'Request failed. Please try again.',
    });
  }

  static storageError(operation: string, originalError?: Error): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.STORAGE_ERROR,
      message: `Storage operation failed: ${operation}`,
      originalError,
      recoverable: false,
      userMessage: 'Unable to access secure storage. Please check your browser settings.',
    });
  }

  static invalidKeyFormat(keyType: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.INVALID_KEY_FORMAT,
      message: `Invalid key format: ${keyType}`,
      context: { keyType },
      recoverable: false,
      userMessage: 'Encryption key is in an invalid format.',
    });
  }

  static conversationNotFound(conversationId: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.CONVERSATION_NOT_FOUND,
      message: 'Conversation not found or access denied',
      conversationId,
      recoverable: false,
      userMessage: 'This conversation could not be found or you do not have access to it.',
    });
  }

  static keyShareFailed(fromDeviceId?: string, toDeviceId?: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.KEY_SHARE_FAILED,
      message: 'Failed to share encryption keys between devices',
      context: { fromDeviceId, toDeviceId },
      recoverable: true,
      userMessage: 'Unable to share encryption keys with your other devices. Please try again.',
    });
  }

  static keyRotationFailed(conversationId?: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.KEY_ROTATION_FAILED,
      message: 'Failed to rotate encryption keys',
      conversationId,
      recoverable: true,
      userMessage: 'Unable to update encryption keys. Please try again later.',
    });
  }

  static securityViolation(reason: string): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.SECURITY_VIOLATION,
      message: `Security violation detected: ${reason}`,
      context: { reason },
      recoverable: false,
      userMessage: 'Security violation detected. Operation blocked for your protection.',
    });
  }

  static rateLimited(retryAfter?: number): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.RATE_LIMITED,
      message: 'Rate limit exceeded',
      context: { retryAfter },
      recoverable: true,
      userMessage: retryAfter 
        ? `Too many requests. Please wait ${retryAfter} seconds before trying again.`
        : 'Too many requests. Please wait a moment before trying again.',
    });
  }

  static serviceUnavailable(): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.SERVICE_UNAVAILABLE,
      message: 'E2EE service is temporarily unavailable',
      recoverable: true,
      userMessage: 'Secure messaging is temporarily unavailable. Please try again later.',
    });
  }
}

export class E2EEErrorHandler {
  private static readonly MAX_RETRY_ATTEMPTS = 3;
  private static readonly RETRY_DELAY_MS = 1000;

  static async withRetry<T>(
    operation: () => Promise<T>,
    maxAttempts: number = this.MAX_RETRY_ATTEMPTS,
    shouldRetry: (error: E2EEError) => boolean = (error) => error.details.recoverable
  ): Promise<T> {
    let lastError: E2EEError;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        return await operation();
      } catch (error) {
        const e2eeError = error instanceof E2EEError ? error : this.wrapError(error);
        lastError = e2eeError;

        if (attempt === maxAttempts || !shouldRetry(e2eeError)) {
          throw e2eeError;
        }

        // Wait before retrying
        await this.delay(this.RETRY_DELAY_MS * attempt);
      }
    }

    throw lastError!;
  }

  static wrapError(error: unknown): E2EEError {
    if (error instanceof E2EEError) {
      return error;
    }

    if (error instanceof TypeError && error.message.includes('fetch')) {
      return E2EEError.networkError(error);
    }

    if (error instanceof Error) {
      return new E2EEError({
        code: E2EEErrorCode.SERVICE_UNAVAILABLE,
        message: error.message,
        originalError: error,
        recoverable: false,
        userMessage: 'An unexpected error occurred. Please try again.',
      });
    }

    return new E2EEError({
      code: E2EEErrorCode.SERVICE_UNAVAILABLE,
      message: 'Unknown error occurred',
      recoverable: false,
      userMessage: 'An unexpected error occurred. Please try again.',
    });
  }

  static async handleApiResponse(response: Response): Promise<any> {
    if (response.ok) {
      return await response.json();
    }

    const status = response.status;
    let responseBody: string;
    
    try {
      responseBody = await response.text();
    } catch {
      responseBody = '';
    }

    switch (status) {
      case 429:
        const retryAfter = response.headers.get('Retry-After');
        throw E2EEError.rateLimited(retryAfter ? parseInt(retryAfter) : undefined);
      
      case 404:
        throw E2EEError.conversationNotFound('');
      
      case 403:
        throw E2EEError.insufficientPermissions();
      
      case 401:
        throw E2EEError.deviceNotTrusted();
      
      default:
        throw E2EEError.apiError(status, responseBody);
    }
  }

  static insufficientPermissions(): E2EEError {
    return new E2EEError({
      code: E2EEErrorCode.INSUFFICIENT_PERMISSIONS,
      message: 'Insufficient permissions for this operation',
      recoverable: false,
      userMessage: 'You do not have permission to perform this action.',
    });
  }

  private static delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}