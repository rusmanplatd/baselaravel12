import { useState, useCallback } from 'react';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';

export interface ExportFormat {
  id: string;
  name: string;
  description: string;
  extension: string;
  mimeType: string;
  supportsEncryption: boolean;
  supportsMedia: boolean;
}

export interface ExportOptions {
  format: string;
  includeMedia: boolean;
  includeReactions: boolean;
  includeReadReceipts: boolean;
  includeThreads: boolean;
  dateRange?: {
    start: Date;
    end: Date;
  };
  participantFilter?: string[];
  encryptExport: boolean;
  password?: string;
  compressionLevel: 'none' | 'standard' | 'maximum';
}

export interface ExportJob {
  id: string;
  conversation_id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  progress: number;
  format: string;
  options: ExportOptions;
  file_size?: number;
  file_path?: string;
  download_url?: string;
  expires_at: string;
  created_at: string;
  error_message?: string;
  stats: {
    total_messages: number;
    processed_messages: number;
    total_media_files: number;
    processed_media_files: number;
    encrypted_messages: number;
    failed_decryptions: number;
  };
}

export interface BackupJob {
  id: string;
  user_id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  progress: number;
  backup_type: 'full' | 'incremental' | 'conversations' | 'media';
  file_size?: number;
  file_path?: string;
  download_url?: string;
  expires_at: string;
  created_at: string;
  encryption_key_id?: string;
  stats: {
    total_conversations: number;
    processed_conversations: number;
    total_messages: number;
    processed_messages: number;
    total_media_size: number;
    processed_media_size: number;
  };
}

interface UseMessageExportReturn {
  // Export functionality
  availableFormats: ExportFormat[];
  exportJobs: ExportJob[];
  isExporting: boolean;
  exportProgress: number;
  
  // Backup functionality
  backupJobs: BackupJob[];
  isBackingUp: boolean;
  backupProgress: number;
  
  // Actions
  startExport: (conversationId: string, options: ExportOptions) => Promise<ExportJob>;
  cancelExport: (jobId: string) => Promise<void>;
  downloadExport: (jobId: string) => Promise<void>;
  deleteExport: (jobId: string) => Promise<void>;
  
  startBackup: (backupType: BackupJob['backup_type'], options?: Partial<ExportOptions>) => Promise<BackupJob>;
  cancelBackup: (jobId: string) => Promise<void>;
  downloadBackup: (jobId: string) => Promise<void>;
  deleteBackup: (jobId: string) => Promise<void>;
  
  // Data management
  getExportJobs: () => Promise<void>;
  getBackupJobs: () => Promise<void>;
  cleanupExpiredJobs: () => Promise<void>;
  
  // Statistics
  getExportStats: () => Promise<{
    total_exports: number;
    total_backups: number;
    storage_used: number;
    oldest_export: string;
  }>;
}

