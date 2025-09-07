/**
 * Logger Service for Frontend Applications
 * 
 * Provides structured logging with environment-aware output.
 * Logs are only shown in development environment.
 */

export enum LogLevel {
    DEBUG = 0,
    INFO = 1,
    WARN = 2,
    ERROR = 3,
}

interface LogEntry {
    level: LogLevel;
    message: string;
    data?: any;
    timestamp: Date;
    component?: string;
    category?: string;
}

class LoggerService {
    private isDevelopment: boolean;
    private minLogLevel: LogLevel;
    private logHistory: LogEntry[] = [];
    private maxHistorySize: number = 1000;

    constructor() {
        // Check if we're in development environment
        this.isDevelopment = import.meta.env.DEV || 
                           import.meta.env.MODE === 'development' ||
                           window.location.hostname === 'localhost' ||
                           window.location.hostname === '127.0.0.1';
        
        // Set minimum log level based on environment
        this.minLogLevel = this.isDevelopment ? LogLevel.DEBUG : LogLevel.ERROR;
    }

    /**
     * Check if logging is enabled for the given level
     */
    private shouldLog(level: LogLevel): boolean {
        return level >= this.minLogLevel;
    }

    /**
     * Add log entry to history (always stored, regardless of environment)
     */
    private addToHistory(entry: LogEntry): void {
        this.logHistory.push(entry);
        
        // Maintain max history size
        if (this.logHistory.length > this.maxHistorySize) {
            this.logHistory.shift();
        }
    }

    /**
     * Format log message with emoji and metadata
     */
    private formatMessage(level: LogLevel, message: string, component?: string): string {
        const levelEmojis = {
            [LogLevel.DEBUG]: '🔍',
            [LogLevel.INFO]: 'ℹ️',
            [LogLevel.WARN]: '⚠️',
            [LogLevel.ERROR]: '❌',
        };

        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        const componentPrefix = component ? `[${component}] ` : '';
        
        return `${levelEmojis[level]} ${timestamp} ${componentPrefix}${message}`;
    }

    /**
     * Log debug message
     */
    debug(message: string, data?: any, component?: string): void {
        const entry: LogEntry = {
            level: LogLevel.DEBUG,
            message,
            data,
            timestamp: new Date(),
            component,
            category: 'debug'
        };

        this.addToHistory(entry);

        if (this.shouldLog(LogLevel.DEBUG)) {
            const formattedMessage = this.formatMessage(LogLevel.DEBUG, message, component);
            if (data !== undefined) {
                console.debug(formattedMessage, data);
            } else {
                console.debug(formattedMessage);
            }
        }
    }

    /**
     * Log info message
     */
    info(message: string, data?: any, component?: string): void {
        const entry: LogEntry = {
            level: LogLevel.INFO,
            message,
            data,
            timestamp: new Date(),
            component,
            category: 'info'
        };

        this.addToHistory(entry);

        if (this.shouldLog(LogLevel.INFO)) {
            const formattedMessage = this.formatMessage(LogLevel.INFO, message, component);
            if (data !== undefined) {
                console.info(formattedMessage, data);
            } else {
                console.info(formattedMessage);
            }
        }
    }

    /**
     * Log warning message
     */
    warn(message: string, data?: any, component?: string): void {
        const entry: LogEntry = {
            level: LogLevel.WARN,
            message,
            data,
            timestamp: new Date(),
            component,
            category: 'warn'
        };

        this.addToHistory(entry);

        if (this.shouldLog(LogLevel.WARN)) {
            const formattedMessage = this.formatMessage(LogLevel.WARN, message, component);
            if (data !== undefined) {
                console.warn(formattedMessage, data);
            } else {
                console.warn(formattedMessage);
            }
        }
    }

    /**
     * Log error message
     */
    error(message: string, error?: any, component?: string): void {
        const entry: LogEntry = {
            level: LogLevel.ERROR,
            message,
            data: error,
            timestamp: new Date(),
            component,
            category: 'error'
        };

        this.addToHistory(entry);

        if (this.shouldLog(LogLevel.ERROR)) {
            const formattedMessage = this.formatMessage(LogLevel.ERROR, message, component);
            if (error !== undefined) {
                console.error(formattedMessage, error);
            } else {
                console.error(formattedMessage);
            }
        }
    }

