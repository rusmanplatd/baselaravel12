import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
  Video, 
  VideoOff, 
  Play,
  Pause,
  Square,
  Download,
  Share2,
  Settings,
  Clock,
  HardDrive,
  Eye,
  Users,
  Calendar,
  FileVideo,
  Trash2,
  Archive,
  Link,
  Shield,
  AlertCircle
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface MeetingRecording {
  id: string;
  recording_id: string;
  recording_name: string;
  description?: string;
  recording_type: 'full_meeting' | 'breakout_room' | 'screen_share' | 'audio_only';
  status: 'starting' | 'recording' | 'processing' | 'completed' | 'failed' | 'stopped';
  started_at: string;
  stopped_at?: string;
  processing_completed_at?: string;
  duration_seconds?: number;
  file_size?: number;
  file_format: string;
  video_resolution: string;
  layout_type: string;
  is_public: boolean;
  view_count: number;
  download_count: number;
  last_accessed_at?: string;
  auto_delete_at?: string;
  retention_policy: string;
  participant_count?: number;
}

interface RecordingSettings {
  recording_name: string;
  description: string;
  recording_type: 'full_meeting' | 'breakout_room' | 'screen_share' | 'audio_only';
  video_resolution: '720p' | '1080p' | '4k';
  video_bitrate: number;
  audio_bitrate: number;
  layout_type: 'grid' | 'speaker' | 'presentation' | 'custom';
  is_public: boolean;
  retention_policy: 'keep_forever' | 'delete_after_30_days' | 'delete_after_90_days' | 'delete_after_1_year';
}

interface MeetingRecordingControlsProps {
  meetingId: string;
  isHost?: boolean;
  canRecord?: boolean;
}