export const useMessageExport = (): UseMessageExportReturn => {
  const [exportJobs, setExportJobs] = useState<ExportJob[]>([]);
  const [backupJobs, setBackupJobs] = useState<BackupJob[]>([]);
  const [isExporting, setIsExporting] = useState(false);
  const [isBackingUp, setIsBackingUp] = useState(false);
  const [exportProgress, setExportProgress] = useState(0);
  const [backupProgress, setBackupProgress] = useState(0);

  // Available export formats
  const availableFormats: ExportFormat[] = [
    {
      id: 'json',
      name: 'JSON',
      description: 'Machine-readable format with full metadata',
      extension: '.json',
      mimeType: 'application/json',
      supportsEncryption: true,
      supportsMedia: true,
    },
    {
      id: 'html',
      name: 'HTML',
      description: 'Human-readable web page format',
      extension: '.html',
      mimeType: 'text/html',
      supportsEncryption: false,
      supportsMedia: true,
    },
    {
      id: 'pdf',
      name: 'PDF',
      description: 'Printable document format',
      extension: '.pdf',
      mimeType: 'application/pdf',
      supportsEncryption: true,
      supportsMedia: true,
    },
    {
      id: 'txt',
      name: 'Text',
      description: 'Plain text format (messages only)',
      extension: '.txt',
      mimeType: 'text/plain',
      supportsEncryption: false,
      supportsMedia: false,
    },
    {
      id: 'csv',
      name: 'CSV',
      description: 'Spreadsheet format for analysis',
      extension: '.csv',
      mimeType: 'text/csv',
      supportsEncryption: false,
      supportsMedia: false,
    },
    {
      id: 'encrypted-archive',
      name: 'Encrypted Archive',
      description: 'Password-protected ZIP with full E2EE preservation',
      extension: '.enc.zip',
      mimeType: 'application/zip',
      supportsEncryption: true,
      supportsMedia: true,
    },
  ];

  // API wrapper
  const apiCall = useCallback(async (url: string, options: RequestInit = {}): Promise<any> => {
    const deviceFingerprint = getDeviceFingerprint();
    
    const response = await fetch(`/api/v1/export${url}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Device-Fingerprint': deviceFingerprint,
        ...options.headers,
      },
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'API call failed');
    }

    return await response.json();
  }, []);

  // Start export
  const startExport = useCallback(async (
    conversationId: string, 
    options: ExportOptions
  ): Promise<ExportJob> => {
    setIsExporting(true);
    setExportProgress(0);

    try {
      const response = await apiCall('/conversations/export', {
        method: 'POST',
        body: JSON.stringify({
          conversation_id: conversationId,
          ...options,
        }),
      });

      const job = response.export_job;
      setExportJobs(prev => [job, ...prev]);

      // Start polling for progress updates
      pollExportProgress(job.id);

      return job;
    } catch (error) {
      setIsExporting(false);
      throw error;
    }
  }, [apiCall]);

  // Poll export progress
  const pollExportProgress = useCallback(async (jobId: string) => {
    const poll = async () => {
      try {
        const response = await apiCall(`/jobs/${jobId}`);
        const job = response.job;
        
        setExportProgress(job.progress);
        setExportJobs(prev => prev.map(j => j.id === jobId ? job : j));

        if (job.status === 'processing' && job.progress < 100) {
          setTimeout(poll, 2000); // Poll every 2 seconds
        } else {
          setIsExporting(false);
          setExportProgress(0);
        }
      } catch (error) {
        console.error('Failed to poll export progress:', error);
        setIsExporting(false);
        setExportProgress(0);
      }
    };

    poll();
  }, [apiCall]);

  // Cancel export
  const cancelExport = useCallback(async (jobId: string): Promise<void> => {
    try {
      await apiCall(`/jobs/${jobId}/cancel`, {
        method: 'POST',
      });

      setExportJobs(prev => prev.map(job => 
        job.id === jobId 
          ? { ...job, status: 'failed', error_message: 'Cancelled by user' }
          : job
      ));

      setIsExporting(false);
      setExportProgress(0);
    } catch (error) {
      console.error('Failed to cancel export:', error);
      throw error;
    }
  }, [apiCall]);

  // Download export
  const downloadExport = useCallback(async (jobId: string): Promise<void> => {
    try {
      const job = exportJobs.find(j => j.id === jobId);
      if (!job || !job.download_url) {
        throw new Error('Export not available for download');
      }

      // Create download link
      const link = document.createElement('a');
      link.href = job.download_url;
      link.download = `export-${job.conversation_id}-${job.format}.${getFormatExtension(job.format)}`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (error) {
      console.error('Failed to download export:', error);
      throw error;
    }
  }, [exportJobs]);

  // Delete export
  const deleteExport = useCallback(async (jobId: string): Promise<void> => {
    try {
      await apiCall(`/jobs/${jobId}`, {
        method: 'DELETE',
      });

      setExportJobs(prev => prev.filter(job => job.id !== jobId));
    } catch (error) {
      console.error('Failed to delete export:', error);
      throw error;
    }
  }, [apiCall]);

  // Start backup
  const startBackup = useCallback(async (
    backupType: BackupJob['backup_type'],
    options: Partial<ExportOptions> = {}
  ): Promise<BackupJob> => {
    setIsBackingUp(true);
    setBackupProgress(0);

    try {
      const response = await apiCall('/backup', {
        method: 'POST',
        body: JSON.stringify({
          backup_type: backupType,
          ...options,
        }),
      });

      const job = response.backup_job;
      setBackupJobs(prev => [job, ...prev]);

      // Start polling for progress updates
      pollBackupProgress(job.id);

      return job;
    } catch (error) {
      setIsBackingUp(false);
      throw error;
    }
  }, [apiCall]);

  // Poll backup progress
  const pollBackupProgress = useCallback(async (jobId: string) => {
    const poll = async () => {
      try {
        const response = await apiCall(`/backup/${jobId}`);
        const job = response.job;
        
        setBackupProgress(job.progress);
        setBackupJobs(prev => prev.map(j => j.id === jobId ? job : j));

        if (job.status === 'processing' && job.progress < 100) {
          setTimeout(poll, 3000); // Poll every 3 seconds for backups
        } else {
          setIsBackingUp(false);
          setBackupProgress(0);
        }
      } catch (error) {
        console.error('Failed to poll backup progress:', error);
        setIsBackingUp(false);
        setBackupProgress(0);
      }
    };

    poll();
  }, [apiCall]);

  // Cancel backup
  const cancelBackup = useCallback(async (jobId: string): Promise<void> => {
    try {
      await apiCall(`/backup/${jobId}/cancel`, {
        method: 'POST',
      });

      setBackupJobs(prev => prev.map(job => 
        job.id === jobId 
          ? { ...job, status: 'failed', error_message: 'Cancelled by user' }
          : job
      ));

      setIsBackingUp(false);
      setBackupProgress(0);
    } catch (error) {
      console.error('Failed to cancel backup:', error);
      throw error;
    }
  }, [apiCall]);

  // Download backup
  const downloadBackup = useCallback(async (jobId: string): Promise<void> => {
    try {
      const job = backupJobs.find(j => j.id === jobId);
      if (!job || !job.download_url) {
        throw new Error('Backup not available for download');
      }

      const link = document.createElement('a');
      link.href = job.download_url;
      link.download = `backup-${job.backup_type}-${new Date(job.created_at).toISOString().split('T')[0]}.enc.zip`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (error) {
      console.error('Failed to download backup:', error);
      throw error;
    }
  }, [backupJobs]);

  // Delete backup
  const deleteBackup = useCallback(async (jobId: string): Promise<void> => {
    try {
      await apiCall(`/backup/${jobId}`, {
        method: 'DELETE',
      });

      setBackupJobs(prev => prev.filter(job => job.id !== jobId));
    } catch (error) {
      console.error('Failed to delete backup:', error);
      throw error;
    }
  }, [apiCall]);

  // Get export jobs
  const getExportJobs = useCallback(async (): Promise<void> => {
    try {
      const response = await apiCall('/jobs');
      setExportJobs(response.export_jobs || []);
    } catch (error) {
      console.error('Failed to get export jobs:', error);
    }
  }, [apiCall]);

  // Get backup jobs
  const getBackupJobs = useCallback(async (): Promise<void> => {
    try {
      const response = await apiCall('/backup');
      setBackupJobs(response.backup_jobs || []);
    } catch (error) {
      console.error('Failed to get backup jobs:', error);
    }
  }, [apiCall]);

  // Cleanup expired jobs
  const cleanupExpiredJobs = useCallback(async (): Promise<void> => {
    try {
      await apiCall('/cleanup', {
        method: 'POST',
      });
      
      // Refresh both lists
      await Promise.all([getExportJobs(), getBackupJobs()]);
    } catch (error) {
      console.error('Failed to cleanup expired jobs:', error);
    }
  }, [apiCall, getExportJobs, getBackupJobs]);

  // Get statistics
  const getExportStats = useCallback(async () => {
    try {
      const response = await apiCall('/stats');
      return response.stats;
    } catch (error) {
      console.error('Failed to get export stats:', error);
      return {
        total_exports: 0,
        total_backups: 0,
        storage_used: 0,
        oldest_export: new Date().toISOString(),
      };
    }
  }, [apiCall]);

  return {
    // Export functionality
    availableFormats,
    exportJobs,
    isExporting,
    exportProgress,
    
    // Backup functionality
    backupJobs,
    isBackingUp,
    backupProgress,
    
    // Actions
    startExport,
    cancelExport,
    downloadExport,
    deleteExport,
    
    startBackup,
    cancelBackup,
    downloadBackup,
    deleteBackup,
    
    // Data management
    getExportJobs,
    getBackupJobs,
    cleanupExpiredJobs,
    
    // Statistics
    getExportStats,
  };
};

// Helper functions
function getDeviceFingerprint(): string {
  let fingerprint = getUserStorageItem('device_fingerprint');
  if (!fingerprint) {
    fingerprint = generateDeviceFingerprint();
    setUserStorageItem('device_fingerprint', fingerprint);
  }
  return fingerprint;
}

function generateDeviceFingerprint(): string {
  const components = [
    navigator.userAgent,
    navigator.language,
    navigator.platform,
    navigator.hardwareConcurrency?.toString() || '',
    screen.width + 'x' + screen.height,
    new Date().getTimezoneOffset().toString(),
  ];
  
  const combined = components.join('|');
  return btoa(combined).replace(/[+/=]/g, '').substring(0, 16);
}

function getFormatExtension(format: string): string {
  const formatMap: Record<string, string> = {
    'json': 'json',
    'html': 'html',
    'pdf': 'pdf',
    'txt': 'txt',
    'csv': 'csv',
    'encrypted-archive': 'enc.zip',
  };
  
  return formatMap[format] || 'unknown';
}