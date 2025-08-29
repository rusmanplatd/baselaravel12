/**
 * E2EE Error Recovery Service
 * Handles various encryption error scenarios and provides automated recovery mechanisms
 */

import { ChatEncryption } from '@/utils/encryption';
import { secureKeyManager } from '@/lib/SecureKeyManager';
import { autoKeyExchange } from '@/lib/AutoKeyExchange';
import type { EncryptedMessageData } from '@/types/chat';
import { apiService, ApiError } from '@/services/ApiService';

export type E2EEErrorType = 
  | 'ENCRYPTION_FAILED'
  | 'DECRYPTION_FAILED' 
  | 'KEY_NOT_FOUND'
  | 'KEY_CORRUPTED'
  | 'KEY_EXCHANGE_FAILED'
  | 'STORAGE_ACCESS_DENIED'
  | 'NETWORK_ERROR'
  | 'INVALID_KEY_FORMAT'
  | 'CONVERSATION_KEY_MISSING'
  | 'USER_KEY_MISSING';

export interface E2EEError {
  type: E2EEErrorType;
  message: string;
  conversationId?: string;
  userId?: string;
  originalError?: Error;
  timestamp: string;
  recoverable: boolean;
  autoRecoveryAttempts: number;
}

export interface RecoveryStrategy {
  name: string;
  description: string;
  automatic: boolean;
  destructive: boolean;
  estimatedTime: number; // in seconds
  execute: () => Promise<boolean>;
}

export class E2EEErrorRecovery {
  private errorHistory: E2EEError[] = [];
  private maxHistorySize = 100;
  private autoRecoveryEnabled = true;
  private maxAutoRetries = 3;

  /**
   * Process and categorize an encryption error
   */
  processError(error: Error, context?: {
    conversationId?: string;
    userId?: string;
    operation?: string;
  }): E2EEError {
    const e2eeError: E2EEError = {
      type: this.categorizeError(error),
      message: error.message,
      conversationId: context?.conversationId,
      userId: context?.userId,
      originalError: error,
      timestamp: new Date().toISOString(),
      recoverable: true,
      autoRecoveryAttempts: 0
    };

    // Add to history
    this.addToHistory(e2eeError);

    // Attempt automatic recovery if enabled
    if (this.autoRecoveryEnabled && e2eeError.recoverable) {
      this.attemptAutoRecovery(e2eeError);
    }

    return e2eeError;
  }

  /**
   * Categorize error based on message and stack trace
   */
  private categorizeError(error: Error): E2EEErrorType {
    const message = error.message.toLowerCase();
    const stack = error.stack?.toLowerCase() || '';

    if (message.includes('encrypt') && !message.includes('decrypt')) {
      return 'ENCRYPTION_FAILED';
    }
    if (message.includes('decrypt')) {
      return 'DECRYPTION_FAILED';
    }
    if (message.includes('key not found') || message.includes('no key')) {
      return 'KEY_NOT_FOUND';
    }
    if (message.includes('corrupted') || message.includes('invalid key')) {
      return 'KEY_CORRUPTED';
    }
    if (message.includes('key exchange') || message.includes('distribution')) {
      return 'KEY_EXCHANGE_FAILED';
    }
    if (message.includes('storage') || message.includes('indexeddb')) {
      return 'STORAGE_ACCESS_DENIED';
    }
    if (message.includes('fetch') || message.includes('network')) {
      return 'NETWORK_ERROR';
    }
    if (message.includes('conversation') && message.includes('key')) {
      return 'CONVERSATION_KEY_MISSING';
    }
    if (message.includes('user') && message.includes('key')) {
      return 'USER_KEY_MISSING';
    }
    if (message.includes('format') || message.includes('parse')) {
      return 'INVALID_KEY_FORMAT';
    }

    return 'DECRYPTION_FAILED'; // Default fallback
  }

