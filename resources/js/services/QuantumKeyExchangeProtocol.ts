import { QuantumSafeE2EE } from './QuantumSafeE2EE';

export interface QuantumKeyExchangeSession {
  sessionId: string;
  participantId: string;
  encapsulationKey: Uint8Array;
  sharedSecret: Uint8Array;
  exchangeTimestamp: number;
  status: 'initiated' | 'completed' | 'failed' | 'expired';
  version: string;
}

export interface QuantumKeyExchangeRequest {
  sessionId: string;
  initiatorId: string;
  targetId: string;
  publicKey: Uint8Array;
  signature: Uint8Array;
  timestamp: number;
  nonce: Uint8Array;
}

export interface QuantumKeyExchangeResponse {
  sessionId: string;
  encapsulatedSecret: Uint8Array;
  signature: Uint8Array;
  publicKey: Uint8Array;
  timestamp: number;
}

export class QuantumKeyExchangeProtocol {
  private quantumE2EE: QuantumSafeE2EE;
  private activeSessions = new Map<string, QuantumKeyExchangeSession>();
  private readonly SESSION_TIMEOUT = 300000; // 5 minutes

  constructor(quantumE2EE: QuantumSafeE2EE) {
    this.quantumE2EE = quantumE2EE;
    this.startSessionCleanup();
  }

  async initiateKeyExchange(
    targetUserId: string,
    conversationId: string
  ): Promise<QuantumKeyExchangeRequest> {
    const sessionId = this.generateSessionId();
    const nonce = crypto.getRandomValues(new Uint8Array(32));
    
    // Generate ephemeral key pair for this exchange
    const keyPair = await this.quantumE2EE.generateQuantumKeyPair();
    
    // Create and sign the exchange request
    const request: QuantumKeyExchangeRequest = {
      sessionId,
      initiatorId: await this.getCurrentUserId(),
      targetId: targetUserId,
      publicKey: keyPair.publicKey,
      signature: new Uint8Array(), // Will be filled below
      timestamp: Date.now(),
      nonce
    };

    // Sign the request with our long-term signing key
    const requestData = this.serializeExchangeRequest(request);
    request.signature = await this.quantumE2EE.signData(requestData);

    // Store session for tracking
    this.activeSessions.set(sessionId, {
      sessionId,
      participantId: targetUserId,
      encapsulationKey: keyPair.privateKey,
      sharedSecret: new Uint8Array(),
      exchangeTimestamp: Date.now(),
      status: 'initiated',
      version: '1.0'
    });

    return request;
  }

  async processKeyExchangeRequest(
    request: QuantumKeyExchangeRequest
  ): Promise<QuantumKeyExchangeResponse> {
    // Verify the request signature
    const requestData = this.serializeExchangeRequest({
      ...request,
      signature: new Uint8Array()
    });
    
    const isValidSignature = await this.quantumE2EE.verifySignature(
      requestData,
      request.signature,
      request.initiatorId
    );

    if (!isValidSignature) {
      throw new Error('Invalid key exchange request signature');
    }

    // Check timestamp to prevent replay attacks
    const currentTime = Date.now();
    if (currentTime - request.timestamp > this.SESSION_TIMEOUT) {
      throw new Error('Key exchange request expired');
    }

    // Generate shared secret using ML-KEM encapsulation
    const { sharedSecret, encapsulatedSecret } = await this.quantumE2EE.encapsulateSecret(
      request.publicKey
    );

    // Get our public key for response
    const ourKeyPair = await this.quantumE2EE.getDeviceKeyPair();
    
    // Create response
    const response: QuantumKeyExchangeResponse = {
      sessionId: request.sessionId,
      encapsulatedSecret,
      signature: new Uint8Array(), // Will be filled below
      publicKey: ourKeyPair.publicKey,
      timestamp: currentTime
    };

    // Sign the response
    const responseData = this.serializeExchangeResponse(response);
    response.signature = await this.quantumE2EE.signData(responseData);

    // Store the shared secret for this session
    this.activeSessions.set(request.sessionId, {
      sessionId: request.sessionId,
      participantId: request.initiatorId,
      encapsulationKey: ourKeyPair.privateKey,
      sharedSecret,
      exchangeTimestamp: currentTime,
      status: 'completed',
      version: '1.0'
    });

    return response;
  }