    /**
     * WebSocket-specific logging methods with consistent emojis
     */
    websocket = {
        event: (eventName: string, data?: any) => {
            const eventEmojis: Record<string, string> = {
                'message.sent': '🎉',
                'message.edited': '✏️',
                'message.deleted': '🗑️',
                'reaction.added': '😀',
                'reaction.removed': '😐',
                'participant.joined': '👥',
                'participant.left': '👋',
                'typing.start': '⌨️',
                'typing.stop': '⏹️',
                'presence.updated': '🟢',
                'connection.opened': '🔌',
                'connection.closed': '🔌❌',
                'connection.error': '🔌⚠️',
            };

            const emoji = eventEmojis[eventName] || '🔔';
            this.debug(`${emoji} WebSocket Event: ${eventName}`, data, 'WebSocket');
        },

        connection: (status: string, data?: any) => {
            const statusEmojis: Record<string, string> = {
                'connecting': '🔌⏳',
                'connected': '🔌✅',
                'disconnected': '🔌❌',
                'reconnecting': '🔌🔄',
                'error': '🔌⚠️',
            };

            const emoji = statusEmojis[status] || '🔌';
            this.info(`${emoji} WebSocket ${status}`, data, 'WebSocket');
        },

        message: (action: string, messageData?: any) => {
            const actionEmojis: Record<string, string> = {
                'sent': '📤',
                'received': '📥',
                'encrypted': '🔒',
                'decrypted': '🔓',
                'failed': '💥',
            };

            const emoji = actionEmojis[action] || '📨';
            this.debug(`${emoji} Message ${action}`, messageData, 'WebSocket');
        }
    };

    /**
     * Chat-specific logging methods
     */
    chat = {
        encryption: (action: string, data?: any) => {
            const actionEmojis: Record<string, string> = {
                'encrypting': '🔒⏳',
                'encrypted': '🔒✅',
                'decrypting': '🔓⏳', 
                'decrypted': '🔓✅',
                'failed': '🔒❌',
                'quantum': '🔒🔬',
            };

            const emoji = actionEmojis[action] || '🔐';
            this.debug(`${emoji} Encryption ${action}`, data, 'E2EE');
        },

        conversation: (action: string, data?: any) => {
            const actionEmojis: Record<string, string> = {
                'loaded': '💬✅',
                'created': '💬➕',
                'joined': '💬👥',
                'left': '💬👋',
                'subscribed': '💬🔔',
                'unsubscribed': '💬🔕',
            };

            const emoji = actionEmojis[action] || '💬';
            this.debug(`${emoji} Conversation ${action}`, data, 'Chat');
        },

        device: (action: string, data?: any) => {
            const actionEmojis: Record<string, string> = {
                'registered': '📱✅',
                'trusted': '📱🤝',
                'revoked': '📱❌',
                'rotated': '📱🔄',
            };

            const emoji = actionEmojis[action] || '📱';
            this.info(`${emoji} Device ${action}`, data, 'DeviceManager');
        }
    };

    /**
     * Get recent log history (useful for debugging)
     */
    getHistory(limit?: number, level?: LogLevel): LogEntry[] {
        let filtered = this.logHistory;
        
        if (level !== undefined) {
            filtered = filtered.filter(entry => entry.level >= level);
        }
        
        if (limit) {
            filtered = filtered.slice(-limit);
        }
        
        return filtered;
    }

    /**
     * Clear log history
     */
    clearHistory(): void {
        this.logHistory = [];
    }

    /**
     * Export logs as JSON (useful for debugging)
     */
    exportLogs(): string {
        return JSON.stringify(this.logHistory, null, 2);
    }

    /**
     * Get environment info
     */
    getEnvironmentInfo() {
        return {
            isDevelopment: this.isDevelopment,
            minLogLevel: LogLevel[this.minLogLevel],
            historySize: this.logHistory.length,
            maxHistorySize: this.maxHistorySize,
        };
    }
}

// Create and export singleton instance
export const logger = new LoggerService();

// Export for debugging in development
if (typeof window !== 'undefined' && logger.getEnvironmentInfo().isDevelopment) {
    (window as any).logger = logger;
}

export default logger;