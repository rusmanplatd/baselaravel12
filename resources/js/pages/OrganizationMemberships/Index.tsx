import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, Play, Pause, StopCircle, Filter } from 'lucide-react';
import { useState } from 'react';

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
    organization_id: string;
}

interface OrganizationPosition {
    id: string;
    title: string;
    organization_unit_id: string;
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
    memberships: {
        data: OrganizationMembership[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    organizations: Organization[];
    users: User[];
    organizationUnits: OrganizationUnit[];
    organizationPositions: OrganizationPosition[];
    filters: {
        organization_id?: string;
        membership_type?: string;
        status?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Memberships', href: '/organization-memberships' },
];

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

export default function Index({ memberships, organizations, filters }: Props) {
    const [selectedFilters, setSelectedFilters] = useState(filters);

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = { ...selectedFilters, [key]: value === 'all' ? undefined : value };
        setSelectedFilters(newFilters);
        router.get('/organization-memberships', newFilters, { 
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (id: string) => {
        if (confirm('Are you sure you want to delete this membership?')) {
            router.delete(`/organization-memberships/${id}`);
        }
    };

    const handleAction = (id: string, action: 'activate' | 'deactivate' | 'terminate') => {
        const messages = {
            activate: 'Are you sure you want to activate this membership?',
            deactivate: 'Are you sure you want to deactivate this membership?',
            terminate: 'Are you sure you want to terminate this membership?'
        };

        if (confirm(messages[action])) {
            router.post(`/organization-memberships/${id}/${action}`, {}, {
                preserveScroll: true,
            });
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString();
    };

    const getStatusBadge = (status: string) => {
        return (
            <Badge variant={statusColors[status as keyof typeof statusColors] || 'outline'}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization Memberships" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Organization Memberships</CardTitle>
                                <CardDescription>
                                    Manage user memberships across organizations and positions.
                                </CardDescription>
                            </div>
                            <Link href="/organization-memberships/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Membership
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-6 flex flex-wrap gap-4 p-4 bg-muted/50 rounded-lg">
                            <div className="flex items-center gap-2">
                                <Filter className="h-4 w-4" />
                                <span className="text-sm font-medium">Filters:</span>
                            </div>
                            <Select value={selectedFilters.organization_id || 'all'} onValueChange={(value) => handleFilterChange('organization_id', value)}>
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="All Organizations" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Organizations</SelectItem>
                                    {organizations.map((org) => (
                                        <SelectItem key={org.id} value={org.id}>
                                            {org.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={selectedFilters.membership_type || 'all'} onValueChange={(value) => handleFilterChange('membership_type', value)}>
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="All Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {Object.entries(membershipTypeLabels).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={selectedFilters.status || 'all'} onValueChange={(value) => handleFilterChange('status', value)}>
                                <SelectTrigger className="w-32">
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                    <SelectItem value="terminated">Terminated</SelectItem>
                                    <SelectItem value="pending">Pending</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Member</TableHead>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Position</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Period</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {memberships.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center text-muted-foreground">
                                                No memberships found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        memberships.data.map((membership) => (
                                            <TableRow key={membership.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex flex-col">
                                                        <span className="font-semibold">{membership.user.name}</span>
                                                        <span className="text-sm text-muted-foreground">{membership.user.email}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-medium">{membership.organization.name}</span>
                                                    {membership.organization_unit && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {membership.organization_unit.name}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {membership.organization_position ? (
                                                        <span className="text-sm">{membership.organization_position.title}</span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">No specific position</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {membershipTypeLabels[membership.membership_type] || membership.membership_type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        <div>{formatDate(membership.start_date)}</div>
                                                        <div className="text-muted-foreground">
                                                            to {formatDate(membership.end_date)}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(membership.status)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Link href={`/organization-memberships/${membership.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/organization-memberships/${membership.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        {membership.status === 'inactive' && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleAction(membership.id, 'activate')}
                                                                title="Activate"
                                                            >
                                                                <Play className="h-4 w-4 text-green-600" />
                                                            </Button>
                                                        )}
                                                        {membership.status === 'active' && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleAction(membership.id, 'deactivate')}
                                                                title="Deactivate"
                                                            >
                                                                <Pause className="h-4 w-4 text-yellow-600" />
                                                            </Button>
                                                        )}
                                                        {membership.status !== 'terminated' && (
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm"
                                                                onClick={() => handleAction(membership.id, 'terminate')}
                                                                title="Terminate"
                                                            >
                                                                <StopCircle className="h-4 w-4 text-red-600" />
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(membership.id)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {memberships.last_page > 1 && (
                            <div className="flex items-center justify-between space-x-2 py-4">
                                <div className="text-sm text-muted-foreground">
                                    Showing {((memberships.current_page - 1) * memberships.per_page) + 1} to {Math.min(memberships.current_page * memberships.per_page, memberships.total)} of {memberships.total} memberships
                                </div>
                                <div className="flex items-center space-x-2">
                                    {memberships.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get('/organization-memberships', { ...selectedFilters, page: memberships.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                    )}
                                    {memberships.current_page < memberships.last_page && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get('/organization-memberships', { ...selectedFilters, page: memberships.current_page + 1 })}
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}