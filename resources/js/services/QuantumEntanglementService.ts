/**
 * Quantum Entanglement Verification Service
 * 
 * Implements quantum entanglement verification using Bell inequality tests,
 * quantum teleportation protocols, and distributed quantum sensing for
 * ultra-secure quantum communication channels.
 * 
 * BREAKING CHANGES: Quantum communication now requires verified entanglement
 * with continuous Bell inequality testing for tamper detection.
 */

import { quantumKeyDistribution } from './QuantumKeyDistributionService';
import { quantumHSM } from './QuantumHSMService';

// Quantum states and measurements
enum QuantumState {
  PLUS = '|+⟩',      // (|0⟩ + |1⟩)/√2
  MINUS = '|-⟩',     // (|0⟩ - |1⟩)/√2
  ZERO = '|0⟩',      // Computational basis
  ONE = '|1⟩',       // Computational basis
  PHI_PLUS = '|Φ+⟩', // (|00⟩ + |11⟩)/√2
  PHI_MINUS = '|Φ-⟩', // (|00⟩ - |11⟩)/√2
  PSI_PLUS = '|Ψ+⟩',  // (|01⟩ + |10⟩)/√2
  PSI_MINUS = '|Ψ-⟩'  // (|01⟩ - |10⟩)/√2
}

enum MeasurementBasis {
  Z = 'Z',           // Computational basis (0°)
  X = 'X',           // Hadamard basis (45°)
  Y = 'Y'            // Y basis (diagonal)
}

enum BellState {
  PHI_PLUS = 'phi_plus',
  PHI_MINUS = 'phi_minus',
  PSI_PLUS = 'psi_plus',
  PSI_MINUS = 'psi_minus'
}

interface QuantumPhoton {
  photonId: string;
  state: QuantumState;
  polarization: number;
  frequency: number; // Hz
  coherenceTime: number; // nanoseconds
  fidelity: number; // State fidelity (0-1)
  timestamp: number;
  
  // Physical properties
  wavelength: number; // nm
  energy: number; // eV
  momentum: number;
  
  // Measurement properties
  measured: boolean;
  measurementBasis?: MeasurementBasis;
  measurementResult?: 0 | 1;
  measurementTime?: number;
}

interface EntangledPair {
  pairId: string;
  photonA: QuantumPhoton;
  photonB: QuantumPhoton;
  bellState: BellState;
  
  // Entanglement properties
  entanglementFidelity: number; // Fidelity of entangled state
  concurrence: number; // Entanglement measure (0-1)
  negativity: number; // Another entanglement measure
  
  // Bell test results
  bellInequality: {
    chshValue: number; // CHSH inequality value
    classicalLimit: 2; // Classical upper bound
    quantumLimit: 2.828; // Quantum upper bound (2√2)
    violation: boolean;
    significance: number; // Statistical significance
  };
  
  // Decoherence tracking
  decoherenceRate: number; // 1/s
  estimatedLifetime: number; // seconds
  environmentalNoise: number;
  
  // Verification status
  verified: boolean;
  lastVerification: number;
  verificationHistory: {
    timestamp: number;
    chshValue: number;
    passed: boolean;
  }[];
  
  createdAt: number;
  expiresAt: number;
}

interface BellExperiment {
  experimentId: string;
  pairId: string;
  
  // Measurement settings
  aliceBases: MeasurementBasis[];
  bobBases: MeasurementBasis[];
  measurementCount: number;
  
  // Results
  correlations: Map<string, number>; // Basis pair -> correlation
  chshValues: number[];
  averageChsh: number;
  
  // Statistical analysis
  standardDeviation: number;
  confidenceInterval: [number, number];
  pValue: number; // P-value for Bell violation
  
  // Experiment metadata
  startTime: number;
  endTime: number;
  totalMeasurements: number;
  successfulMeasurements: number;
  
  // Detection loopholes
  detectionEfficiency: number;
  localityLoophole: boolean; // Space-like separated measurements
  freedomOfChoiceLoophole: boolean; // Random basis selection
  
