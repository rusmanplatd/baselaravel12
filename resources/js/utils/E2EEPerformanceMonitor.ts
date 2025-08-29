/**
 * E2EE Performance Monitor
 * Tracks encryption/decryption performance and provides optimization suggestions
 */

export interface PerformanceMetric {
  operation: string;
  duration: number;
  timestamp: string;
  dataSize?: number;
  keyType?: string;
  success: boolean;
  details?: Record<string, any>;
}

export interface PerformanceStats {
  averageDuration: number;
  totalOperations: number;
  successRate: number;
  slowestOperations: PerformanceMetric[];
  recommendations: string[];
}

export class E2EEPerformanceMonitor {
  private metrics: PerformanceMetric[] = [];
  private maxMetricsSize = 1000;
  private slowOperationThreshold = 1000; // 1 second

  /**
   * Start timing an operation
   */
  startOperation(operation: string): (success?: boolean, details?: { dataSize?: number; keyType?: string; error?: string }) => void {
    const startTime = performance.now();
    const startTimestamp = new Date().toISOString();

    return (success: boolean = true, details?: { dataSize?: number; keyType?: string; error?: string }) => {
      const duration = performance.now() - startTime;
      
      this.addMetric({
        operation,
        duration,
        timestamp: startTimestamp,
        dataSize: details?.dataSize,
        keyType: details?.keyType,
        success,
        details: details ? { ...details } : undefined
      });

      // Log slow operations
      if (duration > this.slowOperationThreshold) {
        console.warn(`Slow E2EE operation detected: ${operation} took ${duration.toFixed(2)}ms`);
      }
    };
  }

  /**
   * Add a metric directly
   */
  addMetric(metric: PerformanceMetric): void {
    this.metrics.unshift(metric);
    
    // Maintain size limit
    if (this.metrics.length > this.maxMetricsSize) {
      this.metrics = this.metrics.slice(0, this.maxMetricsSize);
    }
  }

  /**
   * Get performance statistics for a specific operation
   */
  getStats(operation?: string): PerformanceStats {
    const relevantMetrics = operation 
      ? this.metrics.filter(m => m.operation === operation)
      : this.metrics;

    if (relevantMetrics.length === 0) {
      return {
        averageDuration: 0,
        totalOperations: 0,
        successRate: 0,
        slowestOperations: [],
        recommendations: []
      };
    }

    const successfulOperations = relevantMetrics.filter(m => m.success);
    const averageDuration = relevantMetrics.reduce((sum, m) => sum + m.duration, 0) / relevantMetrics.length;
    const successRate = (successfulOperations.length / relevantMetrics.length) * 100;
    
    const slowestOperations = [...relevantMetrics]
      .sort((a, b) => b.duration - a.duration)
      .slice(0, 5);

    const recommendations = this.generateRecommendations(relevantMetrics, averageDuration, successRate);

    return {
      averageDuration,
      totalOperations: relevantMetrics.length,
      successRate,
      slowestOperations,
      recommendations
    };
  }

  /**
   * Generate performance recommendations
   */
  private generateRecommendations(metrics: PerformanceMetric[], avgDuration: number, successRate: number): string[] {
    const recommendations: string[] = [];

    // Check average performance
    if (avgDuration > 500) {
      recommendations.push('Average operation time is high. Consider key caching or worker threads.');
    }

    // Check success rate
    if (successRate < 95) {
      recommendations.push('Success rate is below 95%. Review error handling and key management.');
    }

    // Check for patterns in slow operations
    const slowOperations = metrics.filter(m => m.duration > this.slowOperationThreshold);
    if (slowOperations.length > metrics.length * 0.1) {
      recommendations.push('High percentage of slow operations. Consider message batching or background processing.');
    }

    // Check data size patterns
    const largeDataOperations = metrics.filter(m => m.dataSize && m.dataSize > 10000);
    if (largeDataOperations.length > 0) {
      recommendations.push('Large data operations detected. Consider chunking or compression.');
    }

    // Check recent performance trends
    const recentMetrics = metrics.slice(0, 50);
    const olderMetrics = metrics.slice(50, 100);
    
    if (recentMetrics.length > 10 && olderMetrics.length > 10) {
      const recentAvg = recentMetrics.reduce((sum, m) => sum + m.duration, 0) / recentMetrics.length;
      const olderAvg = olderMetrics.reduce((sum, m) => sum + m.duration, 0) / olderMetrics.length;
      
      if (recentAvg > olderAvg * 1.5) {
        recommendations.push('Performance has degraded recently. Check for memory leaks or key storage issues.');
      }
    }

    // Check operation frequency
    const operationCounts = new Map<string, number>();
    metrics.forEach(m => {
      operationCounts.set(m.operation, (operationCounts.get(m.operation) || 0) + 1);
    });

    const highFrequencyOps = Array.from(operationCounts.entries())
      .filter(([, count]) => count > 100)
      .map(([op]) => op);

    if (highFrequencyOps.length > 0) {
      recommendations.push(`High-frequency operations detected: ${highFrequencyOps.join(', ')}. Consider caching or optimization.`);
    }

    return recommendations;
  }

