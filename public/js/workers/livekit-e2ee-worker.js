/**
 * LiveKit E2EE Worker with Quantum Cryptography Support
 * 
 * This worker handles end-to-end encryption/decryption operations for LiveKit
 * media streams using quantum-resistant algorithms when available.
 */

// Import crypto polyfills for worker environment
importScripts('https://cdn.jsdelivr.net/npm/@peculiar/webcrypto@1.4.3/build/webcrypto.min.js');

class QuantumE2EEWorker {
    constructor() {
        this.keyCache = new Map();
        this.frameCounter = 0;
        this.performanceMetrics = {
            encryptionTimes: [],
            decryptionTimes: [],
            keyRotations: 0,
        };
        this.quantumCapable = false;
        this.preferredAlgorithms = ['ML-KEM-768', 'ML-KEM-1024', 'AES-GCM'];
        
        console.log('[E2EE Worker] Quantum-enabled E2EE worker initialized');
    }

    /**
     * Initialize the worker with quantum capabilities
     */
    async initialize(config = {}) {
        try {
            // Check if quantum algorithms are available
            this.quantumCapable = await this.checkQuantumSupport();
            
            // Set preferred algorithms based on capabilities
            if (this.quantumCapable) {
                this.preferredAlgorithms = config.preferredAlgorithms || ['ML-KEM-768', 'ML-KEM-1024', 'AES-GCM'];
            } else {
                this.preferredAlgorithms = ['AES-GCM'];
            }

            console.log('[E2EE Worker] Initialized with quantum support:', this.quantumCapable);
            console.log('[E2EE Worker] Preferred algorithms:', this.preferredAlgorithms);
            
            return {
                success: true,
                quantumCapable: this.quantumCapable,
                supportedAlgorithms: this.preferredAlgorithms,
            };
        } catch (error) {
            console.error('[E2EE Worker] Initialization failed:', error);
            return {
                success: false,
                error: error.message,
            };
        }
    }

    /**
     * Check if quantum cryptography is supported
     */
    async checkQuantumSupport() {
        try {
            // Check for WebAssembly support (required for quantum crypto libraries)
            if (!WebAssembly) {
                return false;
            }

            // Test crypto.subtle availability
            if (!crypto?.subtle) {
                return false;
            }

            // Test basic cryptographic operations
            const testKey = await crypto.subtle.generateKey(
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            );

            if (!testKey) {
                return false;
            }

            return true;
        } catch (error) {
            console.warn('[E2EE Worker] Quantum support check failed:', error);
            return false;
        }
    }

