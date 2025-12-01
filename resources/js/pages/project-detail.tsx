import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    ArrowLeft, 
    Plus, 
    Settings, 
    Users, 
    Eye,
    Calendar,
    CheckCircle,
    Circle,
    Clock,
    AlertCircle,
    LayoutGrid,
    Table as TableIcon
} from 'lucide-react';
import apiService from '@/services/ApiService';
import { ProjectBoard } from '@/components/projects/ProjectBoard';
import { ProjectTable } from '@/components/projects/ProjectTable';
import { ProjectMemberManager } from '@/components/projects/ProjectMemberManager';
import { ProjectFieldsManager } from '@/components/projects/ProjectFieldsManager';
import { ProjectWorkflowManager } from '@/components/projects/ProjectWorkflowManager';
import { ProjectIterationManager } from '@/components/projects/ProjectIterationManager';
import { ProjectPermissionManager } from '@/components/projects/ProjectPermissionManager';
import { ProjectViewManager } from '@/components/projects/ProjectViewManager';
import { useProjectPermissions } from '@/hooks/useProjectPermissions';

interface Organization {
    id: string;
    name: string;
    organization_code: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

interface ProjectMember {
    id: string;
    role: string;
    permissions: string[];
    user: User;
    added_at: string;
}

interface ProjectField {
    id: string;
    name: string;
    type: string;
    options?: string[];
    is_required: boolean;
    is_system: boolean;
    sort_order: number;
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

interface ProjectView {
    id: string;
    name: string;
    layout: 'table' | 'board' | 'timeline' | 'roadmap';
    filters?: any;
    is_default: boolean;
    is_public: boolean;
}

interface Project {
    id: string;
    title: string;
    description?: string;
    status: 'open' | 'closed' | 'archived';
    visibility: 'public' | 'private';
    organization: Organization;
    creator: User;
    members: ProjectMember[];
    fields: ProjectField[];
    items: ProjectItem[];
    views: ProjectView[];
    created_at: string;
    updated_at: string;
}

interface ProjectDetailProps {
    projectId: string;
}

export default function ProjectDetail({ projectId }: ProjectDetailProps) {
    const [project, setProject] = useState<Project | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeView, setActiveView] = useState<string>('items');
    const [itemsViewMode, setItemsViewMode] = useState<'table' | 'board'>('table');
    const [currentUser, setCurrentUser] = useState<User | null>(null);
    
    // Use the new permissions hook
    const { permissions, canPerformAction } = useProjectPermissions(projectId);

    useEffect(() => {
        fetchProject();
        fetchCurrentUser();
    }, [projectId]);

    const fetchCurrentUser = async () => {
        try {
            const user = await apiService.get<User>('/api/user');
            setCurrentUser(user);
        } catch (error) {
            console.error('Failed to fetch current user:', error);
        }
    };

