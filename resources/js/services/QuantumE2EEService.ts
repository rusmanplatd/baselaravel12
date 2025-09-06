import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';

export interface QuantumKeyPair {
    publicKey: string;
    privateKey: string;
    algorithm: string;
    securityLevel: number;
}

export interface QuantumEncryptionResult {
    encrypted_content: string;
    content_hash: string;
    algorithm: string;
    key_version: number;
    metadata: Record<string, any>;
}

export interface QuantumDecryptionResult {
    decrypted_content: string;
    verified: boolean;
    algorithm: string;
}

export class QuantumE2EEService {
    private apiBaseUrl = '/api/v1/quantum';
    private deviceId: string | null = null;
    private keyCache = new Map<string, QuantumKeyPair>();

    constructor() {
        this.deviceId = this.getDeviceId();
    }

    /**
     * Generate quantum-safe key pair
     */
    async generateKeyPair(algorithm: string = 'ML-KEM-768'): Promise<QuantumKeyPair> {
        try {
            const response = await fetch(`${this.apiBaseUrl}/generate-keypair`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    algorithm,
                    security_level: this.getSecurityLevel(algorithm),
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            const keyPair: QuantumKeyPair = {
                publicKey: data.public_key,
                privateKey: data.private_key,
                algorithm: data.algorithm,
                securityLevel: data.security_level,
            };

            // Cache the key pair
            this.keyCache.set(`${algorithm}:${this.deviceId}`, keyPair);

            return keyPair;
        } catch (error) {
            console.error('Failed to generate quantum key pair:', error);
            throw new Error('Quantum key generation failed');
        }
    }

    /**
     * Encrypt content for conversation using quantum algorithms
     */
    async encryptForConversation(
        content: string,
        conversationId: string,
        algorithm: string = 'ML-KEM-768'
    ): Promise<QuantumEncryptionResult> {
        try {
            // Get recipient public keys
            const recipients = await this.getConversationRecipients(conversationId);
            
            // Perform quantum key encapsulation for each recipient
            const encapsulations = await Promise.all(
                recipients.map(recipient => this.performKeyEncapsulation(recipient.publicKey, algorithm))
            );

            // Generate session key
            const sessionKey = crypto.getRandomValues(new Uint8Array(32));

            // Encrypt content with session key
            const encryptedContent = await this.encryptWithSessionKey(content, sessionKey);

            // Encrypt session key for each recipient using quantum-derived shared secrets
            const encryptedSessionKeys = await Promise.all(
                encapsulations.map(async (encap, index) => ({
                    recipientId: recipients[index].id,
                    encryptedSessionKey: await this.encryptSessionKey(sessionKey, encap.sharedSecret),
                    ciphertext: encap.ciphertext,
                }))
            );

            return {
                encrypted_content: encryptedContent.ciphertext,
                content_hash: await this.generateContentHash(content),
                algorithm,
                key_version: 1,
                metadata: {
                    iv: encryptedContent.iv,
                    tag: encryptedContent.tag,
                    encryptedSessionKeys,
                    timestamp: Date.now(),
                },
            };
        } catch (error) {
            console.error('Quantum encryption failed:', error);
            throw new Error('Failed to encrypt with quantum algorithm');
        }
    }

    /**
     * Decrypt message using quantum algorithms
     */
    async decryptMessage(
        encryptedData: QuantumEncryptionResult,
        conversationId: string
    ): Promise<QuantumDecryptionResult> {
        try {
            // Find our encrypted session key
            const ourEncryptedSessionKey = encryptedData.metadata.encryptedSessionKeys?.find(
                (esk: any) => esk.recipientId === this.deviceId
            );

            if (!ourEncryptedSessionKey) {
                throw new Error('No session key found for this device');
            }

            // Perform key decapsulation to get shared secret
            const sharedSecret = await this.performKeyDecapsulation(
                ourEncryptedSessionKey.ciphertext,
                encryptedData.algorithm
            );

            // Decrypt session key
            const sessionKey = await this.decryptSessionKey(
                ourEncryptedSessionKey.encryptedSessionKey,
                sharedSecret
            );

            // Decrypt content with session key
            const decryptedContent = await this.decryptWithSessionKey(
                encryptedData.encrypted_content,
                encryptedData.metadata.iv,
                encryptedData.metadata.tag,
                sessionKey
            );

            // Verify content integrity
            const verified = await this.verifyContentHash(decryptedContent, encryptedData.content_hash);

            return {
                decrypted_content: decryptedContent,
                verified,
                algorithm: encryptedData.algorithm,
            };
        } catch (error) {
            console.error('Quantum decryption failed:', error);
            throw new Error('Failed to decrypt with quantum algorithm');
        }
    }

    /**
     * Register device for quantum encryption
     */
    async registerDevice(deviceInfo: {
        deviceName: string;
        capabilities: string[];
    }): Promise<{ deviceId: string; success: boolean }> {
        try {
            // Generate device key pair
            const keyPair = await this.generateKeyPair('ML-KEM-768');

            const response = await fetch(`${this.apiBaseUrl}/register-device`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    device_name: deviceInfo.deviceName,
                    device_type: this.getDeviceType(),
                    public_key: keyPair.publicKey,
                    capabilities: deviceInfo.capabilities,
                    algorithm: keyPair.algorithm,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            // Store device ID
            this.deviceId = data.device_id;
            setUserStorageItem('quantum_device_id', this.deviceId);

            return {
                deviceId: this.deviceId,
                success: true,
            };
        } catch (error) {
            console.error('Device registration failed:', error);
            throw new Error('Failed to register quantum device');
        }
    }

    /**
     * Perform key encapsulation using ML-KEM
     */
    private async performKeyEncapsulation(
        publicKey: string,
        algorithm: string
    ): Promise<{ sharedSecret: Uint8Array; ciphertext: string }> {
        try {
            const response = await fetch(`${this.apiBaseUrl}/encapsulate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    public_key: publicKey,
                    algorithm,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            return {
                sharedSecret: new Uint8Array(atob(data.shared_secret).split('').map(c => c.charCodeAt(0))),
                ciphertext: data.ciphertext,
            };
        } catch (error) {
            console.error('Key encapsulation failed:', error);
            throw new Error('ML-KEM encapsulation failed');
        }
    }

    /**
     * Perform key decapsulation using ML-KEM
     */
    private async performKeyDecapsulation(
        ciphertext: string,
        algorithm: string
    ): Promise<Uint8Array> {
        try {
            // Get our private key
            const keyPair = this.keyCache.get(`${algorithm}:${this.deviceId}`);
            if (!keyPair) {
                throw new Error('Private key not found');
            }

            const response = await fetch(`${this.apiBaseUrl}/decapsulate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    ciphertext,
                    private_key: keyPair.privateKey,
                    algorithm,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            return new Uint8Array(atob(data.shared_secret).split('').map(c => c.charCodeAt(0)));
        } catch (error) {
            console.error('Key decapsulation failed:', error);
            throw new Error('ML-KEM decapsulation failed');
        }
    }

    /**
     * Encrypt content with AES-GCM using session key
     */
    private async encryptWithSessionKey(
        content: string,
        sessionKey: Uint8Array
    ): Promise<{ ciphertext: string; iv: string; tag: string }> {
        const key = await crypto.subtle.importKey(
            'raw',
            sessionKey,
            { name: 'AES-GCM' },
            false,
            ['encrypt']
        );

        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encodedContent = new TextEncoder().encode(content);

        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            encodedContent
        );

        return {
            ciphertext: btoa(String.fromCharCode(...new Uint8Array(encrypted))),
            iv: btoa(String.fromCharCode(...iv)),
            tag: '', // GCM tag is included in the encrypted data
        };
    }

    /**
     * Decrypt content with AES-GCM using session key
     */
    private async decryptWithSessionKey(
        ciphertext: string,
        iv: string,
        tag: string,
        sessionKey: Uint8Array
    ): Promise<string> {
        const key = await crypto.subtle.importKey(
            'raw',
            sessionKey,
            { name: 'AES-GCM' },
            false,
            ['decrypt']
        );

        const encryptedData = new Uint8Array(atob(ciphertext).split('').map(c => c.charCodeAt(0)));
        const ivData = new Uint8Array(atob(iv).split('').map(c => c.charCodeAt(0)));

        const decrypted = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: ivData },
            key,
            encryptedData
        );

        return new TextDecoder().decode(decrypted);
    }

