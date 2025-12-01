import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, Users, Calendar, CheckCircle, Circle, Clock } from 'lucide-react';
import apiService from '@/services/ApiService';
import { CreateProjectModal } from '@/components/projects/CreateProjectModal';

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
    user: User;
    added_at: string;
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
    created_at: string;
    updated_at: string;
}

interface ProjectsResponse {
    data: Project[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

export default function Projects() {
    const [projects, setProjects] = useState<Project[]>([]);
    const [organizations, setOrganizations] = useState<Organization[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showCreateModal, setShowCreateModal] = useState(false);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const [projectsResponse, orgsResponse] = await Promise.all([
                apiService.get<ProjectsResponse>('/api/v1/projects'),
                apiService.get<{ data: Organization[] }>('/api/v1/organizations')
            ]);
            setProjects(projectsResponse.data);
            setOrganizations(orgsResponse.data);
        } catch (err) {
            setError('Failed to load data');
            console.error('Error fetching data:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleProjectCreated = (newProject: Project) => {
        setProjects(prev => [newProject, ...prev]);
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

    const getVisibilityBadge = (visibility: string) => {
        return (
            <Badge variant={visibility === 'public' ? 'default' : 'secondary'}>
                {visibility === 'public' ? 'Public' : 'Private'}
            </Badge>
        );
    };

    return (
        <AppLayout>
            <Head title="Projects" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Projects</h1>
                        <p className="text-muted-foreground">
                            Manage and track your organization's projects
                        </p>
                    </div>
                    <Button 
                        className="flex items-center gap-2"
                        onClick={() => setShowCreateModal(true)}
                    >
                        <Plus className="h-4 w-4" />
                        New Project
                    </Button>
                </div>

                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                            <p className="mt-2 text-muted-foreground">Loading projects...</p>
                        </div>
                    </div>
                )}

                {error && (
                    <Card className="border-destructive">
                        <CardContent className="p-6">
                            <p className="text-destructive">{error}</p>
                        </CardContent>
                    </Card>
                )}

                {!loading && !error && projects.length === 0 && (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <div className="mx-auto w-48 h-48 mb-6 text-muted-foreground/20">
                                <svg viewBox="0 0 24 24" fill="currentColor" className="w-full h-full">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>
                            </div>
                            <h3 className="text-xl font-semibold mb-2">No projects yet</h3>
                            <p className="text-muted-foreground mb-6">
                                Create your first project to get started with organizing your work.
                            </p>
                            <Button 
                                className="flex items-center gap-2"
                                onClick={() => setShowCreateModal(true)}
                            >
                                <Plus className="h-4 w-4" />
                                Create Project
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {!loading && !error && projects.length > 0 && (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <Card 
                                key={project.id} 
                                className="hover:shadow-md transition-shadow cursor-pointer"
                                onClick={() => router.visit(`/projects/${project.id}`)}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{project.title}</CardTitle>
                                            <CardDescription className="mt-1">
                                                {project.organization.name}
                                            </CardDescription>
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            {getStatusBadge(project.status)}
                                            {getVisibilityBadge(project.visibility)}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {project.description && (
                                        <p className="text-sm text-muted-foreground mb-4 line-clamp-2">
                                            {project.description}
                                        </p>
                                    )}
                                    
                                    <div className="flex items-center justify-between text-sm">
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">
                                                {project.members.length} member{project.members.length !== 1 ? 's' : ''}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">
                                                {new Date(project.created_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">Created by:</span>
                                        <span className="text-xs font-medium">{project.creator.name}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <CreateProjectModal
                    open={showCreateModal}
                    onOpenChange={setShowCreateModal}
                    organizations={organizations}
                    onProjectCreated={handleProjectCreated}
                />
            </div>
        </AppLayout>
    );
}