import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Plus, User, Calendar, Tag, Timer, Zap, Users, BarChart3 } from 'lucide-react';
import apiService from '@/services/ApiService';

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
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
    labels?: string[];
    estimate?: number;
    progress?: number;
    iteration?: {
        id: string;
        title: string;
    };
    created_at: string;
    updated_at: string;
    completed_at?: string;
}

interface ProjectBoardProps {
    items: ProjectItem[];
    onItemClick?: (item: ProjectItem) => void;
    onAddItem?: (status: string) => void;
    projectId: string;
    onItemUpdate?: (item: ProjectItem) => void;
}

interface BoardColumn {
    id: string;
    title: string;
    status: string;
    color: string;
}

const BOARD_COLUMNS: BoardColumn[] = [
    { id: 'todo', title: 'To Do', status: 'todo', color: 'bg-gray-50 border-gray-200' },
    { id: 'in_progress', title: 'In Progress', status: 'in_progress', color: 'bg-blue-50 border-blue-200' },
    { id: 'done', title: 'Done', status: 'done', color: 'bg-green-50 border-green-200' },
];

export function ProjectBoard({ items, onItemClick, onAddItem, projectId, onItemUpdate }: ProjectBoardProps) {
    const [draggedItem, setDraggedItem] = useState<ProjectItem | null>(null);
    const [loading, setLoading] = useState(false);

    const getItemTypeBadge = (type: string) => {
        const typeConfig = {
            issue: { label: 'Issue', variant: 'default' as const },
            pull_request: { label: 'PR', variant: 'secondary' as const },
            draft_issue: { label: 'Draft', variant: 'outline' as const },
        };

        const config = typeConfig[type as keyof typeof typeConfig] || typeConfig.issue;

        return (
            <Badge variant={config.variant} className="text-xs">
                {config.label}
            </Badge>
        );
    };

    const handleDragStart = (e: React.DragEvent, item: ProjectItem) => {
        setDraggedItem(item);
        e.dataTransfer.effectAllowed = 'move';
    };

    const handleDragEnd = () => {
        setDraggedItem(null);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const handleDrop = async (e: React.DragEvent, targetStatus: string) => {
        e.preventDefault();
        if (draggedItem && draggedItem.status !== targetStatus && !loading) {
            setLoading(true);
            try {
                const updatedItem = await apiService.post(
                    `/api/v1/projects/${projectId}/items/${draggedItem.id}/update-status`,
                    { status: targetStatus }
                );
                onItemUpdate?.(updatedItem);
            } catch (error) {
                console.error('Failed to update item status:', error);
            } finally {
                setLoading(false);
            }
        }
    };

    const getColumnItems = (status: string) => {
        return items.filter(item => item.status === status);
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 h-full">
            {BOARD_COLUMNS.map((column) => {
                const columnItems = getColumnItems(column.status);
                
                return (
                    <div
                        key={column.id}
                        className={`flex flex-col rounded-lg border-2 border-dashed ${column.color} min-h-[600px]`}
                        onDragOver={handleDragOver}
                        onDrop={(e) => handleDrop(e, column.status)}
                    >
                        {/* Column Header */}
                        <div className="p-4 border-b">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-semibold text-sm">{column.title}</h3>
                                    <Badge variant="secondary" className="text-xs">
                                        {columnItems.length}
                                    </Badge>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onAddItem?.(column.status)}
                                    className="h-6 w-6 p-0"
                                >
                                    <Plus className="h-3 w-3" />
                                </Button>
                            </div>
                        </div>

                        {/* Column Items */}
                        <div className="flex-1 p-2 space-y-2 overflow-y-auto">
                            {columnItems.map((item) => (
                                <Card
                                    key={item.id}
                                    className="cursor-pointer hover:shadow-md transition-shadow"
                                    draggable
                                    onDragStart={(e) => handleDragStart(e, item)}
                                    onDragEnd={handleDragEnd}
                                    onClick={() => onItemClick?.(item)}
                                >
                                    <CardHeader className="p-3 pb-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle className="text-sm font-medium line-clamp-2">
                                                {item.title}
                                            </CardTitle>
                                            {getItemTypeBadge(item.type)}
                                        </div>
                                    </CardHeader>
                                    
                                    {item.description && (
                                        <CardContent className="p-3 pt-0">
                                            <p className="text-xs text-muted-foreground line-clamp-2">
                                                {item.description}
                                            </p>
                                        </CardContent>
                                    )}

                                    <CardContent className="p-3 pt-0 space-y-3">
                                        {/* Labels */}
                                        {item.labels && item.labels.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {item.labels.slice(0, 3).map((label, index) => (
                                                    <Badge key={index} variant="outline" className="text-xs px-1 py-0">
                                                        <Tag className="h-2 w-2 mr-1" />
                                                        {label}
                                                    </Badge>
                                                ))}
                                                {item.labels.length > 3 && (
                                                    <Badge variant="outline" className="text-xs px-1 py-0">
                                                        +{item.labels.length - 3}
                                                    </Badge>
                                                )}
                                            </div>
                                        )}

                                        {/* Progress */}
                                        {typeof item.progress === 'number' && item.progress > 0 && (
                                            <div className="space-y-1">
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="text-muted-foreground">Progress</span>
                                                    <span className="font-medium">{item.progress}%</span>
                                                </div>
                                                <Progress value={item.progress} className="h-1" />
                                            </div>
                                        )}

                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                {item.assignees.length > 0 && (
                                                    <div className="flex items-center gap-1">
                                                        <div className="flex -space-x-1">
                                                            {item.assignees.slice(0, 2).map((assignee) => (
                                                                <Avatar key={assignee.id} className="h-4 w-4 border border-background">
                                                                    <AvatarImage src={assignee.avatar} alt={assignee.name} />
                                                                    <AvatarFallback className="text-xs">
                                                                        {assignee.name.charAt(0)}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                            ))}
                                                            {item.assignees.length > 2 && (
                                                                <div className="h-4 w-4 rounded-full bg-muted border border-background flex items-center justify-center text-xs">
                                                                    +{item.assignees.length - 2}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                                
                                                {item.estimate && (
                                                    <div className="flex items-center gap-1">
                                                        <Timer className="h-3 w-3 text-muted-foreground" />
                                                        <span className="text-xs text-muted-foreground">
                                                            {item.estimate}h
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                            
                                            <div className="flex items-center gap-1">
                                                <Calendar className="h-3 w-3 text-muted-foreground" />
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(item.created_at).toLocaleDateString()}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            {item.field_values?.priority && (
                                                <div className="flex items-center gap-1">
                                                    <Zap className="h-3 w-3 text-muted-foreground" />
                                                    <Badge 
                                                        variant={
                                                            item.field_values.priority === 'urgent' ? 'destructive' :
                                                            item.field_values.priority === 'high' ? 'default' :
                                                            'secondary'
                                                        }
                                                        className="text-xs capitalize"
                                                    >
                                                        {item.field_values.priority}
                                                    </Badge>
                                                </div>
                                            )}

                                            {item.iteration && (
                                                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <BarChart3 className="h-3 w-3" />
                                                    <span className="truncate max-w-20">{item.iteration.title}</span>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            {columnItems.length === 0 && (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <div className="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                                        <Plus className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <p className="text-sm text-muted-foreground mb-2">No items yet</p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onAddItem?.(column.status)}
                                        className="text-xs"
                                    >
                                        Add item
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}