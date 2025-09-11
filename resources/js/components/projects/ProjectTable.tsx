import { useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    MoreHorizontal, 
    ArrowUpDown,
    User,
    Calendar
} from 'lucide-react';

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

interface ProjectTableProps {
    items: ProjectItem[];
    onItemClick?: (item: ProjectItem) => void;
    onSelectionChange?: (selectedIds: string[]) => void;
}

type SortField = 'title' | 'status' | 'type' | 'created_at' | 'priority';
type SortDirection = 'asc' | 'desc';

export function ProjectTable({ items, onItemClick, onSelectionChange }: ProjectTableProps) {
    const [selectedItems, setSelectedItems] = useState<string[]>([]);
    const [sortField, setSortField] = useState<SortField>('created_at');
    const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

    const getItemStatusBadge = (status: string) => {
        const statusConfig = {
            todo: { label: 'To Do', variant: 'secondary' as const },
            in_progress: { label: 'In Progress', variant: 'default' as const },
            done: { label: 'Done', variant: 'default' as const },
            archived: { label: 'Archived', variant: 'outline' as const },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.todo;

        return (
            <Badge variant={config.variant} className="text-xs">
                {config.label}
            </Badge>
        );
    };

    const getItemTypeBadge = (type: string) => {
        const typeConfig = {
            issue: { label: 'Issue', variant: 'default' as const },
            pull_request: { label: 'Pull Request', variant: 'secondary' as const },
            draft_issue: { label: 'Draft', variant: 'outline' as const },
        };

        const config = typeConfig[type as keyof typeof typeConfig] || typeConfig.issue;

        return (
            <Badge variant={config.variant} className="text-xs">
                {config.label}
            </Badge>
        );
    };

    const getPriorityBadge = (priority: string) => {
        if (!priority) return null;

        const priorityConfig = {
            urgent: { variant: 'destructive' as const },
            high: { variant: 'default' as const },
            medium: { variant: 'secondary' as const },
            low: { variant: 'outline' as const },
        };

        const config = priorityConfig[priority as keyof typeof priorityConfig] || priorityConfig.medium;

        return (
            <Badge variant={config.variant} className="text-xs capitalize">
                {priority}
            </Badge>
        );
    };

    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            const allIds = items.map(item => item.id);
            setSelectedItems(allIds);
            onSelectionChange?.(allIds);
        } else {
            setSelectedItems([]);
            onSelectionChange?.([]);
        }
    };

    const handleSelectItem = (itemId: string, checked: boolean) => {
        let newSelection: string[];
        if (checked) {
            newSelection = [...selectedItems, itemId];
        } else {
            newSelection = selectedItems.filter(id => id !== itemId);
        }
        setSelectedItems(newSelection);
        onSelectionChange?.(newSelection);
    };

    const sortedItems = [...items].sort((a, b) => {
        let aValue: any, bValue: any;

        switch (sortField) {
            case 'title':
                aValue = a.title.toLowerCase();
                bValue = b.title.toLowerCase();
                break;
            case 'status':
                aValue = a.status;
                bValue = b.status;
                break;
            case 'type':
                aValue = a.type;
                bValue = b.type;
                break;
            case 'created_at':
                aValue = new Date(a.created_at);
                bValue = new Date(b.created_at);
                break;
            case 'priority':
                const priorities = { urgent: 4, high: 3, medium: 2, low: 1 };
                aValue = priorities[a.field_values?.priority as keyof typeof priorities] || 0;
                bValue = priorities[b.field_values?.priority as keyof typeof priorities] || 0;
                break;
            default:
                return 0;
        }

        if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
        if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    const SortButton = ({ field, children }: { field: SortField; children: React.ReactNode }) => (
        <Button
            variant="ghost"
            className="h-auto p-0 font-semibold hover:bg-transparent"
            onClick={() => handleSort(field)}
        >
            <div className="flex items-center gap-1">
                {children}
                <ArrowUpDown className="h-3 w-3" />
            </div>
        </Button>
    );

    return (
        <div className="border rounded-lg">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12">
                            <Checkbox
                                checked={selectedItems.length === items.length && items.length > 0}
                                onCheckedChange={handleSelectAll}
                                aria-label="Select all items"
                            />
                        </TableHead>
                        <TableHead>
                            <SortButton field="title">Title</SortButton>
                        </TableHead>
                        <TableHead>
                            <SortButton field="status">Status</SortButton>
                        </TableHead>
                        <TableHead>
                            <SortButton field="type">Type</SortButton>
                        </TableHead>
                        <TableHead>
                            <SortButton field="priority">Priority</SortButton>
                        </TableHead>
                        <TableHead>Assignees</TableHead>
                        <TableHead>
                            <SortButton field="created_at">Created</SortButton>
                        </TableHead>
                        <TableHead className="w-12"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sortedItems.map((item) => (
                        <TableRow
                            key={item.id}
                            className="cursor-pointer hover:bg-muted/50"
                            onClick={() => onItemClick?.(item)}
                        >
                            <TableCell onClick={(e) => e.stopPropagation()}>
                                <Checkbox
                                    checked={selectedItems.includes(item.id)}
                                    onCheckedChange={(checked) => handleSelectItem(item.id, !!checked)}
                                    aria-label={`Select ${item.title}`}
                                />
                            </TableCell>
                            <TableCell>
                                <div>
                                    <div className="font-medium">{item.title}</div>
                                    {item.description && (
                                        <div className="text-sm text-muted-foreground line-clamp-1">
                                            {item.description}
                                        </div>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell>
                                {getItemStatusBadge(item.status)}
                            </TableCell>
                            <TableCell>
                                {getItemTypeBadge(item.type)}
                            </TableCell>
                            <TableCell>
                                {getPriorityBadge(item.field_values?.priority)}
                            </TableCell>
                            <TableCell>
                                {item.assignees.length > 0 ? (
                                    <div className="flex items-center gap-1">
                                        <User className="h-3 w-3 text-muted-foreground" />
                                        <span className="text-sm">
                                            {item.assignees.length > 1 
                                                ? `${item.assignees[0].name} +${item.assignees.length - 1}`
                                                : item.assignees[0].name
                                            }
                                        </span>
                                    </div>
                                ) : (
                                    <span className="text-sm text-muted-foreground">Unassigned</span>
                                )}
                            </TableCell>
                            <TableCell>
                                <div className="flex items-center gap-1">
                                    <Calendar className="h-3 w-3 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {new Date(item.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell onClick={(e) => e.stopPropagation()}>
                                <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                                    <MoreHorizontal className="h-3 w-3" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            
            {items.length === 0 && (
                <div className="p-8 text-center">
                    <p className="text-muted-foreground">No items found</p>
                </div>
            )}
        </div>
    );
}