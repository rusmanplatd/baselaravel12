import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, Users } from 'lucide-react';
import { useState, useCallback } from 'react';
import { debounce } from 'lodash';

interface Role {
    id: string;
    name: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles_count: number;
    roles: Role[];
    created_at: string;
    updated_at: string;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Users' },
];

export default function UsersIndex({ users, filters }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const debouncedSearch = useCallback(
        debounce((value: string) => {
            router.get(route('users.index'), {
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

    const handleDelete = (user: User) => {
        if (confirm(`Are you sure you want to delete the user "${user.name}"?`)) {
            router.delete(route('users.destroy', user.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Users" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Users</h1>
                        <p className="text-muted-foreground">
                            Manage system users and their role assignments
                        </p>
                    </div>
                    <PermissionGuard permission="users.create">
                        <Button disabled>
                            <Users className="mr-2 h-4 w-4" />
                            User management via registration
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Users ({users.total})</CardTitle>
                        <CardDescription>
                            View and manage all system users and their roles
                        </CardDescription>
                        
                        <div className="flex gap-4">
                            <div className="relative flex-1 max-w-sm">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search users..."
                                    value={searchValue}
                                    onChange={(e) => handleSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {users.data.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">No users found</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Email Status</TableHead>
                                        <TableHead>Roles</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                <div>
                                                    <p className="font-medium">{user.name}</p>
                                                    <p className="text-sm text-muted-foreground">{user.email}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={user.email_verified_at ? "default" : "destructive"}>
                                                    {user.email_verified_at ? 'Verified' : 'Unverified'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.length === 0 ? (
                                                        <Badge variant="secondary">No roles</Badge>
                                                    ) : (
                                                        <>
                                                            <Badge variant="secondary">
                                                                {user.roles_count} roles
                                                            </Badge>
                                                            {user.roles.slice(0, 2).map((role) => (
                                                                <Badge key={role.id} variant="outline" className="text-xs">
                                                                    {role.name}
                                                                </Badge>
                                                            ))}
                                                            {user.roles.length > 2 && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    +{user.roles.length - 2} more
                                                                </Badge>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="users.read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link href={route('users.show', user.id)}>
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="users.update">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link href={route('users.edit', user.id)}>
                                                                <Edit className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="users.delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(user)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
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