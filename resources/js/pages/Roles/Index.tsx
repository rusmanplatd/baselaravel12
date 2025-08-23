import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, Search } from 'lucide-react';
import { useState, useCallback } from 'react';
import { debounce } from 'lodash';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
}

interface Role {
    id: string;
    name: string;
    guard_name: string;
    team_id: string | null;
    users_count: number;
    permissions: Permission[];
    created_at: string;
    updated_at: string;
}

interface Organization {
    id: string;
    name: string;
}

interface Props {
    roles: {
        data: Role[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    organizations: Organization[];
    filters: {
        search?: string;
        team_id?: string;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Roles' },
];

export default function RolesIndex({ roles, organizations, filters }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const debouncedSearch = useCallback(
        debounce((value: string) => {
            router.get(route('roles.index'), {
                ...filters,
                search: value || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 500),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters]
    );

    const handleSearch = (value: string) => {
        setSearchValue(value);
        debouncedSearch(value);
    };

    const handleOrganizationFilter = (teamId: string) => {
        router.get(route('roles.index'), {
            ...filters,
            team_id: teamId === 'all' ? undefined : teamId,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (role: Role) => {
        if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
            router.delete(route('roles.destroy', role.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Roles" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Roles</h1>
                        <p className="text-muted-foreground">
                            Manage system roles and their permissions
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('roles.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Role
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Roles ({roles.total})</CardTitle>
                        <CardDescription>
                            View and manage all system roles
                        </CardDescription>
                        
                        <div className="flex gap-4">
                            <div className="relative flex-1 max-w-sm">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search roles..."
                                    value={searchValue}
                                    onChange={(e) => handleSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select
                                value={filters.team_id || 'all'}
                                onValueChange={handleOrganizationFilter}
                            >
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Filter by organization" />
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
                        </div>
                    </CardHeader>
                    <CardContent>
                        {roles.data.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">No roles found</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Users</TableHead>
                                        <TableHead>Permissions</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.data.map((role) => {
                                        const organization = organizations.find(org => org.id === role.team_id);
                                        
                                        return (
                                            <TableRow key={role.id}>
                                                <TableCell className="font-medium">
                                                    {role.name}
                                                </TableCell>
                                                <TableCell>
                                                    {organization ? organization.name : 'Global'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary">
                                                        {role.users_count} users
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {role.permissions.length} permissions
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(role.created_at).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link href={route('roles.show', role.id)}>
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link href={route('roles.edit', role.id)}>
                                                                <Edit className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(role)}
                                                            disabled={role.users_count > 0}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}