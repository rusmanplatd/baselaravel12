/**
 * Signal Protocol Implementation Example
 * Demonstrates complete Signal Protocol flow with algorithm negotiation and fallback
 */

import { x3dhKeyAgreement } from '../services/X3DHKeyAgreement';
import { signalSessionManager } from '../services/SignalSessionManager';
import { doubleRatchetE2EE } from '../services/DoubleRatchetE2EE';

interface DeviceScenario {
  deviceName: string;
  capabilities: string[];
  quantumSupport: boolean;
}

/**
 * Example demonstrating different device scenarios and algorithm negotiation
 */
export class SignalProtocolExample {
  
  /**
   * Example 1: Modern quantum-capable devices
   * Both devices support quantum algorithms - should negotiate ML-KEM-768
   */
  async modernDevicesExample() {
    console.log('\n🔬 Example 1: Modern Quantum-Capable Devices');
    console.log('=============================================');
    
    const alice: DeviceScenario = {
      deviceName: 'Alice iPhone 15',
      capabilities: ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512', 'Curve25519', 'P-256'],
      quantumSupport: true
    };
    
    const bob: DeviceScenario = {
      deviceName: 'Bob Pixel 8',
      capabilities: ['ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'Curve25519'],
      quantumSupport: true
    };
    
    console.log(`Alice capabilities: ${alice.capabilities.join(', ')}`);
    console.log(`Bob capabilities: ${bob.capabilities.join(', ')}`);
    
    const result = this.simulateNegotiation(alice.capabilities, bob.capabilities);
    console.log(`✅ Negotiated: ${result.selectedAlgorithm} (${result.algorithmType})`);
    console.log(`   Security Level: ${result.securityLevel}-bit`);
    console.log(`   Quantum Resistant: ${result.isQuantumResistant}`);
    console.log(`   Fallback Used: ${result.fallbackUsed}`);
    
    return result;
  }
  
  /**
   * Example 2: Mixed device capabilities
   * One modern device, one legacy - should fallback gracefully
   */
  async mixedDevicesExample() {
    console.log('\n📱 Example 2: Mixed Device Capabilities');
    console.log('======================================');
    
    const alice: DeviceScenario = {
      deviceName: 'Alice iPhone 15 Pro',
      capabilities: ['ML-KEM-1024', 'ML-KEM-768', 'Curve25519', 'P-256', 'RSA-4096-OAEP'],
      quantumSupport: true
    };
    
    const bob: DeviceScenario = {
      deviceName: 'Bob iPhone 12',
      capabilities: ['Curve25519', 'P-256', 'RSA-4096-OAEP', 'RSA-2048-OAEP'],
      quantumSupport: false
    };
    
    console.log(`Alice capabilities: ${alice.capabilities.join(', ')}`);
    console.log(`Bob capabilities: ${bob.capabilities.join(', ')}`);
    
    const result = this.simulateNegotiation(alice.capabilities, bob.capabilities);
    console.log(`✅ Negotiated: ${result.selectedAlgorithm} (${result.algorithmType})`);
    console.log(`   Security Level: ${result.securityLevel}-bit`);
    console.log(`   Quantum Resistant: ${result.isQuantumResistant}`);
    console.log(`   Fallback Used: ${result.fallbackUsed}`);
    console.log(`   Reason: ${result.negotiationReason}`);
    
    return result;
  }
  
  /**
   * Example 3: Legacy devices only
   * Old devices with minimal capabilities - should use Signal Protocol standard
   */
  async legacyDevicesExample() {
    console.log('\n📟 Example 3: Legacy Devices Only');
    console.log('=================================');
    
    const alice: DeviceScenario = {
      deviceName: 'Alice Android 8',
      capabilities: ['P-256', 'RSA-4096-OAEP', 'RSA-2048-OAEP'],
      quantumSupport: false
    };
    
    const bob: DeviceScenario = {
      deviceName: 'Bob iPhone 8',
      capabilities: ['Curve25519', 'P-256', 'RSA-2048-OAEP'],
      quantumSupport: false
    };
    
    console.log(`Alice capabilities: ${alice.capabilities.join(', ')}`);
    console.log(`Bob capabilities: ${bob.capabilities.join(', ')}`);
    
    const result = this.simulateNegotiation(alice.capabilities, bob.capabilities);
    console.log(`✅ Negotiated: ${result.selectedAlgorithm} (${result.algorithmType})`);
    console.log(`   Security Level: ${result.securityLevel}-bit`);
    console.log(`   Quantum Resistant: ${result.isQuantumResistant}`);
    console.log(`   Note: Uses Signal Protocol standard algorithms`);
    
    return result;
  }
  
  /**
   * Example 4: Transition scenario
   * Shows hybrid algorithm usage during quantum transition period
   */
  async transitionExample() {
    console.log('\n🌉 Example 4: Quantum Transition Scenario');
    console.log('========================================');
    
    const alice: DeviceScenario = {
      deviceName: 'Alice Work Phone',
      capabilities: ['HYBRID-RSA4096-MLKEM768', 'ML-KEM-512', 'Curve25519', 'RSA-4096-OAEP'],
      quantumSupport: true
    };
    
    const bob: DeviceScenario = {
      deviceName: 'Bob Enterprise Device',
      capabilities: ['HYBRID-RSA4096-MLKEM768', 'Curve25519', 'P-256', 'RSA-4096-OAEP'],
      quantumSupport: true
    };
    
    console.log(`Alice capabilities: ${alice.capabilities.join(', ')}`);
    console.log(`Bob capabilities: ${bob.capabilities.join(', ')}`);
    
    const result = this.simulateNegotiation(alice.capabilities, bob.capabilities);
    console.log(`✅ Negotiated: ${result.selectedAlgorithm} (${result.algorithmType})`);
    console.log(`   Security Level: ${result.securityLevel}-bit`);
    console.log(`   Quantum Resistant: ${result.isQuantumResistant}`);
    console.log(`   Note: Hybrid algorithm provides quantum security with classical compatibility`);
    
    return result;
  }
  
  /**
   * Example 5: Complete Signal Protocol flow
   * Demonstrates full message exchange with negotiated algorithm
   */
  async completeProtocolExample() {
    console.log('\n🔄 Example 5: Complete Signal Protocol Flow');
    console.log('==========================================');
    
    try {
      // 1. Initialize X3DH
      await x3dhKeyAgreement.initialize();
      console.log('✅ X3DH initialized');
      
      // 2. Simulate fetching Bob's prekey bundle
      console.log('📦 Fetching prekey bundle...');
      // This would normally fetch from server
      const mockBundle = this.createMockPreKeyBundle();
      
      // 3. Perform key agreement with algorithm negotiation
      console.log('🤝 Performing key agreement...');
      const keyAgreementResult = await x3dhKeyAgreement.performKeyAgreementInitiator(
        'bob_user_id',
        mockBundle
      );
      
      console.log(`✅ Key agreement complete:`);
      console.log(`   Algorithm: ${keyAgreementResult.usedQuantumAlgorithm}`);
      console.log(`   Quantum Resistant: ${keyAgreementResult.isQuantumResistant}`);
      console.log(`   Hybrid Mode: ${keyAgreementResult.hybridMode}`);
      
      // 4. Initialize Signal session
      console.log('🔐 Initializing Signal session...');
      await signalSessionManager.initialize();
      
      // 5. Establish session with negotiated algorithm
      const sessionResult = await signalSessionManager.establishSession(
        'conversation_123',
        'alice_user_id'
      );
      
      console.log(`✅ Session established: ${sessionResult.sessionId}`);
      
      // 6. Send encrypted message
      console.log('📤 Sending encrypted message...');
      const messageResult = await signalSessionManager.sendMessage(
        'conversation_123',
        'alice_user_id',
        'Hello Bob! This message is encrypted with our negotiated algorithm.',
        { priority: 'normal', requiresReceipt: true, forwardSecrecy: true }
      );
      
      console.log(`✅ Message sent: ${messageResult.messageId}`);
      console.log(`   Status: ${messageResult.deliveryStatus}`);
      
    } catch (error) {
      console.error('❌ Protocol flow failed:', error);
    }
  }
  
  /**
   * Run all examples
   */
  async runAllExamples() {
    console.log('🚀 Signal Protocol with Quantum Support - Complete Examples');
    console.log('============================================================');
    
    await this.modernDevicesExample();
    await this.mixedDevicesExample();
    await this.legacyDevicesExample();
    await this.transitionExample();
    await this.completeProtocolExample();
    
    console.log('\n✅ All examples completed successfully!');
    console.log('\nKey Features Demonstrated:');
    console.log('• Algorithm negotiation with fallback');
    console.log('• Quantum-resistant encryption (ML-KEM)');
    console.log('• Signal Protocol standard compliance');
    console.log('• Multi-device compatibility');
    console.log('• Graceful degradation for legacy devices');
    console.log('• Hybrid algorithms for transition periods');
  }
  
  /**
   * Simulate algorithm negotiation (matches X3DH implementation)
   */
  private simulateNegotiation(localCaps: string[], remoteCaps: string[]) {
    const algorithmPriority = [
      'ML-KEM-1024',
      'ML-KEM-768', 
      'ML-KEM-512',
      'HYBRID-RSA4096-MLKEM768',
      'Curve25519',
      'P-256',
      'RSA-4096-OAEP',
      'RSA-2048-OAEP'
    ];
    
    const algorithmInfo = {
      'ML-KEM-1024': { type: 'quantum' as const, security: 256, quantumResistant: true },
      'ML-KEM-768': { type: 'quantum' as const, security: 192, quantumResistant: true },
      'ML-KEM-512': { type: 'quantum' as const, security: 128, quantumResistant: true },
      'HYBRID-RSA4096-MLKEM768': { type: 'hybrid' as const, security: 192, quantumResistant: true },
      'Curve25519': { type: 'classical' as const, security: 128, quantumResistant: false },
      'P-256': { type: 'classical' as const, security: 128, quantumResistant: false },
      'RSA-4096-OAEP': { type: 'classical' as const, security: 112, quantumResistant: false },
      'RSA-2048-OAEP': { type: 'classical' as const, security: 80, quantumResistant: false },
    };
    
    const commonAlgorithms = localCaps.filter(alg => remoteCaps.includes(alg));
    
    for (const algorithm of algorithmPriority) {
      if (commonAlgorithms.includes(algorithm)) {
        const info = algorithmInfo[algorithm as keyof typeof algorithmInfo];
        return {
          selectedAlgorithm: algorithm,
          algorithmType: info.type,
          securityLevel: info.security,
          isQuantumResistant: info.quantumResistant,
          fallbackUsed: false,
          negotiationReason: 'Best available algorithm selected'
        };
      }
    }
    
    return {
      selectedAlgorithm: 'RSA-2048-OAEP',
      algorithmType: 'classical' as const,
      securityLevel: 80,
      isQuantumResistant: false,
      fallbackUsed: true,
      negotiationReason: 'Fallback algorithm used'
    };
  }
  
  /**
   * Create mock prekey bundle for demonstration
   */
  private createMockPreKeyBundle() {
    return {
      identityKey: new ArrayBuffer(32),
      signedPreKey: {
        keyId: 1,
        publicKey: new ArrayBuffer(32),
        signature: new ArrayBuffer(64),
        quantumPublicKey: new ArrayBuffer(1184), // ML-KEM-768 public key size
        quantumAlgorithm: 'ML-KEM-768'
      },
      oneTimePreKey: {
        keyId: 1,
        publicKey: new ArrayBuffer(32)
      },
      registrationId: 12345,
      deviceCapabilities: {
        supportedAlgorithms: ['ML-KEM-768', 'ML-KEM-512', 'Curve25519', 'P-256'],
        quantumCapable: true,
        fallbackAlgorithms: ['Curve25519', 'P-256', 'RSA-2048-OAEP'],
        protocolVersion: '3.0',
        deviceType: 'mobile'
      },
      quantumIdentityKey: new ArrayBuffer(1184)
    };
  }
}

// Export example instance
export const signalProtocolExample = new SignalProtocolExample();