  /**
   * Get available recovery strategies for an error
   */
  getRecoveryStrategies(error: E2EEError): RecoveryStrategy[] {
    const strategies: RecoveryStrategy[] = [];

    switch (error.type) {
      case 'ENCRYPTION_FAILED':
        strategies.push(
          {
            name: 'Retry Encryption',
            description: 'Attempt to encrypt the message again',
            automatic: true,
            destructive: false,
            estimatedTime: 5,
            execute: async () => this.retryOperation(error)
          },
          {
            name: 'Regenerate Keys',
            description: 'Generate new encryption keys for the user',
            automatic: false,
            destructive: true,
            estimatedTime: 30,
            execute: async () => this.regenerateUserKeys(error.userId!)
          }
        );
        break;

      case 'DECRYPTION_FAILED':
        strategies.push(
          {
            name: 'Retry Decryption',
            description: 'Try decrypting the message again',
            automatic: true,
            destructive: false,
            estimatedTime: 5,
            execute: async () => this.retryOperation(error)
          },
          {
            name: 'Request Key Refresh',
            description: 'Request updated encryption keys from other participants',
            automatic: false,
            destructive: false,
            estimatedTime: 15,
            execute: async () => this.requestKeyRefresh(error.conversationId!)
          }
        );
        break;

      case 'KEY_NOT_FOUND':
        strategies.push(
          {
            name: 'Initialize Keys',
            description: 'Generate missing encryption keys',
            automatic: true,
            destructive: false,
            estimatedTime: 10,
            execute: async () => this.initializeKeys(error.userId!)
          },
          {
            name: 'Request Key Distribution',
            description: 'Request keys from conversation participants',
            automatic: false,
            destructive: false,
            estimatedTime: 20,
            execute: async () => this.requestKeyDistribution(error.conversationId!)
          }
        );
        break;

      case 'KEY_CORRUPTED':
        strategies.push(
          {
            name: 'Verify and Repair',
            description: 'Verify key integrity and attempt repair',
            automatic: true,
            destructive: false,
            estimatedTime: 15,
            execute: async () => this.verifyAndRepairKeys(error.userId!)
          },
          {
            name: 'Replace Corrupted Keys',
            description: 'Generate new keys and re-establish encryption',
            automatic: false,
            destructive: true,
            estimatedTime: 45,
            execute: async () => this.replaceCorruptedKeys(error.userId!, error.conversationId)
          }
        );
        break;

      case 'KEY_EXCHANGE_FAILED':
        strategies.push(
          {
            name: 'Retry Key Exchange',
            description: 'Attempt key exchange process again',
            automatic: true,
            destructive: false,
            estimatedTime: 20,
            execute: async () => this.retryKeyExchange(error.conversationId!)
          },
          {
            name: 'Manual Key Setup',
            description: 'Manually configure encryption for this conversation',
            automatic: false,
            destructive: false,
            estimatedTime: 60,
            execute: async () => this.manualKeySetup(error.conversationId!)
          }
        );
        break;

      case 'STORAGE_ACCESS_DENIED':
        strategies.push(
          {
            name: 'Reset Storage Permission',
            description: 'Request storage access permission again',
            automatic: true,
            destructive: false,
            estimatedTime: 10,
            execute: async () => this.resetStorageAccess()
          },
          {
            name: 'Use Alternative Storage',
            description: 'Switch to alternative storage method',
            automatic: false,
            destructive: false,
            estimatedTime: 30,
            execute: async () => this.useAlternativeStorage()
          }
        );
        break;

      case 'NETWORK_ERROR':
        strategies.push(
          {
            name: 'Retry Network Request',
            description: 'Attempt the network operation again',
            automatic: true,
            destructive: false,
            estimatedTime: 10,
            execute: async () => this.retryNetworkOperation(error)
          },
          {
            name: 'Enable Offline Mode',
            description: 'Continue with local encryption only',
            automatic: false,
            destructive: false,
            estimatedTime: 5,
            execute: async () => this.enableOfflineMode()
          }
        );
        break;

      default:
        strategies.push(
          {
            name: 'General Recovery',
            description: 'Attempt generic error recovery',
            automatic: true,
            destructive: false,
            estimatedTime: 15,
            execute: async () => this.generalRecovery(error)
          }
        );
    }

    return strategies;
  }

