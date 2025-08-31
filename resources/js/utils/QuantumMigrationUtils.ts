/**
 * Quantum Migration Utilities
 * Provides utilities for migrating from classical to quantum-resistant encryption
 */

import { optimizedE2EEService } from '@/services/OptimizedE2EEService';
import { QuantumE2EEService } from '@/services/QuantumE2EEService';
import { apiService } from '@/services/ApiService';
import { toast } from 'sonner';

export interface MigrationProgress {
  phase: 'assessment' | 'preparation' | 'migration' | 'verification' | 'completed' | 'failed';
  currentStep: number;
  totalSteps: number;
  stepDescription: string;
  progress: number; // 0-100
  startedAt: Date;
  estimatedCompletion?: Date;
  errors: string[];
  warnings: string[];
}

export interface MigrationAssessment {
  totalConversations: number;
  totalMessages: number;
  quantumReadyDevices: number;
  totalDevices: number;
  compatibilityIssues: Array<{
    type: 'device' | 'algorithm' | 'version';
    severity: 'low' | 'medium' | 'high' | 'critical';
    description: string;
    affectedItems: string[];
    solution: string;
  }>;
  recommendedStrategy: 'immediate' | 'gradual' | 'hybrid' | 'delayed';
  estimatedDuration: number; // in minutes
  riskLevel: 'low' | 'medium' | 'high';
}

export interface MigrationReport {
  id: string;
  startedAt: Date;
  completedAt: Date | null;
  status: 'in_progress' | 'completed' | 'failed' | 'cancelled';
  strategy: string;
  progress: MigrationProgress;
  assessment: MigrationAssessment;
  results: {
    conversationsMigrated: number;
    messagesMigrated: number;
    devicesUpgraded: number;
    algorithmsUpgraded: Record<string, number>;
    errors: Array<{
      timestamp: Date;
      type: string;
      message: string;
      context?: any;
    }>;
  };
}

export class QuantumMigrationUtils {
  private static instance: QuantumMigrationUtils;
  private quantumService: QuantumE2EEService;
  private currentMigration: MigrationReport | null = null;

  private constructor() {
    this.quantumService = QuantumE2EEService.getInstance();
  }

  public static getInstance(): QuantumMigrationUtils {
    if (!QuantumMigrationUtils.instance) {
      QuantumMigrationUtils.instance = new QuantumMigrationUtils();
    }
    return QuantumMigrationUtils.instance;
  }

  /**
   * Assess system readiness for quantum migration
   */
  async assessMigrationReadiness(): Promise<MigrationAssessment> {
    try {
      const [conversations, devices, healthStatus] = await Promise.all([
        this.getConversationStats(),
        this.getDeviceStats(),
        this.quantumService.checkSystemHealth()
      ]);

      const compatibilityIssues = await this.identifyCompatibilityIssues(devices);
      const riskLevel = this.calculateRiskLevel(compatibilityIssues, devices);
      const recommendedStrategy = this.determineStrategy(devices, compatibilityIssues);
      const estimatedDuration = this.estimateMigrationDuration(conversations, devices);

      return {
        totalConversations: conversations.total,
        totalMessages: conversations.totalMessages,
        quantumReadyDevices: devices.quantumReady,
        totalDevices: devices.total,
        compatibilityIssues,
        recommendedStrategy,
        estimatedDuration,
        riskLevel
      };
    } catch (error) {
      console.error('Migration assessment failed:', error);
      throw new Error('Failed to assess migration readiness');
    }
  }

  /**
   * Start quantum migration process
   */
  async startMigration(strategy: 'immediate' | 'gradual' | 'hybrid' = 'gradual'): Promise<string> {
    try {
      if (this.currentMigration && this.currentMigration.status === 'in_progress') {
        throw new Error('Migration already in progress');
      }

      const assessment = await this.assessMigrationReadiness();
      
      const migrationId = `migration_${Date.now()}_${Math.random().toString(36).substring(2)}`;
      
      this.currentMigration = {
        id: migrationId,
        startedAt: new Date(),
        completedAt: null,
        status: 'in_progress',
        strategy,
        progress: {
          phase: 'assessment',
          currentStep: 0,
          totalSteps: this.getTotalSteps(strategy),
          stepDescription: 'Starting migration assessment',
          progress: 0,
          startedAt: new Date(),
          errors: [],
          warnings: []
        },
        assessment,
        results: {
          conversationsMigrated: 0,
          messagesMigrated: 0,
          devicesUpgraded: 0,
          algorithmsUpgraded: {},
          errors: []
        }
      };

      // Start migration process
      this.executeMigration(strategy).catch(error => {
        this.handleMigrationError(error);
      });

      toast.success('Quantum migration started');
      return migrationId;
    } catch (error) {
      console.error('Failed to start migration:', error);
      throw error;
    }
  }

