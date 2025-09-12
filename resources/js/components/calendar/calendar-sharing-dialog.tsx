import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Calendar, CalendarPermission } from '@/types/calendar';
import { CalendarService } from '@/services/CalendarService';
import { AlertCircle, Loader2, Share2, UserPlus, X, Users, Eye, Edit, Settings } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface CalendarSharingDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  calendar: Calendar;
  onPermissionsUpdated: () => void;
}

export function CalendarSharingDialog({
  open,
  onOpenChange,
  calendar,
  onPermissionsUpdated,
}: CalendarSharingDialogProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  
  const [shareEmail, setShareEmail] = useState('');
  const [sharePermission, setSharePermission] = useState<'read' | 'write' | 'admin'>('read');

  const handleShareCalendar = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!shareEmail) return;

    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      await CalendarService.shareCalendar(calendar.id, {
        user_id: shareEmail, // Assuming email lookup on backend
        permission: sharePermission,
      });
      
      setSuccess(`Calendar shared with ${shareEmail} successfully!`);
      setShareEmail('');
      onPermissionsUpdated();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to share calendar');
    } finally {
      setLoading(false);
    }
  };

  const handleRevokeAccess = async (userId: string, userEmail: string) => {
    setLoading(true);
    setError(null);

    try {
      await CalendarService.revokeAccess(calendar.id, userId);
      setSuccess(`Access revoked for ${userEmail}`);
      onPermissionsUpdated();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to revoke access');
    } finally {
      setLoading(false);
    }
  };

  const getPermissionIcon = (permission: string) => {
    switch (permission) {
      case 'read': return <Eye className="w-4 h-4" />;
      case 'write': return <Edit className="w-4 h-4" />;
      case 'admin': return <Settings className="w-4 h-4" />;
      default: return <Eye className="w-4 h-4" />;
    }
  };

  const getPermissionColor = (permission: string) => {
    switch (permission) {
      case 'read': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'write': return 'bg-green-100 text-green-800 border-green-200';
      case 'admin': return 'bg-purple-100 text-purple-800 border-purple-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle className="flex items-center">
            <Share2 className="w-5 h-5 mr-2" />
            Share "{calendar.name}"
          </DialogTitle>
          <DialogDescription>
            Control who can access this calendar and what they can do.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* Share with new user */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg flex items-center">
                <UserPlus className="w-5 h-5 mr-2" />
                Add People
              </CardTitle>
              <CardDescription>
                Share this calendar with others by entering their email address.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleShareCalendar} className="space-y-4">
                {error && (
                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{error}</AlertDescription>
                  </Alert>
                )}

                {success && (
                  <Alert className="border-green-200 bg-green-50">
                    <AlertDescription className="text-green-800">
                      {success}
                    </AlertDescription>
                  </Alert>
                )}

                <div className="flex space-x-2">
                  <div className="flex-1">
                    <Input
                      type="email"
                      placeholder="Enter email address"
                      value={shareEmail}
                      onChange={(e) => setShareEmail(e.target.value)}
                      required
                    />
                  </div>
                  <Select value={sharePermission} onValueChange={(value: any) => setSharePermission(value)}>
                    <SelectTrigger className="w-32">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="read">
                        <div className="flex items-center">
                          <Eye className="w-4 h-4 mr-2" />
                          View
                        </div>
                      </SelectItem>
                      <SelectItem value="write">
                        <div className="flex items-center">
                          <Edit className="w-4 h-4 mr-2" />
                          Edit
                        </div>
                      </SelectItem>
                      <SelectItem value="admin">
                        <div className="flex items-center">
                          <Settings className="w-4 h-4 mr-2" />
                          Admin
                        </div>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <Button type="submit" disabled={loading || !shareEmail}>
                    {loading ? (
                      <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                      <Share2 className="w-4 h-4" />
                    )}
                  </Button>
                </div>

                <div className="text-xs text-muted-foreground">
                  <p><strong>View:</strong> Can see calendar and events</p>
                  <p><strong>Edit:</strong> Can view and create/edit events</p>
                  <p><strong>Admin:</strong> Full access including sharing and settings</p>
                </div>
              </form>
            </CardContent>
          </Card>

          {/* Current permissions */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg flex items-center">
                <Users className="w-5 h-5 mr-2" />
                People with Access
              </CardTitle>
              <CardDescription>
                {calendar.permissions?.length || 0} people have access to this calendar.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {/* Owner */}
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div>
                    <p className="font-medium">{calendar.owner_name}</p>
                    <p className="text-sm text-muted-foreground">Owner</p>
                  </div>
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-200">
                    <Settings className="w-3 h-3 mr-1" />
                    Owner
                  </Badge>
                </div>

                {/* Shared users */}
                {calendar.permissions && calendar.permissions.length > 0 ? (
                  calendar.permissions.map((permission) => (
                    <div key={permission.user_id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div>
                        <p className="font-medium">{permission.user_name}</p>
                        <p className="text-xs text-muted-foreground">
                          Shared on {new Date(permission.granted_at).toLocaleDateString()}
                        </p>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Badge 
                          variant="outline" 
                          className={`flex items-center ${getPermissionColor(permission.permission)}`}
                        >
                          {getPermissionIcon(permission.permission)}
                          <span className="ml-1 capitalize">{permission.permission}</span>
                        </Badge>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleRevokeAccess(permission.user_id, permission.user_name)}
                          disabled={loading}
                          className="text-red-600 hover:text-red-800 hover:bg-red-50"
                        >
                          <X className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="text-center py-4 text-muted-foreground">
                    <Users className="w-8 h-8 mx-auto mb-2 opacity-50" />
                    <p>No one else has access to this calendar yet.</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Calendar Visibility */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Calendar Visibility</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">General Access</p>
                  <p className="text-sm text-muted-foreground">
                    Who can find and access this calendar
                  </p>
                </div>
                <Badge 
                  variant="outline"
                  className={`${calendar.visibility === 'public' ? 'bg-green-100 text-green-800' : 
                    calendar.visibility === 'shared' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}`}
                >
                  {calendar.visibility === 'public' ? 'Public' : 
                   calendar.visibility === 'shared' ? 'Shared' : 'Private'}
                </Badge>
              </div>
              <div className="text-xs text-muted-foreground mt-2">
                {calendar.visibility === 'public' && "Anyone can find and view this calendar"}
                {calendar.visibility === 'shared' && "Only people with access can view this calendar"}
                {calendar.visibility === 'private' && "Only you can view this calendar"}
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="flex justify-end">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Done
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}