    const fetchProject = async () => {
        try {
            setLoading(true);
            const response = await apiService.get<Project>(`/api/v1/projects/${projectId}`);
            setProject(response);
        } catch (err: any) {
            if (err.response?.status === 404) {
                setError('Project not found');
            } else {
                setError('Failed to load project');
            }
            console.error('Error fetching project:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleItemUpdate = (updatedItem: ProjectItem) => {
        setProject(prev => {
            if (!prev) return prev;
            
            return {
                ...prev,
                items: prev.items.map(item => 
                    item.id === updatedItem.id ? updatedItem : item
                )
            };
        });
    };

    const handleMembersUpdate = (updatedMembers: ProjectMember[]) => {
        setProject(prev => {
            if (!prev) return prev;
            
            return {
                ...prev,
                members: updatedMembers
            };
        });
    };

    const handleFieldsUpdate = (updatedFields: ProjectField[]) => {
        setProject(prev => {
            if (!prev) return prev;
            
            return {
                ...prev,
                fields: updatedFields
            };
        });
    };

    const handleWorkflowsUpdate = (updatedWorkflows: ProjectWorkflow[]) => {
        setProject(prev => {
            if (!prev) return prev;
            
            return {
                ...prev,
                workflows: updatedWorkflows
            };
        });
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            open: { label: 'Open', variant: 'default' as const, icon: Circle },
            closed: { label: 'Closed', variant: 'secondary' as const, icon: CheckCircle },
            archived: { label: 'Archived', variant: 'outline' as const, icon: Clock },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.open;
        const Icon = config.icon;

        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getItemStatusBadge = (status: string) => {
        const statusConfig = {
            todo: { label: 'To Do', variant: 'secondary' as const, icon: Circle },
            in_progress: { label: 'In Progress', variant: 'default' as const, icon: Clock },
            done: { label: 'Done', variant: 'default' as const, icon: CheckCircle },
            archived: { label: 'Archived', variant: 'outline' as const, icon: AlertCircle },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.todo;
        const Icon = config.icon;

        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const getItemTypeBadge = (type: string) => {
        const typeConfig = {
            issue: { label: 'Issue', color: 'bg-blue-100 text-blue-800' },
            pull_request: { label: 'Pull Request', color: 'bg-green-100 text-green-800' },
            draft_issue: { label: 'Draft', color: 'bg-gray-100 text-gray-800' },
        };

        const config = typeConfig[type as keyof typeof typeConfig] || typeConfig.issue;

        return (
            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${config.color}`}>
                {config.label}
            </span>
        );
    };

    if (loading) {
        return (
            <AppLayout>
                <Head title="Loading Project..." />
                <div className="flex items-center justify-center py-12">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p className="mt-2 text-muted-foreground">Loading project...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (error || !project) {
        return (
            <AppLayout>
                <Head title="Project Not Found" />
                <div className="flex flex-col items-center justify-center py-12">
                    <AlertCircle className="h-12 w-12 text-muted-foreground mb-4" />
                    <h1 className="text-2xl font-bold mb-2">Project Not Found</h1>
                    <p className="text-muted-foreground mb-6">{error || 'The project you are looking for does not exist.'}</p>
                    <Button onClick={() => router.visit('/projects')}>
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Back to Projects
                    </Button>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title={project.title} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button 
                            variant="ghost" 
                            size="sm"
                            onClick={() => router.visit('/projects')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Projects
                        </Button>
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-3xl font-bold">{project.title}</h1>
                                {getStatusBadge(project.status)}
                                <Badge variant={project.visibility === 'public' ? 'default' : 'secondary'}>
                                    <Eye className="h-3 w-3 mr-1" />
                                    {project.visibility === 'public' ? 'Public' : 'Private'}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground">
                                {project.organization.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm">
                            <Users className="h-4 w-4 mr-2" />
                            Members ({project.members.length})
                        </Button>
                        <Button variant="outline" size="sm">
                            <Settings className="h-4 w-4 mr-2" />
                            Settings
                        </Button>
                    </div>
                </div>

                {/* Description */}
                {project.description && (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                {project.description}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Tabs */}
                <Tabs value={activeView} onValueChange={setActiveView}>
                    <TabsList>
                        <TabsTrigger value="items">Items ({project.items.length})</TabsTrigger>
                        <TabsTrigger value="views">Views ({project.views.length})</TabsTrigger>
                        <TabsTrigger value="iterations">Iterations</TabsTrigger>
                        <TabsTrigger value="members">Members ({project.members.length})</TabsTrigger>
                        <TabsTrigger value="fields">Fields ({project.fields.length})</TabsTrigger>
                        <TabsTrigger value="workflows">Workflows ({project.workflows?.length || 0})</TabsTrigger>
                    </TabsList>

                    <TabsContent value="items" className="space-y-4">
                        <ProjectViewManager
                            projectId={projectId}
                            items={project.items}
                            fields={project.fields}
                            views={project.views}
                            canEdit={canPerformAction('create-view')}
                            onViewUpdate={(views) => setProject(prev => prev ? { ...prev, views } : prev)}
                            onItemUpdate={handleItemUpdate}
                        />
                    </TabsContent>

                    <TabsContent value="views" className="space-y-4">
                        <div className="text-center py-8">
                            <p className="text-muted-foreground">
                                Views are now managed in the Items tab. Switch to the Items tab to create and manage different views of your project.
                            </p>
                            <Button 
                                variant="outline" 
                                onClick={() => setActiveView('items')}
                                className="mt-4"
                            >
                                Go to Items & Views
                            </Button>
                        </div>
                    </TabsContent>

                    <TabsContent value="iterations" className="space-y-4">
                        <ProjectIterationManager
                            projectId={projectId}
                            canAdmin={canPerformAction('create-iteration')}
                        />
                    </TabsContent>

                    <TabsContent value="members" className="space-y-4">
                        {currentUser ? (
                            <ProjectPermissionManager
                                projectId={projectId}
                                members={project.members}
                                currentUser={currentUser}
                                canAdmin={canPerformAction('invite-member')}
                                onMembersUpdate={handleMembersUpdate}
                            />
                        ) : (
                            <ProjectMemberManager
                                projectId={projectId}
                                members={project.members}
                                canAdmin={canPerformAction('invite-member')}
                                onMembersUpdate={handleMembersUpdate}
                            />
                        )}
                    </TabsContent>

                    <TabsContent value="fields" className="space-y-4">
                        <ProjectFieldsManager
                            projectId={projectId}
                            fields={project.fields}
                            canAdmin={canPerformAction('create-field')}
                            onFieldsUpdate={handleFieldsUpdate}
                        />
                    </TabsContent>

                    <TabsContent value="workflows" className="space-y-4">
                        <ProjectWorkflowManager
                            projectId={projectId}
                            workflows={project.workflows || []}
                            canAdmin={canPerformAction('create-workflow')}
                            onWorkflowsUpdate={handleWorkflowsUpdate}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}