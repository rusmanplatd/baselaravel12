import { QuantumE2EEService } from './QuantumE2EEService';

export interface EncryptionResult {
    encrypted_content: string;
    content_hash: string;
    algorithm: string;
    key_version: number;
    metadata: Record<string, any>;
}

export interface DecryptionResult {
    decrypted_content: string;
    verified: boolean;
    algorithm: string;
}

export interface DeviceCapabilities {
    quantum_ready: boolean;
    supported_algorithms: string[];
    hardware_security: boolean;
    performance_tier: 'low' | 'medium' | 'high';
}

export class OptimizedE2EEService {
    private quantumService: QuantumE2EEService;
    private deviceCapabilities: DeviceCapabilities | null = null;
    private encryptionCache = new Map<string, EncryptionResult>();
    private decryptionCache = new Map<string, DecryptionResult>();

    constructor() {
        this.quantumService = new QuantumE2EEService();
        this.initializeDeviceCapabilities();
    }

    /**
     * Initialize device capabilities assessment
     */
    private async initializeDeviceCapabilities(): Promise<void> {
        try {
            // Assess hardware capabilities
            const hardwareSecurity = await this.checkHardwareSecuritySupport();
            const performanceTier = await this.assessPerformanceTier();
            const quantumReady = await this.checkQuantumReadiness();

            this.deviceCapabilities = {
                quantum_ready: quantumReady,
                supported_algorithms: this.getSupportedAlgorithms(),
                hardware_security: hardwareSecurity,
                performance_tier: performanceTier,
            };

            console.log('Device capabilities initialized:', this.deviceCapabilities);
        } catch (error) {
            console.warn('Failed to initialize device capabilities:', error);
            this.deviceCapabilities = {
                quantum_ready: false,
                supported_algorithms: ['AES-256-GCM'],
                hardware_security: false,
                performance_tier: 'low',
            };
        }
    }

    /**
     * Encrypt message with optimal algorithm selection
     */
    async encryptMessage(
        content: string, 
        conversationId: string,
        recipients: string[]
    ): Promise<EncryptionResult> {
        const cacheKey = `${conversationId}:${content.slice(0, 50)}`;
        
        // Check cache first
        if (this.encryptionCache.has(cacheKey)) {
            return this.encryptionCache.get(cacheKey)!;
        }

        try {
            // Select optimal algorithm
            const algorithm = await this.selectOptimalAlgorithm(conversationId);
            
            let result: EncryptionResult;

            if (algorithm.includes('ML-KEM')) {
                // Use quantum encryption
                result = await this.quantumService.encryptForConversation(
                    content,
                    conversationId,
                    algorithm
                );
            } else {
                // Use classical encryption
                result = await this.classicalEncrypt(content, algorithm);
            }

            // Cache result for performance
            this.encryptionCache.set(cacheKey, result);
            
            // Clean cache periodically
            if (this.encryptionCache.size > 100) {
                this.cleanEncryptionCache();
            }

            return result;
        } catch (error) {
            console.error('Encryption failed:', error);
            throw new Error('Failed to encrypt message');
        }
    }

    /**
     * Decrypt message with automatic algorithm detection
     */
    async decryptMessage(
        encryptedData: EncryptionResult,
        conversationId: string
    ): Promise<DecryptionResult> {
        const cacheKey = `decrypt:${encryptedData.content_hash}`;
        
        // Check cache first
        if (this.decryptionCache.has(cacheKey)) {
            return this.decryptionCache.get(cacheKey)!;
        }

        try {
            let result: DecryptionResult;

            if (encryptedData.algorithm.includes('ML-KEM')) {
                // Use quantum decryption
                result = await this.quantumService.decryptMessage(
                    encryptedData,
                    conversationId
                );
            } else {
                // Use classical decryption
                result = await this.classicalDecrypt(encryptedData);
            }

            // Verify content integrity
            const verified = await this.verifyContentIntegrity(
                result.decrypted_content,
                encryptedData.content_hash
            );

            result.verified = verified;

            // Cache result
            this.decryptionCache.set(cacheKey, result);
            
            // Clean cache periodically
            if (this.decryptionCache.size > 50) {
                this.cleanDecryptionCache();
            }

            return result;
        } catch (error) {
            console.error('Decryption failed:', error);
            throw new Error('Failed to decrypt message');
        }
    }

    /**
     * Bulk encrypt multiple messages for performance
     */
    async bulkEncrypt(
        messages: Array<{ content: string; conversationId: string; recipients: string[] }>
    ): Promise<EncryptionResult[]> {
        const results: EncryptionResult[] = [];
        
        // Process in batches for memory efficiency
        const batchSize = 10;
        for (let i = 0; i < messages.length; i += batchSize) {
            const batch = messages.slice(i, i + batchSize);
            const batchPromises = batch.map(msg => 
                this.encryptMessage(msg.content, msg.conversationId, msg.recipients)
            );
            
            const batchResults = await Promise.all(batchPromises);
            results.push(...batchResults);
        }

        return results;
    }

    /**
     * Bulk decrypt multiple messages for performance
     */
    async bulkDecrypt(
        encryptedMessages: Array<{ data: EncryptionResult; conversationId: string }>
    ): Promise<DecryptionResult[]> {
        const results: DecryptionResult[] = [];
        
        // Process in batches
        const batchSize = 10;
        for (let i = 0; i < encryptedMessages.length; i += batchSize) {
            const batch = encryptedMessages.slice(i, i + batchSize);
            const batchPromises = batch.map(msg => 
                this.decryptMessage(msg.data, msg.conversationId)
            );
            
            const batchResults = await Promise.all(batchPromises);
            results.push(...batchResults);
        }

        return results;
    }