    /**
     * Encrypt session key with shared secret
     */
    private async encryptSessionKey(
        sessionKey: Uint8Array,
        sharedSecret: Uint8Array
    ): Promise<string> {
        const key = await crypto.subtle.importKey(
            'raw',
            sharedSecret,
            { name: 'AES-GCM' },
            false,
            ['encrypt']
        );

        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            sessionKey
        );

        // Combine IV and encrypted data
        const combined = new Uint8Array(iv.length + encrypted.byteLength);
        combined.set(iv, 0);
        combined.set(new Uint8Array(encrypted), iv.length);

        return btoa(String.fromCharCode(...combined));
    }

    /**
     * Decrypt session key with shared secret
     */
    private async decryptSessionKey(
        encryptedSessionKey: string,
        sharedSecret: Uint8Array
    ): Promise<Uint8Array> {
        const key = await crypto.subtle.importKey(
            'raw',
            sharedSecret,
            { name: 'AES-GCM' },
            false,
            ['decrypt']
        );

        const combined = new Uint8Array(atob(encryptedSessionKey).split('').map(c => c.charCodeAt(0)));
        const iv = combined.slice(0, 12);
        const encryptedData = combined.slice(12);

        const decrypted = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv },
            key,
            encryptedData
        );

        return new Uint8Array(decrypted);
    }

    /**
     * Get conversation recipients
     */
    private async getConversationRecipients(conversationId: string): Promise<Array<{
        id: string;
        publicKey: string;
    }>> {
        try {
            const response = await fetch(`/api/v1/chat/conversations/${conversationId}/recipients`);
            if (!response.ok) {
                throw new Error('Failed to fetch recipients');
            }
            return await response.json();
        } catch (error) {
            console.error('Failed to get recipients:', error);
            return [];
        }
    }

    /**
     * Generate content hash for integrity verification
     */
    private async generateContentHash(content: string): Promise<string> {
        const encoder = new TextEncoder();
        const data = encoder.encode(content);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        return btoa(String.fromCharCode(...new Uint8Array(hashBuffer)));
    }

    /**
     * Verify content hash
     */
    private async verifyContentHash(content: string, expectedHash: string): Promise<boolean> {
        try {
            const actualHash = await this.generateContentHash(content);
            return actualHash === expectedHash;
        } catch {
            return false;
        }
    }

    /**
     * Get security level from algorithm name
     */
    private getSecurityLevel(algorithm: string): number {
        switch (algorithm) {
            case 'ML-KEM-512': return 128;
            case 'ML-KEM-768': return 192;
            case 'ML-KEM-1024': return 256;
            default: return 128;
        }
    }

    /**
     * Get device ID from localStorage or generate new one
     */
    private getDeviceId(): string {
        let deviceId = getUserStorageItem('quantum_device_id');
        if (!deviceId) {
            deviceId = crypto.randomUUID();
            setUserStorageItem('quantum_device_id', deviceId);
        }
        return deviceId;
    }

    /**
     * Detect device type
     */
    private getDeviceType(): string {
        const userAgent = navigator.userAgent;
        if (/Mobile|Android|iPhone|iPad/.test(userAgent)) {
            return 'mobile';
        } else if (/Tablet/.test(userAgent)) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }
}