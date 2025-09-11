import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    Calendar, 
    User, 
    Tag, 
    Zap, 
    BarChart3, 
    Timer, 
    GitBranch, 
    FileText, 
    MoreVertical,
    Edit,
    Trash2,
    Archive,
    Users
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import apiService from '@/services/ApiService';

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

interface ProjectIteration {
    id: string;
    title: string;
    status: string;
}

interface ProjectItem {
    id: string;
    title: string;
    description?: string;
    type: 'issue' | 'pull_request' | 'draft_issue';
    status: 'todo' | 'in_progress' | 'done' | 'archived';
    field_values: Record<string, any>;
    creator: User;
    assignees: User[];
    iteration?: ProjectIteration;
    labels?: string[];
    estimate?: number;
    progress?: number;
    created_at: string;
    updated_at: string;
    completed_at?: string;
}

interface ProjectItemCardProps {
    item: ProjectItem;
    projectId: string;
    onClick?: (item: ProjectItem) => void;
    onUpdate?: (item: ProjectItem) => void;
    onDelete?: (itemId: string) => void;
    canEdit?: boolean;
    layout?: 'card' | 'compact';
}

export function ProjectItemCard({ 
    item, 
    projectId, 
    onClick, 
    onUpdate, 
    onDelete, 
    canEdit = false,
    layout = 'card'
}: ProjectItemCardProps) {
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [loading, setLoading] = useState(false);

    const getItemTypeBadge = (type: string) => {
        const typeConfig = {
            issue: { label: 'Issue', variant: 'default' as const, icon: FileText },
            pull_request: { label: 'Pull Request', variant: 'secondary' as const, icon: GitBranch },
            draft_issue: { label: 'Draft', variant: 'outline' as const, icon: FileText },
        };

        const config = typeConfig[type as keyof typeof typeConfig] || typeConfig.issue;
        const Icon = config.icon;

        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            todo: { label: 'To Do', variant: 'secondary' as const, color: 'bg-gray-100 text-gray-800' },
            in_progress: { label: 'In Progress', variant: 'default' as const, color: 'bg-blue-100 text-blue-800' },
            done: { label: 'Done', variant: 'default' as const, color: 'bg-green-100 text-green-800' },
            archived: { label: 'Archived', variant: 'outline' as const, color: 'bg-gray-100 text-gray-600' },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.todo;

        return (
            <Badge variant={config.variant}>
                {config.label}
            </Badge>
        );
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            urgent: 'bg-red-100 text-red-800 border-red-200',
            high: 'bg-orange-100 text-orange-800 border-orange-200',
            medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
            low: 'bg-blue-100 text-blue-800 border-blue-200',
        };
        return colors[priority as keyof typeof colors] || colors.medium;
    };

    const updateItemStatus = async (newStatus: string) => {
        try {
            setLoading(true);
            const response = await apiService.post<ProjectItem>(
                `/api/v1/projects/${projectId}/items/${item.id}/update-status`,
                { status: newStatus }
            );
            onUpdate?.(response);
        } catch (error) {
            console.error('Failed to update item status:', error);
        } finally {
            setLoading(false);
        }
    };

    const archiveItem = async () => {
        try {
            setLoading(true);
            const response = await apiService.post<ProjectItem>(
                `/api/v1/projects/${projectId}/items/${item.id}/archive`,
                {}
            );
            onUpdate?.(response);
        } catch (error) {
            console.error('Failed to archive item:', error);
        } finally {
            setLoading(false);
        }
    };

    const deleteItem = async () => {
        if (!confirm('Are you sure you want to delete this item?')) return;

        try {
            setLoading(true);
            await apiService.delete(`/api/v1/projects/${projectId}/items/${item.id}`);
            onDelete?.(item.id);
        } catch (error) {
            console.error('Failed to delete item:', error);
        } finally {
            setLoading(false);
        }
    };

    if (layout === 'compact') {
        return (
            <div className="flex items-center gap-3 p-3 border rounded-lg hover:bg-muted/50 transition-colors cursor-pointer"
                onClick={() => onClick?.(item)}>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        <h4 className="text-sm font-medium truncate">{item.title}</h4>
                        {getItemTypeBadge(item.type)}
                        {getStatusBadge(item.status)}
                    </div>
                    <div className="flex items-center gap-4 text-xs text-muted-foreground">
                        {item.assignees.length > 0 && (
                            <div className="flex items-center gap-1">
                                <User className="h-3 w-3" />
                                <span>{item.assignees[0].name}</span>
                                {item.assignees.length > 1 && <span>+{item.assignees.length - 1}</span>}
                            </div>
                        )}
                        {item.estimate && (
                            <div className="flex items-center gap-1">
                                <Timer className="h-3 w-3" />
                                <span>{item.estimate}h</span>
                            </div>
                        )}
                        {item.labels && item.labels.length > 0 && (
                            <div className="flex items-center gap-1">
                                <Tag className="h-3 w-3" />
                                <span>{item.labels.length} labels</span>
                            </div>
                        )}
                    </div>
                </div>
                {canEdit && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreVertical className="h-3 w-3" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => setShowEditDialog(true)}>
                                <Edit className="h-3 w-3 mr-2" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={archiveItem}>
                                <Archive className="h-3 w-3 mr-2" />
                                Archive
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={deleteItem} className="text-destructive">
                                <Trash2 className="h-3 w-3 mr-2" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        );
    }

    return (
        <>
            <Card className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => onClick?.(item)}>
                <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-2">
                                {getItemTypeBadge(item.type)}
                                {getStatusBadge(item.status)}
                            </div>
                            <CardTitle className="text-base line-clamp-2">{item.title}</CardTitle>
                            {item.description && (
                                <CardDescription className="line-clamp-2 mt-1">
                                    {item.description}
                                </CardDescription>
                            )}
                        </div>
                        {canEdit && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                        <MoreVertical className="h-3 w-3" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => setShowEditDialog(true)}>
                                        <Edit className="h-3 w-3 mr-2" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onClick={archiveItem}>
                                        <Archive className="h-3 w-3 mr-2" />
                                        Archive
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={deleteItem} className="text-destructive">
                                        <Trash2 className="h-3 w-3 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </CardHeader>
                
                <CardContent className="space-y-3">
                    {/* Labels */}
                    {item.labels && item.labels.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {item.labels.map((label, index) => (
                                <Badge key={index} variant="outline" className="text-xs px-2 py-0">
                                    <Tag className="h-2 w-2 mr-1" />
                                    {label}
                                </Badge>
                            ))}
                        </div>
                    )}

                    {/* Progress */}
                    {typeof item.progress === 'number' && item.progress > 0 && (
                        <div className="space-y-1">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">Progress</span>
                                <span className="font-medium">{item.progress}%</span>
                            </div>
                            <Progress value={item.progress} className="h-1.5" />
                        </div>
                    )}

                    {/* Metadata */}
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <div className="flex items-center gap-3">
                            {item.assignees.length > 0 && (
                                <div className="flex items-center gap-1">
                                    <Users className="h-3 w-3" />
                                    <div className="flex -space-x-1">
                                        {item.assignees.slice(0, 3).map((assignee) => (
                                            <Avatar key={assignee.id} className="h-4 w-4 border border-background">
                                                <AvatarImage src={assignee.avatar} alt={assignee.name} />
                                                <AvatarFallback className="text-xs">
                                                    {assignee.name.charAt(0)}
                                                </AvatarFallback>
                                            </Avatar>
                                        ))}
                                        {item.assignees.length > 3 && (
                                            <div className="h-4 w-4 rounded-full bg-muted border border-background flex items-center justify-center text-xs">
                                                +{item.assignees.length - 3}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                            
                            {item.estimate && (
                                <div className="flex items-center gap-1">
                                    <Timer className="h-3 w-3" />
                                    <span>{item.estimate}h</span>
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            {item.field_values?.priority && (
                                <div className="flex items-center gap-1">
                                    <Zap className="h-3 w-3" />
                                    <Badge variant="outline" className={`text-xs px-1 py-0 ${getPriorityColor(item.field_values.priority)}`}>
                                        {item.field_values.priority}
                                    </Badge>
                                </div>
                            )}

                            <div className="flex items-center gap-1">
                                <Calendar className="h-3 w-3" />
                                <span>{new Date(item.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>

                    {/* Iteration */}
                    {item.iteration && (
                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <BarChart3 className="h-3 w-3" />
                            <span>Iteration: {item.iteration.title}</span>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Edit Dialog */}
            <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Item</DialogTitle>
                        <DialogDescription>
                            Update the item details, status, and metadata.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                defaultValue={item.title}
                                placeholder="Item title"
                            />
                        </div>

                        <div>
                            <Label htmlFor="status">Status</Label>
                            <Select defaultValue={item.status} onValueChange={updateItemStatus}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="todo">To Do</SelectItem>
                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                    <SelectItem value="done">Done</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex gap-2 justify-end">
                            <Button variant="outline" onClick={() => setShowEditDialog(false)}>
                                Cancel
                            </Button>
                            <Button onClick={() => setShowEditDialog(false)}>
                                Save Changes
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}