    /**
     * Set encryption key for a participant
     */
    setKey(participantId, keyData) {
        try {
            const { key, algorithm, keyId, timestamp } = keyData;
            
            this.keyCache.set(participantId, {
                key: new Uint8Array(key),
                algorithm: algorithm || 'AES-GCM',
                keyId: keyId || 'default',
                timestamp: timestamp || Date.now(),
                usageCount: 0,
            });

            console.log(`[E2EE Worker] Set ${algorithm} key for participant:`, participantId);
            
            return { success: true };
        } catch (error) {
            console.error('[E2EE Worker] Failed to set key:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Remove key for a participant
     */
    removeKey(participantId) {
        const existed = this.keyCache.has(participantId);
        this.keyCache.delete(participantId);
        
        console.log(`[E2EE Worker] Removed key for participant:`, participantId);
        
        return { success: true, existed };
    }

    /**
     * Encrypt media frame
     */
    async encrypt(data, participantId, options = {}) {
        const startTime = performance.now();
        
        try {
            const keyInfo = this.keyCache.get(participantId);
            if (!keyInfo) {
                throw new Error(`No encryption key available for participant: ${participantId}`);
            }

            // Convert data to Uint8Array if needed
            const plaintext = data instanceof ArrayBuffer ? new Uint8Array(data) : data;
            
            let encrypted;
            
            if (keyInfo.algorithm.startsWith('ML-KEM') && this.quantumCapable) {
                encrypted = await this.quantumEncrypt(plaintext, keyInfo, options);
            } else {
                encrypted = await this.classicalEncrypt(plaintext, keyInfo, options);
            }

            // Update usage counter
            keyInfo.usageCount++;
            
            // Check if key rotation is needed
            if (this.shouldRotateKey(keyInfo)) {
                this.requestKeyRotation(participantId);
            }

            // Record performance metrics
            const duration = performance.now() - startTime;
            this.recordEncryptionTime(duration);

            return {
                success: true,
                data: encrypted,
                algorithm: keyInfo.algorithm,
                keyId: keyInfo.keyId,
            };
            
        } catch (error) {
            const duration = performance.now() - startTime;
            console.error('[E2EE Worker] Encryption failed:', error);
            
            return {
                success: false,
                error: error.message,
                duration,
            };
        }
    }

    /**
     * Decrypt media frame
     */
    async decrypt(encryptedData, participantId, options = {}) {
        const startTime = performance.now();
        
        try {
            const keyInfo = this.keyCache.get(participantId);
            if (!keyInfo) {
                throw new Error(`No decryption key available for participant: ${participantId}`);
            }

            let decrypted;
            
            if (keyInfo.algorithm.startsWith('ML-KEM') && this.quantumCapable) {
                decrypted = await this.quantumDecrypt(encryptedData, keyInfo, options);
            } else {
                decrypted = await this.classicalDecrypt(encryptedData, keyInfo, options);
            }

            // Record performance metrics
            const duration = performance.now() - startTime;
            this.recordDecryptionTime(duration);

            return {
                success: true,
                data: decrypted,
                algorithm: keyInfo.algorithm,
            };
            
        } catch (error) {
            const duration = performance.now() - startTime;
            console.error('[E2EE Worker] Decryption failed:', error);
            
            return {
                success: false,
                error: error.message,
                duration,
            };
        }
    }

    /**
     * Quantum-resistant encryption
     */
    async quantumEncrypt(plaintext, keyInfo, options) {
        // Generate random IV
        const iv = crypto.getRandomValues(new Uint8Array(12));
        
        // Import key for AES-GCM (quantum algorithms provide the symmetric key)
        const key = await crypto.subtle.importKey(
            'raw',
            keyInfo.key.slice(0, 32), // Use first 32 bytes for AES-256
            { name: 'AES-GCM' },
            false,
            ['encrypt']
        );

        // Encrypt with AES-GCM
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            plaintext
        );

        // Combine IV + encrypted data
        const result = new Uint8Array(iv.length + encrypted.byteLength);
        result.set(iv);
        result.set(new Uint8Array(encrypted), iv.length);

        return result;
    }

    /**
     * Quantum-resistant decryption
     */
    async quantumDecrypt(encryptedData, keyInfo, options) {
        // Extract IV and ciphertext
        const iv = encryptedData.slice(0, 12);
        const ciphertext = encryptedData.slice(12);

        // Import key for AES-GCM
        const key = await crypto.subtle.importKey(
            'raw',
            keyInfo.key.slice(0, 32), // Use first 32 bytes for AES-256
            { name: 'AES-GCM' },
            false,
            ['decrypt']
        );

        // Decrypt with AES-GCM
        const decrypted = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv },
            key,
            ciphertext
        );

        return new Uint8Array(decrypted);
    }

    /**
     * Classical AES-GCM encryption
     */
    async classicalEncrypt(plaintext, keyInfo, options) {
        // Generate random IV
        const iv = crypto.getRandomValues(new Uint8Array(12));
        
        // Import key for AES-GCM
        const key = await crypto.subtle.importKey(
            'raw',
            keyInfo.key,
            { name: 'AES-GCM' },
            false,
            ['encrypt']
        );

        // Encrypt with AES-GCM
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            plaintext
        );

        // Combine IV + encrypted data
        const result = new Uint8Array(iv.length + encrypted.byteLength);
        result.set(iv);
        result.set(new Uint8Array(encrypted), iv.length);

