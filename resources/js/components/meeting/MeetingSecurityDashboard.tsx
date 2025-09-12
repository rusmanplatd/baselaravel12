import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { 
  Shield, 
  Lock, 
  Eye, 
  Users, 
  Clock, 
  AlertTriangle, 
  CheckCircle, 
  XCircle, 
  Settings, 
  UserCheck, 
  UserX, 
  Crown,
  Ban,
  Unlock,
  Key,
  Activity,
  FileText,
  Download,
  RefreshCw
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface SecurityControl {
  id: string;
  meeting_id: string;
  password_required: boolean;
  password?: string;
  waiting_room_enabled: boolean;
  participant_approval_required: boolean;
  screen_sharing_restricted: boolean;
  recording_restricted: boolean;
  chat_restricted: boolean;
  file_sharing_restricted: boolean;
  participant_limit?: number;
  domain_restrictions: string[];
  ip_restrictions: string[];
  device_restrictions: string[];
  time_restrictions: {
    start_time?: string;
    end_time?: string;
    allowed_days: string[];
  };
  security_policies: {
    encryption_required: boolean;
    watermark_enabled: boolean;
    screenshot_prevention: boolean;
    recording_notification: boolean;
  };
  created_at: string;
  updated_at: string;
}

interface SecurityEvent {
  id: string;
  meeting_id: string;
  event_type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  description: string;
  user_id?: string;
  user_name?: string;
  ip_address: string;
  user_agent: string;
  metadata: Record<string, any>;
  resolved: boolean;
  resolved_by?: string;
  resolved_at?: string;
  created_at: string;
}

interface Participant {
  id: string;
  user_id: string;
  name: string;
  email: string;
  role: string;
  status: 'waiting' | 'approved' | 'denied' | 'joined' | 'left';
  ip_address: string;
  device_info: string;
  join_attempts: number;
  last_activity: string;
}

interface MeetingSecurityDashboardProps {
  meetingId: string;
  isHost: boolean;
  onSecurityUpdate?: (control: SecurityControl) => void;
  className?: string;
}

export default function MeetingSecurityDashboard({ 
  meetingId, 
  isHost, 
  onSecurityUpdate, 
  className = '' 
}: MeetingSecurityDashboardProps) {
  const [securityControl, setSecurityControl] = useState<SecurityControl | null>(null);
  const [securityEvents, setSecurityEvents] = useState<SecurityEvent[]>([]);
  const [waitingParticipants, setWaitingParticipants] = useState<Participant[]>([]);
  const [activeParticipants, setActiveParticipants] = useState<Participant[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showPasswordDialog, setShowPasswordDialog] = useState(false);
  const [showRestrictionsDialog, setShowRestrictionsDialog] = useState(false);
  const [newPassword, setNewPassword] = useState('');
  const [newDomainRestriction, setNewDomainRestriction] = useState('');
  const [newIpRestriction, setNewIpRestriction] = useState('');

  useEffect(() => {
    loadSecurityData();
    const interval = setInterval(loadSecurityData, 10000); // Refresh every 10 seconds
    return () => clearInterval(interval);
  }, [meetingId]);

  const loadSecurityData = async () => {
    try {
      const [controlResponse, eventsResponse, participantsResponse] = await Promise.all([
        apiService.get(`/api/meetings/${meetingId}/security`),
        apiService.get(`/api/meetings/${meetingId}/security/events`),
        apiService.get(`/api/meetings/${meetingId}/participants`)
      ]);

      if (controlResponse.security_control) {
        setSecurityControl(controlResponse.security_control);
        onSecurityUpdate?.(controlResponse.security_control);
      }

      if (eventsResponse.events) {
        setSecurityEvents(eventsResponse.events);
      }

      if (participantsResponse.participants) {
        const participants = participantsResponse.participants;
        setWaitingParticipants(participants.filter((p: Participant) => p.status === 'waiting'));
        setActiveParticipants(participants.filter((p: Participant) => ['approved', 'joined'].includes(p.status)));
      }
    } catch (error) {
      console.error('Failed to load security data:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const updateSecurityControl = async (updates: Partial<SecurityControl>) => {
    if (!isHost || !securityControl) return;

    setSaving(true);
    try {
      const response = await apiService.patch(`/api/meetings/${meetingId}/security`, updates);
      if (response.success && response.security_control) {
        setSecurityControl(response.security_control);
        onSecurityUpdate?.(response.security_control);
      }
    } catch (error) {
      console.error('Failed to update security control:', error);
    } finally {
      setSaving(false);
    }
  };

  const updatePassword = async () => {
    if (!newPassword.trim()) return;
    
    await updateSecurityControl({ 
      password_required: true, 
      password: newPassword 
    });
    setNewPassword('');
    setShowPasswordDialog(false);
  };

  const removePassword = async () => {
    await updateSecurityControl({ 
      password_required: false, 
      password: undefined 
    });
  };

  const addDomainRestriction = async () => {
    if (!newDomainRestriction.trim() || !securityControl) return;
    
    const domains = [...securityControl.domain_restrictions, newDomainRestriction];
    await updateSecurityControl({ domain_restrictions: domains });
    setNewDomainRestriction('');
  };

  const removeDomainRestriction = async (domain: string) => {
    if (!securityControl) return;
    
    const domains = securityControl.domain_restrictions.filter(d => d !== domain);
    await updateSecurityControl({ domain_restrictions: domains });
  };

  const addIpRestriction = async () => {
    if (!newIpRestriction.trim() || !securityControl) return;
    
    const ips = [...securityControl.ip_restrictions, newIpRestriction];
    await updateSecurityControl({ ip_restrictions: ips });
    setNewIpRestriction('');
  };

  const removeIpRestriction = async (ip: string) => {
    if (!securityControl) return;
    
    const ips = securityControl.ip_restrictions.filter(i => i !== ip);
    await updateSecurityControl({ ip_restrictions: ips });
  };

  const handleParticipantAction = async (participantId: string, action: 'approve' | 'deny' | 'remove') => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/participants/${participantId}/${action}`);
      if (response.success) {
        await loadSecurityData();
      }
    } catch (error) {
      console.error(`Failed to ${action} participant:`, error);
    }
  };

  const resolveSecurityEvent = async (eventId: string) => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/security/events/${eventId}/resolve`);
      if (response.success) {
        await loadSecurityData();
      }
    } catch (error) {
      console.error('Failed to resolve security event:', error);
    }
  };

  const exportSecurityReport = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/security/report`, {
        responseType: 'blob'
      });
      
      const url = window.URL.createObjectURL(new Blob([response]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `meeting-${meetingId}-security-report.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Failed to export security report:', error);
    }
  };

  const refreshData = () => {
    setRefreshing(true);
    loadSecurityData();
  };

  if (loading) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center p-6">
          <div className="flex items-center space-x-2">
            <RefreshCw className="h-4 w-4 animate-spin" />
            <span>Loading security dashboard...</span>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!securityControl) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center p-6">
          <Alert>
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription>
              Security controls not found for this meeting.
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    );
  }

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical': return 'destructive';
      case 'high': return 'destructive';
      case 'medium': return 'default';
      case 'low': return 'secondary';
      default: return 'secondary';
    }
  };

  const unresolvedEvents = securityEvents.filter(event => !event.resolved);
  const criticalEvents = unresolvedEvents.filter(event => event.severity === 'critical');

  return (
    <div className={className}>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-2">
          <Shield className="h-5 w-5" />
          <h2 className="text-xl font-semibold">Meeting Security</h2>
          <Badge variant={criticalEvents.length > 0 ? "destructive" : "secondary"}>
            {unresolvedEvents.length} alerts
          </Badge>
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={refreshData}
            disabled={refreshing}
          >
            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={exportSecurityReport}
          >
            <Download className="h-4 w-4" />
            Export Report
          </Button>
        </div>
      </div>

      <Tabs defaultValue="controls" className="space-y-4">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="controls">Security Controls</TabsTrigger>
          <TabsTrigger value="participants">
            Participants
            {waitingParticipants.length > 0 && (
              <Badge variant="destructive" className="ml-2">
                {waitingParticipants.length}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="events">
            Security Events
            {unresolvedEvents.length > 0 && (
              <Badge variant="destructive" className="ml-2">
                {unresolvedEvents.length}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="monitoring">Live Monitoring</TabsTrigger>
        </TabsList>

        <TabsContent value="controls" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Access Controls */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Lock className="h-4 w-4" />
                  <span>Access Controls</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <Label htmlFor="waiting-room">Waiting Room</Label>
                  <Switch
                    id="waiting-room"
                    checked={securityControl.waiting_room_enabled}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ waiting_room_enabled: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="approval-required">Participant Approval</Label>
                  <Switch
                    id="approval-required"
                    checked={securityControl.participant_approval_required}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ participant_approval_required: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label>Meeting Password</Label>
                    <div className="flex items-center space-x-2">
                      {securityControl.password_required ? (
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="outline" size="sm">
                              <Unlock className="h-4 w-4" />
                              Remove
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Remove Password Protection?</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will remove password protection from the meeting. Anyone with the meeting link will be able to join.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction onClick={removePassword}>
                                Remove Password
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      ) : (
                        <Dialog open={showPasswordDialog} onOpenChange={setShowPasswordDialog}>
                          <DialogTrigger asChild>
                            <Button variant="outline" size="sm">
                              <Key className="h-4 w-4" />
                              Set Password
                            </Button>
                          </DialogTrigger>
                          <DialogContent>
                            <DialogHeader>
                              <DialogTitle>Set Meeting Password</DialogTitle>
                              <DialogDescription>
                                Add password protection to restrict meeting access.
                              </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                              <div>
                                <Label htmlFor="password">Password</Label>
                                <Input
                                  id="password"
                                  type="password"
                                  value={newPassword}
                                  onChange={(e) => setNewPassword(e.target.value)}
                                  placeholder="Enter secure password"
                                />
                              </div>
                            </div>
                            <DialogFooter>
                              <Button 
                                onClick={updatePassword}
                                disabled={!newPassword.trim() || saving}
                              >
                                Set Password
                              </Button>
                            </DialogFooter>
                          </DialogContent>
                        </Dialog>
                      )}
                    </div>
                  </div>
                  {securityControl.password_required && (
                    <Alert>
                      <CheckCircle className="h-4 w-4" />
                      <AlertDescription>
                        Password protection is active
                      </AlertDescription>
                    </Alert>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="participant-limit">Participant Limit</Label>
                  <Input
                    id="participant-limit"
                    type="number"
                    value={securityControl.participant_limit || ''}
                    onChange={(e) => 
                      updateSecurityControl({ 
                        participant_limit: e.target.value ? parseInt(e.target.value) : undefined 
                      })
                    }
                    placeholder="No limit"
                    disabled={!isHost || saving}
                  />
                </div>
              </CardContent>
            </Card>

            {/* Feature Restrictions */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Ban className="h-4 w-4" />
                  <span>Feature Restrictions</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <Label htmlFor="screen-sharing">Restrict Screen Sharing</Label>
                  <Switch
                    id="screen-sharing"
                    checked={securityControl.screen_sharing_restricted}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ screen_sharing_restricted: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="recording">Restrict Recording</Label>
                  <Switch
                    id="recording"
                    checked={securityControl.recording_restricted}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ recording_restricted: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="chat">Restrict Chat</Label>
                  <Switch
                    id="chat"
                    checked={securityControl.chat_restricted}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ chat_restricted: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="file-sharing">Restrict File Sharing</Label>
                  <Switch
                    id="file-sharing"
                    checked={securityControl.file_sharing_restricted}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ file_sharing_restricted: checked })
                    }
                    disabled={!isHost || saving}
                  />
                </div>
              </CardContent>
            </Card>

            {/* Security Policies */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Shield className="h-4 w-4" />
                  <span>Security Policies</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <Label htmlFor="encryption">Require Encryption</Label>
                  <Switch
                    id="encryption"
                    checked={securityControl.security_policies.encryption_required}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ 
                        security_policies: {
                          ...securityControl.security_policies,
                          encryption_required: checked
                        }
                      })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="watermark">Enable Watermark</Label>
                  <Switch
                    id="watermark"
                    checked={securityControl.security_policies.watermark_enabled}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ 
                        security_policies: {
                          ...securityControl.security_policies,
                          watermark_enabled: checked
                        }
                      })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="screenshot-prevention">Prevent Screenshots</Label>
                  <Switch
                    id="screenshot-prevention"
                    checked={securityControl.security_policies.screenshot_prevention}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ 
                        security_policies: {
                          ...securityControl.security_policies,
                          screenshot_prevention: checked
                        }
                      })
                    }
                    disabled={!isHost || saving}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <Label htmlFor="recording-notification">Recording Notifications</Label>
                  <Switch
                    id="recording-notification"
                    checked={securityControl.security_policies.recording_notification}
                    onCheckedChange={(checked) => 
                      updateSecurityControl({ 
                        security_policies: {
                          ...securityControl.security_policies,
                          recording_notification: checked
                        }
                      })
                    }
                    disabled={!isHost || saving}
                  />
                </div>
              </CardContent>
            </Card>

            {/* Access Restrictions */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Eye className="h-4 w-4" />
                  <span>Access Restrictions</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>Domain Restrictions</Label>
                  <div className="flex space-x-2">
                    <Input
                      value={newDomainRestriction}
                      onChange={(e) => setNewDomainRestriction(e.target.value)}
                      placeholder="company.com"
                      disabled={!isHost || saving}
                    />
                    <Button 
                      onClick={addDomainRestriction}
                      disabled={!newDomainRestriction.trim() || !isHost || saving}
                      size="sm"
                    >
                      Add
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {securityControl.domain_restrictions.map((domain) => (
                      <Badge key={domain} variant="secondary">
                        {domain}
                        {isHost && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="ml-1 h-auto p-0"
                            onClick={() => removeDomainRestriction(domain)}
                          >
                            <XCircle className="h-3 w-3" />
                          </Button>
                        )}
                      </Badge>
                    ))}
                  </div>
                </div>

                <div className="space-y-2">
                  <Label>IP Restrictions</Label>
                  <div className="flex space-x-2">
                    <Input
                      value={newIpRestriction}
                      onChange={(e) => setNewIpRestriction(e.target.value)}
                      placeholder="192.168.1.0/24"
                      disabled={!isHost || saving}
                    />
                    <Button 
                      onClick={addIpRestriction}
                      disabled={!newIpRestriction.trim() || !isHost || saving}
                      size="sm"
                    >
                      Add
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {securityControl.ip_restrictions.map((ip) => (
                      <Badge key={ip} variant="secondary">
                        {ip}
                        {isHost && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="ml-1 h-auto p-0"
                            onClick={() => removeIpRestriction(ip)}
                          >
                            <XCircle className="h-3 w-3" />
                          </Button>
                        )}
                      </Badge>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="participants" className="space-y-4">
          {waitingParticipants.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Clock className="h-4 w-4" />
                  <span>Waiting for Approval ({waitingParticipants.length})</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ScrollArea className="h-64">
                  <div className="space-y-2">
                    {waitingParticipants.map((participant) => (
                      <div key={participant.id} className="flex items-center justify-between p-3 border rounded-lg">
                        <div>
                          <div className="font-medium">{participant.name}</div>
                          <div className="text-sm text-gray-500">{participant.email}</div>
                          <div className="text-xs text-gray-400">
                            IP: {participant.ip_address} | Device: {participant.device_info}
                          </div>
                        </div>
                        {isHost && (
                          <div className="flex space-x-2">
                            <Button
                              size="sm"
                              onClick={() => handleParticipantAction(participant.id, 'approve')}
                            >
                              <UserCheck className="h-4 w-4" />
                              Approve
                            </Button>
                            <Button
                              size="sm"
                              variant="destructive"
                              onClick={() => handleParticipantAction(participant.id, 'deny')}
                            >
                              <UserX className="h-4 w-4" />
                              Deny
                            </Button>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </ScrollArea>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Users className="h-4 w-4" />
                <span>Active Participants ({activeParticipants.length})</span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <ScrollArea className="h-64">
                <div className="space-y-2">
                  {activeParticipants.map((participant) => (
                    <div key={participant.id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="flex items-center space-x-3">
                        {participant.role === 'host' && <Crown className="h-4 w-4 text-yellow-500" />}
                        <div>
                          <div className="font-medium">{participant.name}</div>
                          <div className="text-sm text-gray-500">{participant.email}</div>
                          <div className="text-xs text-gray-400">
                            Last activity: {new Date(participant.last_activity).toLocaleString()}
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Badge variant={participant.status === 'joined' ? 'default' : 'secondary'}>
                          {participant.status}
                        </Badge>
                        {isHost && participant.role !== 'host' && (
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleParticipantAction(participant.id, 'remove')}
                          >
                            <UserX className="h-4 w-4" />
                            Remove
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="events" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <AlertTriangle className="h-4 w-4" />
                <span>Security Events</span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <ScrollArea className="h-96">
                <div className="space-y-3">
                  {securityEvents.map((event) => (
                    <div key={event.id} className="border rounded-lg p-4 space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          <Badge variant={getSeverityColor(event.severity) as any}>
                            {event.severity.toUpperCase()}
                          </Badge>
                          <span className="font-medium">{event.event_type}</span>
                          {event.resolved && <CheckCircle className="h-4 w-4 text-green-500" />}
                        </div>
                        <span className="text-sm text-gray-500">
                          {new Date(event.created_at).toLocaleString()}
                        </span>
                      </div>
                      <p className="text-sm">{event.description}</p>
                      {event.user_name && (
                        <p className="text-xs text-gray-500">
                          User: {event.user_name} | IP: {event.ip_address}
                        </p>
                      )}
                      {!event.resolved && isHost && (
                        <Button
                          size="sm"
                          onClick={() => resolveSecurityEvent(event.id)}
                        >
                          Mark as Resolved
                        </Button>
                      )}
                      {event.resolved && (
                        <p className="text-xs text-green-600">
                          Resolved by {event.resolved_by} at {new Date(event.resolved_at!).toLocaleString()}
                        </p>
                      )}
                    </div>
                  ))}
                  {securityEvents.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                      No security events recorded
                    </div>
                  )}
                </div>
              </ScrollArea>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="monitoring" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Activity className="h-4 w-4" />
                  <span>Live Security Status</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <span>Encryption Status</span>
                  <Badge variant={securityControl.security_policies.encryption_required ? "default" : "secondary"}>
                    {securityControl.security_policies.encryption_required ? "Active" : "Disabled"}
                  </Badge>
                </div>
                <div className="flex items-center justify-between">
                  <span>Waiting Room</span>
                  <Badge variant={securityControl.waiting_room_enabled ? "default" : "secondary"}>
                    {securityControl.waiting_room_enabled ? "Active" : "Disabled"}
                  </Badge>
                </div>
                <div className="flex items-center justify-between">
                  <span>Password Protection</span>
                  <Badge variant={securityControl.password_required ? "default" : "secondary"}>
                    {securityControl.password_required ? "Active" : "Disabled"}
                  </Badge>
                </div>
                <div className="flex items-center justify-between">
                  <span>Recording Restrictions</span>
                  <Badge variant={securityControl.recording_restricted ? "default" : "secondary"}>
                    {securityControl.recording_restricted ? "Active" : "Disabled"}
                  </Badge>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <FileText className="h-4 w-4" />
                  <span>Security Summary</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="text-sm space-y-2">
                  <div className="flex justify-between">
                    <span>Total Participants:</span>
                    <span className="font-medium">{activeParticipants.length}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Pending Approvals:</span>
                    <span className="font-medium">{waitingParticipants.length}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Security Events:</span>
                    <span className="font-medium">{unresolvedEvents.length} unresolved</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Critical Alerts:</span>
                    <span className="font-medium text-red-600">{criticalEvents.length}</span>
                  </div>
                  <Separator />
                  <div className="flex justify-between">
                    <span>Domain Restrictions:</span>
                    <span className="font-medium">{securityControl.domain_restrictions.length}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>IP Restrictions:</span>
                    <span className="font-medium">{securityControl.ip_restrictions.length}</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}