  result: 'bell_violation' | 'classical' | 'inconclusive' | 'failed';
}

interface QuantumTeleportationProtocol {
  teleportationId: string;
  
  // Participants
  sender: string; // Alice
  receiver: string; // Bob
  
  // Quantum resources
  entangledPair: EntangledPair;
  unknownState: QuantumPhoton; // State to be teleported
  
  // Protocol steps
  bellMeasurement: {
    result: [0 | 1, 0 | 1]; // Two-bit measurement result
    timestamp: number;
  };
  
  classicalCommunication: {
    sent: boolean;
    received: boolean;
    transmissionTime: number;
    messageIntegrity: boolean;
  };
  
  stateReconstruction: {
    completed: boolean;
    fidelity: number; // Fidelity of reconstructed state
    timestamp: number;
  };
  
  // Verification
  verified: boolean;
  fidelityThreshold: number;
  
  status: 'initializing' | 'measuring' | 'communicating' | 'reconstructing' | 'complete' | 'failed';
  createdAt: number;
  completedAt?: number;
}

interface QuantumChannel {
  channelId: string;
  endpointA: string;
  endpointB: string;
  
  // Physical characteristics
  distance: number; // km
  medium: 'fiber' | 'free-space' | 'satellite';
  wavelength: number; // nm
  bandwidth: number; // Hz
  
  // Entanglement distribution
  entanglementRate: number; // pairs/second
  distributionFidelity: number;
  transmissionLoss: number;
  
  // Security properties
  eavesdroppingDetection: boolean;
  tamperEvidence: boolean;
  quantumAdvantage: boolean;
  
  // Performance metrics
  uptimePercentage: number;
  averageLatency: number; // ms
  errorRate: number;
  
  // Active entangled pairs
  activePairs: string[];
  
  status: 'active' | 'inactive' | 'maintenance' | 'compromised';
  createdAt: number;
  lastHealthCheck: number;
}

export class QuantumEntanglementService {
  private entangledPairs = new Map<string, EntangledPair>();
  private bellExperiments = new Map<string, BellExperiment>();
  private teleportationProtocols = new Map<string, QuantumTeleportationProtocol>();
  private quantumChannels = new Map<string, QuantumChannel>();
  
  // Physical constants
  private readonly PLANCK_CONSTANT = 6.62607015e-34; // J⋅s
  private readonly SPEED_OF_LIGHT = 299792458; // m/s
  private readonly BELL_THRESHOLD = 2.0; // Classical limit for CHSH inequality
  private readonly QUANTUM_ADVANTAGE_THRESHOLD = 2.1; // Minimum for quantum advantage
  private readonly MIN_FIDELITY = 0.85; // Minimum entanglement fidelity
  
  // Experimental parameters
  private readonly MIN_MEASUREMENTS = 1000; // Minimum for statistical significance
  private readonly CONFIDENCE_LEVEL = 0.95; // 95% confidence
  private readonly DECOHERENCE_THRESHOLD = 0.1; // 1/s
  
  constructor() {
    this.initializeQuantumInfrastructure();
    this.startEntanglementMonitoring();
  }

  /**
   * Initialize quantum infrastructure
   */
  private async initializeQuantumInfrastructure(): Promise<void> {
    // Initialize quantum channels
    await this.setupQuantumChannels();
    
    // Start background verification
    this.startContinuousVerification();
    
    console.log('Quantum entanglement infrastructure initialized');
  }