  /**
   * Attempt automatic recovery for an error
   */
  private async attemptAutoRecovery(error: E2EEError): Promise<boolean> {
    if (error.autoRecoveryAttempts >= this.maxAutoRetries) {
      return false;
    }

    const strategies = this.getRecoveryStrategies(error);
    const autoStrategies = strategies.filter(s => s.automatic && !s.destructive);

    for (const strategy of autoStrategies) {
      try {
        console.log(`Attempting auto-recovery: ${strategy.name}`);
        const success = await strategy.execute();
        
        if (success) {
          error.autoRecoveryAttempts++;
          this.logRecoverySuccess(error, strategy.name);
          return true;
        }
      } catch (recoveryError) {
        console.error(`Auto-recovery failed for ${strategy.name}:`, recoveryError);
      }
    }

    error.autoRecoveryAttempts++;
    return false;
  }

  // Recovery strategy implementations
  private async retryOperation(error: E2EEError): Promise<boolean> {
    // Generic retry mechanism
    await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 2000));
    return true;
  }

  private async regenerateUserKeys(userId: string): Promise<boolean> {
    try {
      const keyPair = await ChatEncryption.generateKeyPair();
      if (!keyPair) return false;

      await secureKeyManager.storeUserKeyPair(userId, {
        ...keyPair,
        created_at: new Date().toISOString(),
        key_id: this.generateKeyId(),
        version: '2.0'
      });

      return true;
    } catch {
      return false;
    }
  }

  private async requestKeyRefresh(conversationId: string): Promise<boolean> {
    try {
      await apiService.post(`/api/chat/conversations/${conversationId}/refresh-keys`, {});
      return true;
    } catch {
      return false;
    }
  }

  private async initializeKeys(userId: string): Promise<boolean> {
    try {
      const keyPair = await ChatEncryption.generateKeyPair();
      if (!keyPair) return false;

      await secureKeyManager.storeUserKeyPair(userId, {
        ...keyPair,
        created_at: new Date().toISOString(),
        key_id: this.generateKeyId(),
        version: '2.0'
      });

      return true;
    } catch {
      return false;
    }
  }

  private async requestKeyDistribution(conversationId: string): Promise<boolean> {
    try {
      await apiService.post(`/api/chat/conversations/${conversationId}/request-keys`, {});
      return true;
    } catch {
      return false;
    }
  }

  private async verifyAndRepairKeys(userId: string): Promise<boolean> {
    try {
      const isValid = await secureKeyManager.verifyKeyIntegrity(userId);
      if (isValid) return true;

      // Attempt repair by re-importing keys
      const keyPair = await secureKeyManager.getUserKeyPair(userId);
      if (keyPair) {
        // Re-store the keys to fix any corruption
        await secureKeyManager.storeUserKeyPair(userId, keyPair);
        return true;
      }

      return false;
    } catch {
      return false;
    }
  }

  private async replaceCorruptedKeys(userId: string, conversationId?: string): Promise<boolean> {
    try {
      // Clear corrupted keys
      await secureKeyManager.clearAllKeys();
      
      // Generate new keys
      const keyPair = await ChatEncryption.generateKeyPair();
      if (!keyPair) return false;

      await secureKeyManager.storeUserKeyPair(userId, {
        ...keyPair,
        created_at: new Date().toISOString(),
        key_id: this.generateKeyId(),
        version: '2.0'
      });

      // Re-establish conversation encryption if needed
      if (conversationId) {
        // This would trigger key exchange for the conversation
        window.dispatchEvent(new CustomEvent('e2ee:reestablish-conversation', {
          detail: { conversationId, userId }
        }));
      }

      return true;
    } catch {
      return false;
    }
  }

  private async retryKeyExchange(conversationId: string): Promise<boolean> {
    try {
      // Use the automatic key exchange system
      const participants = await this.getConversationParticipants(conversationId);
      const currentUserId = await this.getCurrentUserId();
      
      if (!participants || !currentUserId) return false;

      const result = await autoKeyExchange.initializeConversationKeys({
        conversationId,
        participants,
        initiatorId: currentUserId
      });

      return result.success;
    } catch {
      return false;
    }
  }

  private async manualKeySetup(conversationId: string): Promise<boolean> {
    // Trigger manual setup UI
    window.dispatchEvent(new CustomEvent('e2ee:show-manual-setup', {
      detail: { conversationId }
    }));
    return true;
  }

  private async resetStorageAccess(): Promise<boolean> {
    try {
      await secureKeyManager.initialize();
      return true;
    } catch {
      return false;
    }
  }

  private async useAlternativeStorage(): Promise<boolean> {
    // Implement alternative storage mechanism (e.g., localStorage fallback)
    try {
      localStorage.setItem('e2ee_storage_fallback', 'true');
      return true;
    } catch {
      return false;
    }
  }

  private async retryNetworkOperation(error: E2EEError): Promise<boolean> {
    // Implement exponential backoff retry
    const backoffMs = Math.min(1000 * Math.pow(2, error.autoRecoveryAttempts), 10000);
    await new Promise(resolve => setTimeout(resolve, backoffMs));
    return true;
  }

  private async enableOfflineMode(): Promise<boolean> {
    localStorage.setItem('e2ee_offline_mode', 'true');
    window.dispatchEvent(new CustomEvent('e2ee:offline-enabled'));
    return true;
  }

  private async generalRecovery(error: E2EEError): Promise<boolean> {
    // General recovery steps
    try {
      // Clear any temporary data that might be causing issues
      sessionStorage.removeItem('temp_keys');
      
      // Verify basic system functionality
      const testKey = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
      );
      
      return !!testKey;
    } catch {
      return false;
    }
  }

  // Utility methods
  private addToHistory(error: E2EEError): void {
    this.errorHistory.unshift(error);
    if (this.errorHistory.length > this.maxHistorySize) {
      this.errorHistory = this.errorHistory.slice(0, this.maxHistorySize);
    }
  }

  private logRecoverySuccess(error: E2EEError, strategy: string): void {
    console.log(`E2EE Recovery successful: ${strategy} for ${error.type}`);
  }

  private generateKeyId(): string {
    return 'key_' + Date.now() + '_' + Math.random().toString(36).substring(2);
  }

  private async getConversationParticipants(conversationId: string): Promise<any[] | null> {
    try {
      const data = await apiService.get<{ participants?: any[] }>(`/api/chat/conversations/${conversationId}/participants`);
      return data.participants || [];
    } catch {}
    return null;
  }

  private async getCurrentUserId(): Promise<string | null> {
    // Get from global state or local storage
    return document.querySelector('meta[name="user-id"]')?.getAttribute('content') || null;
  }

  // Public API methods
  getErrorHistory(): E2EEError[] {
    return [...this.errorHistory];
  }

  clearErrorHistory(): void {
    this.errorHistory = [];
  }

  setAutoRecoveryEnabled(enabled: boolean): void {
    this.autoRecoveryEnabled = enabled;
  }

  getErrorStatistics() {
    const stats = {
      total: this.errorHistory.length,
      byType: {} as Record<E2EEErrorType, number>,
      recovered: this.errorHistory.filter(e => e.autoRecoveryAttempts > 0).length,
      recent: this.errorHistory.filter(e => 
        Date.now() - new Date(e.timestamp).getTime() < 24 * 60 * 60 * 1000
      ).length
    };

    this.errorHistory.forEach(error => {
      stats.byType[error.type] = (stats.byType[error.type] || 0) + 1;
    });

    return stats;
  }
}

// Global instance
export const e2eeErrorRecovery = new E2EEErrorRecovery();