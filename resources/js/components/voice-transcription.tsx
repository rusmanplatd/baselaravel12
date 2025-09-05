import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, Volume2, FileText, RefreshCw, Trash2, Copy, Search } from 'lucide-react';
import { useVoiceTranscription, VoiceTranscription } from '@/hooks/useVoiceTranscription';
import { toast } from 'sonner';

interface VoiceTranscriptionProps {
  messageId: string;
  message: any;
  onTranscriptUpdate?: (transcript: string) => void;
  showFullControls?: boolean;
}

export const VoiceTranscriptionComponent: React.FC<VoiceTranscriptionProps> = ({
  messageId,
  message,
  onTranscriptUpdate,
  showFullControls = true,
}) => {
  const [transcription, setTranscription] = useState<VoiceTranscription | null>(null);
  const [pollingInterval, setPollingInterval] = useState<NodeJS.Timeout | null>(null);
  
  const {
    loading,
    error,
    transcribeMessage,
    getTranscriptionStatus,
    retryTranscription,
    deleteTranscription,
    isVoiceMessage,
    formatDuration,
    getConfidenceColor,
    getStatusColor,
    getStatusIcon,
  } = useVoiceTranscription();

  // Check if this is a voice message
  if (!isVoiceMessage(message)) {
    return null;
  }

  useEffect(() => {
    // Check for existing transcription status
    checkTranscriptionStatus();
  }, [messageId]);

  useEffect(() => {
    // Start polling for processing status
    if (transcription?.status === 'processing' || transcription?.status === 'pending') {
      const interval = setInterval(checkTranscriptionStatus, 2000);
      setPollingInterval(interval);
      
      return () => clearInterval(interval);
    } else if (pollingInterval) {
      clearInterval(pollingInterval);
      setPollingInterval(null);
    }
  }, [transcription?.status]);

  useEffect(() => {
    // Notify parent component when transcript is available
    if (transcription?.transcript && onTranscriptUpdate) {
      onTranscriptUpdate(transcription.transcript);
    }
  }, [transcription?.transcript, onTranscriptUpdate]);

  const checkTranscriptionStatus = async () => {
    try {
      const result = await getTranscriptionStatus(messageId);
      if (result.transcription) {
        setTranscription(result.transcription);
      }
    } catch (err) {
      // Silently handle errors for polling
      console.warn('Failed to check transcription status:', err);
    }
  };

  const handleTranscribe = async () => {
    try {
      const result = await transcribeMessage(messageId);
      setTranscription(result);
      toast.success('Transcription started');
    } catch (err) {
      toast.error('Failed to start transcription');
    }
  };

  const handleRetry = async () => {
    if (!transcription) return;
    
    try {
      const result = await retryTranscription(transcription.id);
      setTranscription(result);
      toast.success('Transcription retry started');
    } catch (err) {
      toast.error('Failed to retry transcription');
    }
  };

  const handleDelete = async () => {
    if (!transcription) return;
    
    if (!confirm('Are you sure you want to delete this transcription?')) {
      return;
    }
    
    try {
      await deleteTranscription(transcription.id);
      setTranscription(null);
      toast.success('Transcription deleted');
    } catch (err) {
      toast.error('Failed to delete transcription');
    }
  };

  const handleCopyTranscript = async () => {
    if (!transcription?.transcript) return;
    
    try {
      await navigator.clipboard.writeText(transcription.transcript);
      toast.success('Transcript copied to clipboard');
    } catch (err) {
      toast.error('Failed to copy transcript');
    }
  };

  const renderTranscriptionStatus = () => {
    if (!transcription) {
      return (
        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
          <div className="flex items-center space-x-2">
            <Volume2 className="h-4 w-4 text-gray-500" />
            <span className="text-sm text-gray-600">Voice message</span>
          </div>
          <Button
            onClick={handleTranscribe}
            disabled={loading}
            size="sm"
            variant="outline"
          >
            {loading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-1" />
            ) : (
              <FileText className="h-4 w-4 mr-1" />
            )}
            Transcribe
          </Button>
        </div>
      );
    }

    const statusColor = getStatusColor(transcription.status);
    const statusIcon = getStatusIcon(transcription.status);

    return (
      <Card className="mt-2">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center justify-between text-sm">
            <div className="flex items-center space-x-2">
              <FileText className="h-4 w-4" />
              <span>Voice Transcription</span>
              <Badge variant="secondary" className={statusColor}>
                {statusIcon} {transcription.status}
              </Badge>
            </div>
            
            {showFullControls && (
              <div className="flex items-center space-x-1">
                {transcription.transcript && (
                  <Button
                    onClick={handleCopyTranscript}
                    size="sm"
                    variant="ghost"
                    title="Copy transcript"
                  >
                    <Copy className="h-4 w-4" />
                  </Button>
                )}
                
                {transcription.can_retry && (
                  <Button
                    onClick={handleRetry}
                    size="sm"
                    variant="ghost"
                    disabled={loading}
                    title="Retry transcription"
                  >
                    <RefreshCw className="h-4 w-4" />
                  </Button>
                )}
                
                <Button
                  onClick={handleDelete}
                  size="sm"
                  variant="ghost"
                  title="Delete transcription"
                  className="text-red-500 hover:text-red-700"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            )}
          </CardTitle>
        </CardHeader>
        
        <CardContent className="pt-0">
          {transcription.status === 'processing' && (
            <div className="flex items-center space-x-2 text-blue-600">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span className="text-sm">Processing audio...</span>
            </div>
          )}
          
          {transcription.status === 'pending' && (
            <div className="flex items-center space-x-2 text-yellow-600">
              <div className="h-4 w-4 border-2 border-yellow-300 border-t-yellow-600 rounded-full animate-spin" />
              <span className="text-sm">Queued for processing...</span>
            </div>
          )}
          
          {transcription.status === 'failed' && (
            <div className="space-y-2">
              <div className="text-red-600 text-sm">
                Transcription failed: {transcription.error_message}
              </div>
              {transcription.can_retry && (
                <Button
                  onClick={handleRetry}
                  size="sm"
                  disabled={loading}
                  className="text-red-600 border-red-300"
                  variant="outline"
                >
                  <RefreshCw className="h-4 w-4 mr-1" />
                  Retry
                </Button>
              )}
            </div>
          )}
          
          {transcription.status === 'completed' && transcription.transcript && (
            <div className="space-y-3">
              <div className="bg-white p-3 rounded border text-sm">
                {transcription.transcript}
              </div>
              
              <div className="flex items-center justify-between text-xs text-gray-500">
                <div className="flex items-center space-x-4">
                  {transcription.language && (
                    <span>Language: {transcription.language.toUpperCase()}</span>
                  )}
                  
                  {transcription.confidence_percentage && (
                    <span className={getConfidenceColor(transcription.confidence_percentage)}>
                      Confidence: {transcription.confidence_percentage}%
                    </span>
                  )}
                  
                  {transcription.duration_formatted && (
                    <span>Duration: {transcription.duration_formatted}</span>
                  )}
                  
                  {transcription.word_count > 0 && (
                    <span>Words: {transcription.word_count}</span>
                  )}
                </div>
                
                <div className="flex items-center space-x-2">
                  <span>Provider: {transcription.provider}</span>
                  {transcription.processing_time && (
                    <span>Processed in {transcription.processing_time}s</span>
                  )}
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    );
  };

  return renderTranscriptionStatus();
};

// Bulk transcription component
interface BulkTranscriptionProps {
  messageIds: string[];
  onComplete?: (results: any) => void;
}

export const BulkTranscriptionComponent: React.FC<BulkTranscriptionProps> = ({
  messageIds,
  onComplete,
}) => {
  const [results, setResults] = useState<any>(null);
  const { loading, bulkTranscribe } = useVoiceTranscription();

  const handleBulkTranscribe = async () => {
    try {
      const bulkResults = await bulkTranscribe(messageIds);
      setResults(bulkResults);
      
      if (onComplete) {
        onComplete(bulkResults);
      }
      
      toast.success(`Bulk transcription completed: ${bulkResults.summary.successful}/${bulkResults.summary.total} successful`);
    } catch (err) {
      toast.error('Bulk transcription failed');
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <Volume2 className="h-5 w-5" />
          <span>Bulk Voice Transcription</span>
        </CardTitle>
      </CardHeader>
      
      <CardContent>
        <div className="space-y-4">
          <div className="text-sm text-gray-600">
            {messageIds.length} voice message{messageIds.length !== 1 ? 's' : ''} selected for transcription
          </div>
          
          <Button
            onClick={handleBulkTranscribe}
            disabled={loading || messageIds.length === 0}
            className="w-full"
          >
            {loading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : (
              <FileText className="h-4 w-4 mr-2" />
            )}
            Start Bulk Transcription
          </Button>
          
          {results && (
            <div className="space-y-2 p-3 bg-gray-50 rounded">
              <div className="font-medium text-sm">Transcription Results:</div>
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div className="text-center">
                  <div className="font-bold text-green-600">{results.summary.successful}</div>
                  <div>Successful</div>
                </div>
                <div className="text-center">
                  <div className="font-bold text-red-600">{results.summary.failed}</div>
                  <div>Failed</div>
                </div>
                <div className="text-center">
                  <div className="font-bold">{results.summary.total}</div>
                  <div>Total</div>
                </div>
              </div>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};

// Transcription search component
interface TranscriptionSearchProps {
  onResults?: (results: any) => void;
}

export const TranscriptionSearchComponent: React.FC<TranscriptionSearchProps> = ({
  onResults,
}) => {
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState({
    language: '',
    min_confidence: '',
  });
  const [results, setResults] = useState<any>(null);
  
  const { loading, searchTranscriptions } = useVoiceTranscription();

  const handleSearch = async () => {
    if (!query.trim()) {
      toast.error('Please enter a search query');
      return;
    }

    try {
      const searchResults = await searchTranscriptions({
        query: query.trim(),
        language: filters.language || undefined,
        min_confidence: filters.min_confidence ? Number(filters.min_confidence) : undefined,
      });
      
      setResults(searchResults);
      
      if (onResults) {
        onResults(searchResults);
      }
    } catch (err) {
      toast.error('Search failed');
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <Search className="h-5 w-5" />
          <span>Search Voice Transcriptions</span>
        </CardTitle>
      </CardHeader>
      
      <CardContent>
        <div className="space-y-4">
          <div>
            <input
              type="text"
              placeholder="Search transcripts..."
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              className="w-full px-3 py-2 border rounded-md"
              onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
            />
          </div>
          
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">Language</label>
              <select
                value={filters.language}
                onChange={(e) => setFilters({ ...filters, language: e.target.value })}
                className="w-full px-3 py-2 border rounded-md text-sm"
              >
                <option value="">Any language</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
                <option value="ru">Russian</option>
                <option value="ja">Japanese</option>
                <option value="ko">Korean</option>
                <option value="zh">Chinese</option>
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium mb-1">Min Confidence</label>
              <select
                value={filters.min_confidence}
                onChange={(e) => setFilters({ ...filters, min_confidence: e.target.value })}
                className="w-full px-3 py-2 border rounded-md text-sm"
              >
                <option value="">Any confidence</option>
                <option value="90">90%+</option>
                <option value="80">80%+</option>
                <option value="70">70%+</option>
                <option value="60">60%+</option>
              </select>
            </div>
          </div>
          
          <Button
            onClick={handleSearch}
            disabled={loading || !query.trim()}
            className="w-full"
          >
            {loading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : (
              <Search className="h-4 w-4 mr-2" />
            )}
            Search Transcriptions
          </Button>
          
          {results && (
            <div className="space-y-2">
              <div className="text-sm text-gray-600">
                Found {results.pagination.total} results
              </div>
              
              <div className="max-h-96 overflow-y-auto space-y-2">
                {results.transcriptions.map((transcription: any) => (
                  <div key={transcription.id} className="p-3 border rounded-md">
                    <div className="text-sm">{transcription.transcript}</div>
                    <div className="text-xs text-gray-500 mt-1">
                      {transcription.language?.toUpperCase()} • 
                      {transcription.confidence_percentage}% confidence • 
                      {new Date(transcription.created_at).toLocaleDateString()}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};