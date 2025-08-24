/**
 * Optimized E2EE Service
 * Provides performance optimizations for encryption operations including caching, batching, and background processing
 */

import { ChatEncryption } from '@/utils/encryption';
import { secureKeyManager } from '@/lib/SecureKeyManager';
import { e2eePerformanceMonitor } from '@/utils/E2EEPerformanceMonitor';
import type { EncryptedMessageData } from '@/types/chat';

interface CachedKey {
  key: CryptoKey;
  expiresAt: number;
  lastUsed: number;
  hitCount: number;
}

interface BatchOperation {
  id: string;
  operation: 'encrypt' | 'decrypt';
  data: any;
  conversationId: string;
  resolve: (result: any) => void;
  reject: (error: Error) => void;
  timestamp: number;
}

interface MessageChunk {
  index: number;
  data: string;
  total: number;
}

export class OptimizedE2EEService {
  private keyCache = new Map<string, CachedKey>();
  private keyCacheTTL = 5 * 60 * 1000; // 5 minutes
  private maxCacheSize = 100;

  private batchQueue: BatchOperation[] = [];
  private batchSize = 10;
  private batchTimeout = 100; // 100ms
  private batchTimer: NodeJS.Timeout | null = null;

  private workerPool: Worker[] = [];
  private maxWorkers = navigator.hardwareConcurrency || 4;
  private workerEnabled = false;

  private compressionThreshold = 1000; // Compress messages over 1KB
  private chunkThreshold = 10000; // Chunk messages over 10KB

  constructor() {
    this.initializeWorkers();
    this.startCacheCleanup();
  }

  /**
   * Initialize Web Workers for background encryption
   */
  private async initializeWorkers(): Promise<void> {
    if (typeof Worker === 'undefined') {
      console.warn('Web Workers not supported, falling back to main thread');
      return;
    }

    try {
      // Create a simple encryption worker
      const workerScript = `
        self.onmessage = async function(e) {
          const { id, operation, data } = e.data;
          
          try {
            let result;
            
            if (operation === 'encrypt') {
              const { message, key } = data;
              const encoder = new TextEncoder();
              const messageBuffer = encoder.encode(message);
              const iv = crypto.getRandomValues(new Uint8Array(12));
              
              const encryptedBuffer = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv },
                key,
                messageBuffer
              );
              
              result = {
                encryptedData: Array.from(new Uint8Array(encryptedBuffer)),
                iv: Array.from(iv)
              };
            } else if (operation === 'decrypt') {
              const { encryptedData, iv, key } = data;
              const encryptedBuffer = new Uint8Array(encryptedData);
              const ivBuffer = new Uint8Array(iv);
              
              const decryptedBuffer = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: ivBuffer },
                key,
                encryptedBuffer
              );
              
              const decoder = new TextDecoder();
              result = decoder.decode(decryptedBuffer);
            }
            
            self.postMessage({ id, success: true, result });
          } catch (error) {
            self.postMessage({ id, success: false, error: error.message });
          }
        };
      `;

      const workerBlob = new Blob([workerScript], { type: 'application/javascript' });
      const workerUrl = URL.createObjectURL(workerBlob);

      // Create worker pool
      for (let i = 0; i < this.maxWorkers; i++) {
        const worker = new Worker(workerUrl);
        this.workerPool.push(worker);
      }

      this.workerEnabled = true;
      console.log(`E2EE Worker pool initialized with ${this.maxWorkers} workers`);
    } catch (error) {
      console.warn('Failed to initialize E2EE workers:', error);
    }
  }

  /**
   * Get or create cached key
   */
  private async getCachedKey(keyId: string, keyGetter: () => Promise<CryptoKey>): Promise<CryptoKey> {
    const now = Date.now();
    const cached = this.keyCache.get(keyId);

    if (cached && cached.expiresAt > now) {
      cached.lastUsed = now;
      cached.hitCount++;
      return cached.key;
    }

    // Clean expired entries while we're here
    this.cleanExpiredKeys();

    const key = await keyGetter();
    
    // Ensure we don't exceed cache size
    if (this.keyCache.size >= this.maxCacheSize) {
      this.evictOldestKey();
    }

    this.keyCache.set(keyId, {
      key,
      expiresAt: now + this.keyCacheTTL,
      lastUsed: now,
      hitCount: 1
    });

    return key;
  }

  /**
   * Clean expired keys from cache
   */
  private cleanExpiredKeys(): void {
    const now = Date.now();
    for (const [keyId, cached] of this.keyCache.entries()) {
      if (cached.expiresAt <= now) {
        this.keyCache.delete(keyId);
      }
    }
  }

