import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Play, Square, CheckCircle2, Calendar, Target, Users, Clock, Trash2 } from 'lucide-react';
import apiService from '@/services/ApiService';

interface ProjectIteration {
    id: string;
    title: string;
    description?: string;
    start_date: string;
    end_date: string;
    status: 'planned' | 'active' | 'completed' | 'cancelled';
    duration_weeks: number;
    goals?: string[];
    creator: {
        id: string;
        name: string;
        email: string;
    };
    items_count: number;
    completion_stats: {
        total: number;
        completed: number;
        percentage: number;
    };
    time_stats: {
        duration_days: number;
        remaining_days: number;
        progress_percentage: number;
    };
    created_at: string;
    updated_at: string;
}

interface ProjectIterationManagerProps {
    projectId: string;
    canAdmin: boolean;
}

export function ProjectIterationManager({ projectId, canAdmin }: ProjectIterationManagerProps) {
    const [iterations, setIterations] = useState<ProjectIteration[]>([]);
    const [loading, setLoading] = useState(true);
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [createForm, setCreateForm] = useState({
        title: '',
        description: '',
        start_date: '',
        end_date: '',
        goals: [] as string[]
    });
    const [newGoal, setNewGoal] = useState('');

    useEffect(() => {
        fetchIterations();
    }, [projectId]);

    const fetchIterations = async () => {
        try {
            setLoading(true);
            const response = await apiService.get<{ data: ProjectIteration[] }>(`/api/v1/projects/${projectId}/iterations`);
            setIterations(response.data);
        } catch (error) {
            console.error('Failed to fetch iterations:', error);
        } finally {
            setLoading(false);
        }
    };

    const createIteration = async () => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(`/api/v1/projects/${projectId}/iterations`, createForm);
            setIterations(prev => [response.data, ...prev]);
            setShowCreateDialog(false);
            setCreateForm({ title: '', description: '', start_date: '', end_date: '', goals: [] });
        } catch (error) {
            console.error('Failed to create iteration:', error);
        }
    };

    const updateIterationStatus = async (iterationId: string, action: 'start' | 'complete' | 'cancel') => {
        try {
            const response = await apiService.post<{ data: ProjectIteration }>(`/api/v1/projects/${projectId}/iterations/${iterationId}/${action}`, {});
            setIterations(prev => prev.map(iteration => 
                iteration.id === iterationId ? response.data : iteration
            ));
        } catch (error) {
            console.error(`Failed to ${action} iteration:`, error);
        }
    };

    const deleteIteration = async (iterationId: string) => {
        if (!confirm('Are you sure you want to delete this iteration?')) return;

        try {
            await apiService.delete(`/api/v1/projects/${projectId}/iterations/${iterationId}`);
            setIterations(prev => prev.filter(iteration => iteration.id !== iterationId));
        } catch (error) {
            console.error('Failed to delete iteration:', error);
        }
    };

    const addGoal = () => {
        if (newGoal.trim()) {
            setCreateForm(prev => ({
                ...prev,
                goals: [...prev.goals, newGoal.trim()]
            }));
            setNewGoal('');
        }
    };

    const removeGoal = (index: number) => {
        setCreateForm(prev => ({
            ...prev,
            goals: prev.goals.filter((_, i) => i !== index)
        }));
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            planned: { label: 'Planned', variant: 'secondary' as const, icon: Calendar },
            active: { label: 'Active', variant: 'default' as const, icon: Play },
            completed: { label: 'Completed', variant: 'default' as const, icon: CheckCircle2 },
            cancelled: { label: 'Cancelled', variant: 'destructive' as const, icon: Square },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.planned;
        const Icon = config.icon;

        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getProgressBar = (percentage: number) => (
        <div className="w-full bg-muted rounded-full h-2">
            <div 
                className="bg-primary h-2 rounded-full transition-all duration-300" 
                style={{ width: `${Math.min(percentage, 100)}%` }}
            />
        </div>
    );

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                    <p className="mt-2 text-muted-foreground">Loading iterations...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold">Iterations</h3>
                    <p className="text-sm text-muted-foreground">
                        Organize work into time-boxed iterations
                    </p>
                </div>
                {canAdmin && (
                    <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="h-4 w-4 mr-2" />
                                New Iteration
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Create Iteration</DialogTitle>
                                <DialogDescription>
                                    Create a new iteration to organize work into sprints.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        placeholder="Sprint 1"
                                        value={createForm.title}
                                        onChange={(e) => setCreateForm(prev => ({ ...prev, title: e.target.value }))}
                                    />
                                </div>
                                
                                <div>
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        placeholder="What will this iteration focus on?"
                                        value={createForm.description}
                                        onChange={(e) => setCreateForm(prev => ({ ...prev, description: e.target.value }))}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="start_date">Start Date</Label>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={createForm.start_date}
                                            onChange={(e) => setCreateForm(prev => ({ ...prev, start_date: e.target.value }))}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="end_date">End Date</Label>
                                        <Input
                                            id="end_date"
                                            type="date"
                                            value={createForm.end_date}
                                            onChange={(e) => setCreateForm(prev => ({ ...prev, end_date: e.target.value }))}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <Label>Goals</Label>
                                    <div className="space-y-2">
                                        {createForm.goals.map((goal, index) => (
                                            <div key={index} className="flex items-center gap-2">
                                                <div className="flex-1 p-2 bg-muted rounded text-sm">
                                                    {goal}
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeGoal(index)}
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        ))}
                                        <div className="flex gap-2">
                                            <Input
                                                placeholder="Add a goal..."
                                                value={newGoal}
                                                onChange={(e) => setNewGoal(e.target.value)}
                                                onKeyPress={(e) => e.key === 'Enter' && addGoal()}
                                            />
                                            <Button type="button" variant="outline" onClick={addGoal}>
                                                Add
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex gap-2 justify-end">
                                    <Button variant="outline" onClick={() => setShowCreateDialog(false)}>
                                        Cancel
                                    </Button>
                                    <Button onClick={createIteration} disabled={!createForm.title || !createForm.start_date || !createForm.end_date}>
                                        Create Iteration
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </div>

            {iterations.length === 0 ? (
                <Card>
                    <CardContent className="p-12 text-center">
                        <div className="mx-auto w-24 h-24 mb-6 text-muted-foreground/20">
                            <Target className="w-full h-full" />
                        </div>
                        <h3 className="text-lg font-semibold mb-2">No iterations yet</h3>
                        <p className="text-muted-foreground mb-6">
                            Create your first iteration to organize work into sprints.
                        </p>
                        {canAdmin && (
                            <Button onClick={() => setShowCreateDialog(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Create Iteration
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4">
                    {iterations.map((iteration) => (
                        <Card key={iteration.id} className="relative">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <CardTitle className="text-lg">{iteration.title}</CardTitle>
                                            {getStatusBadge(iteration.status)}
                                        </div>
                                        {iteration.description && (
                                            <CardDescription>{iteration.description}</CardDescription>
                                        )}
                                    </div>
                                    {canAdmin && (
                                        <div className="flex items-center gap-2">
                                            {iteration.status === 'planned' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => updateIterationStatus(iteration.id, 'start')}
                                                >
                                                    <Play className="h-3 w-3 mr-1" />
                                                    Start
                                                </Button>
                                            )}
                                            {iteration.status === 'active' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => updateIterationStatus(iteration.id, 'complete')}
                                                >
                                                    <CheckCircle2 className="h-3 w-3 mr-1" />
                                                    Complete
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => deleteIteration(iteration.id)}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </CardHeader>
                            
                            <CardContent className="space-y-4">
                                {/* Date Range */}
                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-1">
                                        <Calendar className="h-4 w-4" />
                                        <span>
                                            {new Date(iteration.start_date).toLocaleDateString()} - {new Date(iteration.end_date).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Clock className="h-4 w-4" />
                                        <span>{iteration.duration_weeks} weeks</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Target className="h-4 w-4" />
                                        <span>{iteration.items_count} items</span>
                                    </div>
                                </div>

                                {/* Progress */}
                                <div>
                                    <div className="flex items-center justify-between text-sm mb-2">
                                        <span>Completion Progress</span>
                                        <span className="font-medium">
                                            {iteration.completion_stats.completed} / {iteration.completion_stats.total} items ({iteration.completion_stats.percentage}%)
                                        </span>
                                    </div>
                                    {getProgressBar(iteration.completion_stats.percentage)}
                                </div>

                                {/* Time Progress */}
                                {iteration.status === 'active' && (
                                    <div>
                                        <div className="flex items-center justify-between text-sm mb-2">
                                            <span>Time Progress</span>
                                            <span className="font-medium">
                                                {iteration.time_stats.remaining_days} days remaining
                                            </span>
                                        </div>
                                        {getProgressBar(iteration.time_stats.progress_percentage)}
                                    </div>
                                )}

                                {/* Goals */}
                                {iteration.goals && iteration.goals.length > 0 && (
                                    <div>
                                        <h4 className="text-sm font-medium mb-2">Goals</h4>
                                        <ul className="space-y-1">
                                            {iteration.goals.map((goal, index) => (
                                                <li key={index} className="text-sm text-muted-foreground flex items-start gap-2">
                                                    <span className="w-1 h-1 bg-muted-foreground rounded-full mt-2 flex-shrink-0" />
                                                    {goal}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                <div className="text-xs text-muted-foreground">
                                    Created by {iteration.creator.name} on {new Date(iteration.created_at).toLocaleDateString()}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}