  async processKeyExchangeResponse(
    response: QuantumKeyExchangeResponse
  ): Promise<Uint8Array> {
    const session = this.activeSessions.get(response.sessionId);
    if (!session) {
      throw new Error('Unknown key exchange session');
    }

    // Verify response signature
    const responseData = this.serializeExchangeResponse({
      ...response,
      signature: new Uint8Array()
    });

    const isValidSignature = await this.quantumE2EE.verifySignature(
      responseData,
      response.signature,
      session.participantId
    );

    if (!isValidSignature) {
      session.status = 'failed';
      throw new Error('Invalid key exchange response signature');
    }

    // Decapsulate the shared secret
    const sharedSecret = await this.quantumE2EE.decapsulateSecret(
      response.encapsulatedSecret,
      session.encapsulationKey
    );

    // Update session
    session.sharedSecret = sharedSecret;
    session.status = 'completed';

    return sharedSecret;
  }

  async deriveConversationKeys(
    sessionId: string,
    conversationId: string,
    participantIds: string[]
  ): Promise<{
    encryptionKey: Uint8Array;
    macKey: Uint8Array;
    keyId: string;
  }> {
    const session = this.activeSessions.get(sessionId);
    if (!session || session.status !== 'completed') {
      throw new Error('Invalid or incomplete key exchange session');
    }

    // Derive conversation-specific keys from the shared secret
    const keyMaterial = new Uint8Array([
      ...session.sharedSecret,
      ...new TextEncoder().encode(conversationId),
      ...new TextEncoder().encode(participantIds.sort().join(','))
    ]);

    const derivedKeys = await this.quantumE2EE.deriveKeys(keyMaterial, 96); // 32 + 32 + 32 bytes

    const encryptionKey = derivedKeys.slice(0, 32);
    const macKey = derivedKeys.slice(32, 64);
    const keyIdMaterial = derivedKeys.slice(64, 96);
    
    // Generate a unique key ID for this conversation key
    const keyId = Array.from(keyIdMaterial.slice(0, 16))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');

    // Clean up the session
    this.activeSessions.delete(sessionId);

    return {
      encryptionKey,
      macKey,
      keyId
    };
  }

  async rotateConversationKeys(
    conversationId: string,
    currentKeyId: string
  ): Promise<{
    encryptionKey: Uint8Array;
    macKey: Uint8Array;
    keyId: string;
  }> {
    // Get current conversation participants
    const participants = await this.getConversationParticipants(conversationId);
    
    // Initiate new key exchange with all participants
    const keyExchanges = await Promise.all(
      participants.map(participantId => 
        this.initiateKeyExchange(participantId, conversationId)
      )
    );

    // For simplicity, use the first successful exchange as the base
    // In a real implementation, you'd want to aggregate all exchanges
    const primaryExchange = keyExchanges[0];
    
    return this.deriveConversationKeys(
      primaryExchange.sessionId,
      conversationId,
      participants
    );
  }

  getActiveSessionCount(): number {
    return this.activeSessions.size;
  }

  clearExpiredSessions(): number {
    const currentTime = Date.now();
    let cleared = 0;

    for (const [sessionId, session] of this.activeSessions) {
      if (currentTime - session.exchangeTimestamp > this.SESSION_TIMEOUT) {
        this.activeSessions.delete(sessionId);
        cleared++;
      }
    }

    return cleared;
  }

  private generateSessionId(): string {
    const randomBytes = crypto.getRandomValues(new Uint8Array(16));
    return Array.from(randomBytes)
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  private serializeExchangeRequest(request: Omit<QuantumKeyExchangeRequest, 'signature'>): Uint8Array {
    const encoder = new TextEncoder();
    return new Uint8Array([
      ...encoder.encode(request.sessionId),
      ...encoder.encode(request.initiatorId),
      ...encoder.encode(request.targetId),
      ...request.publicKey,
      ...new Uint8Array(new BigUint64Array([BigInt(request.timestamp)]).buffer),
      ...request.nonce
    ]);
  }

  private serializeExchangeResponse(response: Omit<QuantumKeyExchangeResponse, 'signature'>): Uint8Array {
    const encoder = new TextEncoder();
    return new Uint8Array([
      ...encoder.encode(response.sessionId),
      ...response.encapsulatedSecret,
      ...response.publicKey,
      ...new Uint8Array(new BigUint64Array([BigInt(response.timestamp)]).buffer)
    ]);
  }

  private startSessionCleanup(): void {
    setInterval(() => {
      this.clearExpiredSessions();
    }, 60000); // Clean up every minute
  }

  private async getCurrentUserId(): Promise<string> {
    return 'current-user-id'; // TODO: Get from auth context
  }

  private async getConversationParticipants(conversationId: string): Promise<string[]> {
    return []; // TODO: Get from conversation service
  }
}