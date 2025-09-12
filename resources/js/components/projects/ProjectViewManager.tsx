import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Plus, 
    Table as TableIcon, 
    LayoutGrid, 
    Calendar, 
    BarChart3, 
    Eye,
    EyeOff,
    Settings,
    Star,
    Share,
    Filter,
    SortAsc,
    Trash2,
    Edit
} from 'lucide-react';
import { ProjectBoard } from './ProjectBoard';
import { ProjectTable } from './ProjectTable';
import { ProjectItemCard } from './ProjectItemCard';
import apiService from '@/services/ApiService';

interface ProjectItem {
    id: string;
    title: string;
    description?: string;
    type: 'issue' | 'pull_request' | 'draft_issue';
    status: 'todo' | 'in_progress' | 'done' | 'archived';
    field_values: Record<string, any>;
    assignees: Array<{
        id: string;
        name: string;
        email: string;
        avatar?: string;
    }>;
    labels?: string[];
    estimate?: number;
    progress?: number;
    created_at: string;
    iteration?: {
        id: string;
        title: string;
    };
}

interface ProjectField {
    id: string;
    name: string;
    type: string;
    options?: string[];
    is_required: boolean;
    is_system: boolean;
    show_in_card: boolean;
    icon?: string;
    color?: string;
}

interface ProjectView {
    id: string;
    name: string;
    layout: 'table' | 'board' | 'timeline' | 'roadmap' | 'list';
    filters?: Record<string, any>;
    sort?: { field: string; direction: 'asc' | 'desc' }[];
    group_by?: string;
    visible_fields: string[];
    card_fields?: string[];
    grouping_options?: Record<string, any>;
    is_default: boolean;
    is_public: boolean;
    items_per_page: number;
    show_item_count: boolean;
    empty_state_message?: string;
    created_by: {
        id: string;
        name: string;
    };
    created_at: string;
}

interface ProjectViewManagerProps {
    projectId: string;
    items: ProjectItem[];
    fields: ProjectField[];
    views: ProjectView[];
    canEdit: boolean;
    onViewUpdate: (views: ProjectView[]) => void;
    onItemUpdate: (item: ProjectItem) => void;
}

