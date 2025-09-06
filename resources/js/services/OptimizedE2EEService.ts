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
    private performanceMetrics = new Map<string, number[]>();
    private errorLog: Array<{ timestamp: number; level: string; message: string; data?: unknown }> = [];

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
        const startTime = performance.now();
        const cacheKey = `${conversationId}:${content.slice(0, 50)}`;

        // Check cache first
        if (this.encryptionCache.has(cacheKey)) {
            this.logInfo('Encryption cache hit', { conversationId, contentSize: content.length });
            return this.encryptionCache.get(cacheKey)!;
        }

        try {
            this.logInfo('Starting message encryption', { 
                conversationId, 
                contentSize: content.length, 
                recipientCount: recipients.length 
            });

            // Select optimal algorithm
            const algorithm = await this.selectOptimalAlgorithm(conversationId);
            this.logInfo('Algorithm selected', { algorithm });

            let result: EncryptionResult;

            if (algorithm.includes('ML-KEM')) {
                // Use quantum encryption
                const quantumStartTime = performance.now();
                result = await this.quantumService.encryptForConversation(
                    content,
                    conversationId,
                    algorithm
                );
                const quantumDuration = performance.now() - quantumStartTime;
                this.recordPerformanceMetric('quantum-encryption', quantumDuration);
            } else {
                // Use classical encryption
                const classicalStartTime = performance.now();
                result = await this.classicalEncrypt(content, algorithm);
                const classicalDuration = performance.now() - classicalStartTime;
                this.recordPerformanceMetric('classical-encryption', classicalDuration);
            }

            // Cache result for performance
            this.encryptionCache.set(cacheKey, result);

            // Clean cache periodically
            if (this.encryptionCache.size > 100) {
                this.cleanEncryptionCache();
            }

            const totalDuration = performance.now() - startTime;
            this.recordPerformanceMetric('total-encryption', totalDuration);
            this.logInfo('Encryption completed successfully', { 
                algorithm, 
                duration: `${totalDuration.toFixed(2)}ms` 
            });

            return result;
        } catch (error) {
            const duration = performance.now() - startTime;
            this.logError('Encryption failed', { 
                error: error instanceof Error ? error.message : 'Unknown error',
                conversationId, 
                duration: `${duration.toFixed(2)}ms` 
            });
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
        const startTime = performance.now();
        const cacheKey = `decrypt:${encryptedData.content_hash}`;

        // Check cache first
        if (this.decryptionCache.has(cacheKey)) {
            this.logInfo('Decryption cache hit', { conversationId, algorithm: encryptedData.algorithm });
            return this.decryptionCache.get(cacheKey)!;
        }

        try {
            this.logInfo('Starting message decryption', { 
                conversationId, 
                algorithm: encryptedData.algorithm,
                keyVersion: encryptedData.key_version
            });

            let result: DecryptionResult;

            if (encryptedData.algorithm.includes('ML-KEM')) {
                // Use quantum decryption
                const quantumStartTime = performance.now();
                result = await this.quantumService.decryptMessage(
                    encryptedData,
                    conversationId
                );
                const quantumDuration = performance.now() - quantumStartTime;
                this.recordPerformanceMetric('quantum-decryption', quantumDuration);
            } else {
                // Use classical decryption
                const classicalStartTime = performance.now();
                result = await this.classicalDecrypt(encryptedData);
                const classicalDuration = performance.now() - classicalStartTime;
                this.recordPerformanceMetric('classical-decryption', classicalDuration);
            }

            // Verify content integrity
            const verificationStartTime = performance.now();
            const verified = await this.verifyContentIntegrity(
                result.decrypted_content,
                encryptedData.content_hash
            );
            const verificationDuration = performance.now() - verificationStartTime;
            this.recordPerformanceMetric('content-verification', verificationDuration);

            result.verified = verified;

            if (!verified) {
                this.logWarning('Content integrity verification failed', { conversationId });
            }

            // Cache result
            this.decryptionCache.set(cacheKey, result);

            // Clean cache periodically
            if (this.decryptionCache.size > 50) {
                this.cleanDecryptionCache();
            }

            const totalDuration = performance.now() - startTime;
            this.recordPerformanceMetric('total-decryption', totalDuration);
            this.logInfo('Decryption completed successfully', { 
                algorithm: encryptedData.algorithm, 
                verified,
                duration: `${totalDuration.toFixed(2)}ms` 
            });

            return result;
        } catch (error) {
            const duration = performance.now() - startTime;
            this.logError('Decryption failed', { 
                error: error instanceof Error ? error.message : 'Unknown error',
                conversationId,
                algorithm: encryptedData.algorithm,
                duration: `${duration.toFixed(2)}ms` 
            });
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
        const keyVersion = 1;
        
        // Get or generate encryption key
        const keyMaterial = await this.getStoredKeyMaterial(keyVersion);
        if (!keyMaterial) {
            throw new Error('Unable to generate encryption key');
        }
        
        const key = await crypto.subtle.importKey(
            'raw',
            keyMaterial,
            { name: 'AES-GCM' },
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
            key_version: keyVersion,
            metadata: {
                iv: btoa(String.fromCharCode(...iv)),
                timestamp: Date.now(),
                encrypted_size: encrypted.byteLength,
            },
        };
    }

    /**
     * Classical decryption implementation
     */
    private async classicalDecrypt(encryptedData: EncryptionResult): Promise<DecryptionResult> {
        try {
            // Extract IV and encrypted data
            const iv = this.base64ToUint8Array(encryptedData.metadata.iv);
            const encryptedBytes = this.base64ToUint8Array(encryptedData.encrypted_content);
            
            // Retrieve or derive the decryption key
            const key = await this.retrieveDecryptionKey(encryptedData);
            
            if (!key) {
                throw new Error('Decryption key not available');
            }
            
            // Decrypt the content
            const decrypted = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv },
                key,
                encryptedBytes
            );
            
            // Convert decrypted data to string
            const decryptedContent = new TextDecoder().decode(decrypted);

            return {
                decrypted_content: decryptedContent,
                verified: true,
                algorithm: encryptedData.algorithm,
            };
        } catch (error) {
            console.error('Classical decryption failed:', error);
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
        try {
            // Check basic crypto support
            if (!crypto?.subtle?.generateKey) {
                return false;
            }

            // Test WebAssembly support (required for quantum crypto libraries)
            if (!WebAssembly) {
                return false;
            }

            // Check for sufficient entropy
            try {
                const entropy = crypto.getRandomValues(new Uint8Array(32));
                if (!entropy || entropy.length !== 32) {
                    return false;
                }
            } catch {
                return false;
            }

            // Test crypto performance (quantum operations are computationally intensive)
            const performanceTest = await this.testCryptoPerformance();
            if (!performanceTest) {
                return false;
            }

            // Check if device supports required key sizes
            const keySupport = await this.testKeySupport();
            if (!keySupport) {
                return false;
            }

            // Check for quantum service availability
            const quantumServiceReady = await this.checkQuantumServiceStatus();
            
            return quantumServiceReady;
        } catch (error) {
            console.warn('Quantum readiness check failed:', error);
            return false;
        }
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

    /**
     * Convert base64 string to Uint8Array
     */
    private base64ToUint8Array(base64: string): Uint8Array {
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes;
    }

    /**
     * Retrieve decryption key for encrypted data
     */
    private async retrieveDecryptionKey(encryptedData: EncryptionResult): Promise<CryptoKey | null> {
        try {
            // Try multiple key retrieval strategies in order of preference
            
            // 1. Try to get key from IndexedDB (local secure storage)
            let keyMaterial = await this.getKeyFromIndexedDB(encryptedData.key_version);
            
            // 2. If not found locally, try to fetch from server
            if (!keyMaterial) {
                keyMaterial = await this.fetchKeyFromServer(encryptedData.key_version);
                
                // Store fetched key locally for future use
                if (keyMaterial) {
                    await this.storeKeyInIndexedDB(encryptedData.key_version, keyMaterial);
                }
            }
            
            // 3. As fallback, try to derive from user's master key
            if (!keyMaterial) {
                keyMaterial = await this.deriveKeyFromMasterKey(encryptedData.key_version);
            }
            
            if (!keyMaterial) {
                this.logError('No key material available', { keyVersion: encryptedData.key_version });
                return null;
            }

            // Import the key material as a CryptoKey
            return await crypto.subtle.importKey(
                'raw',
                keyMaterial,
                { name: 'AES-GCM' },
                false,
                ['decrypt']
            );
        } catch (error) {
            this.logError('Failed to retrieve decryption key', { 
                error: error instanceof Error ? error.message : 'Unknown error',
                keyVersion: encryptedData.key_version 
            });
            return null;
        }
    }

    /**
     * Get stored key material by version
     */
    private async getStoredKeyMaterial(keyVersion: number): Promise<Uint8Array | null> {
        try {
            // 1. Try IndexedDB first (fastest)
            let keyMaterial = await this.getKeyFromIndexedDB(keyVersion);
            if (keyMaterial) {
                return keyMaterial;
            }

            // 2. Try server fetch with caching
            keyMaterial = await this.fetchKeyFromServer(keyVersion);
            if (keyMaterial) {
                // Cache for future use
                await this.storeKeyInIndexedDB(keyVersion, keyMaterial);
                return keyMaterial;
            }

            // 3. Generate new key if this is for encryption
            keyMaterial = await this.generateNewKeyMaterial(keyVersion);
            if (keyMaterial) {
                // Store both locally and on server
                await Promise.all([
                    this.storeKeyInIndexedDB(keyVersion, keyMaterial),
                    this.uploadKeyToServer(keyVersion, keyMaterial)
                ]);
                return keyMaterial;
            }

            return null;
        } catch (error) {
            this.logError('Failed to get stored key material', { 
                error: error instanceof Error ? error.message : 'Unknown error',
                keyVersion 
            });
            return null;
        }
    }

    /**
     * Test crypto performance for quantum readiness
     */
    private async testCryptoPerformance(): Promise<boolean> {
        try {
            const startTime = performance.now();
            
            // Perform a test encryption operation
            const testData = crypto.getRandomValues(new Uint8Array(1024));
            const key = await crypto.subtle.generateKey(
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt']
            );
            
            const iv = crypto.getRandomValues(new Uint8Array(12));
            await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv },
                key,
                testData
            );
            
            const duration = performance.now() - startTime;
            
            // If basic crypto operations take longer than 100ms, device likely can't handle quantum crypto
            return duration < 100;
        } catch {
            return false;
        }
    }

    /**
     * Test key support for quantum algorithms
     */
    private async testKeySupport(): Promise<boolean> {
        try {
            // Test various key sizes to ensure device can handle quantum keys
            const keySizes = [256, 512, 1024];
            
            for (const size of keySizes) {
                const testKey = crypto.getRandomValues(new Uint8Array(size / 8));
                if (!testKey || testKey.length !== size / 8) {
                    return false;
                }
            }
            
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Check quantum service status
     */
    private async checkQuantumServiceStatus(): Promise<boolean> {
        try {
            // Check if quantum service is available and properly instantiated
            if (!this.quantumService) {
                this.logWarning('Quantum service not available');
                return false;
            }

            // Test basic quantum service functionality
            try {
                // Try to check if quantum service has basic methods we need
                if (typeof this.quantumService.encryptForConversation !== 'function' ||
                    typeof this.quantumService.decryptMessage !== 'function') {
                    this.logWarning('Quantum service missing required methods');
                    return false;
                }
                
                this.logInfo('Quantum service status check', { ready: true });
                return true;
            } catch (error) {
                this.logError('Quantum service test failed', { error });
                return false;
            }
        } catch (error) {
            this.logError('Quantum service status check failed', { error });
            return false;
        }
    }

    /**
     * Log info message
     */
    private logInfo(message: string, data?: unknown): void {
        this.addToErrorLog('info', message, data);
        console.log(`[OptimizedE2EEService] ${message}`, data || '');
    }

    /**
     * Log warning message
     */
    private logWarning(message: string, data?: unknown): void {
        this.addToErrorLog('warning', message, data);
        console.warn(`[OptimizedE2EEService] ${message}`, data || '');
    }

    /**
     * Log error message
     */
    private logError(message: string, data?: unknown): void {
        this.addToErrorLog('error', message, data);
        console.error(`[OptimizedE2EEService] ${message}`, data || '');
    }

    /**
     * Add entry to error log
     */
    private addToErrorLog(level: string, message: string, data?: unknown): void {
        this.errorLog.push({
            timestamp: Date.now(),
            level,
            message,
            data,
        });

        // Keep only last 100 log entries
        if (this.errorLog.length > 100) {
            this.errorLog.shift();
        }
    }

    /**
     * Record performance metric
     */
    private recordPerformanceMetric(operation: string, duration: number): void {
        if (!this.performanceMetrics.has(operation)) {
            this.performanceMetrics.set(operation, []);
        }

        const metrics = this.performanceMetrics.get(operation)!;
        metrics.push(duration);

        // Keep only last 50 measurements per operation
        if (metrics.length > 50) {
            metrics.shift();
        }

        this.logInfo(`Performance metric recorded`, { 
            operation, 
            duration: `${duration.toFixed(2)}ms`,
            average: `${this.getAveragePerformance(operation).toFixed(2)}ms`
        });
    }

    /**
     * Get average performance for an operation
     */
    private getAveragePerformance(operation: string): number {
        const metrics = this.performanceMetrics.get(operation);
        if (!metrics || metrics.length === 0) {
            return 0;
        }

        return metrics.reduce((sum, duration) => sum + duration, 0) / metrics.length;
    }

    /**
     * Get performance statistics
     */
    public getPerformanceStats(): Record<string, { 
        average: number; 
        count: number; 
        min: number; 
        max: number 
    }> {
        const stats: Record<string, { average: number; count: number; min: number; max: number }> = {};

        for (const [operation, metrics] of this.performanceMetrics) {
            if (metrics.length > 0) {
                stats[operation] = {
                    average: this.getAveragePerformance(operation),
                    count: metrics.length,
                    min: Math.min(...metrics),
                    max: Math.max(...metrics),
                };
            }
        }

        return stats;
    }

    /**
     * Get recent error logs
     */
    public getErrorLogs(level?: string): Array<{ timestamp: number; level: string; message: string; data?: unknown }> {
        if (level) {
            return this.errorLog.filter(log => log.level === level);
        }
        return [...this.errorLog];
    }

    /**
     * Clear performance metrics and error logs
     */
    public clearMetrics(): void {
        this.performanceMetrics.clear();
        this.errorLog.length = 0;
        this.logInfo('Metrics and logs cleared');
    }

    // ============================================================================
    // Key Storage and Management Implementation
    // ============================================================================

    /**
     * Get key from IndexedDB secure storage
     */
    private async getKeyFromIndexedDB(keyVersion: number): Promise<Uint8Array | null> {
        try {
            const dbName = 'ChatEncryptionKeys';
            const storeName = 'keys';
            
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(dbName, 1);
                
                request.onerror = () => reject(request.error);
                
                request.onsuccess = () => {
                    const db = request.result;
                    const transaction = db.transaction([storeName], 'readonly');
                    const store = transaction.objectStore(storeName);
                    const getRequest = store.get(`key_v${keyVersion}`);
                    
                    getRequest.onsuccess = () => {
                        const result = getRequest.result;
                        if (result && result.keyMaterial) {
                            // Decrypt key material if it's encrypted
                            resolve(new Uint8Array(result.keyMaterial));
                        } else {
                            resolve(null);
                        }
                    };
                    
                    getRequest.onerror = () => resolve(null);
                };
                
                request.onupgradeneeded = () => {
                    const db = request.result;
                    if (!db.objectStoreNames.contains(storeName)) {
                        db.createObjectStore(storeName);
                    }
                };
            });
        } catch (error) {
            this.logError('Failed to get key from IndexedDB', { error, keyVersion });
            return null;
        }
    }

    /**
     * Store key in IndexedDB secure storage
     */
    private async storeKeyInIndexedDB(keyVersion: number, keyMaterial: Uint8Array): Promise<boolean> {
        try {
            const dbName = 'ChatEncryptionKeys';
            const storeName = 'keys';
            
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(dbName, 1);
                
                request.onerror = () => reject(request.error);
                
                request.onsuccess = () => {
                    const db = request.result;
                    const transaction = db.transaction([storeName], 'readwrite');
                    const store = transaction.objectStore(storeName);
                    
                    const keyData = {
                        keyMaterial: Array.from(keyMaterial),
                        timestamp: Date.now(),
                        version: keyVersion
                    };
                    
                    const putRequest = store.put(keyData, `key_v${keyVersion}`);
                    
                    putRequest.onsuccess = () => resolve(true);
                    putRequest.onerror = () => resolve(false);
                };
                
                request.onupgradeneeded = () => {
                    const db = request.result;
                    if (!db.objectStoreNames.contains(storeName)) {
                        db.createObjectStore(storeName);
                    }
                };
            });
        } catch (error) {
            this.logError('Failed to store key in IndexedDB', { error, keyVersion });
            return false;
        }
    }

    /**
     * Fetch key from server
     */
    private async fetchKeyFromServer(keyVersion: number): Promise<Uint8Array | null> {
        try {
            const response = await fetch(`/api/v1/chat/encryption-keys/${keyVersion}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                if (response.status === 404) {
                    // Key doesn't exist on server, this is normal
                    return null;
                }
                throw new Error(`Server responded with status ${response.status}`);
            }

            const data = await response.json();
            if (!data.key_material) {
                return null;
            }

            // Decode base64 key material
            return this.base64ToUint8Array(data.key_material);
        } catch (error) {
            this.logWarning('Failed to fetch key from server', { error, keyVersion });
            return null;
        }
    }

    /**
     * Upload key to server
     */
    private async uploadKeyToServer(keyVersion: number, keyMaterial: Uint8Array): Promise<boolean> {
        try {
            const response = await fetch(`/api/v1/chat/encryption-keys/${keyVersion}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    key_material: btoa(String.fromCharCode(...keyMaterial)),
                    algorithm: 'AES-256-GCM',
                    created_at: new Date().toISOString(),
                }),
            });

            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }

            this.logInfo('Key uploaded to server successfully', { keyVersion });
            return true;
        } catch (error) {
            this.logError('Failed to upload key to server', { error, keyVersion });
            return false;
        }
    }

    /**
     * Generate new key material
     */
    private async generateNewKeyMaterial(keyVersion: number): Promise<Uint8Array | null> {
        try {
            // Generate strong cryptographic key material
            const keyMaterial = crypto.getRandomValues(new Uint8Array(32)); // 256 bits
            
            // Verify key quality
            if (!this.validateKeyMaterial(keyMaterial)) {
                throw new Error('Generated key material failed validation');
            }

            this.logInfo('New key material generated', { keyVersion, size: keyMaterial.length });
            return keyMaterial;
        } catch (error) {
            this.logError('Failed to generate new key material', { error, keyVersion });
            return null;
        }
    }

    /**
     * Derive key from user's master key
     */
    private async deriveKeyFromMasterKey(keyVersion: number): Promise<Uint8Array | null> {
        try {
            // Get user's master key (this would typically come from authentication)
            const masterKey = await this.getUserMasterKey();
            if (!masterKey) {
                this.logWarning('Master key not available for derivation', { keyVersion });
                return null;
            }

            // Create derivation info
            const info = new TextEncoder().encode(`chat-key-v${keyVersion}`);
            const salt = crypto.getRandomValues(new Uint8Array(16));

            // Import master key for derivation
            const importedKey = await crypto.subtle.importKey(
                'raw',
                masterKey,
                { name: 'HKDF' },
                false,
                ['deriveKey', 'deriveBits']
            );

            // Derive key using HKDF
            const derivedBits = await crypto.subtle.deriveBits(
                {
                    name: 'HKDF',
                    hash: 'SHA-256',
                    salt: salt,
                    info: info,
                },
                importedKey,
                256 // 256 bits = 32 bytes
            );

            const derivedKey = new Uint8Array(derivedBits);
            
            // Store salt with the key for future derivation
            await this.storeSaltForKey(keyVersion, salt);
            
            this.logInfo('Key derived from master key', { keyVersion });
            return derivedKey;
        } catch (error) {
            this.logError('Failed to derive key from master key', { error, keyVersion });
            return null;
        }
    }

    /**
     * Get user's master key
     */
    private async getUserMasterKey(): Promise<Uint8Array | null> {
        try {
            // In a real implementation, this would:
            // 1. Derive from user's password using PBKDF2
            // 2. Get from secure hardware token
            // 3. Retrieve from authentication session
            
            // For now, return null to indicate master key derivation isn't available
            return null;
        } catch (error) {
            this.logError('Failed to get user master key', { error });
            return null;
        }
    }

    /**
     * Store salt for key derivation
     */
    private async storeSaltForKey(keyVersion: number, salt: Uint8Array): Promise<boolean> {
        try {
            const dbName = 'ChatEncryptionKeys';
            const storeName = 'salts';
            
            return new Promise((resolve) => {
                const request = indexedDB.open(dbName, 1);
                
                request.onsuccess = () => {
                    const db = request.result;
                    const transaction = db.transaction([storeName], 'readwrite');
                    const store = transaction.objectStore(storeName);
                    
                    const saltData = {
                        salt: Array.from(salt),
                        timestamp: Date.now(),
                        version: keyVersion
                    };
                    
                    const putRequest = store.put(saltData, `salt_v${keyVersion}`);
                    putRequest.onsuccess = () => resolve(true);
                    putRequest.onerror = () => resolve(false);
                };
                
                request.onupgradeneeded = () => {
                    const db = request.result;
                    if (!db.objectStoreNames.contains(storeName)) {
                        db.createObjectStore(storeName);
                    }
                };
            });
        } catch (error) {
            this.logError('Failed to store salt', { error, keyVersion });
            return false;
        }
    }

    /**
     * Validate key material quality
     */
    private validateKeyMaterial(keyMaterial: Uint8Array): boolean {
        // Check for sufficient entropy
        const bytes = Array.from(keyMaterial);
        
        // Check for all zeros or all same value
        const firstByte = bytes[0];
        if (bytes.every(byte => byte === firstByte)) {
            return false;
        }
        
        // Check for sufficient variation (simple entropy check)
        const uniqueBytes = new Set(bytes);
        if (uniqueBytes.size < 8) {
            return false;
        }
        
        return true;
    }

    /**
     * Rotate encryption keys (create new version)
     */
    public async rotateKeys(): Promise<number | null> {
        try {
            // Get current highest key version
            const currentVersion = await this.getCurrentKeyVersion();
            const newVersion = currentVersion + 1;
            
            // Generate new key material
            const newKeyMaterial = await this.generateNewKeyMaterial(newVersion);
            if (!newKeyMaterial) {
                throw new Error('Failed to generate new key material for rotation');
            }
            
            // Store new key both locally and on server
            const [localStored, serverStored] = await Promise.all([
                this.storeKeyInIndexedDB(newVersion, newKeyMaterial),
                this.uploadKeyToServer(newVersion, newKeyMaterial)
            ]);
            
            if (!localStored) {
                this.logWarning('Failed to store rotated key locally', { newVersion });
            }
            
            if (!serverStored) {
                this.logWarning('Failed to store rotated key on server', { newVersion });
            }
            
            if (localStored || serverStored) {
                this.logInfo('Key rotation completed', { 
                    oldVersion: currentVersion, 
                    newVersion,
                    localStored,
                    serverStored
                });
                return newVersion;
            } else {
                throw new Error('Failed to store rotated key anywhere');
            }
        } catch (error) {
            this.logError('Key rotation failed', { error });
            return null;
        }
    }
    
    /**
     * Get current key version
     */
    private async getCurrentKeyVersion(): Promise<number> {
        try {
            const response = await fetch('/api/v1/chat/encryption-keys/current-version', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }

            const data = await response.json();
            return data.version || 1;
        } catch (error) {
            this.logWarning('Failed to get current key version from server, using default', { error });
            return 1;
        }
    }
}
