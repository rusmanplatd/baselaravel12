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

interface Role {
    id: string;
    name: string;
}

interface Permission {
    id: string;
    name: string;
    guard_name: string;
    roles_count: number;
    roles: Role[];
    created_at: string;
    updated_at: string;
}

interface Props {
    permissions: {
        data: Permission[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    guardNames: string[];
    filters: {
        search?: string;
        guard_name?: string;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Permissions' },
];

export default function PermissionsIndex({ permissions, guardNames, filters }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const debouncedSearch = useCallback(
        debounce((value: string) => {
            router.get(route('permissions.index'), {
                ...filters,
                search: value || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 500),
         
        [filters]
    );

    const handleSearch = (value: string) => {
        setSearchValue(value);
        debouncedSearch(value);
    };

    const handleGuardFilter = (guardName: string) => {
        router.get(route('permissions.index'), {
            ...filters,
            guard_name: guardName === 'all' ? undefined : guardName,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (permission: Permission) => {
        if (confirm(`Are you sure you want to delete the permission "${permission.name}"?`)) {
            router.delete(route('permissions.destroy', permission.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Permissions" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Permissions</h1>
                        <p className="text-muted-foreground">
                            Manage system permissions and their assignments
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('permissions.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Permission
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Permissions ({permissions.total})</CardTitle>
                        <CardDescription>
                            View and manage all system permissions
                        </CardDescription>
                        
                        <div className="flex gap-4">
                            <div className="relative flex-1 max-w-sm">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search permissions..."
                                    value={searchValue}
                                    onChange={(e) => handleSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select
                                value={filters.guard_name || 'all'}
                                onValueChange={handleGuardFilter}
                            >
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Filter by guard" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Guards</SelectItem>
                                    {guardNames.map((guard) => (
                                        <SelectItem key={guard} value={guard}>
                                            {guard}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {permissions.data.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">No permissions found</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Guard</TableHead>
                                        <TableHead>Roles</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {permissions.data.map((permission) => (
                                        <TableRow key={permission.id}>
                                            <TableCell className="font-medium">
                                                <div>
                                                    <p>{permission.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {permission.name.includes(':') 
                                                            ? permission.name.split(':')[0].replace(/[-_]/g, ' ')
                                                            : 'General'
                                                        }
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{permission.guard_name}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {permission.roles.length === 0 ? (
                                                        <Badge variant="secondary">No roles</Badge>
                                                    ) : (
                                                        <>
                                                            <Badge variant="secondary">
                                                                {permission.roles_count} roles
                                                            </Badge>
                                                            {permission.roles.slice(0, 2).map((role) => (
                                                                <Badge key={role.id} variant="outline" className="text-xs">
                                                                    {role.name}
                                                                </Badge>
                                                            ))}
                                                            {permission.roles.length > 2 && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    +{permission.roles.length - 2} more
                                                                </Badge>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(permission.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={route('permissions.show', permission.id)}>
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={route('permissions.edit', permission.id)}>
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(permission)}
                                                        disabled={permission.roles_count > 0}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}