export function ProjectViewManager({ 
    projectId, 
    items, 
    fields, 
    views, 
    canEdit, 
    onViewUpdate, 
    onItemUpdate 
}: ProjectViewManagerProps) {
    const [activeViewId, setActiveViewId] = useState<string>('');
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [filteredItems, setFilteredItems] = useState<ProjectItem[]>(items);
    const [createForm, setCreateForm] = useState({
        name: '',
        layout: 'table' as 'table' | 'board' | 'timeline' | 'roadmap' | 'list',
        visible_fields: ['title', 'status', 'assignees'] as string[],
        is_public: false,
        items_per_page: 50,
        show_item_count: true
    });

    // Set default active view
    useEffect(() => {
        if (views.length > 0 && !activeViewId) {
            const defaultView = views.find(v => v.is_default) || views[0];
            setActiveViewId(defaultView.id);
        }
    }, [views, activeViewId]);

    // Filter items based on active view
    useEffect(() => {
        const activeView = views.find(v => v.id === activeViewId);
        if (!activeView) {
            setFilteredItems(items);
            return;
        }

        let filtered = [...items];

        // Apply filters
        if (activeView.filters) {
            Object.entries(activeView.filters).forEach(([field, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    filtered = filtered.filter(item => {
                        if (field === 'status') return item.status === value;
                        if (field === 'type') return item.type === value;
                        if (field === 'assignee') return item.assignees.some(a => a.id === value);
                        if (field === 'label') return item.labels?.includes(value);
                        return item.field_values?.[field] === value;
                    });
                }
            });
        }

        // Apply sorting
        if (activeView.sort && activeView.sort.length > 0) {
            filtered.sort((a, b) => {
                for (const sortRule of activeView.sort!) {
                    const aValue = a[sortRule.field as keyof ProjectItem] || a.field_values?.[sortRule.field];
                    const bValue = b[sortRule.field as keyof ProjectItem] || b.field_values?.[sortRule.field];

                    if (aValue === bValue) continue;

                    const modifier = sortRule.direction === 'desc' ? -1 : 1;
                    
                    if (typeof aValue === 'string' && typeof bValue === 'string') {
                        return aValue.localeCompare(bValue) * modifier;
                    }
                    
                    return (aValue < bValue ? -1 : 1) * modifier;
                }
                return 0;
            });
        }

        setFilteredItems(filtered);
    }, [items, activeViewId, views]);

    const createView = async () => {
        try {
            const response = await apiService.post<{ data: ProjectView }>(`/api/v1/projects/${projectId}/views`, createForm);
            const newView = response.data;
            onViewUpdate([...views, newView]);
            setActiveViewId(newView.id);
            setShowCreateDialog(false);
            setCreateForm({
                name: '',
                layout: 'table',
                visible_fields: ['title', 'status', 'assignees'],
                is_public: false,
                items_per_page: 50,
                show_item_count: true
            });
        } catch (error) {
            console.error('Failed to create view:', error);
        }
    };

    const deleteView = async (viewId: string) => {
        if (!confirm('Are you sure you want to delete this view?')) return;

        try {
            await apiService.delete(`/api/v1/projects/${projectId}/views/${viewId}`);
            const updatedViews = views.filter(v => v.id !== viewId);
            onViewUpdate(updatedViews);
            
            if (activeViewId === viewId) {
                const nextView = updatedViews.find(v => v.is_default) || updatedViews[0];
                setActiveViewId(nextView?.id || '');
            }
        } catch (error) {
            console.error('Failed to delete view:', error);
        }
    };

    const setDefaultView = async (viewId: string) => {
        try {
            await apiService.post(`/api/v1/projects/${projectId}/views/${viewId}/make-default`, {});
            const updatedViews = views.map(v => ({
                ...v,
                is_default: v.id === viewId
            }));
            onViewUpdate(updatedViews);
        } catch (error) {
            console.error('Failed to set default view:', error);
        }
    };

    const getViewIcon = (layout: string) => {
        const icons = {
            table: TableIcon,
            board: LayoutGrid,
            timeline: Calendar,
            roadmap: BarChart3,
            list: TableIcon
        };
        return icons[layout as keyof typeof icons] || TableIcon;
    };

    const activeView = views.find(v => v.id === activeViewId);

    const renderViewContent = () => {
        if (!activeView) {
            return (
                <div className="flex items-center justify-center py-12">
                    <div className="text-center">
                        <h3 className="text-lg font-semibold mb-2">No view selected</h3>
                        <p className="text-muted-foreground">Select a view to display items</p>
                    </div>
                </div>
            );
        }

        if (filteredItems.length === 0) {
            return (
                <Card>
                    <CardContent className="p-12 text-center">
                        <div className="mx-auto w-24 h-24 mb-6 text-muted-foreground/20">
                            {activeView.layout === 'board' ? <LayoutGrid className="w-full h-full" /> : 
                             activeView.layout === 'timeline' ? <Calendar className="w-full h-full" /> : 
                             <TableIcon className="w-full h-full" />}
                        </div>
                        <h3 className="text-lg font-semibold mb-2">
                            {activeView.empty_state_message || 'No items found'}
                        </h3>
                        <p className="text-muted-foreground mb-6">
                            {activeView.filters ? 'Try adjusting your filters to see more items.' : 
                             'Create your first item to get started.'}
                        </p>
                        {canEdit && (
                            <Button>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Item
                            </Button>
                        )}
                    </CardContent>
                </Card>
            );
        }

        switch (activeView.layout) {
            case 'board':
                return (
                    <ProjectBoard
                        items={filteredItems}
                        projectId={projectId}
                        onItemUpdate={onItemUpdate}
                        onItemClick={(item) => console.log('Item clicked:', item)}
                        onAddItem={(status) => console.log('Add item:', status)}
                    />
                );

            case 'table':
                return (
                    <ProjectTable
                        items={filteredItems}
                        fields={fields}
                        visibleFields={activeView.visible_fields}
                        onItemClick={(item) => console.log('Item clicked:', item)}
                        onSelectionChange={(ids) => console.log('Selection:', ids)}
                    />
                );

            case 'list':
                return (
                    <div className="space-y-2">
                        {filteredItems.map(item => (
                            <ProjectItemCard
                                key={item.id}
                                item={item}
                                projectId={projectId}
                                layout="compact"
                                onUpdate={onItemUpdate}
                                canEdit={canEdit}
                            />
                        ))}
                    </div>
                );

            case 'timeline':
                return (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filteredItems.map(item => (
                            <ProjectItemCard
                                key={item.id}
                                item={item}
                                projectId={projectId}
                                onUpdate={onItemUpdate}
                                canEdit={canEdit}
                            />
                        ))}
                    </div>
                );

            default:
                return <div className="text-center py-8">View layout not supported yet</div>;
        }
    };

    const toggleFieldVisibility = (fieldName: string) => {
        setCreateForm(prev => ({
            ...prev,
            visible_fields: prev.visible_fields.includes(fieldName)
                ? prev.visible_fields.filter(f => f !== fieldName)
                : [...prev.visible_fields, fieldName]
        }));
    };

    return (
        <div className="space-y-4">
            {/* View Tabs */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    {views.map((view) => {
                        const ViewIcon = getViewIcon(view.layout);
                        return (
                            <Button
                                key={view.id}
                                variant={activeViewId === view.id ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setActiveViewId(view.id)}
                                className="flex items-center gap-2"
                            >
                                <ViewIcon className="h-3 w-3" />
                                {view.name}
                                {view.show_item_count && (
                                    <Badge variant="secondary" className="ml-1 h-4 text-xs">
                                        {filteredItems.length}
                                    </Badge>
                                )}
                                {view.is_default && <Star className="h-3 w-3 ml-1 fill-current" />}
                            </Button>
                        );
                    })}
                </div>

                <div className="flex items-center gap-2">
                    {activeView && (
                        <>
                            <Button variant="ghost" size="sm">
                                <Filter className="h-3 w-3 mr-1" />
                                Filter
                            </Button>
                            <Button variant="ghost" size="sm">
                                <SortAsc className="h-3 w-3 mr-1" />
                                Sort
                            </Button>
                        </>
                    )}
                    
                    {canEdit && (
                        <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                            <DialogTrigger asChild>
                                <Button size="sm">
                                    <Plus className="h-4 w-4 mr-2" />
                                    New View
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Create View</DialogTitle>
                                    <DialogDescription>
                                        Create a new view to organize and display your project items.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-4">
                                    <div>
                                        <Label htmlFor="name">View Name</Label>
                                        <Input
                                            id="name"
                                            placeholder="My awesome view"
                                            value={createForm.name}
                                            onChange={(e) => setCreateForm(prev => ({ ...prev, name: e.target.value }))}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="layout">Layout</Label>
                                        <Select 
                                            value={createForm.layout} 
                                            onValueChange={(value) => setCreateForm(prev => ({ 
                                                ...prev, 
                                                layout: value as any 
                                            }))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="table">
                                                    <div className="flex items-center gap-2">
                                                        <TableIcon className="h-3 w-3" />
                                                        Table
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="board">
                                                    <div className="flex items-center gap-2">
                                                        <LayoutGrid className="h-3 w-3" />
                                                        Board
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="list">
                                                    <div className="flex items-center gap-2">
                                                        <TableIcon className="h-3 w-3" />
                                                        List
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="timeline">
                                                    <div className="flex items-center gap-2">
                                                        <Calendar className="h-3 w-3" />
                                                        Timeline
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label>Visible Fields</Label>
                                        <div className="mt-2 space-y-2 max-h-32 overflow-y-auto border rounded-md p-2">
                                            {['title', 'status', 'type', 'assignees', 'created_at', ...fields.map(f => f.name)].map(fieldName => (
                                                <div key={fieldName} className="flex items-center space-x-2">
                                                    <Checkbox
                                                        id={fieldName}
                                                        checked={createForm.visible_fields.includes(fieldName)}
                                                        onCheckedChange={() => toggleFieldVisibility(fieldName)}
                                                    />
                                                    <Label htmlFor={fieldName} className="text-sm capitalize cursor-pointer">
                                                        {fieldName.replace('_', ' ')}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_public"
                                            checked={createForm.is_public}
                                            onCheckedChange={(checked) => setCreateForm(prev => ({ 
                                                ...prev, 
                                                is_public: !!checked 
                                            }))}
                                        />
                                        <Label htmlFor="is_public" className="text-sm">
                                            Make this view public (visible to all project members)
                                        </Label>
                                    </div>

                                    <div className="flex gap-2 justify-end">
                                        <Button variant="outline" onClick={() => setShowCreateDialog(false)}>
                                            Cancel
                                        </Button>
                                        <Button onClick={createView} disabled={!createForm.name}>
                                            Create View
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
            </div>

            {/* View Content */}
            <div className="min-h-[400px]">
                {renderViewContent()}
            </div>

            {/* View Settings */}
            {activeView && canEdit && (
                <div className="flex items-center justify-between text-sm text-muted-foreground border-t pt-4">
                    <div className="flex items-center gap-4">
                        <span>Created by {activeView.created_by.name}</span>
                        <div className="flex items-center gap-1">
                            {activeView.is_public ? <Eye className="h-3 w-3" /> : <EyeOff className="h-3 w-3" />}
                            {activeView.is_public ? 'Public' : 'Private'}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {!activeView.is_default && (
                            <Button 
                                variant="ghost" 
                                size="sm"
                                onClick={() => setDefaultView(activeView.id)}
                            >
                                <Star className="h-3 w-3 mr-1" />
                                Set as default
                            </Button>
                        )}
                        <Button 
                            variant="ghost" 
                            size="sm"
                            onClick={() => deleteView(activeView.id)}
                            className="text-destructive hover:text-destructive"
                        >
                            <Trash2 className="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}