export function MeetingRecordingControls({ meetingId, isHost = false, canRecord = false }: MeetingRecordingControlsProps) {
  const [recordings, setRecordings] = useState<MeetingRecording[]>([]);
  const [currentRecording, setCurrentRecording] = useState<MeetingRecording | null>(null);
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [stopping, setStopping] = useState(false);
  const [showSettingsDialog, setShowSettingsDialog] = useState(false);
  const [showRecordingsDialog, setShowRecordingsDialog] = useState(false);
  const [selectedRecording, setSelectedRecording] = useState<MeetingRecording | null>(null);
  const [shareToken, setShareToken] = useState<string>('');
  
  const [recordingSettings, setRecordingSettings] = useState<RecordingSettings>({
    recording_name: `Meeting Recording - ${new Date().toLocaleDateString()}`,
    description: '',
    recording_type: 'full_meeting',
    video_resolution: '1080p',
    video_bitrate: 4000000,
    audio_bitrate: 128000,
    layout_type: 'grid',
    is_public: false,
    retention_policy: 'delete_after_90_days'
  });

  useEffect(() => {
    loadRecordings();
    
    // Poll for recording status updates every 5 seconds
    const interval = setInterval(() => {
      if (currentRecording && ['starting', 'recording', 'processing'].includes(currentRecording.status)) {
        loadRecordings();
      }
    }, 5000);

    return () => clearInterval(interval);
  }, [meetingId]);

  const loadRecordings = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/recordings`);
      setRecordings(response.data);
      
      // Find active recording
      const activeRecording = response.data.find((r: MeetingRecording) => 
        ['starting', 'recording', 'processing'].includes(r.status)
      );
      setCurrentRecording(activeRecording || null);
    } catch (error) {
      console.error('Failed to load recordings:', error);
    } finally {
      setLoading(false);
    }
  };

  const startRecording = async () => {
    if (!isHost || !canRecord) return;
    
    setStarting(true);
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/recordings`, recordingSettings);
      
      if (response.success) {
        await loadRecordings();
        setShowSettingsDialog(false);
      }
    } catch (error) {
      console.error('Failed to start recording:', error);
    } finally {
      setStarting(false);
    }
  };

  const stopRecording = async () => {
    if (!currentRecording || !isHost) return;
    
    setStopping(true);
    try {
      await apiService.post(`/api/meetings/${meetingId}/recordings/${currentRecording.id}/stop`);
      await loadRecordings();
    } catch (error) {
      console.error('Failed to stop recording:', error);
    } finally {
      setStopping(false);
    }
  };

  const downloadRecording = async (recordingId: string) => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/recordings/${recordingId}/download`, {
        responseType: 'blob'
      });
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `recording-${recordingId}.mp4`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      // Refresh recordings to update download count
      await loadRecordings();
    } catch (error) {
      console.error('Failed to download recording:', error);
    }
  };

  const generateShareLink = async (recordingId: string) => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/recordings/${recordingId}/share`);
      setShareToken(response.share_url);
    } catch (error) {
      console.error('Failed to generate share link:', error);
    }
  };

  const deleteRecording = async (recordingId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.delete(`/api/meetings/${meetingId}/recordings/${recordingId}`);
      await loadRecordings();
      setSelectedRecording(null);
    } catch (error) {
      console.error('Failed to delete recording:', error);
    }
  };

  const archiveRecording = async (recordingId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/recordings/${recordingId}/archive`);
      await loadRecordings();
    } catch (error) {
      console.error('Failed to archive recording:', error);
    }
  };

  const formatDuration = (seconds?: number) => {
    if (!seconds) return '00:00';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return '0 B';
    
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    
    return `${size.toFixed(1)} ${units[unitIndex]}`;
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'recording':
        return <Badge className="bg-red-500 animate-pulse">Recording</Badge>;
      case 'processing':
        return <Badge className="bg-yellow-500">Processing</Badge>;
      case 'completed':
        return <Badge className="bg-green-500">Completed</Badge>;
      case 'failed':
        return <Badge className="bg-red-500">Failed</Badge>;
      case 'starting':
        return <Badge className="bg-blue-500">Starting</Badge>;
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const completedRecordings = recordings.filter(r => r.status === 'completed');
  const isRecording = currentRecording?.status === 'recording';

  if (loading) {
    return (
      <Card>
        <CardContent className="p-6">
          <div className="animate-pulse space-y-4">
            <div className="h-4 bg-gray-200 rounded w-1/4"></div>
            <div className="h-8 bg-gray-200 rounded"></div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Video className="h-5 w-5" />
            Recording Controls
          </CardTitle>
          <div className="flex gap-2">
            {completedRecordings.length > 0 && (
              <Button variant="outline" size="sm" onClick={() => setShowRecordingsDialog(true)}>
                <FileVideo className="h-4 w-4 mr-2" />
                View Recordings ({completedRecordings.length})
              </Button>
            )}
            {isHost && canRecord && (
              <>
                {!currentRecording ? (
                  <Dialog open={showSettingsDialog} onOpenChange={setShowSettingsDialog}>
                    <DialogTrigger asChild>
                      <Button size="sm">
                        <Video className="h-4 w-4 mr-2" />
                        Start Recording
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-md">
                      <DialogHeader>
                        <DialogTitle>Recording Settings</DialogTitle>
                      </DialogHeader>
                      <div className="space-y-4">
                        <div className="space-y-2">
                          <Label>Recording Name</Label>
                          <Input
                            value={recordingSettings.recording_name}
                            onChange={(e) => setRecordingSettings(prev => ({ 
                              ...prev, 
                              recording_name: e.target.value 
                            }))}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Description</Label>
                          <Textarea
                            value={recordingSettings.description}
                            onChange={(e) => setRecordingSettings(prev => ({ 
                              ...prev, 
                              description: e.target.value 
                            }))}
                            rows={2}
                          />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                          <div className="space-y-2">
                            <Label>Recording Type</Label>
                            <Select 
                              value={recordingSettings.recording_type} 
                              onValueChange={(value: any) => setRecordingSettings(prev => ({ 
                                ...prev, 
                                recording_type: value 
                              }))}
                            >
                              <SelectTrigger>
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="full_meeting">Full Meeting</SelectItem>
                                <SelectItem value="screen_share">Screen Share Only</SelectItem>
                                <SelectItem value="audio_only">Audio Only</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                          <div className="space-y-2">
                            <Label>Video Quality</Label>
                            <Select 
                              value={recordingSettings.video_resolution} 
                              onValueChange={(value: any) => setRecordingSettings(prev => ({ 
                                ...prev, 
                                video_resolution: value 
                              }))}
                            >
                              <SelectTrigger>
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="720p">720p HD</SelectItem>
                                <SelectItem value="1080p">1080p Full HD</SelectItem>
                                <SelectItem value="4k">4K Ultra HD</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                        </div>
                        <div className="space-y-2">
                          <Label>Layout Type</Label>
                          <Select 
                            value={recordingSettings.layout_type} 
                            onValueChange={(value: any) => setRecordingSettings(prev => ({ 
                              ...prev, 
                              layout_type: value 
                            }))}
                          >
                            <SelectTrigger>
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="grid">Grid View</SelectItem>
                              <SelectItem value="speaker">Speaker Focus</SelectItem>
                              <SelectItem value="presentation">Presentation Mode</SelectItem>
                              <SelectItem value="custom">Custom Layout</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="space-y-2">
                          <Label>Retention Policy</Label>
                          <Select 
                            value={recordingSettings.retention_policy} 
                            onValueChange={(value: any) => setRecordingSettings(prev => ({ 
                              ...prev, 
                              retention_policy: value 
                            }))}
                          >
                            <SelectTrigger>
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="delete_after_30_days">Delete after 30 days</SelectItem>
                              <SelectItem value="delete_after_90_days">Delete after 90 days</SelectItem>
                              <SelectItem value="delete_after_1_year">Delete after 1 year</SelectItem>
                              <SelectItem value="keep_forever">Keep forever</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="flex items-center justify-between">
                          <Label>Make Public</Label>
                          <Switch
                            checked={recordingSettings.is_public}
                            onCheckedChange={(checked) => setRecordingSettings(prev => ({ 
                              ...prev, 
                              is_public: checked 
                            }))}
                          />
                        </div>
                        <div className="flex gap-2 pt-4">
                          <Button onClick={startRecording} disabled={starting} className="flex-1">
                            {starting ? 'Starting...' : 'Start Recording'}
                          </Button>
                          <Button variant="outline" onClick={() => setShowSettingsDialog(false)}>
                            Cancel
                          </Button>
                        </div>
                      </div>
                    </DialogContent>
                  </Dialog>
                ) : (
                  <Button 
                    size="sm" 
                    variant={isRecording ? "destructive" : "outline"}
                    onClick={stopRecording}
                    disabled={stopping}
                  >
                    <Square className="h-4 w-4 mr-2" />
                    {stopping ? 'Stopping...' : 'Stop Recording'}
                  </Button>
                )}
              </>
            )}
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {currentRecording ? (
          <Card>
            <CardContent className="p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <h4 className="font-medium">{currentRecording.recording_name}</h4>
                  <p className="text-sm text-gray-600">
                    Started: {new Date(currentRecording.started_at).toLocaleString()}
                  </p>
                </div>
                {getStatusBadge(currentRecording.status)}
              </div>
              
              {isRecording && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between text-sm">
                    <span>Duration</span>
                    <span>{formatDuration(currentRecording.duration_seconds)}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-red-500 rounded-full animate-pulse" />
                    <span className="text-sm text-red-600">Live Recording</span>
                  </div>
                </div>
              )}
              
              {currentRecording.status === 'processing' && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between text-sm">
                    <span>Processing recording...</span>
                  </div>
                  <Progress value={65} className="w-full" />
                </div>
              )}
            </CardContent>
          </Card>
        ) : (
          <div className="text-center py-8 text-gray-500">
            <VideoOff className="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p className="mb-2">No active recording</p>
            {isHost && canRecord && <p className="text-sm">Start a recording to capture this meeting</p>}
            {!canRecord && <p className="text-sm flex items-center justify-center gap-2">
              <Shield className="h-4 w-4" />
              Recording permission required
            </p>}
          </div>
        )}

        {/* Recordings Dialog */}
        <Dialog open={showRecordingsDialog} onOpenChange={setShowRecordingsDialog}>
          <DialogContent className="max-w-4xl">
            <DialogHeader>
              <DialogTitle>Meeting Recordings</DialogTitle>
            </DialogHeader>
            <ScrollArea className="max-h-[600px]">
              <div className="space-y-4">
                {completedRecordings.map((recording) => (
                  <Card key={recording.id}>
                    <CardContent className="p-4">
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-2">
                            <h4 className="font-medium">{recording.recording_name}</h4>
                            {getStatusBadge(recording.status)}
                            {recording.is_public && <Badge variant="outline">Public</Badge>}
                          </div>
                          {recording.description && (
                            <p className="text-sm text-gray-600 mb-2">{recording.description}</p>
                          )}
                          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                            <div className="flex items-center gap-1">
                              <Clock className="h-3 w-3" />
                              {formatDuration(recording.duration_seconds)}
                            </div>
                            <div className="flex items-center gap-1">
                              <HardDrive className="h-3 w-3" />
                              {formatFileSize(recording.file_size)}
                            </div>
                            <div className="flex items-center gap-1">
                              <Eye className="h-3 w-3" />
                              {recording.view_count} views
                            </div>
                            <div className="flex items-center gap-1">
                              <Users className="h-3 w-3" />
                              {recording.participant_count} participants
                            </div>
                          </div>
                          <div className="text-xs text-gray-500 mt-2">
                            Recorded: {new Date(recording.started_at).toLocaleString()}
                          </div>
                        </div>
                        <div className="flex gap-2 ml-4">
                          <Button size="sm" variant="outline" onClick={() => downloadRecording(recording.id)}>
                            <Download className="h-3 w-3" />
                          </Button>
                          <Button size="sm" variant="outline" onClick={() => generateShareLink(recording.id)}>
                            <Share2 className="h-3 w-3" />
                          </Button>
                          <Button size="sm" variant="outline" onClick={() => setSelectedRecording(recording)}>
                            <Settings className="h-3 w-3" />
                          </Button>
                          {isHost && (
                            <>
                              <Button size="sm" variant="outline" onClick={() => archiveRecording(recording.id)}>
                                <Archive className="h-3 w-3" />
                              </Button>
                              <Button 
                                size="sm" 
                                variant="outline" 
                                onClick={() => deleteRecording(recording.id)}
                                className="text-red-600 hover:text-red-700"
                              >
                                <Trash2 className="h-3 w-3" />
                              </Button>
                            </>
                          )}
                        </div>
                      </div>
                      
                      {recording.auto_delete_at && (
                        <div className="mt-3 p-2 bg-yellow-50 rounded text-sm">
                          <div className="flex items-center gap-2 text-yellow-700">
                            <AlertCircle className="h-4 w-4" />
                            Auto-delete scheduled for: {new Date(recording.auto_delete_at).toLocaleDateString()}
                          </div>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                ))}
              </div>
            </ScrollArea>
          </DialogContent>
        </Dialog>

        {/* Share Link Dialog */}
        <Dialog open={!!shareToken} onOpenChange={() => setShareToken('')}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Share Recording</DialogTitle>
            </DialogHeader>
            <div className="space-y-4">
              <div className="space-y-2">
                <Label>Share Link</Label>
                <div className="flex gap-2">
                  <Input value={shareToken} readOnly />
                  <Button 
                    variant="outline" 
                    onClick={() => navigator.clipboard.writeText(shareToken)}
                  >
                    <Link className="h-4 w-4" />
                  </Button>
                </div>
              </div>
              <div className="text-sm text-gray-600">
                This link will expire in 24 hours and can be used to view the recording without authentication.
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
}