  /**
   * Evict oldest key based on last used time
   */
  private evictOldestKey(): void {
    let oldestKeyId = '';
    let oldestTime = Date.now();

    for (const [keyId, cached] of this.keyCache.entries()) {
      if (cached.lastUsed < oldestTime) {
        oldestTime = cached.lastUsed;
        oldestKeyId = keyId;
      }
    }

    if (oldestKeyId) {
      this.keyCache.delete(oldestKeyId);
    }
  }

  /**
   * Start cache cleanup interval
   */
  private startCacheCleanup(): void {
    setInterval(() => {
      this.cleanExpiredKeys();
    }, 60000); // Clean every minute
  }

  /**
   * Compress message content if needed
   */
  private async compressIfNeeded(content: string): Promise<{ compressed: boolean; data: string }> {
    if (content.length < this.compressionThreshold) {
      return { compressed: false, data: content };
    }

    try {
      const encoder = new TextEncoder();
      const data = encoder.encode(content);
      const compressed = await this.compress(data);
      const compressedBase64 = btoa(String.fromCharCode(...compressed));
      
      // Only use compression if it actually reduces size
      if (compressedBase64.length < content.length * 0.8) {
        return { compressed: true, data: compressedBase64 };
      }
    } catch (error) {
      console.warn('Compression failed:', error);
    }

    return { compressed: false, data: content };
  }

  /**
   * Decompress message content if needed
   */
  private async decompressIfNeeded(data: string, compressed: boolean): Promise<string> {
    if (!compressed) {
      return data;
    }

    try {
      const compressedData = Uint8Array.from(atob(data), c => c.charCodeAt(0));
      const decompressed = await this.decompress(compressedData);
      const decoder = new TextDecoder();
      return decoder.decode(decompressed);
    } catch (error) {
      console.warn('Decompression failed:', error);
      return data;
    }
  }

  /**
   * Simple compression using GZIP-like algorithm
   */
  private async compress(data: Uint8Array): Promise<Uint8Array> {
    const cs = new CompressionStream('gzip');
    const writer = cs.writable.getWriter();
    const reader = cs.readable.getReader();
    
    writer.write(data);
    writer.close();
    
    const chunks: Uint8Array[] = [];
    let done = false;
    
    while (!done) {
      const { value, done: readerDone } = await reader.read();
      done = readerDone;
      if (value) chunks.push(value);
    }
    
    const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    const result = new Uint8Array(totalLength);
    let offset = 0;
    
    for (const chunk of chunks) {
      result.set(chunk, offset);
      offset += chunk.length;
    }
    
    return result;
  }

  /**
   * Simple decompression
   */
  private async decompress(data: Uint8Array): Promise<Uint8Array> {
    const ds = new DecompressionStream('gzip');
    const writer = ds.writable.getWriter();
    const reader = ds.readable.getReader();
    
    writer.write(data);
    writer.close();
    
    const chunks: Uint8Array[] = [];
    let done = false;
    
    while (!done) {
      const { value, done: readerDone } = await reader.read();
      done = readerDone;
      if (value) chunks.push(value);
    }
    
    const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    const result = new Uint8Array(totalLength);
    let offset = 0;
    
    for (const chunk of chunks) {
      result.set(chunk, offset);
      offset += chunk.length;
    }
    
    return result;
  }

  /**
   * Chunk large messages for better performance
   */
  private chunkMessage(content: string): MessageChunk[] {
    if (content.length <= this.chunkThreshold) {
      return [{ index: 0, data: content, total: 1 }];
    }

    const chunks: MessageChunk[] = [];
    const chunkSize = this.chunkThreshold;
    const totalChunks = Math.ceil(content.length / chunkSize);

    for (let i = 0; i < totalChunks; i++) {
      const start = i * chunkSize;
      const end = Math.min(start + chunkSize, content.length);
      chunks.push({
        index: i,
        data: content.substring(start, end),
        total: totalChunks
      });
    }

    return chunks;
  }

  /**
   * Reassemble chunked message
   */
  private reassembleMessage(chunks: MessageChunk[]): string {
    return chunks
      .sort((a, b) => a.index - b.index)
      .map(chunk => chunk.data)
      .join('');
  }

  /**
   * Add operation to batch queue
   */
  private addToBatch(operation: Omit<BatchOperation, 'id' | 'timestamp'>): Promise<any> {
    return new Promise((resolve, reject) => {
      const batchOp: BatchOperation = {
        ...operation,
        id: Math.random().toString(36).substring(2),
        timestamp: Date.now(),
        resolve,
        reject
      };

      this.batchQueue.push(batchOp);

      // Process immediately if batch is full
      if (this.batchQueue.length >= this.batchSize) {
        this.processBatch();
      } else {
        // Set timer for batch processing
        if (!this.batchTimer) {
          this.batchTimer = setTimeout(() => {
            this.processBatch();
          }, this.batchTimeout);
        }
      }
    });
  }