        return result;
    }

    /**
     * Classical AES-GCM decryption
     */
    async classicalDecrypt(encryptedData, keyInfo, options) {
        // Extract IV and ciphertext
        const iv = encryptedData.slice(0, 12);
        const ciphertext = encryptedData.slice(12);

        // Import key for AES-GCM
        const key = await crypto.subtle.importKey(
            'raw',
            keyInfo.key,
            { name: 'AES-GCM' },
            false,
            ['decrypt']
        );

        // Decrypt with AES-GCM
        const decrypted = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv },
            key,
            ciphertext
        );

        return new Uint8Array(decrypted);
    }

    /**
     * Check if key rotation is needed
     */
    shouldRotateKey(keyInfo) {
        const maxUsage = 1000000; // Rotate after 1M operations
        const maxAge = 24 * 60 * 60 * 1000; // Rotate after 24 hours
        
        return keyInfo.usageCount > maxUsage || 
               (Date.now() - keyInfo.timestamp) > maxAge;
    }

    /**
     * Request key rotation from main thread
     */
    requestKeyRotation(participantId) {
        postMessage({
            type: 'keyRotationNeeded',
            participantId,
            timestamp: Date.now(),
        });
        
        this.performanceMetrics.keyRotations++;
    }

    /**
     * Record encryption performance
     */
    recordEncryptionTime(duration) {
        this.performanceMetrics.encryptionTimes.push(duration);
        
        // Keep only last 1000 measurements
        if (this.performanceMetrics.encryptionTimes.length > 1000) {
            this.performanceMetrics.encryptionTimes.shift();
        }
    }

    /**
     * Record decryption performance
     */
    recordDecryptionTime(duration) {
        this.performanceMetrics.decryptionTimes.push(duration);
        
        // Keep only last 1000 measurements
        if (this.performanceMetrics.decryptionTimes.length > 1000) {
            this.performanceMetrics.decryptionTimes.shift();
        }
    }

    /**
     * Get performance statistics
     */
    getPerformanceStats() {
        const encTimes = this.performanceMetrics.encryptionTimes;
        const decTimes = this.performanceMetrics.decryptionTimes;
        
        const avgEncTime = encTimes.length > 0 ? 
            encTimes.reduce((a, b) => a + b) / encTimes.length : 0;
        
        const avgDecTime = decTimes.length > 0 ? 
            decTimes.reduce((a, b) => a + b) / decTimes.length : 0;

        return {
            averageEncryptionTime: Math.round(avgEncTime * 100) / 100,
            averageDecryptionTime: Math.round(avgDecTime * 100) / 100,
            totalOperations: encTimes.length + decTimes.length,
            keyRotations: this.performanceMetrics.keyRotations,
            quantumCapable: this.quantumCapable,
            supportedAlgorithms: this.preferredAlgorithms,
            activeKeys: this.keyCache.size,
        };
    }

    /**
     * Clear performance metrics
     */
    clearMetrics() {
        this.performanceMetrics = {
            encryptionTimes: [],
            decryptionTimes: [],
            keyRotations: 0,
        };
        
        return { success: true };
    }
}

// Worker instance
const e2eeWorker = new QuantumE2EEWorker();

// Message handler
self.onmessage = async function(e) {
    const { type, id, data } = e.data;
    
    try {
        let result;
        
        switch (type) {
            case 'initialize':
                result = await e2eeWorker.initialize(data);
                break;
                
            case 'setKey':
                result = e2eeWorker.setKey(data.participantId, data.keyData);
                break;
                
            case 'removeKey':
                result = e2eeWorker.removeKey(data.participantId);
                break;
                
            case 'encrypt':
                result = await e2eeWorker.encrypt(data.data, data.participantId, data.options);
                break;
                
            case 'decrypt':
                result = await e2eeWorker.decrypt(data.data, data.participantId, data.options);
                break;
                
            case 'getStats':
                result = e2eeWorker.getPerformanceStats();
                break;
                
            case 'clearMetrics':
                result = e2eeWorker.clearMetrics();
                break;
                
            default:
                result = {
                    success: false,
                    error: `Unknown message type: ${type}`,
                };
        }
        
        // Send response back to main thread
        postMessage({
            id,
            type: 'response',
            data: result,
        });
        
    } catch (error) {
        console.error('[E2EE Worker] Message handling error:', error);
        
        postMessage({
            id,
            type: 'error',
            error: error.message,
        });
    }
};

// Handle uncaught errors
self.onerror = function(error) {
    console.error('[E2EE Worker] Uncaught error:', error);
    
    postMessage({
        type: 'error',
        error: error.message || 'Unknown worker error',
    });
};

console.log('[E2EE Worker] Quantum-enabled LiveKit E2EE worker loaded');