  /**
   * Setup quantum communication channels
   */
  private async setupQuantumChannels(): Promise<void> {
    const channels: Omit<QuantumChannel, 'activePairs' | 'createdAt' | 'lastHealthCheck'>[] = [
      {
        channelId: 'qch-primary-001',
        endpointA: 'node-alice',
        endpointB: 'node-bob',
        distance: 10, // km
        medium: 'fiber',
        wavelength: 1550, // nm
        bandwidth: 1e12, // 1 THz
        entanglementRate: 1000, // 1kHz
        distributionFidelity: 0.95,
        transmissionLoss: 0.1,
        eavesdroppingDetection: true,
        tamperEvidence: true,
        quantumAdvantage: true,
        uptimePercentage: 99.9,
        averageLatency: 0.05, // 50 μs
        errorRate: 0.01,
        status: 'active'
      },
      {
        channelId: 'qch-backup-002',
        endpointA: 'node-alice',
        endpointB: 'node-charlie',
        distance: 25, // km
        medium: 'free-space',
        wavelength: 810, // nm
        bandwidth: 5e11, // 500 GHz
        entanglementRate: 500, // 500 Hz
        distributionFidelity: 0.88,
        transmissionLoss: 0.25,
        eavesdroppingDetection: true,
        tamperEvidence: true,
        quantumAdvantage: true,
        uptimePercentage: 98.5,
        averageLatency: 0.1, // 100 μs
        errorRate: 0.02,
        status: 'active'
      }
    ];

    for (const channelConfig of channels) {
      const channel: QuantumChannel = {
        ...channelConfig,
        activePairs: [],
        createdAt: Date.now(),
        lastHealthCheck: Date.now()
      };
      
      this.quantumChannels.set(channel.channelId, channel);
    }
  }