  /**
   * Process batch of operations
   */
  private async processBatch(): Promise<void> {
    if (this.batchTimer) {
      clearTimeout(this.batchTimer);
      this.batchTimer = null;
    }

    if (this.batchQueue.length === 0) return;

    const batch = this.batchQueue.splice(0, this.batchSize);
    const stopTimer = e2eePerformanceMonitor.startOperation(`batch_${batch[0].operation}`);

    try {
      // Group by conversation for better key reuse
      const byConversation = new Map<string, BatchOperation[]>();
      batch.forEach(op => {
        const ops = byConversation.get(op.conversationId) || [];
        ops.push(op);
        byConversation.set(op.conversationId, ops);
      });

      // Process each conversation's operations
      for (const [conversationId, ops] of byConversation) {
        await this.processBatchForConversation(conversationId, ops);
      }

      stopTimer(true, { 
        dataSize: batch.length,
        keyType: 'batch' 
      });
    } catch (error) {
      stopTimer(false, { 
        error: error instanceof Error ? error.message : 'Batch processing failed'
      });
      
      // Reject all operations in the batch
      batch.forEach(op => op.reject(error as Error));
    }
  }

  /**
   * Process batch operations for a specific conversation
   */
  private async processBatchForConversation(conversationId: string, operations: BatchOperation[]): Promise<void> {
    // Get the conversation key once for all operations
    const symmetricKey = await this.getCachedKey(
      `conversation_${conversationId}`,
      async () => {
        const keyData = await secureKeyManager.getConversationKey(conversationId);
        if (!keyData) throw new Error('Conversation key not found');
        
        return await crypto.subtle.importKey(
          'raw',
          new Uint8Array(atob(keyData).split('').map(c => c.charCodeAt(0))),
          { name: 'AES-GCM' },
          false,
          ['encrypt', 'decrypt']
        );
      }
    );

    // Process operations in parallel
    const promises = operations.map(async (op) => {
      try {
        let result;
        
        if (op.operation === 'encrypt') {
          result = await this.performEncryption(op.data, symmetricKey);
        } else {
          result = await this.performDecryption(op.data, symmetricKey);
        }
        
        op.resolve(result);
      } catch (error) {
        op.reject(error as Error);
      }
    });

    await Promise.all(promises);
  }

  /**
   * Perform optimized encryption
   */
  private async performEncryption(message: string, key: CryptoKey): Promise<EncryptedMessageData> {
    // Compress if needed
    const { compressed, data } = await this.compressIfNeeded(message);
    
    // Check if we need to chunk the message
    if (data.length > this.chunkThreshold) {
      const chunks = this.chunkMessage(data);
      const encryptedChunks = await Promise.all(
        chunks.map(chunk => this.encryptChunk(chunk.data, key))
      );
      
      return {
        content: JSON.stringify({
          type: 'chunked',
          chunks: encryptedChunks,
          compressed,
          total: chunks.length
        }),
        iv: encryptedChunks[0].iv, // Use first chunk's IV as reference
        version: '2.0'
      };
    }

    // Standard encryption
    const encoder = new TextEncoder();
    const messageBuffer = encoder.encode(data);
    const iv = crypto.getRandomValues(new Uint8Array(12));

    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      messageBuffer
    );

