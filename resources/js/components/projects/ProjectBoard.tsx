import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Plus, User, Calendar } from 'lucide-react';
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

                                    <CardContent className="p-3 pt-0">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                {item.assignees.length > 0 && (
                                                    <div className="flex items-center gap-1">
                                                        <User className="h-3 w-3 text-muted-foreground" />
                                                        <span className="text-xs text-muted-foreground">
                                                            {item.assignees.length > 1 
                                                                ? `${item.assignees[0].name} +${item.assignees.length - 1}`
                                                                : item.assignees[0].name
                                                            }
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

                                        {item.field_values?.priority && (
                                            <div className="mt-2">
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