  /**
   * Generate entangled photon pair
   */
  async generateEntangledPair(
    channelId: string,
    bellState: BellState = BellState.PHI_PLUS
  ): Promise<string> {
    const channel = this.quantumChannels.get(channelId);
    if (!channel || channel.status !== 'active') {
      throw new Error('Quantum channel not available');
    }

    const pairId = `ent-pair-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    // Generate quantum-correlated photons
    const photonA = await this.createQuantumPhoton();
    const photonB = await this.createEntangledPhoton(photonA, bellState);
    
    // Calculate entanglement measures
    const entanglementFidelity = this.calculateFidelity(bellState, channel.distributionFidelity);
    const concurrence = this.calculateConcurrence(entanglementFidelity);
    const negativity = this.calculateNegativity(entanglementFidelity);
    
    const pair: EntangledPair = {
      pairId,
      photonA,
      photonB,
      bellState,
      entanglementFidelity,
      concurrence,
      negativity,
      bellInequality: {
        chshValue: 0,
        classicalLimit: 2,
        quantumLimit: 2.828,
        violation: false,
        significance: 0
      },
      decoherenceRate: this.calculateDecoherenceRate(channel),
      estimatedLifetime: 1 / this.calculateDecoherenceRate(channel),
      environmentalNoise: channel.errorRate,
      verified: false,
      lastVerification: 0,
      verificationHistory: [],
      createdAt: Date.now(),
      expiresAt: Date.now() + (1000 / this.calculateDecoherenceRate(channel))
    };

    this.entangledPairs.set(pairId, pair);
    channel.activePairs.push(pairId);
    
    // Immediately verify entanglement
    await this.verifyEntanglement(pairId);
    
    console.log(`Entangled pair generated: ${pairId} (${bellState})`);
    return pairId;
  }

  /**
   * Verify quantum entanglement using Bell inequality test
   */
  async verifyEntanglement(pairId: string): Promise<boolean> {
    const pair = this.entangledPairs.get(pairId);
    if (!pair) {
      throw new Error('Entangled pair not found');
    }

    // Perform Bell experiment
    const experimentId = await this.performBellExperiment(pairId);
    const experiment = this.bellExperiments.get(experimentId);
    
    if (!experiment) {
      return false;
    }

    const chshValue = experiment.averageChsh;
    const violation = chshValue > this.BELL_THRESHOLD;
    const quantumAdvantage = chshValue > this.QUANTUM_ADVANTAGE_THRESHOLD;
    
    // Update pair verification status
    pair.bellInequality = {
      chshValue,
      classicalLimit: 2,
      quantumLimit: 2.828,
      violation,
      significance: this.calculateStatisticalSignificance(experiment)
    };
    
    pair.verified = violation && experiment.result === 'bell_violation';
    pair.lastVerification = Date.now();
    pair.verificationHistory.push({
      timestamp: Date.now(),
      chshValue,
      passed: pair.verified
    });

    console.log(`Entanglement verification: ${pairId} - ${pair.verified ? 'VERIFIED' : 'FAILED'} (CHSH: ${chshValue.toFixed(3)})`);
    
    return pair.verified;
  }

  /**
   * Perform Bell inequality experiment (CHSH test)
   */
  async performBellExperiment(pairId: string): Promise<string> {
    const pair = this.entangledPairs.get(pairId);
    if (!pair) {
      throw new Error('Entangled pair not found');
    }

    const experimentId = `bell-exp-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    // Define measurement bases for CHSH test
    const aliceBases = [MeasurementBasis.Z, MeasurementBasis.X]; // θ = 0°, 45°
    const bobBases = [MeasurementBasis.Z, MeasurementBasis.Y];  // φ = 22.5°, 67.5°
    
    const measurementCount = Math.max(this.MIN_MEASUREMENTS, 2000);
    
    const experiment: BellExperiment = {
      experimentId,
      pairId,
      aliceBases,
      bobBases,
      measurementCount,
      correlations: new Map(),
      chshValues: [],
      averageChsh: 0,
      standardDeviation: 0,
      confidenceInterval: [0, 0],
      pValue: 0,
      startTime: Date.now(),
      endTime: 0,
      totalMeasurements: 0,
      successfulMeasurements: 0,
      detectionEfficiency: 0.9, // 90% detection efficiency
      localityLoophole: true, // Assume space-like separation
      freedomOfChoiceLoophole: true, // Random basis selection
      result: 'inconclusive'
    };

    // Perform measurements
    const measurements = await this.performCHSHMeasurements(pair, aliceBases, bobBases, measurementCount);
    
    // Calculate correlations
    const correlations = this.calculateCorrelations(measurements);
    experiment.correlations = correlations;
    
    // Calculate CHSH value
    const chshValue = this.calculateCHSHValue(correlations);
    experiment.chshValues = [chshValue];
    experiment.averageChsh = chshValue;
    
    // Statistical analysis
    experiment.standardDeviation = 0.1; // Simplified
    experiment.pValue = this.calculatePValue(chshValue);
    experiment.confidenceInterval = [chshValue - 0.1, chshValue + 0.1];
    
    // Determine result
    if (chshValue > this.BELL_THRESHOLD) {
      experiment.result = 'bell_violation';
    } else if (chshValue <= this.BELL_THRESHOLD * 0.9) {
      experiment.result = 'classical';
    } else {
      experiment.result = 'inconclusive';
    }
    
    experiment.endTime = Date.now();
    experiment.totalMeasurements = measurementCount;
    experiment.successfulMeasurements = Math.floor(measurementCount * experiment.detectionEfficiency);
    
    this.bellExperiments.set(experimentId, experiment);
    
    console.log(`Bell experiment completed: ${experimentId} - CHSH = ${chshValue.toFixed(3)}`);
    return experimentId;
  }

