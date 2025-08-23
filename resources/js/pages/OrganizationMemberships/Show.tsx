import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Play, Pause, StopCircle, User, Building, Calendar, Target, Tag } from 'lucide-react';

interface User {
    id: string;
    name: string;
    email: string;
}

interface Organization {
    id: string;
    name: string;
}

interface OrganizationUnit {
    id: string;
    name: string;
}

interface OrganizationPosition {
    id: string;
    title: string;
}

interface OrganizationMembership {
    id: string;
    user_id: string;
    organization_id: string;
    organization_unit_id: string | null;
    organization_position_id: string | null;
    membership_type: 'board_member' | 'executive' | 'management' | 'employee' | 'consultant' | 'volunteer';
    start_date: string;
    end_date: string | null;
    status: 'active' | 'inactive' | 'terminated' | 'pending';
    additional_roles: string[] | null;
    user: User;
    organization: Organization;
    organization_unit?: OrganizationUnit;
    organization_position?: OrganizationPosition;
    created_at: string;
    updated_at: string;
}

interface Props {
    membership: OrganizationMembership;
}

const membershipTypeLabels = {
    board_member: 'Board Member',
    executive: 'Executive',
    management: 'Management',
    employee: 'Employee',
    consultant: 'Consultant',
    volunteer: 'Volunteer',
};

const statusColors = {
    active: 'default',
    inactive: 'secondary',
    terminated: 'destructive',
    pending: 'outline',
} as const;

export default function Show({ membership }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Memberships', href: '/organization-memberships' },
        { title: membership.user.name, href: `/organization-memberships/${membership.id}` },
    ];

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this membership?')) {
            router.delete(`/organization-memberships/${membership.id}`);
        }
    };

    const handleAction = (action: 'activate' | 'deactivate' | 'terminate') => {
        const messages = {
            activate: 'Are you sure you want to activate this membership?',
            deactivate: 'Are you sure you want to deactivate this membership?',
            terminate: 'Are you sure you want to terminate this membership?'
        };

        if (confirm(messages[action])) {
            router.post(`/organization-memberships/${membership.id}/${action}`, {}, {
                preserveScroll: true,
            });
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Ongoing';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const calculateDuration = () => {
        const start = new Date(membership.start_date);
        const end = membership.end_date ? new Date(membership.end_date) : new Date();
        const diffTime = Math.abs(end.getTime() - start.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 30) {
            return `${diffDays} days`;
        } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            return `${months} month${months > 1 ? 's' : ''}`;
        } else {
            const years = Math.floor(diffDays / 365);
            const remainingMonths = Math.floor((diffDays % 365) / 30);
            return `${years} year${years > 1 ? 's' : ''}${remainingMonths > 0 ? `, ${remainingMonths} month${remainingMonths > 1 ? 's' : ''}` : ''}`;
        }
    };

    const getStatusBadge = (status: string) => {
        return (
            <Badge variant={statusColors[status as keyof typeof statusColors] || 'outline'} className="text-sm">
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Membership - ${membership.user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Link href="/organization-memberships">
                                    <Button variant="ghost" size="sm">
                                        <ArrowLeft className="h-4 w-4" />
                                    </Button>
                                </Link>
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="h-5 w-5" />
                                        {membership.user.name}
                                    </CardTitle>
                                    <CardDescription>
                                        Organization membership details
                                    </CardDescription>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {membership.status === 'inactive' && (
                                    <Button 
                                        variant="outline"
                                        onClick={() => handleAction('activate')}
                                        className="text-green-600 border-green-600 hover:bg-green-50"
                                    >
                                        <Play className="mr-2 h-4 w-4" />
                                        Activate
                                    </Button>
                                )}
                                {membership.status === 'active' && (
                                    <Button 
                                        variant="outline"
                                        onClick={() => handleAction('deactivate')}
                                        className="text-yellow-600 border-yellow-600 hover:bg-yellow-50"
                                    >
                                        <Pause className="mr-2 h-4 w-4" />
                                        Deactivate
                                    </Button>
                                )}
                                {membership.status !== 'terminated' && (
                                    <Button 
                                        variant="outline"
                                        onClick={() => handleAction('terminate')}
                                        className="text-red-600 border-red-600 hover:bg-red-50"
                                    >
                                        <StopCircle className="mr-2 h-4 w-4" />
                                        Terminate
                                    </Button>
                                )}
                                <Link href={`/organization-memberships/${membership.id}/edit`}>
                                    <Button variant="outline">
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit
                                    </Button>
                                </Link>
                                <Button variant="outline" onClick={handleDelete}>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Status */}
                        <div className="flex items-center gap-4">
                            <span className="text-sm font-medium">Status:</span>
                            {getStatusBadge(membership.status)}
                        </div>

                        <Separator />

                        {/* Member Information */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card className="border-l-4 border-l-blue-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <User className="h-4 w-4" />
                                        Member Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Name</span>
                                        <p className="text-sm font-semibold">{membership.user.name}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Email</span>
                                        <p className="text-sm">{membership.user.email}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Organization Information */}
                            <Card className="border-l-4 border-l-green-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <Building className="h-4 w-4" />
                                        Organization
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Organization</span>
                                        <p className="text-sm font-semibold">{membership.organization.name}</p>
                                    </div>
                                    {membership.organization_unit && (
                                        <div>
                                            <span className="text-sm font-medium text-muted-foreground">Unit</span>
                                            <p className="text-sm">{membership.organization_unit.name}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Position Information */}
                            {membership.organization_position && (
                                <Card className="border-l-4 border-l-purple-500">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-lg flex items-center gap-2">
                                            <Target className="h-4 w-4" />
                                            Position
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div>
                                            <span className="text-sm font-medium text-muted-foreground">Title</span>
                                            <p className="text-sm font-semibold">{membership.organization_position.title}</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Membership Details */}
                            <Card className="border-l-4 border-l-orange-500">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <Tag className="h-4 w-4" />
                                        Membership Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Type</span>
                                        <p className="text-sm">
                                            <Badge variant="outline">
                                                {membershipTypeLabels[membership.membership_type]}
                                            </Badge>
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Separator />

                        {/* Timeline Information */}
                        <Card className="border-l-4 border-l-indigo-500">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Start Date</span>
                                        <p className="text-sm font-semibold">{formatDate(membership.start_date)}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">End Date</span>
                                        <p className="text-sm font-semibold">{formatDate(membership.end_date)}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-muted-foreground">Duration</span>
                                        <p className="text-sm font-semibold">{calculateDuration()}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Additional Roles */}
                        {membership.additional_roles && membership.additional_roles.length > 0 && (
                            <>
                                <Separator />
                                <div>
                                    <h3 className="text-lg font-semibold mb-3">Additional Roles</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {membership.additional_roles.map((role, index) => (
                                            <Badge key={index} variant="secondary">
                                                {role}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}

                        <Separator />

                        {/* Metadata */}
                        <div className="text-xs text-muted-foreground space-y-1">
                            <p>Created: {new Date(membership.created_at).toLocaleString()}</p>
                            <p>Last updated: {new Date(membership.updated_at).toLocaleString()}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}