  /**
   * Get current migration status
   */
  getMigrationStatus(): MigrationReport | null {
    return this.currentMigration;
  }

  /**
   * Cancel ongoing migration
   */
  async cancelMigration(reason?: string): Promise<void> {
    if (!this.currentMigration || this.currentMigration.status !== 'in_progress') {
      throw new Error('No migration in progress');
    }

    this.currentMigration.status = 'cancelled';
    this.currentMigration.completedAt = new Date();
    this.currentMigration.results.errors.push({
      timestamp: new Date(),
      type: 'cancellation',
      message: reason || 'Migration cancelled by user',
    });

    toast.info('Migration cancelled');
  }

  /**
   * Execute migration process
   */
  private async executeMigration(strategy: string): Promise<void> {
    try {
      if (!this.currentMigration) return;

      // Phase 1: Preparation
      this.updateProgress('preparation', 1, 'Preparing devices for migration');
      await this.prepareDevicesForMigration();

      // Phase 2: Migration
      this.updateProgress('migration', 2, 'Migrating encryption algorithms');
      
      switch (strategy) {
        case 'immediate':
          await this.executeImmediateMigration();
          break;
        case 'gradual':
          await this.executeGradualMigration();
          break;
        case 'hybrid':
          await this.executeHybridMigration();
          break;
      }

      // Phase 3: Verification
      this.updateProgress('verification', this.getTotalSteps(strategy) - 1, 'Verifying migration results');
      await this.verifyMigration();

      // Complete migration
      this.currentMigration.status = 'completed';
      this.currentMigration.completedAt = new Date();
      this.updateProgress('completed', this.getTotalSteps(strategy), 'Migration completed successfully');
      
      toast.success('Quantum migration completed successfully');
    } catch (error) {
      this.handleMigrationError(error);
    }
  }

  /**
   * Prepare devices for migration
   */
  private async prepareDevicesForMigration(): Promise<void> {
    const devices = await this.quantumService.getQuantumCapableDevices();
    
    for (const device of devices) {
      if (!device.quantumReady) {
        try {
          await this.quantumService.migrateDeviceToQuantum(device.deviceId);
          this.currentMigration!.results.devicesUpgraded++;
        } catch (error) {
          this.currentMigration!.progress.warnings.push(
            `Failed to upgrade device ${device.deviceName}: ${error}`
          );
        }
      }
    }
  }

  /**
   * Execute immediate migration strategy
   */
  private async executeImmediateMigration(): Promise<void> {
    const conversations = await this.getAllConversations();
    
    for (const conversation of conversations) {
      this.updateProgress('migration', 2, `Migrating conversation: ${conversation.name}`);
      
      try {
        await optimizedE2EEService.upgradeConversationEncryption(
          conversation.id, 
          'ML-KEM-768'
        );
        this.currentMigration!.results.conversationsMigrated++;
        
        // Update algorithm count
        const algorithm = 'ML-KEM-768';
        this.currentMigration!.results.algorithmsUpgraded[algorithm] = 
          (this.currentMigration!.results.algorithmsUpgraded[algorithm] || 0) + 1;
      } catch (error) {
        this.addMigrationError('conversation_migration', error, { conversationId: conversation.id });
      }
    }
  }

  /**
   * Execute gradual migration strategy
   */
  private async executeGradualMigration(): Promise<void> {
    const conversations = await this.getAllConversations();
    const batchSize = 5;
    
    for (let i = 0; i < conversations.length; i += batchSize) {
      const batch = conversations.slice(i, i + batchSize);
      const progress = Math.round((i / conversations.length) * 100);
      
      this.updateProgress('migration', 2, `Migrating batch ${Math.ceil(i/batchSize) + 1} of ${Math.ceil(conversations.length/batchSize)}`, progress);
      
      await Promise.all(
        batch.map(async (conversation) => {
          try {
            await optimizedE2EEService.upgradeConversationEncryption(
              conversation.id,
              'ML-KEM-768'
            );
            this.currentMigration!.results.conversationsMigrated++;
          } catch (error) {
            this.addMigrationError('conversation_migration', error, { conversationId: conversation.id });
          }
        })
      );

      // Small delay between batches
      await new Promise(resolve => setTimeout(resolve, 1000));
    }
  }

  /**
   * Execute hybrid migration strategy
   */
  private async executeHybridMigration(): Promise<void> {
    const conversations = await this.getAllConversations();
    
    for (const conversation of conversations) {
      this.updateProgress('migration', 2, `Migrating conversation: ${conversation.name}`);
      
      try {
        // Use hybrid algorithm for transition period
        await optimizedE2EEService.upgradeConversationEncryption(
          conversation.id,
          'HYBRID-RSA4096-MLKEM768'
        );
        this.currentMigration!.results.conversationsMigrated++;
      } catch (error) {
        this.addMigrationError('conversation_migration', error, { conversationId: conversation.id });
      }
    }
  }