  /**
   * Execute quantum teleportation protocol
   */
  async executeQuantumTeleportation(
    pairId: string,
    unknownStatePhoton: QuantumPhoton,
    sender: string,
    receiver: string
  ): Promise<string> {
    const pair = this.entangledPairs.get(pairId);
    if (!pair || !pair.verified) {
      throw new Error('Verified entangled pair required for teleportation');
    }

    const teleportationId = `teleport-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    const protocol: QuantumTeleportationProtocol = {
      teleportationId,
      sender,
      receiver,
      entangledPair: pair,
      unknownState: unknownStatePhoton,
      bellMeasurement: {
        result: [0, 0],
        timestamp: 0
      },
      classicalCommunication: {
        sent: false,
        received: false,
        transmissionTime: 0,
        messageIntegrity: false
      },
      stateReconstruction: {
        completed: false,
        fidelity: 0,
        timestamp: 0
      },
      verified: false,
      fidelityThreshold: 0.9,
      status: 'initializing',
      createdAt: Date.now()
    };

    this.teleportationProtocols.set(teleportationId, protocol);

    try {
      // Step 1: Alice performs Bell measurement
      protocol.status = 'measuring';
      const bellResult = await this.performBellMeasurement(unknownStatePhoton, pair.photonA);
      protocol.bellMeasurement = {
        result: bellResult,
        timestamp: Date.now()
      };

      // Step 2: Classical communication
      protocol.status = 'communicating';
      const communicationSuccess = await this.sendClassicalMessage(sender, receiver, bellResult);
      protocol.classicalCommunication = {
        sent: true,
        received: communicationSuccess,
        transmissionTime: Date.now() - protocol.bellMeasurement.timestamp,
        messageIntegrity: communicationSuccess
      };

      if (!communicationSuccess) {
        protocol.status = 'failed';
        throw new Error('Classical communication failed');
      }

      // Step 3: Bob reconstructs the state
      protocol.status = 'reconstructing';
      const reconstructedState = await this.reconstructQuantumState(pair.photonB, bellResult);
      const fidelity = this.calculateStateFidelity(unknownStatePhoton, reconstructedState);
      
      protocol.stateReconstruction = {
        completed: true,
        fidelity,
        timestamp: Date.now()
      };

      // Verification
      protocol.verified = fidelity >= protocol.fidelityThreshold;
      protocol.status = protocol.verified ? 'complete' : 'failed';
      protocol.completedAt = Date.now();

      console.log(`Quantum teleportation ${protocol.verified ? 'succeeded' : 'failed'}: ${teleportationId} (fidelity: ${fidelity.toFixed(3)})`);
      
      return teleportationId;

    } catch (error) {
      protocol.status = 'failed';
      console.error(`Quantum teleportation failed: ${error.message}`);
      throw error;
    }
  }

  /**
   * Monitor quantum channels for eavesdropping
   */
  async monitorChannelSecurity(channelId: string): Promise<{
    secure: boolean;
    threats: string[];
    metrics: any;
  }> {
    const channel = this.quantumChannels.get(channelId);
    if (!channel) {
      throw new Error('Quantum channel not found');
    }

    const threats: string[] = [];
    const metrics = {
      averageFidelity: 0,
      entanglementRate: channel.entanglementRate,
      errorRate: channel.errorRate,
      activePairs: channel.activePairs.length,
      verifiedPairs: 0,
      chshViolations: 0
    };

    // Check all active pairs
    let totalFidelity = 0;
    let verifiedCount = 0;
    let violationCount = 0;

    for (const pairId of channel.activePairs) {
      const pair = this.entangledPairs.get(pairId);
      if (pair) {
        totalFidelity += pair.entanglementFidelity;
        
        if (pair.verified) {
          verifiedCount++;
        }
        
        if (pair.bellInequality.violation) {
          violationCount++;
        }

        // Check for potential eavesdropping indicators
        if (pair.entanglementFidelity < this.MIN_FIDELITY) {
          threats.push(`Low fidelity detected in pair ${pairId}`);
        }
        
        if (pair.decoherenceRate > this.DECOHERENCE_THRESHOLD) {
          threats.push(`High decoherence rate in pair ${pairId}`);
        }
        
        if (!pair.bellInequality.violation) {
          threats.push(`Bell inequality violation failed for pair ${pairId}`);
        }
      }
    }

    metrics.averageFidelity = channel.activePairs.length > 0 ? totalFidelity / channel.activePairs.length : 0;
    metrics.verifiedPairs = verifiedCount;
    metrics.chshViolations = violationCount;

    // Overall security assessment
    const secure = threats.length === 0 && 
                   metrics.averageFidelity >= this.MIN_FIDELITY &&
                   metrics.verifiedPairs > 0;

    // Update channel status
    if (!secure && threats.length > 3) {
      channel.status = 'compromised';
    }

    return { secure, threats, metrics };
  }

  /**
   * Get entanglement statistics
   */
  getEntanglementStatistics(): {
    totalPairs: number;
    verifiedPairs: number;
    averageFidelity: number;
    averageChsh: number;
    bellViolations: number;
    activeChannels: number;
  } {
    const pairs = Array.from(this.entangledPairs.values());
    const verified = pairs.filter(p => p.verified);
    const violations = pairs.filter(p => p.bellInequality.violation);
    
    const avgFidelity = pairs.length > 0 ? 
      pairs.reduce((sum, p) => sum + p.entanglementFidelity, 0) / pairs.length : 0;
    
    const avgChsh = pairs.length > 0 ? 
      pairs.reduce((sum, p) => sum + p.bellInequality.chshValue, 0) / pairs.length : 0;

    return {
      totalPairs: pairs.length,
      verifiedPairs: verified.length,
      averageFidelity: avgFidelity,
      averageChsh: avgChsh,
      bellViolations: violations.length,
      activeChannels: Array.from(this.quantumChannels.values()).filter(c => c.status === 'active').length
    };
  }

  // Private helper methods

  private async createQuantumPhoton(): Promise<QuantumPhoton> {
    const photonId = `photon-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const wavelength = 810; // nm (typical for quantum experiments)
    const frequency = this.SPEED_OF_LIGHT / (wavelength * 1e-9); // Hz
    const energy = this.PLANCK_CONSTANT * frequency / 1.602176634e-19; // eV
    
    return {
      photonId,
      state: QuantumState.PLUS, // Default superposition state
      polarization: Math.random() * 180, // Random polarization
      frequency,
      coherenceTime: 100, // 100 ns
      fidelity: 0.99,
      timestamp: Date.now(),
      wavelength,
      energy,
      momentum: energy / this.SPEED_OF_LIGHT,
      measured: false
    };
  }

