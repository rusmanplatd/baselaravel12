import { useState, useCallback } from 'react';
import { apiService } from '@/services/ApiService';

export interface VoiceTranscription {
  id: string;
  message_id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  transcript?: string;
  language?: string;
  confidence?: number;
  confidence_percentage?: number;
  duration?: number;
  duration_formatted?: string;
  word_count: number;
  provider: string;
  error_message?: string;
  retry_count: number;
  can_retry: boolean;
  processed_at?: string;
  processing_time?: number;
  created_at: string;
  segments?: TranscriptionSegment[];
}

export interface TranscriptionSegment {
  id: number;
  start: number;
  end: number;
  text: string;
  avg_logprob: number;
  compression_ratio: number;
  no_speech_prob: number;
  words?: TranscriptionWord[];
}

export interface TranscriptionWord {
  word: string;
  start: number;
  end: number;
  probability: number;
}

export interface TranscriptionStatus {
  status: string;
  progress: number;
  error?: string;
}

export interface TranscriptionStats {
  total_transcriptions: number;
  completed: number;
  failed: number;
  processing: number;
  pending: number;
  languages: Record<string, number>;
  providers: Record<string, number>;
  average_confidence?: number;
  total_duration: number;
  total_words: number;
}

export const useVoiceTranscription = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(endpoint: string, options: RequestInit = {}): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const url = `/api/v1/voice-transcriptions/${endpoint}`;
      const method = (options.method || 'GET').toUpperCase();
      const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers as Record<string, string> | undefined),
      };

      let data: any;
      switch (method) {
        case 'GET':
          data = await apiService.get(url, { headers });
          break;
        case 'POST':
          data = await apiService.post(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'PUT':
          data = await apiService.put(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'PATCH':
          data = await apiService.patch(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'DELETE':
          data = await apiService.delete(url, { headers });
          break;
        default:
          data = await apiService.request(url, { ...options, headers });
      }

      return data as T;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred';
      setError(errorMessage);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const transcribeMessage = useCallback(async (messageId: string): Promise<VoiceTranscription> => {
    const result = await makeApiCall<{ transcription: VoiceTranscription; message: string }>('transcribe', {
      method: 'POST',
      body: JSON.stringify({ message_id: messageId }),
    });
    return result.transcription;
  }, []);

  const getTranscriptionStatus = useCallback(async (messageId: string): Promise<{
    status: TranscriptionStatus;
    transcription?: VoiceTranscription;
  }> => {
    return makeApiCall<{
      status: TranscriptionStatus;
      transcription?: VoiceTranscription;
    }>(`status/${messageId}`);
  }, []);

  const getTranscription = useCallback(async (transcriptionId: string, includeSegments = false): Promise<VoiceTranscription> => {
    const endpoint = includeSegments ? `${transcriptionId}?include_segments=true` : transcriptionId;
    const result = await makeApiCall<{ transcription: VoiceTranscription }>(endpoint);
    return result.transcription;
  }, []);

  const retryTranscription = useCallback(async (transcriptionId: string): Promise<VoiceTranscription> => {
    const result = await makeApiCall<{ transcription: VoiceTranscription; message: string }>(`${transcriptionId}/retry`, {
      method: 'POST',
    });
    return result.transcription;
  }, []);

  const deleteTranscription = useCallback(async (transcriptionId: string): Promise<void> => {
    await makeApiCall<{ message: string }>(`${transcriptionId}`, {
      method: 'DELETE',
    });
  }, []);

  const bulkTranscribe = useCallback(async (messageIds: string[]): Promise<{
    results: Record<string, { success: boolean; transcription_id?: string; error?: string }>;
    summary: { total: number; successful: number; failed: number };
  }> => {
    const result = await makeApiCall<{
      results: Record<string, { success: boolean; transcription_id?: string; error?: string }>;
      summary: { total: number; successful: number; failed: number };
      message: string;
    }>('bulk-transcribe', {
      method: 'POST',
      body: JSON.stringify({ message_ids: messageIds }),
    });

    return {
      results: result.results,
      summary: result.summary,
    };
  }, []);

  const getStatistics = useCallback(async (): Promise<TranscriptionStats> => {
    const result = await makeApiCall<{ statistics: TranscriptionStats }>('statistics');
    return result.statistics;
  }, []);

  const searchTranscriptions = useCallback(async (params: {
    query: string;
    language?: string;
    min_confidence?: number;
    page?: number;
    limit?: number;
  }): Promise<{
    transcriptions: VoiceTranscription[];
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  }> => {
    const queryParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = `search?${queryParams.toString()}`;
    return makeApiCall<{
      transcriptions: VoiceTranscription[];
      pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      };
    }>(endpoint);
  }, []);

  // Helper functions
  const isVoiceMessage = useCallback((message: any): boolean => {
    if (message.content_type === 'voice') {
      return true;
    }

    return message.attachments?.some((attachment: any) =>
      attachment.content_type?.startsWith('audio/')
    ) ?? false;
  }, []);

  const formatDuration = useCallback((seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  }, []);

  const getConfidenceColor = useCallback((confidence: number): string => {
    if (confidence >= 90) return 'text-green-600';
    if (confidence >= 70) return 'text-yellow-600';
    return 'text-red-600';
  }, []);

  const getStatusColor = useCallback((status: string): string => {
    switch (status) {
      case 'completed':
        return 'text-green-600';
      case 'processing':
        return 'text-blue-600';
      case 'pending':
        return 'text-yellow-600';
      case 'failed':
        return 'text-red-600';
      default:
        return 'text-gray-600';
    }
  }, []);

  const getStatusIcon = useCallback((status: string): string => {
    switch (status) {
      case 'completed':
        return '✓';
      case 'processing':
        return '⟳';
      case 'pending':
        return '⏱';
      case 'failed':
        return '✗';
      default:
        return '?';
    }
  }, []);

  return {
    loading,
    error,
    transcribeMessage,
    getTranscriptionStatus,
    getTranscription,
    retryTranscription,
    deleteTranscription,
    bulkTranscribe,
    getStatistics,
    searchTranscriptions,
    // Helper functions
    isVoiceMessage,
    formatDuration,
    getConfidenceColor,
    getStatusColor,
    getStatusIcon,
  };
};