    return {
      content: btoa(String.fromCharCode(...new Uint8Array(encryptedBuffer))),
      iv: btoa(String.fromCharCode(...iv)),
      version: '2.0',
      compressed
    };
  }

  /**
   * Encrypt a single chunk
   */
  private async encryptChunk(chunk: string, key: CryptoKey): Promise<{ content: string; iv: string }> {
    const encoder = new TextEncoder();
    const chunkBuffer = encoder.encode(chunk);
    const iv = crypto.getRandomValues(new Uint8Array(12));

    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      chunkBuffer
    );

    return {
      content: btoa(String.fromCharCode(...new Uint8Array(encryptedBuffer))),
      iv: btoa(String.fromCharCode(...iv))
    };
  }

  /**
   * Perform optimized decryption
   */
  private async performDecryption(encryptedData: EncryptedMessageData, key: CryptoKey): Promise<string> {
    try {
      // Check if it's chunked data
      const parsedContent = JSON.parse(encryptedData.content);
      if (parsedContent.type === 'chunked') {
        const decryptedChunks = await Promise.all(
          parsedContent.chunks.map((chunk: any) => this.decryptChunk(chunk, key))
        );
        
        const reassembled = decryptedChunks.join('');
        return await this.decompressIfNeeded(reassembled, parsedContent.compressed);
      }
    } catch {
      // Not chunked data, continue with standard decryption
    }

    // Standard decryption
    const encryptedBuffer = new Uint8Array(
      atob(encryptedData.content).split('').map(c => c.charCodeAt(0))
    );
    const iv = new Uint8Array(
      atob(encryptedData.iv).split('').map(c => c.charCodeAt(0))
    );

    const decryptedBuffer = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      key,
      encryptedBuffer
    );

    const decoder = new TextDecoder();
    const decrypted = decoder.decode(decryptedBuffer);
    
    return await this.decompressIfNeeded(decrypted, (encryptedData as any).compressed || false);
  }

  /**
   * Decrypt a single chunk
   */
  private async decryptChunk(chunk: { content: string; iv: string }, key: CryptoKey): Promise<string> {
    const encryptedBuffer = new Uint8Array(
      atob(chunk.content).split('').map(c => c.charCodeAt(0))
    );
    const iv = new Uint8Array(
      atob(chunk.iv).split('').map(c => c.charCodeAt(0))
    );

    const decryptedBuffer = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      key,
      encryptedBuffer
    );

    const decoder = new TextDecoder();
    return decoder.decode(decryptedBuffer);
  }

  // Public API methods

  /**
   * Optimized message encryption
   */
  async encryptMessage(message: string, conversationId: string): Promise<EncryptedMessageData | null> {
    const stopTimer = e2eePerformanceMonitor.startOperation('encrypt_optimized');

    try {
      const result = await this.addToBatch({
        operation: 'encrypt',
        data: message,
        conversationId,
        resolve: () => {},
        reject: () => {}
      });

      stopTimer(true, {
        dataSize: message.length,
        keyType: 'symmetric'
      });

      return result;
    } catch (error) {
      stopTimer(false, {
        error: error instanceof Error ? error.message : 'Encryption failed'
      });
      return null;
    }
  }

  /**
   * Optimized message decryption
   */
  async decryptMessage(encryptedData: EncryptedMessageData, conversationId: string): Promise<string | null> {
    const stopTimer = e2eePerformanceMonitor.startOperation('decrypt_optimized');

    try {
      const result = await this.addToBatch({
        operation: 'decrypt',
        data: encryptedData,
        conversationId,
        resolve: () => {},
        reject: () => {}
      });

      stopTimer(true, {
        dataSize: encryptedData.content.length,
        keyType: 'symmetric'
      });

      return result;
    } catch (error) {
      stopTimer(false, {
        error: error instanceof Error ? error.message : 'Decryption failed'
      });
      return null;
    }
  }

  /**
   * Get cache statistics
   */
  getCacheStats() {
    const stats = {
      size: this.keyCache.size,
      hitRate: 0,
      totalHits: 0,
      entries: [] as Array<{
        keyId: string;
        hitCount: number;
        lastUsed: string;
        expiresAt: string;
      }>
    };

    let totalAccess = 0;
    let totalHits = 0;

    for (const [keyId, cached] of this.keyCache.entries()) {
      totalAccess += cached.hitCount;
      totalHits += cached.hitCount;
      
      stats.entries.push({
        keyId: keyId.substring(0, 16) + '...',
        hitCount: cached.hitCount,
        lastUsed: new Date(cached.lastUsed).toISOString(),
        expiresAt: new Date(cached.expiresAt).toISOString()
      });
    }

    stats.totalHits = totalHits;
    stats.hitRate = totalAccess > 0 ? (totalHits / totalAccess) * 100 : 0;

    return stats;
  }

  /**
   * Clear all caches
   */
  clearCaches(): void {
    this.keyCache.clear();
  }

  /**
   * Configure optimization settings
   */
  configure(settings: {
    keyCacheTTL?: number;
    maxCacheSize?: number;
    batchSize?: number;
    batchTimeout?: number;
    compressionThreshold?: number;
    chunkThreshold?: number;
  }): void {
    if (settings.keyCacheTTL !== undefined) this.keyCacheTTL = settings.keyCacheTTL;
    if (settings.maxCacheSize !== undefined) this.maxCacheSize = settings.maxCacheSize;
    if (settings.batchSize !== undefined) this.batchSize = settings.batchSize;
    if (settings.batchTimeout !== undefined) this.batchTimeout = settings.batchTimeout;
    if (settings.compressionThreshold !== undefined) this.compressionThreshold = settings.compressionThreshold;
    if (settings.chunkThreshold !== undefined) this.chunkThreshold = settings.chunkThreshold;
  }
}

// Global optimized E2EE service instance
export const optimizedE2EEService = new OptimizedE2EEService();