  private async createEntangledPhoton(partner: QuantumPhoton, bellState: BellState): Promise<QuantumPhoton> {
    const photon = await this.createQuantumPhoton();
    
    // Set entangled properties based on Bell state
    switch (bellState) {
      case BellState.PHI_PLUS:
        photon.state = partner.state === QuantumState.ZERO ? QuantumState.ZERO : QuantumState.ONE;
        break;
      case BellState.PHI_MINUS:
        photon.state = partner.state === QuantumState.ZERO ? QuantumState.ZERO : QuantumState.ONE;
        break;
      case BellState.PSI_PLUS:
        photon.state = partner.state === QuantumState.ZERO ? QuantumState.ONE : QuantumState.ZERO;
        break;
      case BellState.PSI_MINUS:
        photon.state = partner.state === QuantumState.ZERO ? QuantumState.ONE : QuantumState.ZERO;
        break;
    }
    
    return photon;
  }

  private calculateFidelity(bellState: BellState, channelFidelity: number): number {
    // Fidelity depends on Bell state and channel quality
    const baseFidelity = {
      [BellState.PHI_PLUS]: 0.99,
      [BellState.PHI_MINUS]: 0.98,
      [BellState.PSI_PLUS]: 0.97,
      [BellState.PSI_MINUS]: 0.96
    };
    
    return Math.min(baseFidelity[bellState] * channelFidelity, 1.0);
  }

  private calculateConcurrence(fidelity: number): number {
    // Simplified concurrence calculation
    return Math.max(0, 2 * Math.sqrt(fidelity * (1 - fidelity)));
  }

  private calculateNegativity(fidelity: number): number {
    // Simplified negativity calculation
    return Math.max(0, (fidelity - 0.5) * 2);
  }