  /**
   * Verify migration results
   */
  private async verifyMigration(): Promise<void> {
    const healthStatus = await this.quantumService.checkSystemHealth();
    
    if (!healthStatus.quantum_support.ml_kem_available) {
      throw new Error('Quantum support verification failed');
    }

    // Verify a sample of conversations
    const conversations = await this.getAllConversations();
    const sampleSize = Math.min(5, conversations.length);
    const sample = conversations.slice(0, sampleSize);
    
    for (const conversation of sample) {
      try {
        const settings = await this.quantumService.getConversationQuantumSettings(conversation.id);
        if (!settings.quantumResistant) {
          this.currentMigration!.progress.warnings.push(
            `Conversation ${conversation.name} not quantum-resistant after migration`
          );
        }
      } catch (error) {
        this.addMigrationError('verification', error, { conversationId: conversation.id });
      }
    }
  }

  // Utility methods

  private async getConversationStats() {
    const response = await apiService.get<any>('/api/chat/conversations/stats');
    return response.data;
  }

  private async getDeviceStats() {
    const devices = await this.quantumService.getQuantumCapableDevices();
    return {
      total: devices.length,
      quantumReady: devices.filter(d => d.quantumReady).length
    };
  }

  private async getAllConversations() {
    const response = await apiService.get<any>('/api/chat/conversations');
    return response.data || [];
  }

  private async identifyCompatibilityIssues(devices: any) {
    const issues = [];
    
    const legacyDevices = devices.total - devices.quantumReady;
    if (legacyDevices > 0) {
      issues.push({
        type: 'device',
        severity: legacyDevices > devices.total * 0.5 ? 'high' : 'medium',
        description: `${legacyDevices} devices need quantum upgrade`,
        affectedItems: [`${legacyDevices} devices`],
        solution: 'Upgrade devices to support quantum algorithms'
      });
    }

    return issues;
  }

  private calculateRiskLevel(issues: any[], devices: any): 'low' | 'medium' | 'high' {
    const criticalIssues = issues.filter(i => i.severity === 'critical').length;
    const highIssues = issues.filter(i => i.severity === 'high').length;
    
    if (criticalIssues > 0) return 'high';
    if (highIssues > 1) return 'high';
    if (devices.quantumReady / devices.total < 0.5) return 'medium';
    return 'low';
  }

  private determineStrategy(devices: any, issues: any[]): 'immediate' | 'gradual' | 'hybrid' | 'delayed' {
    if (devices.quantumReady === devices.total) return 'immediate';
    if (devices.quantumReady / devices.total > 0.7) return 'gradual';
    if (devices.quantumReady / devices.total > 0.3) return 'hybrid';
    return 'delayed';
  }

  private estimateMigrationDuration(conversations: any, devices: any): number {
    const baseTime = 5; // 5 minutes base
    const conversationTime = conversations.total * 0.5; // 30 seconds per conversation
    const deviceTime = (devices.total - devices.quantumReady) * 2; // 2 minutes per device
    
    return Math.ceil(baseTime + conversationTime + deviceTime);
  }

  private getTotalSteps(strategy: string): number {
    switch (strategy) {
      case 'immediate': return 4;
      case 'gradual': return 6;
      case 'hybrid': return 5;
      default: return 4;
    }
  }

  private updateProgress(phase: MigrationProgress['phase'], step: number, description: string, progress?: number): void {
    if (!this.currentMigration) return;
    
    this.currentMigration.progress.phase = phase;
    this.currentMigration.progress.currentStep = step;
    this.currentMigration.progress.stepDescription = description;
    this.currentMigration.progress.progress = progress || Math.round((step / this.currentMigration.progress.totalSteps) * 100);
  }

  private addMigrationError(type: string, error: any, context?: any): void {
    if (!this.currentMigration) return;
    
    this.currentMigration.results.errors.push({
      timestamp: new Date(),
      type,
      message: error instanceof Error ? error.message : String(error),
      context
    });
  }

  private handleMigrationError(error: any): void {
    if (!this.currentMigration) return;
    
    this.currentMigration.status = 'failed';
    this.currentMigration.completedAt = new Date();
    this.addMigrationError('migration_failure', error);
    
    toast.error('Migration failed: ' + (error instanceof Error ? error.message : String(error)));
  }
}

// Export singleton instance
export const quantumMigrationUtils = QuantumMigrationUtils.getInstance();