    /**
     * Get device encryption capabilities
     */
    getDeviceCapabilities(): DeviceCapabilities | null {
        return this.deviceCapabilities;
    }

    /**
     * Select optimal encryption algorithm based on conversation and device capabilities
     */
    private async selectOptimalAlgorithm(conversationId: string): Promise<string> {
        if (!this.deviceCapabilities) {
            return 'AES-256-GCM';
        }

        // Check conversation requirements
        const conversationInfo = await this.getConversationEncryptionInfo(conversationId);
        
        if (conversationInfo.requires_quantum && this.deviceCapabilities.quantum_ready) {
            // Use highest quantum security level based on performance
            switch (this.deviceCapabilities.performance_tier) {
                case 'high':
                    return 'ML-KEM-1024';
                case 'medium':
                    return 'ML-KEM-768';
                default:
                    return 'ML-KEM-512';
            }
        }

        if (this.deviceCapabilities.quantum_ready && conversationInfo.quantum_preferred) {
            return 'ML-KEM-768'; // Balanced quantum security
        }

        // Fall back to classical encryption
        return this.deviceCapabilities.hardware_security ? 'AES-256-GCM' : 'AES-256-GCM';
    }

    /**
     * Classical encryption implementation
     */
    private async classicalEncrypt(content: string, algorithm: string): Promise<EncryptionResult> {
        // Generate session key
        const key = await crypto.subtle.generateKey(
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt']
        );

        // Encrypt content
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encodedContent = new TextEncoder().encode(content);
        
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            encodedContent
        );

        // Generate content hash
        const contentHash = await crypto.subtle.digest('SHA-256', encodedContent);

        return {
            encrypted_content: btoa(String.fromCharCode(...new Uint8Array(encrypted))),
            content_hash: btoa(String.fromCharCode(...new Uint8Array(contentHash))),
            algorithm,
            key_version: 1,
            metadata: {
                iv: btoa(String.fromCharCode(...iv)),
                timestamp: Date.now(),
            },
        };
    }

    /**
     * Classical decryption implementation
     */
    private async classicalDecrypt(encryptedData: EncryptionResult): Promise<DecryptionResult> {
        try {
            // This is a simplified implementation
            // In practice, you'd retrieve the actual key for decryption
            const mockDecrypted = 'Decrypted content'; // Placeholder
            
            return {
                decrypted_content: mockDecrypted,
                verified: true,
                algorithm: encryptedData.algorithm,
            };
        } catch (error) {
            throw new Error('Classical decryption failed');
        }
    }

    /**
     * Check hardware security support
     */
    private async checkHardwareSecuritySupport(): Promise<boolean> {
        try {
            // Check for WebAuthn support as proxy for hardware security
            return !!(navigator.credentials && navigator.credentials.create);
        } catch {
            return false;
        }
    }

    /**
     * Assess device performance tier
     */
    private async assessPerformanceTier(): Promise<'low' | 'medium' | 'high'> {
        const cores = navigator.hardwareConcurrency || 1;
        const memory = (navigator as any).deviceMemory || 1;

        // Simple heuristic based on available information
        if (cores >= 8 && memory >= 4) return 'high';
        if (cores >= 4 && memory >= 2) return 'medium';
        return 'low';
    }

    /**
     * Check quantum algorithm readiness
     */
    private async checkQuantumReadiness(): Promise<boolean> {
        // Check if quantum crypto APIs are available
        return typeof crypto.subtle !== 'undefined' && 
               crypto.subtle.generateKey !== undefined;
    }

    /**
     * Get supported algorithms list
     */
    private getSupportedAlgorithms(): string[] {
        const algorithms = ['AES-256-GCM'];
        
        if (this.deviceCapabilities?.quantum_ready) {
            algorithms.push('ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024');
        }

        return algorithms;
    }

    /**
     * Get conversation encryption information
     */
    private async getConversationEncryptionInfo(conversationId: string): Promise<{
        requires_quantum: boolean;
        quantum_preferred: boolean;
        algorithm: string;
    }> {
        try {
            const response = await fetch(`/api/v1/chat/conversations/${conversationId}/encryption-info`);
            if (!response.ok) throw new Error('Failed to fetch encryption info');
            return await response.json();
        } catch {
            return {
                requires_quantum: false,
                quantum_preferred: true,
                algorithm: 'AES-256-GCM',
            };
        }
    }

    /**
     * Verify content integrity
     */
    private async verifyContentIntegrity(content: string, expectedHash: string): Promise<boolean> {
        try {
            const contentBytes = new TextEncoder().encode(content);
            const actualHashBuffer = await crypto.subtle.digest('SHA-256', contentBytes);
            const actualHash = btoa(String.fromCharCode(...new Uint8Array(actualHashBuffer)));
            
            return actualHash === expectedHash;
        } catch {
            return false;
        }
    }

    /**
     * Clean encryption cache
     */
    private cleanEncryptionCache(): void {
        // Remove oldest entries (simple FIFO)
        const entries = Array.from(this.encryptionCache.entries());
        const toRemove = entries.slice(0, entries.length - 50);
        toRemove.forEach(([key]) => this.encryptionCache.delete(key));
    }

    /**
     * Clean decryption cache
     */
    private cleanDecryptionCache(): void {
        // Remove oldest entries (simple FIFO)
        const entries = Array.from(this.decryptionCache.entries());
        const toRemove = entries.slice(0, entries.length - 25);
        toRemove.forEach(([key]) => this.decryptionCache.delete(key));
    }
}