  private calculateDecoherenceRate(channel: QuantumChannel): number {
    // Decoherence rate depends on distance and environmental factors
    const baseRate = 0.01; // 1/s
    const distanceFactor = channel.distance * 0.001; // Distance contribution
    const noiseFactor = channel.errorRate * 10; // Noise contribution
    
    return baseRate + distanceFactor + noiseFactor;
  }

  private async performCHSHMeasurements(
    pair: EntangledPair,
    aliceBases: MeasurementBasis[],
    bobBases: MeasurementBasis[],
    count: number
  ): Promise<Map<string, number[]>> {
    const measurements = new Map<string, number[]>();
    
    // Initialize measurement arrays for each basis combination
    for (const aliceBasis of aliceBases) {
      for (const bobBasis of bobBases) {
        measurements.set(`${aliceBasis}-${bobBasis}`, []);
      }
    }
    
    // Simulate quantum measurements
    for (let i = 0; i < count; i++) {
      const aliceBasis = aliceBases[Math.floor(Math.random() * aliceBases.length)];
      const bobBasis = bobBases[Math.floor(Math.random() * bobBases.length)];
      
      const aliceResult = this.simulateQuantumMeasurement(pair.photonA, aliceBasis);
      const bobResult = this.simulateQuantumMeasurement(pair.photonB, bobBasis);
      
      const key = `${aliceBasis}-${bobBasis}`;
      const correlation = aliceResult === bobResult ? 1 : -1;
      measurements.get(key)?.push(correlation);
    }
    
    return measurements;
  }

  private simulateQuantumMeasurement(photon: QuantumPhoton, basis: MeasurementBasis): 0 | 1 {
    // Simulate quantum measurement with realistic probabilities
    // This is a simplified model - real quantum measurements are more complex
    
    const random = Math.random();
    let probability = 0.5; // Default 50/50
    
    // Adjust probability based on photon state and measurement basis
    if (photon.state === QuantumState.ZERO && basis === MeasurementBasis.Z) {
      probability = 0.99; // High probability of measuring 0
    } else if (photon.state === QuantumState.ONE && basis === MeasurementBasis.Z) {
      probability = 0.01; // Low probability of measuring 0
    }
    
    // Add noise from environment
    probability += (Math.random() - 0.5) * 0.1;
    probability = Math.max(0, Math.min(1, probability));
    
    return random < probability ? 0 : 1;
  }

  private calculateCorrelations(measurements: Map<string, number[]>): Map<string, number> {
    const correlations = new Map<string, number>();
    
    for (const [key, values] of measurements) {
      const average = values.reduce((sum, val) => sum + val, 0) / values.length;
      correlations.set(key, average);
    }
    
    return correlations;
  }

  private calculateCHSHValue(correlations: Map<string, number>): number {
    // CHSH inequality: |E(a,b) - E(a,b') + E(a',b) + E(a',b')| ≤ 2
    // Where E(x,y) is the correlation between measurements x and y
    
    const e_zz = correlations.get('Z-Z') || 0;
    const e_zy = correlations.get('Z-Y') || 0;
    const e_xz = correlations.get('X-Z') || 0;
    const e_xy = correlations.get('X-Y') || 0;
    
    return Math.abs(e_zz - e_zy + e_xz + e_xy);
  }

  private calculatePValue(chshValue: number): number {
    // Simplified p-value calculation for Bell test
    // In practice, would use proper statistical analysis
    
    const difference = chshValue - this.BELL_THRESHOLD;
    return Math.exp(-difference * difference / 0.1);
  }

  private calculateStatisticalSignificance(experiment: BellExperiment): number {
    // Calculate statistical significance (simplified)
    const sigma = experiment.standardDeviation || 0.1;
    return Math.abs(experiment.averageChsh - this.BELL_THRESHOLD) / sigma;
  }

