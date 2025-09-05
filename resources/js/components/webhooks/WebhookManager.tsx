import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import { 
  Plus, 
  Settings, 
  Trash2, 
  TestTube, 
  RefreshCw,
  Eye,
  EyeOff,
  Copy,
  ExternalLink,
  Activity,
  AlertCircle
} from 'lucide-react';
import { useWebhookApi } from '@/hooks/useWebhookApi';
import { Webhook, WebhookDelivery, WebhookEvent } from '@/types/webhook';
import { toast } from 'sonner';

interface WebhookManagerProps {
  className?: string;
}

const WebhookManager: React.FC<WebhookManagerProps> = ({ className }) => {
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [selectedWebhook, setSelectedWebhook] = useState<Webhook | null>(null);
  const [deliveries, setDeliveries] = useState<WebhookDelivery[]>([]);
  const [events, setEvents] = useState<WebhookEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [showSecretDialog, setShowSecretDialog] = useState(false);
  const [currentSecret, setCurrentSecret] = useState('');
  
  const { 
    fetchWebhooks, 
    createWebhook, 
    updateWebhook, 
    deleteWebhook,
    fetchWebhookDeliveries,
    testWebhook,
    regenerateSecret,
    retryDelivery,
    fetchEvents
  } = useWebhookApi();

  const loadWebhooks = async () => {
    try {
      setLoading(true);
      const [webhooksData, eventsData] = await Promise.all([
        fetchWebhooks(),
        fetchEvents()
      ]);
      setWebhooks(webhooksData.webhooks);
      setEvents(eventsData.events);
    } catch (error) {
      toast.error('Failed to load webhooks');
    } finally {
      setLoading(false);
    }
  };

  const loadDeliveries = async (webhookId: string) => {
    try {
      const data = await fetchWebhookDeliveries(webhookId);
      setDeliveries(data.deliveries);
    } catch (error) {
      toast.error('Failed to load deliveries');
    }
  };

  useEffect(() => {
    loadWebhooks();
  }, []);

  const handleCreateWebhook = async (webhookData: Partial<Webhook>) => {
    try {
      await createWebhook(webhookData);
      toast.success('Webhook created successfully');
      setShowCreateDialog(false);
      loadWebhooks();
    } catch (error) {
      toast.error('Failed to create webhook');
    }
  };

  const handleTestWebhook = async (webhook: Webhook) => {
    try {
      await testWebhook(webhook.id);
      toast.success('Test webhook sent');
      loadDeliveries(webhook.id);
    } catch (error) {
      toast.error('Failed to send test webhook');
    }
  };

  const handleRegenerateSecret = async (webhook: Webhook) => {
    try {
      const result = await regenerateSecret(webhook.id);
      setCurrentSecret(result.secret);
      setShowSecretDialog(true);
      toast.success('Secret regenerated');
      loadWebhooks();
    } catch (error) {
      toast.error('Failed to regenerate secret');
    }
  };

  const handleRetryDelivery = async (webhookId: string, deliveryId: string) => {
    try {
      await retryDelivery(webhookId, deliveryId);
      toast.success('Delivery retried');
      loadDeliveries(webhookId);
    } catch (error) {
      toast.error('Failed to retry delivery');
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('Copied to clipboard');
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'default';
      case 'inactive': return 'secondary';
      case 'disabled': return 'destructive';
      default: return 'outline';
    }
  };

  const getDeliveryStatusColor = (status: string) => {
    switch (status) {
      case 'success': return 'default';
      case 'failed': return 'destructive';
      case 'pending': return 'secondary';
      default: return 'outline';
    }
  };

  if (loading) {
    return (
      <div className={`space-y-6 ${className}`}>
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/4"></div>
          <div className="h-32 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Webhook Management</h1>
          <p className="text-muted-foreground">
            Manage webhooks for real-time event notifications
          </p>
        </div>
        <div className="flex gap-2">
          <Button onClick={loadWebhooks} variant="outline">
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </Button>
          <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Create Webhook
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
              <WebhookCreateForm 
                events={events}
                onSubmit={handleCreateWebhook}
                onCancel={() => setShowCreateDialog(false)}
              />
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* Webhooks List */}
      <div className="grid gap-6">
        {webhooks.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center">
              <div className="space-y-4">
                <div className="text-muted-foreground">
                  No webhooks configured yet.
                </div>
                <Button onClick={() => setShowCreateDialog(true)}>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Your First Webhook
                </Button>
              </div>
            </CardContent>
          </Card>
        ) : (
          webhooks.map((webhook) => (
            <Card key={webhook.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle className="flex items-center gap-2">
                      {webhook.name}
                      <Badge variant={getStatusColor(webhook.status)}>
                        {webhook.status}
                      </Badge>
                    </CardTitle>
                    <CardDescription>{webhook.url}</CardDescription>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleTestWebhook(webhook)}
                    >
                      <TestTube className="h-4 w-4 mr-2" />
                      Test
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleRegenerateSecret(webhook)}
                    >
                      <RefreshCw className="h-4 w-4 mr-2" />
                      Regenerate Secret
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setSelectedWebhook(webhook);
                        loadDeliveries(webhook.id);
                      }}
                    >
                      <Eye className="h-4 w-4 mr-2" />
                      Details
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                      <div className="text-sm font-medium">Success Rate</div>
                      <div className="text-2xl font-bold text-green-600">
                        {webhook.success_rate?.toFixed(1)}%
                      </div>
                    </div>
                    <div>
                      <div className="text-sm font-medium">Total Deliveries</div>
                      <div className="text-2xl font-bold">
                        {webhook.total_deliveries || 0}
                      </div>
                    </div>
                    <div>
                      <div className="text-sm font-medium">Events</div>
                      <div className="text-sm text-muted-foreground">
                        {webhook.events?.length || 0} types
                      </div>
                    </div>
                    <div>
                      <div className="text-sm font-medium">Timeout</div>
                      <div className="text-sm text-muted-foreground">
                        {webhook.timeout}s
                      </div>
                    </div>
                  </div>
                  
                  <div>
                    <div className="text-sm font-medium mb-2">Subscribed Events</div>
                    <div className="flex flex-wrap gap-1">
                      {webhook.events?.slice(0, 5).map((event) => (
                        <Badge key={event} variant="outline" className="text-xs">
                          {event}
                        </Badge>
                      ))}
                      {(webhook.events?.length || 0) > 5 && (
                        <Badge variant="outline" className="text-xs">
                          +{(webhook.events?.length || 0) - 5} more
                        </Badge>
                      )}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>

      {/* Webhook Details Modal */}
      {selectedWebhook && (
        <Dialog 
          open={!!selectedWebhook} 
          onOpenChange={(open) => !open && setSelectedWebhook(null)}
        >
          <DialogContent className="max-w-4xl">
            <DialogHeader>
              <DialogTitle>Webhook Details: {selectedWebhook.name}</DialogTitle>
              <DialogDescription>
                View deliveries and manage webhook settings
              </DialogDescription>
            </DialogHeader>
            <WebhookDetailsView 
              webhook={selectedWebhook}
              deliveries={deliveries}
              onRetryDelivery={handleRetryDelivery}
            />
          </DialogContent>
        </Dialog>
      )}

      {/* Secret Display Modal */}
      <Dialog open={showSecretDialog} onOpenChange={setShowSecretDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Webhook Secret</DialogTitle>
            <DialogDescription>
              Copy this secret and store it securely. It won't be shown again.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <Input value={currentSecret} readOnly />
              <Button
                variant="outline"
                onClick={() => copyToClipboard(currentSecret)}
              >
                <Copy className="h-4 w-4" />
              </Button>
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => setShowSecretDialog(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

// Webhook Create Form Component
const WebhookCreateForm: React.FC<{
  events: WebhookEvent[];
  onSubmit: (data: Partial<Webhook>) => void;
  onCancel: () => void;
}> = ({ events, onSubmit, onCancel }) => {
  const [formData, setFormData] = useState({
    name: '',
    url: '',
    events: [] as string[],
    timeout: 30,
    retry_attempts: 3,
    headers: {} as Record<string, string>,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(formData);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <DialogHeader>
        <DialogTitle>Create New Webhook</DialogTitle>
        <DialogDescription>
          Configure a new webhook to receive real-time event notifications.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="My Webhook"
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="url">URL</Label>
            <Input
              id="url"
              type="url"
              value={formData.url}
              onChange={(e) => setFormData({ ...formData, url: e.target.value })}
              placeholder="https://api.example.com/webhooks"
              required
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label>Events</Label>
          <div className="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto border rounded p-3">
            {Object.entries(events).map(([category, categoryEvents]) => (
              <div key={category} className="space-y-2">
                <div className="font-medium text-sm capitalize">{category}</div>
                {(categoryEvents as any[]).map((event) => (
                  <div key={event.event} className="flex items-center space-x-2">
                    <input
                      type="checkbox"
                      id={event.event}
                      checked={formData.events.includes(event.event)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setFormData({
                            ...formData,
                            events: [...formData.events, event.event]
                          });
                        } else {
                          setFormData({
                            ...formData,
                            events: formData.events.filter(e => e !== event.event)
                          });
                        }
                      }}
                    />
                    <label htmlFor={event.event} className="text-sm">
                      {event.event}
                    </label>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="timeout">Timeout (seconds)</Label>
            <Input
              id="timeout"
              type="number"
              min="5"
              max="120"
              value={formData.timeout}
              onChange={(e) => setFormData({ ...formData, timeout: parseInt(e.target.value) })}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="retry_attempts">Retry Attempts</Label>
            <Input
              id="retry_attempts"
              type="number"
              min="0"
              max="10"
              value={formData.retry_attempts}
              onChange={(e) => setFormData({ ...formData, retry_attempts: parseInt(e.target.value) })}
            />
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" disabled={formData.events.length === 0}>
          Create Webhook
        </Button>
      </DialogFooter>
    </form>
  );
};

// Webhook Details View Component
const WebhookDetailsView: React.FC<{
  webhook: Webhook;
  deliveries: WebhookDelivery[];
  onRetryDelivery: (webhookId: string, deliveryId: string) => void;
}> = ({ webhook, deliveries, onRetryDelivery }) => {
  return (
    <Tabs defaultValue="deliveries" className="space-y-4">
      <TabsList>
        <TabsTrigger value="deliveries">Recent Deliveries</TabsTrigger>
        <TabsTrigger value="settings">Settings</TabsTrigger>
      </TabsList>

      <TabsContent value="deliveries">
        <div className="space-y-4">
          {deliveries.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              No deliveries yet
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Event</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>HTTP Status</TableHead>
                  <TableHead>Attempt</TableHead>
                  <TableHead>Time</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {deliveries.map((delivery) => (
                  <TableRow key={delivery.id}>
                    <TableCell>{delivery.event_type}</TableCell>
                    <TableCell>
                      <Badge variant={getDeliveryStatusColor(delivery.status)}>
                        {delivery.status}
                      </Badge>
                    </TableCell>
                    <TableCell>{delivery.http_status || '-'}</TableCell>
                    <TableCell>{delivery.attempt}</TableCell>
                    <TableCell>
                      {new Date(delivery.created_at).toLocaleString()}
                    </TableCell>
                    <TableCell>
                      {delivery.status === 'failed' && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => onRetryDelivery(webhook.id, delivery.id)}
                        >
                          Retry
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>
      </TabsContent>

      <TabsContent value="settings">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>URL</Label>
              <div className="flex items-center gap-2">
                <Input value={webhook.url} readOnly />
                <Button variant="outline" size="sm">
                  <ExternalLink className="h-4 w-4" />
                </Button>
              </div>
            </div>
            <div>
              <Label>Status</Label>
              <Badge variant={getStatusColor(webhook.status)}>
                {webhook.status}
              </Badge>
            </div>
          </div>
          
          <div>
            <Label>Subscribed Events</Label>
            <div className="flex flex-wrap gap-2 mt-2">
              {webhook.events?.map((event) => (
                <Badge key={event} variant="outline">
                  {event}
                </Badge>
              ))}
            </div>
          </div>
        </div>
      </TabsContent>
    </Tabs>
  );
};

export default WebhookManager;