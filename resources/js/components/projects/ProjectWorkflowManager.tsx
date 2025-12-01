import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { 
    Plus, 
    Play, 
    Pause, 
    MoreHorizontal, 
    Edit3, 
    Trash2, 
    Copy,
    Zap,
    Target,
    Settings,
    AlertTriangle
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import apiService from '@/services/ApiService';

interface ProjectWorkflow {
    id: string;
    name: string;
    description?: string;
    triggers: WorkflowTrigger[];
    actions: WorkflowAction[];
    is_active: boolean;
    sort_order: number;
    created_at: string;
}

interface WorkflowTrigger {
    event: string;
    conditions?: Record<string, any>;
}

interface WorkflowAction {
    type: string;
    config?: Record<string, any>;
}

interface AvailableEvent {
    id: string;
    name: string;
    description: string;
    category: string;
}

interface AvailableAction {
    id: string;
    name: string;
    description: string;
    category: string;
    config_schema?: Record<string, any>;
}

interface ProjectWorkflowManagerProps {
    projectId: string;
    workflows: ProjectWorkflow[];
    canAdmin: boolean;
    onWorkflowsUpdate: (workflows: ProjectWorkflow[]) => void;
}

export function ProjectWorkflowManager({ 
    projectId, 
    workflows, 
    canAdmin, 
    onWorkflowsUpdate 
}: ProjectWorkflowManagerProps) {
    const [showAddDialog, setShowAddDialog] = useState(false);
    const [availableEvents, setAvailableEvents] = useState<AvailableEvent[]>([]);
    const [availableActions, setAvailableActions] = useState<AvailableAction[]>([]);
    const [workflowName, setWorkflowName] = useState('');
    const [workflowDescription, setWorkflowDescription] = useState('');
    const [selectedEvent, setSelectedEvent] = useState('');
    const [selectedAction, setSelectedAction] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (showAddDialog) {
            fetchAvailableOptions();
        }
    }, [showAddDialog]);

    const fetchAvailableOptions = async () => {
        try {
            const [events, actions] = await Promise.all([
                apiService.get<AvailableEvent[]>(`/api/v1/projects/${projectId}/workflows/events`),
                apiService.get<AvailableAction[]>(`/api/v1/projects/${projectId}/workflows/actions`)
            ]);
            setAvailableEvents(events);
            setAvailableActions(actions);
        } catch (error) {
            console.error('Failed to fetch workflow options:', error);
        }
    };

    const handleAddWorkflow = async () => {
        if (!workflowName.trim() || !selectedEvent || !selectedAction) return;

        setLoading(true);
        try {
            const workflowData = {
                name: workflowName.trim(),
                description: workflowDescription.trim() || null,
                triggers: [{ event: selectedEvent }],
                actions: [{ type: selectedAction }],
                is_active: isActive,
            };

            await apiService.post(`/api/v1/projects/${projectId}/workflows`, workflowData);

            // Refresh workflows list
            const updatedWorkflows = await apiService.get<ProjectWorkflow[]>(
                `/api/v1/projects/${projectId}/workflows`
            );
            onWorkflowsUpdate(updatedWorkflows);

            setShowAddDialog(false);
            resetForm();
        } catch (error: any) {
            console.error('Failed to add workflow:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const resetForm = () => {
        setWorkflowName('');
        setWorkflowDescription('');
        setSelectedEvent('');
        setSelectedAction('');
        setIsActive(true);
    };

    const handleToggleWorkflow = async (workflowId: string) => {
        setLoading(true);
        try {
            await apiService.post(`/api/v1/projects/${projectId}/workflows/${workflowId}/toggle`);

            // Refresh workflows list
            const updatedWorkflows = await apiService.get<ProjectWorkflow[]>(
                `/api/v1/projects/${projectId}/workflows`
            );
            onWorkflowsUpdate(updatedWorkflows);
        } catch (error: any) {
            console.error('Failed to toggle workflow:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDuplicateWorkflow = async (workflowId: string) => {
        setLoading(true);
        try {
            await apiService.post(`/api/v1/projects/${projectId}/workflows/${workflowId}/duplicate`);

            // Refresh workflows list
            const updatedWorkflows = await apiService.get<ProjectWorkflow[]>(
                `/api/v1/projects/${projectId}/workflows`
            );
            onWorkflowsUpdate(updatedWorkflows);
        } catch (error: any) {
            console.error('Failed to duplicate workflow:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteWorkflow = async (workflowId: string) => {
        if (!confirm('Are you sure you want to delete this workflow?')) return;

        setLoading(true);
        try {
            await apiService.delete(`/api/v1/projects/${projectId}/workflows/${workflowId}`);

            // Refresh workflows list
            const updatedWorkflows = await apiService.get<ProjectWorkflow[]>(
                `/api/v1/projects/${projectId}/workflows`
            );
            onWorkflowsUpdate(updatedWorkflows);
        } catch (error: any) {
            console.error('Failed to delete workflow:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const getEventName = (eventId: string) => {
        const event = availableEvents.find(e => e.id === eventId);
        return event?.name || eventId;
    };

    const getActionName = (actionId: string) => {
        const action = availableActions.find(a => a.id === actionId);
        return action?.name || actionId;
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium">Project Workflows</h3>
                {canAdmin && (
                    <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Workflow
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Create Workflow</DialogTitle>
                                <DialogDescription>
                                    Create an automated workflow that responds to project events.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="workflow-name">Name</Label>
                                    <Input
                                        id="workflow-name"
                                        placeholder="Enter workflow name..."
                                        value={workflowName}
                                        onChange={(e) => setWorkflowName(e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="workflow-description">Description</Label>
                                    <Textarea
                                        id="workflow-description"
                                        placeholder="Describe what this workflow does..."
                                        value={workflowDescription}
                                        onChange={(e) => setWorkflowDescription(e.target.value)}
                                        rows={2}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="trigger-event">Trigger Event</Label>
                                    <Select value={selectedEvent} onValueChange={setSelectedEvent}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a trigger event..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableEvents.map((event) => (
                                                <SelectItem key={event.id} value={event.id}>
                                                    <div>
                                                        <div className="font-medium">{event.name}</div>
                                                        <div className="text-xs text-muted-foreground">{event.description}</div>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="action-type">Action</Label>
                                    <Select value={selectedAction} onValueChange={setSelectedAction}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose an action..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableActions.map((action) => (
                                                <SelectItem key={action.id} value={action.id}>
                                                    <div>
                                                        <div className="font-medium">{action.name}</div>
                                                        <div className="text-xs text-muted-foreground">{action.description}</div>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Switch 
                                        id="active" 
                                        checked={isActive}
                                        onCheckedChange={setIsActive}
                                    />
                                    <Label htmlFor="active">Active workflow</Label>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button 
                                    variant="outline" 
                                    onClick={() => setShowAddDialog(false)}
                                    disabled={loading}
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    onClick={handleAddWorkflow} 
                                    disabled={!workflowName.trim() || !selectedEvent || !selectedAction || loading}
                                >
                                    {loading ? 'Creating...' : 'Create Workflow'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>

            <div className="space-y-3">
                {workflows.map((workflow) => (
                    <Card key={workflow.id} className={workflow.is_active ? '' : 'opacity-60'}>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className={`p-2 rounded-full ${workflow.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'}`}>
                                        {workflow.is_active ? <Zap className="h-4 w-4" /> : <Pause className="h-4 w-4" />}
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">{workflow.name}</CardTitle>
                                        {workflow.description && (
                                            <p className="text-sm text-muted-foreground mt-1">{workflow.description}</p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={workflow.is_active ? 'default' : 'secondary'}>
                                        {workflow.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                    {canAdmin && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem 
                                                    onClick={() => handleToggleWorkflow(workflow.id)}
                                                >
                                                    {workflow.is_active ? (
                                                        <>
                                                            <Pause className="h-4 w-4 mr-2" />
                                                            Pause Workflow
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Play className="h-4 w-4 mr-2" />
                                                            Activate Workflow
                                                        </>
                                                    )}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <Edit3 className="h-4 w-4 mr-2" />
                                                    Edit Workflow
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    onClick={() => handleDuplicateWorkflow(workflow.id)}
                                                >
                                                    <Copy className="h-4 w-4 mr-2" />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem 
                                                    onClick={() => handleDeleteWorkflow(workflow.id)}
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete Workflow
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div>
                                    <div className="flex items-center gap-2 mb-2">
                                        <AlertTriangle className="h-4 w-4 text-orange-500" />
                                        <span className="text-sm font-medium">Triggers</span>
                                    </div>
                                    <div className="space-y-1">
                                        {workflow.triggers.map((trigger, index) => (
                                            <Badge key={index} variant="outline" className="text-xs">
                                                {getEventName(trigger.event)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4 text-blue-500" />
                                        <span className="text-sm font-medium">Actions</span>
                                    </div>
                                    <div className="space-y-1">
                                        {workflow.actions.map((action, index) => (
                                            <Badge key={index} variant="secondary" className="text-xs">
                                                {getActionName(action.type)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {workflows.length === 0 && (
                <Card>
                    <CardContent className="p-8 text-center">
                        <div className="mx-auto w-12 h-12 mb-4 text-muted-foreground/20">
                            <Settings className="w-full h-full" />
                        </div>
                        <h3 className="text-sm font-semibold mb-2">No workflows configured</h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            Create automated workflows to streamline your project management.
                        </p>
                        {canAdmin && (
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setShowAddDialog(true)}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                Create Workflow
                            </Button>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}