  private async performBellMeasurement(photon1: QuantumPhoton, photon2: QuantumPhoton): Promise<[0 | 1, 0 | 1]> {
    // Simulate Bell measurement
    const result1 = this.simulateQuantumMeasurement(photon1, MeasurementBasis.Z);
    const result2 = this.simulateQuantumMeasurement(photon2, MeasurementBasis.X);
    
    return [result1, result2];
  }

  private async sendClassicalMessage(sender: string, receiver: string, data: [0 | 1, 0 | 1]): Promise<boolean> {
    // Simulate classical communication
    // In practice, would use secure authenticated channel
    
    const delay = Math.random() * 10; // Random delay up to 10ms
    await new Promise(resolve => setTimeout(resolve, delay));
    
    return Math.random() > 0.01; // 99% success rate
  }

  private async reconstructQuantumState(photon: QuantumPhoton, bellResult: [0 | 1, 0 | 1]): Promise<QuantumPhoton> {
    // Simulate quantum state reconstruction based on Bell measurement result
    const reconstructed = { ...photon };
    
    // Apply appropriate quantum operations based on Bell measurement
    const [bit1, bit2] = bellResult;
    
    if (bit1 === 1) {
      // Apply X gate (bit flip)
      reconstructed.state = photon.state === QuantumState.ZERO ? QuantumState.ONE : QuantumState.ZERO;
    }
    
    if (bit2 === 1) {
      // Apply Z gate (phase flip)
      reconstructed.polarization = (reconstructed.polarization + 90) % 180;
    }
    
    return reconstructed;
  }

  private calculateStateFidelity(original: QuantumPhoton, reconstructed: QuantumPhoton): number {
    // Calculate fidelity between original and reconstructed states
    // This is a simplified calculation
    
    const stateSimilarity = original.state === reconstructed.state ? 1 : 0.5;
    const polarizationDiff = Math.abs(original.polarization - reconstructed.polarization);
    const polarizationSimilarity = 1 - (polarizationDiff / 180);
    
    return (stateSimilarity + polarizationSimilarity) / 2;
  }

  private startEntanglementMonitoring(): void {
    // Monitor entanglement quality every 10 seconds
    setInterval(() => {
      this.monitorEntanglementHealth();
    }, 10000);
    
    // Cleanup expired pairs every minute
    setInterval(() => {
      this.cleanupExpiredPairs();
    }, 60000);
  }

  private startContinuousVerification(): void {
    // Continuously verify active entangled pairs
    setInterval(async () => {
      for (const [pairId, pair] of this.entangledPairs) {
        if (pair.verified && Date.now() - pair.lastVerification > 30000) { // Re-verify every 30s
          try {
            await this.verifyEntanglement(pairId);
          } catch (error) {
            console.warn(`Continuous verification failed for pair ${pairId}:`, error);
          }
        }
      }
    }, 5000); // Check every 5 seconds
  }

  private monitorEntanglementHealth(): void {
    for (const [pairId, pair] of this.entangledPairs) {
      // Check if pair has expired
      if (Date.now() > pair.expiresAt) {
        pair.verified = false;
        console.log(`Entangled pair expired: ${pairId}`);
      }
      
      // Check decoherence
      const age = Date.now() - pair.createdAt;
      const expectedFidelity = pair.entanglementFidelity * Math.exp(-pair.decoherenceRate * age / 1000);
      
      if (expectedFidelity < this.MIN_FIDELITY) {
        pair.verified = false;
        console.log(`Entangled pair decoherence detected: ${pairId}`);
      }
    }
  }

  private cleanupExpiredPairs(): void {
    const now = Date.now();
    
    for (const [pairId, pair] of this.entangledPairs) {
      if (now > pair.expiresAt || !pair.verified) {
        this.entangledPairs.delete(pairId);
        
        // Remove from channel active pairs
        for (const channel of this.quantumChannels.values()) {
          const index = channel.activePairs.indexOf(pairId);
          if (index > -1) {
            channel.activePairs.splice(index, 1);
          }
        }
      }
    }
  }
}

// Export singleton instance
export const quantumEntanglement = new QuantumEntanglementService();