  /**
   * Get metrics for a specific time period
   */
  getMetricsInPeriod(startTime: Date, endTime: Date): PerformanceMetric[] {
    return this.metrics.filter(metric => {
      const metricTime = new Date(metric.timestamp);
      return metricTime >= startTime && metricTime <= endTime;
    });
  }

  /**
   * Clear all metrics
   */
  clearMetrics(): void {
    this.metrics = [];
  }

  /**
   * Export metrics for analysis
   */
  exportMetrics(): string {
    return JSON.stringify({
      exportedAt: new Date().toISOString(),
      metrics: this.metrics,
      stats: this.getStats()
    }, null, 2);
  }

  /**
   * Get real-time performance summary
   */
  getRealTimeSummary(): {
    currentLoad: 'low' | 'medium' | 'high';
    recentOperations: number;
    averageLatency: number;
    errorRate: number;
  } {
    // Get metrics from last 5 minutes
    const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
    const recentMetrics = this.getMetricsInPeriod(fiveMinutesAgo, new Date());

    const recentOperations = recentMetrics.length;
    const averageLatency = recentMetrics.length > 0 
      ? recentMetrics.reduce((sum, m) => sum + m.duration, 0) / recentMetrics.length
      : 0;

    const errorRate = recentMetrics.length > 0
      ? (recentMetrics.filter(m => !m.success).length / recentMetrics.length) * 100
      : 0;

    let currentLoad: 'low' | 'medium' | 'high' = 'low';
    if (recentOperations > 50 || averageLatency > 1000) {
      currentLoad = 'high';
    } else if (recentOperations > 20 || averageLatency > 500) {
      currentLoad = 'medium';
    }

    return {
      currentLoad,
      recentOperations,
      averageLatency,
      errorRate
    };
  }

  /**
   * Check if system needs optimization
   */
  needsOptimization(): {
    needed: boolean;
    priority: 'low' | 'medium' | 'high';
    reasons: string[];
  } {
    const stats = this.getStats();
    const realtimeSummary = this.getRealTimeSummary();
    const reasons: string[] = [];
    let priority: 'low' | 'medium' | 'high' = 'low';

    // Check various performance indicators
    if (stats.averageDuration > 1000) {
      reasons.push('Average operation time exceeds 1 second');
      priority = 'high';
    } else if (stats.averageDuration > 500) {
      reasons.push('Average operation time is above optimal threshold');
      priority = priority === 'low' ? 'medium' : priority;
    }

    if (stats.successRate < 90) {
      reasons.push('Success rate is critically low');
      priority = 'high';
    } else if (stats.successRate < 95) {
      reasons.push('Success rate is below recommended threshold');
      priority = priority === 'low' ? 'medium' : priority;
    }

    if (realtimeSummary.currentLoad === 'high') {
      reasons.push('Current system load is high');
      priority = priority === 'low' ? 'medium' : priority;
    }

    if (realtimeSummary.errorRate > 10) {
      reasons.push('Error rate is elevated');
      priority = 'high';
    }

    return {
      needed: reasons.length > 0,
      priority,
      reasons
    };
  }
}

// Global instance
export const e2eePerformanceMonitor = new E2EEPerformanceMonitor();